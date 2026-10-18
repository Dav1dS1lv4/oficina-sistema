<?php
class Payment
{
    private PDO $conn;
    private string $table = "payments";

    // Códigos guardados na base de dados (o Angular mostra os nomes bonitos)
    public const METHODS = ['numerario', 'mbway', 'cartao', 'transferencia'];

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    /**
     * Pagamento + manutenção + veículo + cliente numa só consulta.
     * É o que a página de pagamento e a fatura precisam.
     */
    private function baseSelect(): string
    {
        return "SELECT p.*,
                       m.type AS maintenance_type, m.service_date, m.start_time, m.end_time,
                       m.hourly_rate, m.labor_hours, m.cost AS labor_cost,
                       m.subtotal, m.tax_amount, m.total_amount, m.notes,
                       v.license_plate, v.brand, v.model, v.year,
                       c.name AS client_name, c.nif AS client_nif, c.email AS client_email,
                       c.phone AS client_phone, c.address AS client_address,
                       u.name AS issued_by_name,
                       cu.name AS cancelled_by_name
                FROM {$this->table} p
                JOIN maintenance m ON m.id = p.maintenance_id
                JOIN vehicles v ON v.id = m.vehicle_id
                JOIN clients c ON c.id = v.client_id
                LEFT JOIN users u ON u.id = p.created_by
                LEFT JOIN users cu ON cu.id = p.cancelled_by";
    }

    public function findAll(int $page = 1, int $perPage = 10, string $search = ''): array
    {
        $offset = ($page - 1) * $perPage;

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = "WHERE p.reference LIKE :search OR c.name LIKE :search
                      OR c.nif LIKE :search OR v.license_plate LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        $query = $this->baseSelect() . " {$where} ORDER BY p.id DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAll(string $search = ''): int
    {
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = "WHERE p.reference LIKE :search OR c.name LIKE :search
                      OR c.nif LIKE :search OR v.license_plate LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        $query = "SELECT COUNT(*) AS total
                  FROM {$this->table} p
                  JOIN maintenance m ON m.id = p.maintenance_id
                  JOIN vehicles v ON v.id = m.vehicle_id
                  JOIN clients c ON c.id = v.client_id
                  {$where}";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetch()['total'];
    }

    public function findByReference(string $reference): ?array
    {
        $query = $this->baseSelect() . " WHERE p.reference = :reference LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':reference', $reference);
        $stmt->execute();

        $payment = $stmt->fetch();
        return $payment ?: null;
    }

    /**
     * Procura o pagamento ATIVO de uma manutenção (ignora os anulados).
     */
    public function findByMaintenanceId(int $maintenanceId): ?array
    {
        $query = "SELECT id, reference FROM {$this->table}
                  WHERE maintenance_id = :maintenance_id AND status <> 'Anulado'
                  LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':maintenance_id', $maintenanceId, PDO::PARAM_INT);
        $stmt->execute();

        $payment = $stmt->fetch();
        return $payment ?: null;
    }

    /**
     * Próximo número do ano, ex: PAG-2026-0001, PAG-2026-0002...
     * Conta também os anulados, por isso um número anulado nunca é reutilizado.
     */
    public function nextReference(int $year): string
    {
        $prefix = "PAG-{$year}-";
        $start = strlen($prefix) + 1; // posição onde começa a parte numérica

        $query = "SELECT MAX(CAST(SUBSTRING(reference, {$start}) AS UNSIGNED)) AS last_number
                  FROM {$this->table}
                  WHERE reference LIKE :pattern";

        $pattern = $prefix . '%';
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':pattern', $pattern);
        $stmt->execute();

        $last = (int) ($stmt->fetch()['last_number'] ?? 0);

        return $prefix . str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    public function create(string $reference, int $maintenanceId, int $createdBy): int
    {
        $query = "INSERT INTO {$this->table} (reference, maintenance_id, created_by)
                  VALUES (:reference, :maintenance_id, :created_by)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':reference', $reference);
        $stmt->bindParam(':maintenance_id', $maintenanceId, PDO::PARAM_INT);
        $stmt->bindParam(':created_by', $createdBy, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->conn->lastInsertId();
    }

    public function markPaid(int $id, string $method, string $paidAt): bool
    {
        $query = "UPDATE {$this->table}
                  SET status = 'Pago', payment_method = :method, paid_at = :paid_at
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':method', $method);
        $stmt->bindParam(':paid_at', $paidAt);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function reopen(int $id): bool
    {
        $query = "UPDATE {$this->table}
                  SET status = 'Pendente', payment_method = NULL, paid_at = NULL
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Anula o pagamento e guarda a "fotografia" da fatura tal como estava.
     */
    public function cancel(int $id, int $cancelledBy, string $reason, string $snapshotJson): bool
    {
        $query = "UPDATE {$this->table}
                  SET status = 'Anulado', cancelled_at = NOW(), cancelled_by = :cancelled_by,
                      cancel_reason = :reason, snapshot = :snapshot
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':cancelled_by', $cancelledBy, PDO::PARAM_INT);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':snapshot', $snapshotJson);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}