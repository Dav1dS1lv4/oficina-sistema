<?php
/**
 * AuthMiddleware: verifica se o pedido tem um token válido e,
 * opcionalmente, se o utilizador tem uma das roles permitidas.
 */

require_once __DIR__ . '/../utils/JwtHelper.php';

class AuthMiddleware
{
    public static function authorize(array $allowedRoles = []): array
    {
        $token = JwtHelper::getTokenFromHeader();

        if (!$token) {
            self::deny(401, "Token não fornecido.");
        }

        $userData = JwtHelper::validateToken($token);

        if (!$userData) {
            self::deny(401, "Token inválido ou expirado.");
        }

        if (!empty($allowedRoles) && !in_array($userData['role'], $allowedRoles)) {
            self::deny(403, "Não tens permissão para aceder a este recurso.");
        }

        return $userData;
    }

    private static function deny(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        echo json_encode(["error" => $message]);
        exit;
    }
}