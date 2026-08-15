<?php
/**
 * Model User: responsável por toda a comunicação com a tabela `users`.
 * Não tem lógica de "resposta HTTP" — só sabe falar com a base de dados.
 * Quem decide o que responder ao browser é o Controller.
 */

class User
{
    private PDO $conn;
    private string $table = "users";

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function findByEmail(string $email): ?array
    {
        $query = "SELECT u.*, r.name AS role_name
                  FROM {$this->table} u
                  JOIN roles r ON r.id = u.role_id
                  WHERE u.email = :email
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $query = "SELECT u.id, u.name, u.email, u.active, u.role_id, r.name AS role_name
                  FROM {$this->table} u
                  JOIN roles r ON r.id = u.role_id
                  WHERE u.id = :id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function create(string $name, string $email, string $hashedPassword, int $roleId): int
    {
        $query = "INSERT INTO {$this->table} (name, email, password, role_id)
                  VALUES (:name, :email, :password, :role_id)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->conn->lastInsertId();
    }

    public function saveResetToken(int $userId, string $token, string $expiresAt): bool
    {
        $query = "UPDATE {$this->table}
                  SET reset_token = :token, reset_token_expires = :expires
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expires', $expiresAt);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function findByResetToken(string $token): ?array
    {
        $query = "SELECT * FROM {$this->table}
                  WHERE reset_token = :token AND reset_token_expires > NOW()
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();

        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function updatePassword(int $userId, string $hashedPassword): bool
    {
        $query = "UPDATE {$this->table}
                  SET password = :password, reset_token = NULL, reset_token_expires = NULL
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function emailExists(string $email): bool
    {
        $query = "SELECT id FROM {$this->table} WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        return $stmt->fetch() !== false;
    }
}
