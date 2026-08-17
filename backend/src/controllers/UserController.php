<?php
/**
 * UserController: gestão de utilizadores (Módulo 2).
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class UserController
{
    private PDO $conn;
    private User $userModel;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
        $this->userModel = new User($db);
    }

    public function index(): void
    {
        AuthMiddleware::authorize(['Administrador']);

        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? min(50, max(1, (int)$_GET['per_page'])) : 10;
        $search = trim($_GET['search'] ?? '');

        $users = $this->userModel->findAll($page, $perPage, $search);
        $total = $this->userModel->countAll($search);

        $this->respond(200, [
            "data" => $users,
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
        AuthMiddleware::authorize(['Administrador']);

        $user = $this->userModel->findById($id);

        if (!$user) {
            $this->respond(404, ["error" => "Utilizador não encontrado."]);
            return;
        }

        $this->respond(200, ["data" => $user]);
    }

    public function store(array $input): void
    {
        AuthMiddleware::authorize(['Administrador']);

        $name = trim($input['name'] ?? '');
        $email = trim(strtolower($input['email'] ?? ''));
        $password = $input['password'] ?? '';
        $roleId = (int) ($input['role_id'] ?? 0);

        if ($name === '' || $email === '' || $password === '' || $roleId === 0) {
            $this->respond(422, ["error" => "Nome, email, password e role são obrigatórios."]);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond(422, ["error" => "Email inválido."]);
            return;
        }

        if (strlen($password) < 6) {
            $this->respond(422, ["error" => "A password deve ter pelo menos 6 caracteres."]);
            return;
        }

        if (!in_array($roleId, [1, 2, 3])) {
            $this->respond(422, ["error" => "Role inválida."]);
            return;
        }

        if ($this->userModel->emailExists($email)) {
            $this->respond(409, ["error" => "Já existe uma conta com este email."]);
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $newUserId = $this->userModel->create($name, $email, $hashedPassword, $roleId);

        $this->respond(201, [
            "message" => "Utilizador criado com sucesso.",
            "data" => $this->userModel->findById($newUserId)
        ]);
    }

    public function update(int $id, array $input): void
    {
        $currentUser = AuthMiddleware::authorize(['Administrador']);

        $existingUser = $this->userModel->findById($id);
        if (!$existingUser) {
            $this->respond(404, ["error" => "Utilizador não encontrado."]);
            return;
        }

        $name = trim($input['name'] ?? '');
        $email = trim(strtolower($input['email'] ?? ''));
        $roleId = (int) ($input['role_id'] ?? 0);

        if ($name === '' || $email === '' || $roleId === 0) {
            $this->respond(422, ["error" => "Nome, email e role são obrigatórios."]);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->respond(422, ["error" => "Email inválido."]);
            return;
        }

        if (!in_array($roleId, [1, 2, 3])) {
            $this->respond(422, ["error" => "Role inválida."]);
            return;
        }

        if ((int)$currentUser['id'] === $id && $roleId !== 1) {
            $this->respond(400, ["error" => "Não podes remover a tua própria permissão de Administrador."]);
            return;
        }

        if ($this->userModel->emailExists($email, $id)) {
            $this->respond(409, ["error" => "Já existe outra conta com este email."]);
            return;
        }

        $this->userModel->update($id, $name, $email, $roleId);

        if (!empty($input['password'])) {
            if (strlen($input['password']) < 6) {
                $this->respond(422, ["error" => "A password deve ter pelo menos 6 caracteres."]);
                return;
            }
            $hashedPassword = password_hash($input['password'], PASSWORD_BCRYPT);
            $this->userModel->updatePasswordById($id, $hashedPassword);
        }

        $this->respond(200, [
            "message" => "Utilizador atualizado com sucesso.",
            "data" => $this->userModel->findById($id)
        ]);
    }

    public function toggleActive(int $id): void
    {
        $currentUser = AuthMiddleware::authorize(['Administrador']);

        $existingUser = $this->userModel->findById($id);
        if (!$existingUser) {
            $this->respond(404, ["error" => "Utilizador não encontrado."]);
            return;
        }

        if ((int)$currentUser['id'] === $id) {
            $this->respond(400, ["error" => "Não podes desativar a tua própria conta."]);
            return;
        }

        $newStatus = !((bool) $existingUser['active']);
        $this->userModel->setActive($id, $newStatus);

        $this->respond(200, [
            "message" => $newStatus ? "Conta ativada." : "Conta desativada.",
            "data" => $this->userModel->findById($id)
        ]);
    }

    public function destroy(int $id): void
    {
        $currentUser = AuthMiddleware::authorize(['Administrador']);

        $existingUser = $this->userModel->findById($id);
        if (!$existingUser) {
            $this->respond(404, ["error" => "Utilizador não encontrado."]);
            return;
        }

        if ((int)$currentUser['id'] === $id) {
            $this->respond(400, ["error" => "Não podes remover a tua própria conta."]);
            return;
        }

        $this->userModel->delete($id);

        $this->respond(200, ["message" => "Utilizador removido com sucesso."]);
    }

    private function respond(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        echo json_encode($data);
    }
}