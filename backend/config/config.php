<?php
class Database {
    private $host = "db";
    private $db_name = "autosync_db";
    private $username = "root";
    private $password = "root";
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8mb4");
        } catch(PDOException $exception) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(array("message" => "Error de conexión: " . $exception->getMessage()));
            exit;
        }

        return $this->conn;
    }
}
?>
