<?php
/**
 * Model User: responsável por toda a comunicação com a tabela `users`.
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
        $query = "SELECT u.id, u.name, u.email, u.active, u.role_id, r.name AS role_name, u.created_at
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

    public function findAll(int $page = 1, int $perPage = 10, string $search = ''): array
    {
        $offset = ($page - 1) * $perPage;

        $whereClause = '';
        $params = [];

        if ($search !== '') {
            $whereClause = "WHERE u.name LIKE :search OR u.email LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        $query = "SELECT u.id, u.name, u.email, u.active, u.role_id, r.name AS role_name, u.created_at
                  FROM {$this->table} u
                  JOIN roles r ON r.id = u.role_id
                  {$whereClause}
                  ORDER BY u.created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAll(string $search = ''): int
    {
        $whereClause = '';
        $params = [];

        if ($search !== '') {
            $whereClause = "WHERE name LIKE :search OR email LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        $query = "SELECT COUNT(*) as total FROM {$this->table} {$whereClause}";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetch()['total'];
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

    public function update(int $id, string $name, string $email, int $roleId): bool
    {
        $query = "UPDATE {$this->table}
                  SET name = :name, email = :email, role_id = :role_id
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function updatePasswordById(int $id, string $hashedPassword): bool
    {
        $query = "UPDATE {$this->table} SET password = :password WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function setActive(int $id, bool $active): bool
    {
        $query = "UPDATE {$this->table} SET active = :active WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $activeInt = $active ? 1 : 0;
        $stmt->bindParam(':active', $activeInt, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function delete(int $id): bool
    {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
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

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        $query = "SELECT id FROM {$this->table} WHERE email = :email";
        $params = [':email' => $email];

        if ($excludeId !== null) {
            $query .= " AND id != :excludeId";
            $params[':excludeId'] = $excludeId;
        }

        $query .= " LIMIT 1";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetch() !== false;
    }
}