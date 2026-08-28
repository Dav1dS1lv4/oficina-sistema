<?php
require_once __DIR__ . '/../models/Client.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class ClientController
{
    private Client $clientModel;

    public function __construct(PDO $db)
    {
        $this->clientModel = new Client($db);
    }

    public function index(): void
    {
        AuthMiddleware::authorize();

        $search = trim($_GET['search'] ?? '');
        $clients = $this->clientModel->findAll($search);

        $this->respond(200, ["data" => $clients]);
    }

    public function show(int $id): void
    {
        AuthMiddleware::authorize();

        $client = $this->clientModel->findById($id);
        if (!$client) {
            $this->respond(404, ["error" => "Cliente não encontrado."]);
            return;
        }

        $this->respond(200, ["data" => $client]);
    }

    public function store(array $input): void
    {
        AuthMiddleware::authorize(['Administrador', 'Funcionário']);

        $name = trim($input['name'] ?? '');
        $nif = trim($input['nif'] ?? '') ?: null;
        $email = trim($input['email'] ?? '') ?: null;
        $phone = trim($input['phone'] ?? '') ?: null;
        $address = trim($input['address'] ?? '') ?: null;

        if ($name === '') {
            $this->respond(422, ["error" => "O nome do cliente é obrigatório."]);
            return;
        }

        if ($nif !== null && !preg_match('/^\d{9}$/', $nif)) {
            $this->respond(422, ["error" => "O NIF deve ter exatamente 9 dígitos."]);
            return;
        }

        if ($nif !== null && $this->clientModel->nifExists($nif)) {
            $this->respond(409, ["error" => "Já existe um cliente com este NIF."]);
            return;
        }

        $id = $this->clientModel->create($name, $nif, $email, $phone, $address);

        $this->respond(201, [
            "message" => "Cliente criado com sucesso.",
            "data" => $this->clientModel->findById($id)
        ]);
    }

    public function update(int $id, array $input): void
    {
        AuthMiddleware::authorize(['Administrador', 'Funcionário']);

        $existing = $this->clientModel->findById($id);
        if (!$existing) {
            $this->respond(404, ["error" => "Cliente não encontrado."]);
            return;
        }

        $name = trim($input['name'] ?? '');
        $nif = trim($input['nif'] ?? '') ?: null;
        $email = trim($input['email'] ?? '') ?: null;
        $phone = trim($input['phone'] ?? '') ?: null;
        $address = trim($input['address'] ?? '') ?: null;

        if ($name === '') {
            $this->respond(422, ["error" => "O nome do cliente é obrigatório."]);
            return;
        }

        if ($nif !== null && !preg_match('/^\d{9}$/', $nif)) {
            $this->respond(422, ["error" => "O NIF deve ter exatamente 9 dígitos."]);
            return;
        }

        if ($nif !== null && $this->clientModel->nifExists($nif, $id)) {
            $this->respond(409, ["error" => "Já existe outro cliente com este NIF."]);
            return;
        }

        $this->clientModel->update($id, $name, $nif, $email, $phone, $address);

        $this->respond(200, [
            "message" => "Cliente atualizado com sucesso.",
            "data" => $this->clientModel->findById($id)
        ]);
    }

    public function destroy(int $id): void
    {
        AuthMiddleware::authorize(['Administrador', 'Funcionário']);

        $existing = $this->clientModel->findById($id);
        if (!$existing) {
            $this->respond(404, ["error" => "Cliente não encontrado."]);
            return;
        }

        if ($this->clientModel->hasVehicles($id)) {
            $this->respond(400, ["error" => "Não é possível remover: este cliente tem veículos associados."]);
            return;
        }

        $this->clientModel->delete($id);
        $this->respond(200, ["message" => "Cliente removido com sucesso."]);
    }

    private function respond(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        echo json_encode($data);
    }
}