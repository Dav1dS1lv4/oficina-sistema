<?php
require_once __DIR__ . '/../models/Maintenance.php';
require_once __DIR__ . '/../models/Part.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class MaintenanceController
{
    private PDO $conn;
    private Maintenance $maintenanceModel;
    private Part $partModel;

    // Taxa de IVA normal em Portugal continental.
    private const IVA_RATE = 0.23;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
        $this->maintenanceModel = new Maintenance($db);
        $this->partModel = new Part($db);
    }

    public function index(): void
    {
        AuthMiddleware::authorize();

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? min(50, max(1, (int)$_GET['per_page'])) : 10;
        $search = trim($_GET['search'] ?? '');

        $records = $this->maintenanceModel->findAll($page, $perPage, $search);
        $total = $this->maintenanceModel->countAll($search);

        $this->respond(200, [
            "data" => $records,
            "pagination" => [
                "page" => $page,
                "per_page" => $perPage,
                "total" => $total,
                "total_pages" => (int) ceil($total / $perPage)
            ]
        ]);
    }

    public function show(int $id): void
    {
        AuthMiddleware::authorize();

        $record = $this->maintenanceModel->findById($id);
        if (!$record) {
            $this->respond(404, ["error" => "Registo de manutenção não encontrado."]);
            return;
        }

        $record['parts'] = $this->maintenanceModel->findParts($id);
        $record['edits'] = $this->maintenanceModel->findEdits($id);

        $this->respond(200, ["data" => $record]);
    }

    public function byVehicle(int $vehicleId): void
    {
        AuthMiddleware::authorize();

        $records = $this->maintenanceModel->findByVehicle($vehicleId);
        $this->respond(200, ["data" => $records]);
    }

    /**
     * POST /api/maintenance
     * Body: { vehicle_id, type, service_date, start_time?, end_time?, hourly_rate, notes?,
     *         parts: [ { part_id?, name?, quantity, unit_price } ] }
     */
    public function store(array $input): void
    {
        $currentUser = AuthMiddleware::authorize(['Administrador', 'Funcionário']);

        $vehicleId = (int) ($input['vehicle_id'] ?? 0);
        $type = trim($input['type'] ?? '');
        $serviceDate = trim($input['service_date'] ?? '');
        $notes = trim($input['notes'] ?? '') ?: null;
        $partsInput = is_array($input['parts'] ?? null) ? $input['parts'] : [];

        if ($vehicleId === 0 || $type === '' || $serviceDate === '') {
            $this->respond(422, ["error" => "Veículo, tipo de manutenção e data são obrigatórios."]);
            return;
        }

        $labor = $this->parseLabor($input);
        if (isset($labor['error'])) {
            $this->respond(422, ["error" => $labor['error']]);
            return;
        }

        $partsError = $this->validatePartsInput($partsInput);
        if ($partsError !== null) {
            $this->respond(422, ["error" => $partsError]);
            return;
        }

        [$subtotal, $taxAmount, $totalAmount] = $this->calculateTotals($partsInput, $labor['cost']);

        try {
            $this->conn->beginTransaction();

            $maintenanceId = $this->maintenanceModel->create(
                $vehicleId,
                (int) $currentUser['id'],
                $type,
                $serviceDate,
                $labor['start'],
                $labor['end'],
                $labor['rate'],
                $labor['hours'],
                $labor['cost'],
                $notes,
                $subtotal,
                $taxAmount,
                $totalAmount
            );

            $this->applyParts($maintenanceId, $partsInput);

            $this->conn->commit();

            $record = $this->maintenanceModel->findById($maintenanceId);
            $record['parts'] = $this->maintenanceModel->findParts($maintenanceId);

            $this->respond(201, [
                "message" => "Manutenção registada com sucesso.",
                "data" => $record
            ]);
        } catch (Exception $e) {
            $this->conn->rollBack();
            $this->respond(500, ["error" => "Erro ao registar manutenção: " . $e->getMessage()]);
        }
    }

    /**
     * PUT /api/maintenance/{id}
     * Só o Administrador, e só se a manutenção não tiver um pagamento ativo.
     * Body: { type, start_time?, end_time?, hourly_rate, notes?, reason, parts: [...] }
     */
    public function update(int $id, array $input): void
    {
        $currentUser = AuthMiddleware::authorize(['Administrador']);

        $existing = $this->maintenanceModel->findById($id);
        if (!$existing) {
            $this->respond(404, ["error" => "Registo de manutenção não encontrado."]);
            return;
        }

        if ($this->maintenanceModel->hasActivePayment($id)) {
            $this->respond(409, ["error" => "Esta manutenção tem um pagamento ativo. Anula o pagamento antes de a corrigir."]);
            return;
        }

        $type = trim($input['type'] ?? '');
        $notes = trim($input['notes'] ?? '') ?: null;
        $reason = trim($input['reason'] ?? '');
        $partsInput = is_array($input['parts'] ?? null) ? $input['parts'] : [];

        if ($type === '') {
            $this->respond(422, ["error" => "O tipo de manutenção é obrigatório."]);
            return;
        }

        if (strlen($reason) < 5) {
            $this->respond(422, ["error" => "Indica o motivo da correção (mínimo 5 caracteres)."]);
            return;
        }
        if (strlen($reason) > 255) {
            $this->respond(422, ["error" => "O motivo é demasiado longo (máximo 255 caracteres)."]);
            return;
        }

        $labor = $this->parseLabor($input);
        if (isset($labor['error'])) {
            $this->respond(422, ["error" => $labor['error']]);
            return;
        }

        $partsError = $this->validatePartsInput($partsInput);
        if ($partsError !== null) {
            $this->respond(422, ["error" => $partsError]);
            return;
        }

        [$subtotal, $taxAmount, $totalAmount] = $this->calculateTotals($partsInput, $labor['cost']);

        // Estado anterior, guardado no histórico de correções
        $previous = $existing;
        $previous['parts'] = $this->maintenanceModel->findParts($id);
        $previousJson = json_encode($previous, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($previousJson === false) {
            $this->respond(500, ["error" => "Erro ao guardar o histórico da correção."]);
            return;
        }

        try {
            $this->conn->beginTransaction();

            // 1) Repõe o stock das peças usadas antes
            foreach ($previous['parts'] as $oldLine) {
                $this->partModel->adjustQuantity((int) $oldLine['part_id'], (int) $oldLine['quantity_used']);
            }

            // 2) Remove as linhas de peças antigas
            $this->maintenanceModel->deleteParts($id);

            // 3) Atualiza a manutenção
            $this->maintenanceModel->update(
                $id,
                $type,
                $labor['start'],
                $labor['end'],
                $labor['rate'],
                $labor['hours'],
                $labor['cost'],
                $notes,
                $subtotal,
                $taxAmount,
                $totalAmount
            );

            // 4) Grava as peças novas e desconta o stock
            $this->applyParts($id, $partsInput);

            // 5) Regista a correção
            $this->maintenanceModel->logEdit($id, (int) $currentUser['id'], $reason, $previousJson);

            $this->conn->commit();

            $record = $this->maintenanceModel->findById($id);
            $record['parts'] = $this->maintenanceModel->findParts($id);
            $record['edits'] = $this->maintenanceModel->findEdits($id);

            $this->respond(200, [
                "message" => "Manutenção corrigida com sucesso.",
                "data" => $record
            ]);
        } catch (Exception $e) {
            $this->conn->rollBack();
            $this->respond(500, ["error" => "Erro ao corrigir manutenção: " . $e->getMessage()]);
        }
    }

    public function destroy(int $id): void
    {
        AuthMiddleware::authorize(['Administrador']);

        $existing = $this->maintenanceModel->findById($id);
        if (!$existing) {
            $this->respond(404, ["error" => "Registo de manutenção não encontrado."]);
            return;
        }

        // Uma manutenção já cobrada não se apaga: o pagamento deixaria de ter origem
        if ($this->maintenanceModel->hasPayment($id)) {
            $this->respond(409, ["error" => "Não é possível remover: esta manutenção já tem um pagamento associado."]);
            return;
        }

        $this->maintenanceModel->delete($id);

        $this->respond(200, ["message" => "Registo de manutenção removido com sucesso."]);
    }

    // =====================================================
    // Funções auxiliares (partilhadas pelo registo e pela correção)
    // =====================================================

    /**
     * Lê e valida a mão de obra. Devolve ['error' => ...] ou os valores calculados.
     */
    private function parseLabor(array $input): array
    {
        $startTime = trim($input['start_time'] ?? '') ?: null;
        $endTime = trim($input['end_time'] ?? '') ?: null;
        $hourlyRate = (float) ($input['hourly_rate'] ?? 0);

        if ($hourlyRate < 0) {
            return ['error' => "O valor por hora não pode ser negativo."];
        }

        $laborMinutes = 0;
        if ($startTime !== null || $endTime !== null) {
            // Aceita HH:MM e HH:MM:SS (a base de dados devolve HH:MM:SS)
            $timePattern = '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';

            if ($startTime === null || $endTime === null
                || !preg_match($timePattern, $startTime)
                || !preg_match($timePattern, $endTime)) {
                return ['error' => "Indica a hora de início e a hora de fim no formato HH:MM."];
            }

            $startTime = substr($startTime, 0, 5);
            $endTime = substr($endTime, 0, 5);

            $laborMinutes = $this->minutesBetween($startTime, $endTime);
            if ($laborMinutes <= 0) {
                return ['error' => "A hora de fim tem de ser depois da hora de início."];
            }
        }

        return [
            'start' => $startTime,
            'end' => $endTime,
            'rate' => $hourlyRate,
            'hours' => round($laborMinutes / 60, 2),
            'cost' => round($laborMinutes * $hourlyRate / 60, 2),
        ];
    }

    private function minutesBetween(string $start, string $end): int
    {
        [$startHour, $startMinute] = explode(':', $start);
        [$endHour, $endMinute] = explode(':', $end);

        return ((int) $endHour * 60 + (int) $endMinute) - ((int) $startHour * 60 + (int) $startMinute);
    }

    /**
     * Valida as linhas de peças. Devolve null se estiver tudo bem, ou a mensagem de erro.
     */
    private function validatePartsInput(array $partsInput): ?string
    {
        foreach ($partsInput as $line) {
            if (!is_array($line)) {
                return "Linha de peça inválida.";
            }

            $quantity = (int) ($line['quantity'] ?? 0);
            $unitPrice = (float) ($line['unit_price'] ?? -1);
            $hasPartId = !empty($line['part_id']);
            $hasName = !empty(trim($line['name'] ?? ''));

            if ($quantity <= 0) {
                return "A quantidade de cada peça deve ser maior que zero.";
            }
            if ($unitPrice < 0) {
                return "O preço unitário de cada peça é obrigatório.";
            }
            if (!$hasPartId && !$hasName) {
                return "Cada peça precisa de um ID existente ou de um nome.";
            }
        }

        return null;
    }

    /**
     * Subtotal, IVA e total (iguais ao que o Angular mostra em tempo real).
     */
    private function calculateTotals(array $partsInput, float $laborCost): array
    {
        $partsSubtotal = 0.0;
        foreach ($partsInput as $line) {
            $partsSubtotal += (int) $line['quantity'] * (float) $line['unit_price'];
        }

        $subtotal = round($partsSubtotal + $laborCost, 2);
        $taxAmount = round($subtotal * self::IVA_RATE, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        return [$subtotal, $taxAmount, $totalAmount];
    }

    /**
     * Grava as peças de uma manutenção: usa a peça existente (por ID ou por nome) ou cria-a
     * com stock zero, desconta o stock (pode ficar negativo) e associa à manutenção.
     * Tem de ser chamado dentro de uma transação.
     */
    private function applyParts(int $maintenanceId, array $partsInput): void
    {
        foreach ($partsInput as $line) {
            $quantity = (int) $line['quantity'];
            $unitPrice = (float) $line['unit_price'];
            $partId = !empty($line['part_id']) ? (int) $line['part_id'] : null;

            if ($partId !== null) {
                if (!$this->partModel->findById($partId)) {
                    throw new Exception("Peça com ID {$partId} não encontrada.");
                }
            } else {
                $name = trim($line['name']);
                $existingPart = $this->partModel->findByExactName($name);

                if ($existingPart) {
                    $partId = (int) $existingPart['id'];
                } else {
                    $partId = $this->partModel->create($name, null, 0, $unitPrice, null, 5);
                }
            }

            $this->partModel->adjustQuantity($partId, -$quantity);
            $this->maintenanceModel->addPart($maintenanceId, $partId, $quantity, $unitPrice);
        }
    }

    private function respond(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        echo json_encode($data);
    }
}