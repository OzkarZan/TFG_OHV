<?php

class Usuario {
    private $conn;
    private $table_name = "USUARIOS";

    public $id_usuario;
    public $email;
    public $password_hash;
    public $rol;
    public $nombre_completo;
    public $fecha_registro;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function emailExists() {
        $query = "SELECT id_usuario, password_hash, rol, nombre_completo FROM " . $this->table_name . " WHERE email = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->id_usuario = $row['id_usuario'];
            $this->password_hash = $row['password_hash'];
            $this->rol = $row['rol'];
            $this->nombre_completo = $row['nombre_completo'];
            return true;
        }
        return false;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (email, password_hash, rol, nombre_completo) VALUES (:email, :password_hash, :rol, :nombre_completo)";
        $stmt = $this->conn->prepare($query);

        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->rol = htmlspecialchars(strip_tags($this->rol));
        $this->nombre_completo = htmlspecialchars(strip_tags($this->nombre_completo));

        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':password_hash', $this->password_hash);
        $stmt->bindParam(':rol', $this->rol);
        $stmt->bindParam(':nombre_completo', $this->nombre_completo);

        if ($stmt->execute()) {
            $this->id_usuario = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }
}
