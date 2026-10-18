<?php
require_once __DIR__ . '/../models/Payment.php';
require_once __DIR__ . '/../models/Maintenance.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class PaymentController
{
    // Campos que formam a "fotografia" da fatura guardada ao anular
    private const SNAPSHOT_FIELDS = [
        'maintenance_type', 'service_date', 'start_time', 'end_time',
        'hourly_rate', 'labor_hours', 'labor_cost',
        'subtotal', 'tax_amount', 'total_amount', 'notes',
        'license_plate', 'brand', 'model', 'year',
        'client_name', 'client_nif', 'client_email', 'client_phone', 'client_address',
        'parts'
    ];

    private Payment $paymentModel;
    private Maintenance $maintenanceModel;

    public function __construct(PDO $db)
    {
        $this->paymentModel = new Payment($db);
        $this->maintenanceModel = new Maintenance($db);
    }

    public function index(): void
    {
        AuthMiddleware::authorize();

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? min(50, max(1, (int)$_GET['per_page'])) : 10;
        $search = trim($_GET['search'] ?? '');

        $rows = $this->paymentModel->findAll($page, $perPage, $search);
        $payments = array_map(function (array $row) {
            $row = $this->applySnapshot($row);
            unset($row['parts']); // a lista não precisa das peças
            return $row;
        }, $rows);

        $total = $this->paymentModel->countAll($search);

        $this->respond(200, [
            "data" => $payments,
            "pagination" => [
                "page" => $page,
                "per_page" => $perPage,
                "total" => $total,
                "total_pages" => (int) ceil($total / $perPage)
            ]
        ]);
    }

    /**
     * GET /api/payments/ref/PAG-2026-0001
     * Devolve tudo o que a página de pagamento e a fatura precisam.
     */
    public function showByReference(string $reference): void
    {
        AuthMiddleware::authorize();

        $payment = $this->findFull($reference);
        if (!$payment) {
            $this->respond(404, ["error" => "Pagamento não encontrado."]);
            return;
        }

        $this->respond(200, ["data" => $payment]);
    }

    /**
     * POST /api/payments   Body: { maintenance_id }
     * Gera o número do pagamento para uma manutenção.
     */
    public function store(array $input): void
    {
        $currentUser = AuthMiddleware::authorize(['Administrador', 'Funcionário']);

        $maintenanceId = (int) ($input['maintenance_id'] ?? 0);
        if ($maintenanceId === 0) {
            $this->respond(422, ["error" => "A manutenção é obrigatória."]);
            return;
        }

        if (!$this->maintenanceModel->findById($maintenanceId)) {
            $this->respond(404, ["error" => "Manutenção não encontrada."]);
            return;
        }

        // Só conta o pagamento ativo: se o anterior foi anulado, pode gerar outro
        $existing = $this->paymentModel->findByMaintenanceId($maintenanceId);
        if ($existing) {
            $this->respond(409, [
                "error" => "Esta manutenção já tem um pagamento gerado.",
                "reference" => $existing['reference']
            ]);
            return;
        }

        // Se duas pessoas gerarem ao mesmo tempo, o UNIQUE da base de dados impede
        // números repetidos. Nesse caso tentamos de novo com o número seguinte.
        $reference = '';
        $created = false;
        for ($attempt = 1; $attempt <= 3 && !$created; $attempt++) {
            try {
                $reference = $this->paymentModel->nextReference((int) date('Y'));
                $this->paymentModel->create($reference, $maintenanceId, (int) $currentUser['id']);
                $created = true;
            } catch (PDOException $e) {
                $isDuplicate = (int) ($e->errorInfo[1] ?? 0) === 1062;

                $existing = $this->paymentModel->findByMaintenanceId($maintenanceId);
                if ($existing) {
                    $this->respond(409, [
                        "error" => "Esta manutenção já tem um pagamento gerado.",
                        "reference" => $existing['reference']
                    ]);
                    return;
                }

                if (!$isDuplicate || $attempt === 3) {
                    $this->respond(500, ["error" => "Erro ao gerar o pagamento."]);
                    return;
                }
            }
        }

        $this->respond(201, [
            "message" => "Pagamento gerado com sucesso.",
            "data" => $this->findFull($reference)
        ]);
    }

    /**
     * PATCH /api/payments/ref/PAG-2026-0001/pay
     * Body: { payment_method, paid_at? }
     */
    public function pay(string $reference, array $input): void
    {
        AuthMiddleware::authorize(['Administrador', 'Funcionário']);

        $payment = $this->findFull($reference);
        if (!$payment) {
            $this->respond(404, ["error" => "Pagamento não encontrado."]);
            return;
        }

        if ($payment['status'] === 'Anulado') {
            $this->respond(409, ["error" => "Este pagamento está anulado."]);
            return;
        }

        if ($payment['status'] === 'Pago') {
            $this->respond(409, ["error" => "Este pagamento já está registado como pago."]);
            return;
        }

        $method = trim($input['payment_method'] ?? '');
        if (!in_array($method, Payment::METHODS, true)) {
            $this->respond(422, ["error" => "Método de pagamento inválido."]);
            return;
        }

        $paidAt = trim($input['paid_at'] ?? '') ?: date('Y-m-d');
        $date = DateTime::createFromFormat('Y-m-d', $paidAt);
        if (!$date || $date->format('Y-m-d') !== $paidAt) {
            $this->respond(422, ["error" => "Data de pagamento inválida."]);
            return;
        }

        $this->paymentModel->markPaid((int) $payment['id'], $method, $paidAt);

        $this->respond(200, [
            "message" => "Pagamento registado como pago.",
            "data" => $this->findFull($reference)
        ]);
    }

    /**
     * PATCH /api/payments/ref/PAG-2026-0001/reopen
     * Só o Administrador: desfaz um "Pago" marcado por engano.
     */
    public function reopen(string $reference): void
    {
        AuthMiddleware::authorize(['Administrador']);

        $payment = $this->findFull($reference);
        if (!$payment) {
            $this->respond(404, ["error" => "Pagamento não encontrado."]);
            return;
        }

        if ($payment['status'] !== 'Pago') {
            $this->respond(409, ["error" => "Só é possível reabrir um pagamento que esteja pago."]);
            return;
        }

        $this->paymentModel->reopen((int) $payment['id']);

        $this->respond(200, [
            "message" => "Pagamento reaberto como pendente.",
            "data" => $this->findFull($reference)
        ]);
    }

    /**
     * PATCH /api/payments/ref/PAG-2026-0001/cancel
     * Body: { reason }
     * Só o Administrador. Guarda uma cópia exata da fatura e marca-a como anulada.
     * Depois disto a manutenção pode ser corrigida e gerado um pagamento novo.
     */
    public function cancel(string $reference, array $input): void
    {
        $currentUser = AuthMiddleware::authorize(['Administrador']);

        $payment = $this->findFull($reference);
        if (!$payment) {
            $this->respond(404, ["error" => "Pagamento não encontrado."]);
            return;
        }

        if ($payment['status'] === 'Anulado') {
            $this->respond(409, ["error" => "Este pagamento já está anulado."]);
            return;
        }

        $reason = trim($input['reason'] ?? '');
        if (strlen($reason) < 5) {
            $this->respond(422, ["error" => "Indica o motivo da anulação (mínimo 5 caracteres)."]);
            return;
        }
        if (strlen($reason) > 255) {
            $this->respond(422, ["error" => "O motivo é demasiado longo (máximo 255 caracteres)."]);
            return;
        }

        // Fotografia da fatura tal como está agora (ainda com os valores originais)
        $snapshot = [];
        foreach (self::SNAPSHOT_FIELDS as $field) {
            $snapshot[$field] = $payment[$field] ?? null;
        }

        $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($snapshotJson === false) {
            $this->respond(500, ["error" => "Erro ao guardar os dados da fatura."]);
            return;
        }

        $this->paymentModel->cancel((int) $payment['id'], (int) $currentUser['id'], $reason, $snapshotJson);

        $this->respond(200, [
            "message" => "Pagamento anulado.",
            "data" => $this->findFull($reference)
        ]);
    }

    private function findFull(string $reference): ?array
    {
        $payment = $this->paymentModel->findByReference(strtoupper($reference));
        if (!$payment) {
            return null;
        }

        $payment['parts'] = $this->maintenanceModel->findParts((int) $payment['maintenance_id']);
        return $this->applySnapshot($payment);
    }

    /**
     * Num pagamento anulado, os dados da fatura vêm da cópia guardada ao anular
     * (e não da manutenção, que entretanto pode ter sido corrigida).
     */
    private function applySnapshot(array $payment): array
    {
        if (($payment['status'] ?? '') === 'Anulado' && !empty($payment['snapshot'])) {
            $snapshot = json_decode($payment['snapshot'], true);
            if (is_array($snapshot)) {
                $payment = array_merge($payment, $snapshot);
            }
        }

        unset($payment['snapshot']); // não vai para o Angular
        return $payment;
    }

    private function respond(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        echo json_encode($data);
    }
}