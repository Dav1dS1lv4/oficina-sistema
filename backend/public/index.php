<?php
/**
 * Ponto de entrada de toda a API.
 * Todos os pedidos passam por aqui (graças ao .htaccess) e são
 * encaminhados para o "controller" certo, consoante a rota pedida.
 */

// Permite que o Angular (que corre noutra porta) aceda a esta API
header("Access-Control-Allow-Origin: http://localhost:4200");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// O navegador envia um pedido OPTIONS antes de pedidos "reais" (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/controllers/AuthController.php';

// Descobre a rota pedida, removendo o caminho base do projeto
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = '/oficina-backend';
$route = str_replace($basePath, '', $requestUri);
$route = trim($route, '/'); // ex: "api/auth/login"

$method = $_SERVER['REQUEST_METHOD'];

// Lê o corpo do pedido (JSON) e transforma em array PHP
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$db = new Database();
$conn = $db->connect();

$authController = new AuthController($conn);

// ============================================
// ROTEAMENTO
// ============================================
switch (true) {
    case $route === 'api/auth/register' && $method === 'POST':
        $authController->register($input);
        break;

    case $route === 'api/auth/login' && $method === 'POST':
        $authController->login($input);
        break;

    case $route === 'api/auth/logout' && $method === 'POST':
        $authController->logout();
        break;

    case $route === 'api/auth/forgot-password' && $method === 'POST':
        $authController->forgotPassword($input);
        break;

    case $route === 'api/auth/reset-password' && $method === 'POST':
        $authController->resetPassword($input);
        break;

    case $route === 'api/auth/me' && $method === 'GET':
        $authController->me();
        break;

    default:
        http_response_code(404);
        echo json_encode(["error" => "Rota não encontrada: $route"]);
        break;
}
