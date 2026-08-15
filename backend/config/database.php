<?php
/**
 * Ligação à base de dados usando PDO.
 * PDO é usado (em vez de mysqli) porque suporta "prepared statements"
 * de forma mais limpa, o que nos protege de SQL Injection.
 */

class Database
{
    private string $host = "127.0.0.1";
    private string $db_name = "oficina";
    private string $username = "root";
    private string $password = ""; // XAMPP por defeito não tem password no root
    private ?PDO $conn = null;

    public function connect(): PDO
    {
        if ($this->conn !== null) {
            return $this->conn;
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $this->conn = new PDO($dsn, $this->username, $this->password);

            // Faz com que erros de SQL lancem exceções em vez de falhar silenciosamente
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Devolve os resultados como arrays associativos (com nomes de coluna)
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => "Erro de ligação à base de dados: " . $e->getMessage()]);
            exit;
        }

        return $this->conn;
    }
}
