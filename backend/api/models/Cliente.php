<?php

class Cliente {
    private $conn;
    private $table_name = "CLIENTES";

    public $id_cliente;
    public $id_usuario;
    public $telefono;
    public $direccion;
    public $nombre_completo; // from USUARIOS table
    public $correo; // from USUARIOS table

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readAll() {
        $query = "SELECT c.*, u.nombre_completo, u.correo 
                  FROM " . $this->table_name . " c
                  LEFT JOIN USUARIOS u ON c.id_usuario = u.id_usuario
                  ORDER BY u.nombre_completo ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // El cliente se crea automáticamente en el registro, pero podemos actualizar sus datos aquí
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                 SET telefono = :telefono, direccion = :direccion 
                 WHERE id_cliente = :id_cliente";
        
        $stmt = $this->conn->prepare($query);

        $this->telefono = htmlspecialchars(strip_tags($this->telefono));
        $this->direccion = htmlspecialchars(strip_tags($this->direccion));

        $stmt->bindParam(":telefono", $this->telefono);
        $stmt->bindParam(":direccion", $this->direccion);
        $stmt->bindParam(":id_cliente", $this->id_cliente);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function delete() {
        // Primero obtener el id_usuario
        $query_get_user = "SELECT id_usuario FROM " . $this->table_name . " WHERE id_cliente = ?";
        $stmt_get = $this->conn->prepare($query_get_user);
        $stmt_get->bindParam(1, $this->id_cliente);
        $stmt_get->execute();
        $row = $stmt_get->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Eliminar el usuario (esto eliminará en cascada el cliente si la DB está bien configurada)
            $query_del = "DELETE FROM USUARIOS WHERE id_usuario = ?";
            $stmt_del = $this->conn->prepare($query_del);
            $stmt_del->bindParam(1, $row['id_usuario']);
            if ($stmt_del->execute()) {
                return true;
            }
        }
        return false;
    }
}
