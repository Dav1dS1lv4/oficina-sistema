<?php
/**
 * Funções auxiliares para criar e validar tokens JWT.
 * A chave secreta e o tempo de expiração vêm do ficheiro .env.
 */

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHelper
{
    private static function getSecretKey(): string
    {
        return $_ENV['JWT_SECRET'] ?? 'chave_por_defeito_insegura';
    }

    private static function getExpirationSeconds(): int
    {
        return (int) ($_ENV['JWT_EXPIRATION_SECONDS'] ?? 3600 * 8);
    }

    private static string $algorithm = "HS256";

    public static function generateToken(array $userData): string
    {
        $issuedAt = time();
        $expire = $issuedAt + self::getExpirationSeconds();

        $payload = [
            "iat" => $issuedAt,
            "exp" => $expire,
            "data" => [
                "id" => $userData['id'],
                "name" => $userData['name'],
                "email" => $userData['email'],
                "role" => $userData['role'],
            ]
        ];

        return JWT::encode($payload, self::getSecretKey(), self::$algorithm);
    }

    public static function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::getSecretKey(), self::$algorithm));
            return (array) $decoded->data;
        } catch (Exception $e) {
            return null;
        }
    }

    public static function getTokenFromHeader(): ?string
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if (!$authHeader) {
            return null;
        }

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return $matches[1];
        }

        return null;
    }
}