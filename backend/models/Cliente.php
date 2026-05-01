<?php
class Cliente {
    private $conn;
    private $table_name = "CLIENTES";

    public $id_cliente;
    public $nombre;
    public $email;
    public $token_acceso;
    public $google_id;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($email, $google_id = null) {
        $query = "SELECT id_cliente, nombre, email, token_acceso FROM " . $this->table_name . " WHERE email = :email LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $num = $stmt->rowCount();

        if ($num > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $token = bin2hex(random_bytes(16));
            $updateQuery = "UPDATE " . $this->table_name . " SET token_acceso = :token, google_id = :google_id WHERE id_cliente = :id";
            $updStmt = $this->conn->prepare($updateQuery);
            $updStmt->bindParam(':token', $token);
            $updStmt->bindParam(':google_id', $google_id);
            $updStmt->bindParam(':id', $row['id_cliente']);
            $updStmt->execute();

            return array(
                "success" => true,
                "token" => $token,
                "id_cliente" => $row['id_cliente'],
                "nombre" => $row['nombre']
            );
        } else {
            return array("success" => false, "message" => "Acceso denegado. Este correo no se encuentra registrado en el sistema.");
        }
    }

    public function register($nombre, $email) {
        $checkQuery = "SELECT id_cliente FROM " . $this->table_name . " WHERE email = :email LIMIT 1";
        $stmtCheck = $this->conn->prepare($checkQuery);
        $stmtCheck->bindParam(':email', $email);
        $stmtCheck->execute();

        if ($stmtCheck->rowCount() > 0) {
            return array("success" => false, "message" => "El correo ingresado ya existe. Por favor, inicia sesión con él.");
        } else {
            $token = bin2hex(random_bytes(16));
            $insertQuery = "INSERT INTO " . $this->table_name . " (nombre, email, token_acceso) VALUES (:nombre, :email, :token)";
            $insStmt = $this->conn->prepare($insertQuery);
            $insStmt->bindParam(':nombre', $nombre);
            $insStmt->bindParam(':email', $email);
            $insStmt->bindParam(':token', $token);

            if ($insStmt->execute()) {
                return array(
                    "success" => true,
                    "token" => $token,
                    "id_cliente" => $this->conn->lastInsertId(),
                    "nombre" => $nombre
                );
            } else {
                return array("success" => false, "message" => "Error base de datos. No se pudo registrar la cuenta.");
            }
        }
    }
}
?>