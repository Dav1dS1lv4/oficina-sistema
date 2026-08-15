<?php
/**
 * Funções auxiliares para criar e validar tokens JWT.
 *
 * Um JWT tem 3 partes separadas por pontos: header.payload.signature
 * - O "payload" contém dados sobre o utilizador (ex: id, role) — não é secreto,
 *   qualquer pessoa consegue ler o conteúdo (não é encriptado, só assinado).
 * - A "signature" garante que ninguém alterou o conteúdo sem saber a nossa chave secreta.
 */

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtHelper
{
    // Em produção, isto devia vir de uma variável de ambiente (.env), nunca escrito diretamente no código.
    // Para o projeto de aprendizagem, mantemos aqui, mas fica o aviso.
    private static string $secretKey = "oficina_chave_secreta_troca_isto_em_producao";
    private static string $algorithm = "HS256";
    private static int $expirationSeconds = 3600 * 8; // token válido por 8 horas

    public static function generateToken(array $userData): string
    {
        $issuedAt = time();
        $expire = $issuedAt + self::$expirationSeconds;

        $payload = [
            "iat" => $issuedAt,   // issued at (quando foi criado)
            "exp" => $expire,     // expiration (quando expira)
            "data" => [
                "id" => $userData['id'],
                "name" => $userData['name'],
                "email" => $userData['email'],
                "role" => $userData['role'],
            ]
        ];

        return JWT::encode($payload, self::$secretKey, self::$algorithm);
    }

    /**
     * Valida o token e devolve os dados do utilizador, ou null se for inválido/expirado.
     */
    public static function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::$secretKey, self::$algorithm));
            return (array) $decoded->data;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Extrai o token do cabeçalho "Authorization: Bearer <token>"
     */
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
