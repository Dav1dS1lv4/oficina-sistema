<?php
/**
 * AuthController: lida com tudo relacionado a autenticação.
 * Cada método corresponde a uma rota da API (ver public/index.php).
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../utils/JwtHelper.php';

class AuthController
{
    private PDO $conn;
    private User $userModel;

    public function __construct(PDO $db)
    {
        $this->conn = $db;
        $this->userModel = new User($db);
    }

    /**
     * POST /api/auth/register
     * Body esperado: { name, email, password }
     * Novos registos ficam sempre com role "Visitante" por segurança —
     * só um Administrador pode promover alguém a Funcionário/Administrador (Módulo 2).
     */
    public function register(array $input): void
    {
        $name = trim($input['name'] ?? '');
        $email = trim(strtolower($input['email'] ?? ''));
        $password = $input['password'] ?? '';

        // Validações básicas
        if ($name === '' || $email === '' || $password === '') {
            $this->respond(422, ["error" => "Nome, email e password são obrigatórios."]);
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

        if ($this->userModel->emailExists($email)) {
            $this->respond(409, ["error" => "Já existe uma conta com este email."]);
            return;
        }

        // password_hash() usa bcrypt por defeito — nunca guardamos a password em texto simples
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // role_id 3 = "Visitante" (ver tabela roles no schema)
        $visitorRoleId = 3;
        $newUserId = $this->userModel->create($name, $email, $hashedPassword, $visitorRoleId);

        $this->respond(201, [
            "message" => "Utilizador registado com sucesso.",
            "user_id" => $newUserId
        ]);
    }

    /**
     * POST /api/auth/login
     * Body esperado: { email, password }
     */
    public function login(array $input): void
    {
        $email = trim(strtolower($input['email'] ?? ''));
        $password = $input['password'] ?? '';

        if ($email === '' || $password === '') {
            $this->respond(422, ["error" => "Email e password são obrigatórios."]);
            return;
        }

        $user = $this->userModel->findByEmail($email);

        // Mensagem genérica de propósito: não revelar se foi o email ou a password que falhou
        if (!$user || !password_verify($password, $user['password'])) {
            $this->respond(401, ["error" => "Email ou password incorretos."]);
            return;
        }

        if ((int)$user['active'] === 0) {
            $this->respond(403, ["error" => "Esta conta está desativada. Contacte um administrador."]);
            return;
        }

        $token = JwtHelper::generateToken([
            "id" => $user['id'],
            "name" => $user['name'],
            "email" => $user['email'],
            "role" => $user['role_name'],
        ]);

        $this->respond(200, [
            "message" => "Login efetuado com sucesso.",
            "token" => $token,
            "user" => [
                "id" => $user['id'],
                "name" => $user['name'],
                "email" => $user['email'],
                "role" => $user['role_name'],
            ]
        ]);
    }

    /**
     * POST /api/auth/logout
     * Com JWT, o "logout" é feito principalmente no lado do cliente
     * (o Angular apaga o token guardado). O servidor não guarda sessões.
     */
    public function logout(): void
    {
        $this->respond(200, ["message" => "Logout efetuado com sucesso."]);
    }

    /**
     * GET /api/auth/me
     * Devolve os dados do utilizador autenticado, a partir do token enviado.
     * Serve para o Angular saber "quem está logado" ao recarregar a página.
     */
    public function me(): void
    {
        $token = JwtHelper::getTokenFromHeader();

        if (!$token) {
            $this->respond(401, ["error" => "Token não fornecido."]);
            return;
        }

        $data = JwtHelper::validateToken($token);

        if (!$data) {
            $this->respond(401, ["error" => "Token inválido ou expirado."]);
            return;
        }

        $this->respond(200, ["user" => $data]);
    }

    /**
     * POST /api/auth/forgot-password
     * Body esperado: { email }
     * Gera um token temporário. Num sistema real, enviaríamos este token por email;
     * aqui, devolvemo-lo diretamente na resposta só para efeitos de aprendizagem/teste.
     */
    public function forgotPassword(array $input): void
    {
        $email = trim(strtolower($input['email'] ?? ''));

        if ($email === '') {
            $this->respond(422, ["error" => "Email é obrigatório."]);
            return;
        }

        $user = $this->userModel->findByEmail($email);

        // Por segurança, respondemos sempre da mesma forma,
        // mesmo que o email não exista (evita confirmar quais emails estão registados)
        if (!$user) {
            $this->respond(200, [
                "message" => "Se o email existir, receberá instruções de recuperação."
            ]);
            return;
        }

        $resetToken = bin2hex(random_bytes(32)); // token aleatório seguro
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $this->userModel->saveResetToken($user['id'], $resetToken, $expiresAt);

        // ATENÇÃO: em produção nunca devolvias o token na resposta da API.
        // Enviarias por email e a resposta seria só a mensagem genérica abaixo.
        $this->respond(200, [
            "message" => "Se o email existir, receberá instruções de recuperação.",
            "dev_reset_token" => $resetToken // só para testarmos sem servidor de email
        ]);
    }

    /**
     * POST /api/auth/reset-password
     * Body esperado: { token, password }
     */
    public function resetPassword(array $input): void
    {
        $token = $input['token'] ?? '';
        $newPassword = $input['password'] ?? '';

        if ($token === '' || $newPassword === '') {
            $this->respond(422, ["error" => "Token e nova password são obrigatórios."]);
            return;
        }

        if (strlen($newPassword) < 6) {
            $this->respond(422, ["error" => "A password deve ter pelo menos 6 caracteres."]);
            return;
        }

        $user = $this->userModel->findByResetToken($token);

        if (!$user) {
            $this->respond(400, ["error" => "Token inválido ou expirado."]);
            return;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->userModel->updatePassword($user['id'], $hashedPassword);

        $this->respond(200, ["message" => "Password redefinida com sucesso."]);
    }

    /**
     * Helper interno para responder sempre no mesmo formato (JSON + status code correto)
     */
    private function respond(int $statusCode, array $data): void
    {
        http_response_code($statusCode);
        echo json_encode($data);
    }
}
