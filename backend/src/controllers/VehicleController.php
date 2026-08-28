<?php
require_once __DIR__ . '/../models/Vehicle.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class VehicleController
{
    private Vehicle $vehicleModel;

    public function __construct(PDO $db)
    {
        $this->vehicleModel = new Vehicle($db);
    }

    public function index(): void
    {
        AuthMiddleware::authorize();

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? min(50, max(1, (int)$_GET['per_page'])) : 10;
        $search = trim($_GET['search'] ?? '');

        $vehicles = $this->vehicleModel->findAll($page, $perPage, $search);
        $total = $this->vehicleModel->countAll($search);

        $this->respond(200, [
            "data" => $vehicles,
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

        $vehicle = $this->vehicleModel->findById($id);
        if (!$vehicle) {
            $this->respond(404, ["error" => "Veículo não encontrado."]);
            return;
        }

        $this->respond(200, ["data" => $vehicle]);
    }

    public function store(array $input): void
    {
        $currentUser = AuthMiddleware::authorize(['Administrador', 'Funcionário']);

        $licensePlate = strtoupper(trim($input['license_plate'] ?? ''));
        $brand = trim($input['brand'] ?? '');
        $model = trim($input['model'] ?? '');
        $year = (int) ($input['year'] ?? 0);
        $clientId = (int) ($input['client_id'] ?? 0);

        if ($licensePlate === '' || $brand === '' || $model === '' || $year === 0 || $clientId === 0) {
            $this->respond(422, ["error" => "Matrícula, marca, modelo, ano e cliente são obrigatórios."]);
            return;
        }

        $currentYear = (int) date('Y');
        if ($year < 1900 || $year > $currentYear + 1) {
            $this->respond(422, ["error" => "Ano inválido."]);
            return;
        }

        if ($this->vehicleModel->licensePlateExists($licensePlate)) {
            $this->respond(409, ["error" => "Já existe um veículo com esta matrícula."]);
            return;
        }

        $id = $this->vehicleModel->create($licensePlate, $brand, $model, $year, $clientId, (int) $currentUser['id']);

        $this->respond(201, [
            "message" => "Veículo criado com sucesso.",
            "data" => $this->vehicleModel->findById($id)
        ]);
    }

    public function update(int $id, array $input): void
    {
        AuthMiddleware::authorize(['Administrador', 'Funcionário']);

        $existing = $this->vehicleModel->findById($id);
        if (!$existing) {
            $this->respond(404, ["error" => "Veículo não encontrado."]);
            return;
        }

        $licensePlate = strtoupper(trim($input['license_plate'] ?? ''));
        $brand = trim($input['brand'] ?? '');
        $model = trim($input['model'] ?? '');
        $year = (int) ($input['year'] ?? 0);
        $clientId = (int) ($input['client_id'] ?? 0);

        if ($licensePlate === '' || $brand === '' || $model === '' || $year === 0 || $clientId === 0) {
            $this->respond(422, ["error" => "Matrícula, marca, modelo, ano e cliente são obrigatórios."]);
            return;
        }

        if ($this->vehicleModel->licensePlateExists($licensePlate, $id)) {
            $this->respond(409, ["error" => "Já existe outro veículo com esta matrícula."]);
            return;
        }

        $this->vehicleModel->update($id, $licensePlate, $brand, $model, $year, $clientId);

        $this->respond(200, [
            "message" => "Veículo atualizado com sucesso.",
            "data" => $this->vehicleModel->findById($id)
        ]);
    }

    public function destroy(int $id): void
    {
        AuthMiddleware::authorize(['Administrador', 'Funcionário']);

        $existing = $this->vehicleModel->findById($id);
        if (!$existing) {
            $this->respond(404, ["error" => "Veículo não encontrado."]);
            return;
        }

        $this->vehicleModel->delete($id);
        $this->respond(200, ["message" => "Veículo removido com sucesso."]);
    }

    private function respond(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        echo json_encode($data);
    }
}