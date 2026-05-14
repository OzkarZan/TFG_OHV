<?php
class Database
{
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function getConnection()
    {
        $this->conn = null;

        // Inicializamos las variables aquí dentro, donde sí se permiten funciones
        $this->host = getenv('DB_HOST') ?: 'db';
        $this->db_name = getenv('DB_NAME');
        $this->username = getenv('DB_USER');
        $this->password = getenv('DB_PASSWORD'); // Asegúrate de que coincida con el .env

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8mb4");
        } catch (PDOException $exception) {
            error_log("DB connection error: " . $exception->getMessage());
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(array("message" => "Error de conexión con la base de datos."));
            exit;
        }

        return $this->conn;
    }
}