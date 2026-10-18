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
require_once __DIR__ . '/../src/controllers/ClientController.php';
require_once __DIR__ . '/../src/controllers/VehicleController.php';
require_once __DIR__ . '/../src/controllers/PartController.php';
require_once __DIR__ . '/../src/controllers/MaintenanceController.php';
require_once __DIR__ . '/../src/controllers/PaymentController.php';

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
$clientController = new ClientController($conn);
$vehicleController = new VehicleController($conn);
$partController = new PartController($conn);
$maintenanceController = new MaintenanceController($conn);
$paymentController = new PaymentController($conn);

// ============================================
// ROTEAMENTO
// ============================================

// --- Autenticação ---
if ($route === 'api/auth/register' && $method === 'POST') { $authController->register($input); exit; }
if ($route === 'api/auth/login' && $method === 'POST') { $authController->login($input); exit; }
if ($route === 'api/auth/logout' && $method === 'POST') { $authController->logout(); exit; }
if ($route === 'api/auth/forgot-password' && $method === 'POST') { $authController->forgotPassword($input); exit; }
if ($route === 'api/auth/reset-password' && $method === 'POST') { $authController->resetPassword($input); exit; }
if ($route === 'api/auth/me' && $method === 'GET') { $authController->me(); exit; }

// --- Utilizadores (Módulo 2) ---
if ($route === 'api/users' && $method === 'GET') { $userController->index(); exit; }
if ($route === 'api/users' && $method === 'POST') { $userController->store($input); exit; }
if (preg_match('#^api/users/(\d+)$#', $route, $m) && $method === 'GET') { $userController->show((int)$m[1]); exit; }
if (preg_match('#^api/users/(\d+)$#', $route, $m) && $method === 'PUT') { $userController->update((int)$m[1], $input); exit; }
if (preg_match('#^api/users/(\d+)/toggle-active$#', $route, $m) && $method === 'PATCH') { $userController->toggleActive((int)$m[1]); exit; }
if (preg_match('#^api/users/(\d+)$#', $route, $m) && $method === 'DELETE') { $userController->destroy((int)$m[1]); exit; }

// --- Clientes (Módulo 3) ---
if ($route === 'api/clients' && $method === 'GET') { $clientController->index(); exit; }
if ($route === 'api/clients' && $method === 'POST') { $clientController->store($input); exit; }
if (preg_match('#^api/clients/(\d+)$#', $route, $m) && $method === 'GET') { $clientController->show((int)$m[1]); exit; }
if (preg_match('#^api/clients/(\d+)$#', $route, $m) && $method === 'PUT') { $clientController->update((int)$m[1], $input); exit; }
if (preg_match('#^api/clients/(\d+)$#', $route, $m) && $method === 'DELETE') { $clientController->destroy((int)$m[1]); exit; }

// --- Veículos (Módulo 3) ---
if ($route === 'api/vehicles' && $method === 'GET') { $vehicleController->index(); exit; }
if ($route === 'api/vehicles' && $method === 'POST') { $vehicleController->store($input); exit; }
if (preg_match('#^api/vehicles/(\d+)$#', $route, $m) && $method === 'GET') { $vehicleController->show((int)$m[1]); exit; }
if (preg_match('#^api/vehicles/(\d+)$#', $route, $m) && $method === 'PUT') { $vehicleController->update((int)$m[1], $input); exit; }
if (preg_match('#^api/vehicles/(\d+)$#', $route, $m) && $method === 'DELETE') { $vehicleController->destroy((int)$m[1]); exit; }

// --- Peças (Módulo 4) ---
if ($route === 'api/parts' && $method === 'GET') { $partController->index(); exit; }
if ($route === 'api/parts' && $method === 'POST') { $partController->store($input); exit; }
if (preg_match('#^api/parts/(\d+)$#', $route, $m) && $method === 'GET') { $partController->show((int)$m[1]); exit; }
if (preg_match('#^api/parts/(\d+)$#', $route, $m) && $method === 'PUT') { $partController->update((int)$m[1], $input); exit; }
if (preg_match('#^api/parts/(\d+)$#', $route, $m) && $method === 'DELETE') { $partController->destroy((int)$m[1]); exit; }

// --- Manutenção (Módulo 5) ---
if ($route === 'api/maintenance' && $method === 'GET') { $maintenanceController->index(); exit; }
if ($route === 'api/maintenance' && $method === 'POST') { $maintenanceController->store($input); exit; }
if (preg_match('#^api/maintenance/(\d+)$#', $route, $m) && $method === 'GET') { $maintenanceController->show((int)$m[1]); exit; }
if (preg_match('#^api/maintenance/(\d+)$#', $route, $m) && $method === 'DELETE') { $maintenanceController->destroy((int)$m[1]); exit; }
if (preg_match('#^api/vehicles/(\d+)/maintenance$#', $route, $m) && $method === 'GET') { $maintenanceController->byVehicle((int)$m[1]); exit; }

// --- Pagamentos ---
if ($route === 'api/payments' && $method === 'GET') { $paymentController->index(); exit; }
if ($route === 'api/payments' && $method === 'POST') { $paymentController->store($input); exit; }
if (preg_match('#^api/payments/ref/([A-Za-z0-9\-]+)$#', $route, $m) && $method === 'GET') { $paymentController->showByReference($m[1]); exit; }
if (preg_match('#^api/payments/ref/([A-Za-z0-9\-]+)/pay$#', $route, $m) && $method === 'PATCH') { $paymentController->pay($m[1], $input); exit; }
if (preg_match('#^api/payments/ref/([A-Za-z0-9\-]+)/reopen$#', $route, $m) && $method === 'PATCH') { $paymentController->reopen($m[1]); exit; }
if (preg_match('#^api/payments/ref/([A-Za-z0-9\-]+)/cancel$#', $route, $m) && $method === 'PATCH') { $paymentController->cancel($m[1], $input); exit; }
if (preg_match('#^api/maintenance/(\d+)$#', $route, $m) && $method === 'PUT') { $maintenanceController->update((int)$m[1], $input); exit; }

// --- Rota não encontrada ---
http_response_code(404);
echo json_encode(["error" => "Rota não encontrada: $route"]);