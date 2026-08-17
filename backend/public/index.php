<?php
/**
 * Ponto de entrada de toda a API.
 */

header("Access-Control-Allow-Origin: http://localhost:4200");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/controllers/AuthController.php';
require_once __DIR__ . '/../src/controllers/UserController.php';

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/oficina-backend';
$route = str_replace($basePath, '', $requestUri);
$route = trim($route, '/');

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$db = new Database();
$conn = $db->connect();

$authController = new AuthController($conn);
$userController = new UserController($conn);

// --- Autenticação ---
if ($route === 'api/auth/register' && $method === 'POST') {
    $authController->register($input);
    exit;
}
if ($route === 'api/auth/login' && $method === 'POST') {
    $authController->login($input);
    exit;
}
if ($route === 'api/auth/logout' && $method === 'POST') {
    $authController->logout();
    exit;
}
if ($route === 'api/auth/forgot-password' && $method === 'POST') {
    $authController->forgotPassword($input);
    exit;
}
if ($route === 'api/auth/reset-password' && $method === 'POST') {
    $authController->resetPassword($input);
    exit;
}
if ($route === 'api/auth/me' && $method === 'GET') {
    $authController->me();
    exit;
}

// --- Utilizadores (Módulo 2) ---
if ($route === 'api/users' && $method === 'GET') {
    $userController->index();
    exit;
}
if ($route === 'api/users' && $method === 'POST') {
    $userController->store($input);
    exit;
}
if (preg_match('#^api/users/(\d+)$#', $route, $matches) && $method === 'GET') {
    $userController->show((int)$matches[1]);
    exit;
}
if (preg_match('#^api/users/(\d+)$#', $route, $matches) && $method === 'PUT') {
    $userController->update((int)$matches[1], $input);
    exit;
}
if (preg_match('#^api/users/(\d+)/toggle-active$#', $route, $matches) && $method === 'PATCH') {
    $userController->toggleActive((int)$matches[1]);
    exit;
}
if (preg_match('#^api/users/(\d+)$#', $route, $matches) && $method === 'DELETE') {
    $userController->destroy((int)$matches[1]);
    exit;
}

http_response_code(404);
echo json_encode(["error" => "Rota não encontrada: $route"]);