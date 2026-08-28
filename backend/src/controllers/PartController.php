<?php
require_once __DIR__ . '/../models/Part.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class PartController
{
    private Part $partModel;

    public function __construct(PDO $db)
    {
        $this->partModel = new Part($db);
    }

    public function index(): void
    {
        AuthMiddleware::authorize();

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? min(50, max(1, (int)$_GET['per_page'])) : 10;
        $search = trim($_GET['search'] ?? '');

        $parts = $this->partModel->findAll($page, $perPage, $search);
        $total = $this->partModel->countAll($search);

        $this->respond(200, [
            "data" => $parts,
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

        $part = $this->partModel->findById($id);
        if (!$part) {
            $this->respond(404, ["error" => "Peça não encontrada."]);
            return;
        }

        $this->respond(200, ["data" => $part]);
    }

    public function store(array $input): void
    {
        AuthMiddleware::authorize(['Administrador', 'Funcionário']);

        $name = trim($input['name'] ?? '');
        $category = trim($input['category'] ?? '') ?: null;
        $quantity = (int) ($input['quantity'] ?? 0);
        $price = (float) ($input['price'] ?? 0);
        $supplier = trim($input['supplier'] ?? '') ?: null;
        $lowStockThreshold = (int) ($input['low_stock_threshold'] ?? 5);

        if ($name === '') {
            $this->respond(422, ["error" => "O nome da peça é obrigatório."]);
            return;
        }

        if ($price < 0) {
            $this->respond(422, ["error" => "O preço não pode ser negativo."]);
            return;
        }

        if ($quantity < 0) {
            $this->respond(422, ["error" => "A quantidade não pode ser negativa."]);
            return;
        }

        $id = $this->partModel->create($name, $category, $quantity, $price, $supplier, $lowStockThreshold);

        $this->respond(201, [
            "message" => "Peça criada com sucesso.",
            "data" => $this->partModel->findById($id)
        ]);
    }

    public function update(int $id, array $input): void
    {
        AuthMiddleware::authorize(['Administrador', 'Funcionário']);

        $existing = $this->partModel->findById($id);
        if (!$existing) {
            $this->respond(404, ["error" => "Peça não encontrada."]);
            return;
        }

        $name = trim($input['name'] ?? '');
        $category = trim($input['category'] ?? '') ?: null;
        $quantity = (int) ($input['quantity'] ?? 0);
        $price = (float) ($input['price'] ?? 0);
        $supplier = trim($input['supplier'] ?? '') ?: null;
        $lowStockThreshold = (int) ($input['low_stock_threshold'] ?? 5);

        if ($name === '') {
            $this->respond(422, ["error" => "O nome da peça é obrigatório."]);
            return;
        }

        if ($price < 0 || $quantity < 0) {
            $this->respond(422, ["error" => "Preço e quantidade não podem ser negativos."]);
            return;
        }

        $this->partModel->update($id, $name, $category, $quantity, $price, $supplier, $lowStockThreshold);

        $this->respond(200, [
            "message" => "Peça atualizada com sucesso.",
            "data" => $this->partModel->findById($id)
        ]);
    }

    public function destroy(int $id): void
    {
        AuthMiddleware::authorize(['Administrador', 'Funcionário']);

        $existing = $this->partModel->findById($id);
        if (!$existing) {
            $this->respond(404, ["error" => "Peça não encontrada."]);
            return;
        }

        $this->partModel->delete($id);
        $this->respond(200, ["message" => "Peça removida com sucesso."]);
    }

    private function respond(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        echo json_encode($data);
    }
}