<?php
class Client
{
    private PDO $conn;
    private string $table = "clients";

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function findAll(string $search = ''): array
    {
        $where = '';
        $params = [];

        if ($search !== '') {
            $where = "WHERE name LIKE :search OR email LIKE :search OR phone LIKE :search OR nif LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        $query = "SELECT * FROM {$this->table} {$where} ORDER BY name ASC";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $query = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $client = $stmt->fetch();
        return $client ?: null;
    }

    public function nifExists(string $nif, ?int $excludeId = null): bool
    {
        $query = "SELECT id FROM {$this->table} WHERE nif = :nif";
        $params = [':nif' => $nif];

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

    public function create(string $name, ?string $nif, ?string $email, ?string $phone, ?string $address): int
    {
        $query = "INSERT INTO {$this->table} (name, nif, email, phone, address)
                  VALUES (:name, :nif, :email, :phone, :address)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':nif', $nif);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
        $stmt->execute();

        return (int) $this->conn->lastInsertId();
    }

    public function update(int $id, string $name, ?string $nif, ?string $email, ?string $phone, ?string $address): bool
    {
        $query = "UPDATE {$this->table}
                  SET name = :name, nif = :nif, email = :email, phone = :phone, address = :address
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':nif', $nif);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':address', $address);
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

    public function hasVehicles(int $id): bool
    {
        $query = "SELECT id FROM vehicles WHERE client_id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch() !== false;
    }
}