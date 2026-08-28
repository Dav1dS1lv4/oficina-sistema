<?php
class Part
{
    private PDO $conn;
    private string $table = "parts";

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    public function findAll(int $page = 1, int $perPage = 10, string $search = ''): array
    {
        $offset = ($page - 1) * $perPage;

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = "WHERE name LIKE :search OR category LIKE :search OR supplier LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        $query = "SELECT * FROM {$this->table} {$where} ORDER BY name ASC LIMIT :limit OFFSET :offset";
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
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = "WHERE name LIKE :search OR category LIKE :search OR supplier LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        $query = "SELECT COUNT(*) as total FROM {$this->table} {$where}";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetch()['total'];
    }

    public function findById(int $id): ?array
    {
        $query = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $part = $stmt->fetch();
        return $part ?: null;
    }

    public function create(string $name, ?string $category, int $quantity, float $price, ?string $supplier, int $lowStockThreshold): int
    {
        $query = "INSERT INTO {$this->table} (name, category, quantity, price, supplier, low_stock_threshold)
                  VALUES (:name, :category, :quantity, :price, :supplier, :low_stock_threshold)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':supplier', $supplier);
        $stmt->bindParam(':low_stock_threshold', $lowStockThreshold, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->conn->lastInsertId();
    }

    public function update(int $id, string $name, ?string $category, int $quantity, float $price, ?string $supplier, int $lowStockThreshold): bool
    {
        $query = "UPDATE {$this->table}
                  SET name = :name, category = :category, quantity = :quantity, price = :price,
                      supplier = :supplier, low_stock_threshold = :low_stock_threshold
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':supplier', $supplier);
        $stmt->bindParam(':low_stock_threshold', $lowStockThreshold, PDO::PARAM_INT);
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

    public function countTotal(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) as total FROM {$this->table}");
        return (int) $stmt->fetch()['total'];
    }

    public function countLowStock(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) as total FROM {$this->table} WHERE quantity <= low_stock_threshold");
        return (int) $stmt->fetch()['total'];
    }

    public function findLowStock(): array
    {
        $stmt = $this->conn->query("SELECT * FROM {$this->table} WHERE quantity <= low_stock_threshold ORDER BY quantity ASC");
        return $stmt->fetchAll();
    }
}