<?php
class Vehicle
{
    private PDO $conn;
    private string $table = "vehicles";

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
            $where = "WHERE v.license_plate LIKE :search OR v.brand LIKE :search OR v.model LIKE :search OR c.name LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        $query = "SELECT v.*, c.name AS client_name, u.name AS created_by_name
                  FROM {$this->table} v
                  JOIN clients c ON c.id = v.client_id
                  LEFT JOIN users u ON u.id = v.created_by
                  {$where}
                  ORDER BY v.created_at DESC
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
        $where = '';
        $params = [];
        if ($search !== '') {
            $where = "WHERE v.license_plate LIKE :search OR v.brand LIKE :search OR v.model LIKE :search OR c.name LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        $query = "SELECT COUNT(*) as total FROM {$this->table} v JOIN clients c ON c.id = v.client_id {$where}";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetch()['total'];
    }

    public function findById(int $id): ?array
    {
        $query = "SELECT v.*, c.name AS client_name, u.name AS created_by_name
                  FROM {$this->table} v
                  JOIN clients c ON c.id = v.client_id
                  LEFT JOIN users u ON u.id = v.created_by
                  WHERE v.id = :id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $vehicle = $stmt->fetch();
        return $vehicle ?: null;
    }

    public function create(string $licensePlate, string $brand, string $model, int $year, int $clientId, int $createdBy): int
    {
        $query = "INSERT INTO {$this->table} (license_plate, brand, model, year, client_id, created_by)
                  VALUES (:license_plate, :brand, :model, :year, :client_id, :created_by)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':license_plate', $licensePlate);
        $stmt->bindParam(':brand', $brand);
        $stmt->bindParam(':model', $model);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->bindParam(':client_id', $clientId, PDO::PARAM_INT);
        $stmt->bindParam(':created_by', $createdBy, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->conn->lastInsertId();
    }

    public function update(int $id, string $licensePlate, string $brand, string $model, int $year, int $clientId): bool
    {
        // Nota: propositadamente não alteramos "created_by" numa edição —
        // o registo mantém sempre quem o criou originalmente.
        $query = "UPDATE {$this->table}
                  SET license_plate = :license_plate, brand = :brand, model = :model, year = :year, client_id = :client_id
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':license_plate', $licensePlate);
        $stmt->bindParam(':brand', $brand);
        $stmt->bindParam(':model', $model);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->bindParam(':client_id', $clientId, PDO::PARAM_INT);
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

    public function licensePlateExists(string $licensePlate, ?int $excludeId = null): bool
    {
        $query = "SELECT id FROM {$this->table} WHERE license_plate = :license_plate";
        $params = [':license_plate' => $licensePlate];

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

    public function countTotal(): int
    {
        $stmt = $this->conn->query("SELECT COUNT(*) as total FROM {$this->table}");
        return (int) $stmt->fetch()['total'];
    }
}