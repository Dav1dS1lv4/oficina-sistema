<?php
class Maintenance
{
    private PDO $conn;
    private string $table = "maintenance";

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

        public function findAll(int $page = 1, int $perPage = 10, string $search = ''): array
    {
        $offset = ($page - 1) * $perPage;

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = "WHERE v.license_plate LIKE :search OR m.type LIKE :search OR c.name LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        // O pagamento mostrado é o ATIVO; o último anulado vem à parte
        $query = "SELECT m.*, v.license_plate, v.brand, v.model, c.name AS client_name, u.name AS user_name,
                         pay.reference AS payment_reference, pay.status AS payment_status,
                         (SELECT p3.reference FROM payments p3
                          WHERE p3.maintenance_id = m.id AND p3.status = 'Anulado'
                          ORDER BY p3.id DESC LIMIT 1) AS last_cancelled_reference,
                         (SELECT COUNT(*) FROM maintenance_edits e WHERE e.maintenance_id = m.id) AS edit_count
                  FROM {$this->table} m
                  JOIN vehicles v ON v.id = m.vehicle_id
                  JOIN clients c ON c.id = v.client_id
                  JOIN users u ON u.id = m.user_id
                  LEFT JOIN payments pay ON pay.id = (
                      SELECT MAX(p2.id) FROM payments p2
                      WHERE p2.maintenance_id = m.id AND p2.status <> 'Anulado'
                  )
                  {$where}
                  ORDER BY m.service_date DESC, m.id DESC
                  LIMIT :limit OFFSET :offset";

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
            $where = "WHERE v.license_plate LIKE :search OR m.type LIKE :search OR c.name LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        $query = "SELECT COUNT(*) as total
                  FROM {$this->table} m
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

    public function findById(int $id): ?array
    {
        $query = "SELECT m.*, v.license_plate, v.brand, v.model, c.name AS client_name, u.name AS user_name
                  FROM {$this->table} m
                  JOIN vehicles v ON v.id = m.vehicle_id
                  JOIN clients c ON c.id = v.client_id
                  JOIN users u ON u.id = m.user_id
                  WHERE m.id = :id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $maintenance = $stmt->fetch();
        return $maintenance ?: null;
    }

    /**
     * Peças usadas numa manutenção, com o preço praticado nessa altura.
     */
    public function findParts(int $maintenanceId): array
    {
        $query = "SELECT mp.*, p.name AS part_name
                  FROM maintenance_parts mp
                  JOIN parts p ON p.id = mp.part_id
                  WHERE mp.maintenance_id = :maintenance_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':maintenance_id', $maintenanceId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Histórico de correções de uma manutenção (sem os dados antigos, que são pesados).
     */
    public function findEdits(int $maintenanceId): array
    {
        $query = "SELECT e.id, e.reason, e.created_at, u.name AS edited_by_name
                  FROM maintenance_edits e
                  JOIN users u ON u.id = e.edited_by
                  WHERE e.maintenance_id = :maintenance_id
                  ORDER BY e.id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':maintenance_id', $maintenanceId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function create(int $vehicleId, int $userId, string $type, string $serviceDate, ?string $startTime, ?string $endTime, float $hourlyRate, float $laborHours, float $laborCost, ?string $notes, float $subtotal, float $taxAmount, float $totalAmount): int
    {
        $query = "INSERT INTO {$this->table}
                  (vehicle_id, user_id, type, service_date, start_time, end_time, hourly_rate, labor_hours, cost, notes, subtotal, tax_amount, total_amount)
                  VALUES (:vehicle_id, :user_id, :type, :service_date, :start_time, :end_time, :hourly_rate, :labor_hours, :cost, :notes, :subtotal, :tax_amount, :total_amount)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':vehicle_id', $vehicleId, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':service_date', $serviceDate);
        $stmt->bindParam(':start_time', $startTime);
        $stmt->bindParam(':end_time', $endTime);
        $stmt->bindParam(':hourly_rate', $hourlyRate);
        $stmt->bindParam(':labor_hours', $laborHours);
        $stmt->bindParam(':cost', $laborCost);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':subtotal', $subtotal);
        $stmt->bindParam(':tax_amount', $taxAmount);
        $stmt->bindParam(':total_amount', $totalAmount);
        $stmt->execute();

        return (int) $this->conn->lastInsertId();
    }

    /**
     * Corrige uma manutenção existente. O veículo, a data e quem a registou não mudam.
     */
    public function update(int $id, string $type, ?string $startTime, ?string $endTime, float $hourlyRate, float $laborHours, float $laborCost, ?string $notes, float $subtotal, float $taxAmount, float $totalAmount): bool
    {
        $query = "UPDATE {$this->table}
                  SET type = :type, start_time = :start_time, end_time = :end_time,
                      hourly_rate = :hourly_rate, labor_hours = :labor_hours, cost = :cost,
                      notes = :notes, subtotal = :subtotal, tax_amount = :tax_amount,
                      total_amount = :total_amount
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':start_time', $startTime);
        $stmt->bindParam(':end_time', $endTime);
        $stmt->bindParam(':hourly_rate', $hourlyRate);
        $stmt->bindParam(':labor_hours', $laborHours);
        $stmt->bindParam(':cost', $laborCost);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':subtotal', $subtotal);
        $stmt->bindParam(':tax_amount', $taxAmount);
        $stmt->bindParam(':total_amount', $totalAmount);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function addPart(int $maintenanceId, int $partId, int $quantityUsed, float $unitPrice): void
    {
        $query = "INSERT INTO maintenance_parts (maintenance_id, part_id, quantity_used, unit_price)
                  VALUES (:maintenance_id, :part_id, :quantity_used, :unit_price)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':maintenance_id', $maintenanceId, PDO::PARAM_INT);
        $stmt->bindParam(':part_id', $partId, PDO::PARAM_INT);
        $stmt->bindParam(':quantity_used', $quantityUsed, PDO::PARAM_INT);
        $stmt->bindParam(':unit_price', $unitPrice);
        $stmt->execute();
    }

    public function deleteParts(int $maintenanceId): void
    {
        $query = "DELETE FROM maintenance_parts WHERE maintenance_id = :maintenance_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':maintenance_id', $maintenanceId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Regista uma correção: quem, porquê e como estava antes (em JSON).
     */
    public function logEdit(int $maintenanceId, int $editedBy, string $reason, string $previousJson): void
    {
        $query = "INSERT INTO maintenance_edits (maintenance_id, edited_by, reason, previous_data)
                  VALUES (:maintenance_id, :edited_by, :reason, :previous_data)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':maintenance_id', $maintenanceId, PDO::PARAM_INT);
        $stmt->bindParam(':edited_by', $editedBy, PDO::PARAM_INT);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':previous_data', $previousJson);
        $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Tem algum pagamento, mesmo anulado? (impede apagar a manutenção)
     */
    public function hasPayment(int $id): bool
    {
        $stmt = $this->conn->prepare("SELECT id FROM payments WHERE maintenance_id = :id LIMIT 1");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() !== false;
    }

    /**
     * Tem um pagamento ativo, ou seja, Pendente ou Pago? (impede corrigir a manutenção)
     */
    public function hasActivePayment(int $id): bool
    {
        $stmt = $this->conn->prepare("SELECT id FROM payments WHERE maintenance_id = :id AND status <> 'Anulado' LIMIT 1");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() !== false;
    }

    public function findByVehicle(int $vehicleId): array
    {
        $query = "SELECT m.*, u.name AS user_name
                  FROM {$this->table} m
                  JOIN users u ON u.id = m.user_id
                  WHERE m.vehicle_id = :vehicle_id
                  ORDER BY m.service_date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':vehicle_id', $vehicleId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countTotal(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) as total FROM {$this->table}");
        return (int) $stmt->fetch()['total'];
    }
}