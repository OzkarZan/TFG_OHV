<?php

class Vehiculo {
    private $conn;
    private $table_name = "VEHICULOS";

    public $id_vehiculo;
    public $matricula;
    public $modelo;
    public $marca;
    public $anio;
    public $id_cliente;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readByCliente() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id_cliente = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_cliente);
        $stmt->execute();
        return $stmt;
    }

    public function readAll() {
        $query = "SELECT * FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                 SET matricula = :matricula, modelo = :modelo, marca = :marca, anio = :anio
                 WHERE id_vehiculo = :id_vehiculo";
        $stmt = $this->conn->prepare($query);

        $this->matricula = htmlspecialchars(strip_tags($this->matricula));
        $this->modelo    = htmlspecialchars(strip_tags($this->modelo));
        $this->marca     = !empty($this->marca) ? htmlspecialchars(strip_tags($this->marca)) : null;
        $this->anio      = !empty($this->anio)  ? (int) $this->anio : null;

        $stmt->bindParam(":matricula",   $this->matricula);
        $stmt->bindParam(":modelo",      $this->modelo);
        $stmt->bindParam(":marca",       $this->marca);
        $stmt->bindParam(":anio",        $this->anio);
        $stmt->bindParam(":id_vehiculo", $this->id_vehiculo);

        return $stmt->execute();
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id_vehiculo = ?";
        $stmt  = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_vehiculo);
        return $stmt->execute();
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (matricula, modelo, marca, anio, id_cliente) VALUES (:matricula, :modelo, :marca, :anio, :id_cliente)";
        $stmt = $this->conn->prepare($query);

        $this->matricula = htmlspecialchars(strip_tags($this->matricula));
        $this->modelo = htmlspecialchars(strip_tags($this->modelo));
        $this->marca = !empty($this->marca) ? htmlspecialchars(strip_tags($this->marca)) : null;
        $this->anio = !empty($this->anio) ? (int) htmlspecialchars(strip_tags($this->anio)) : null;
        
        $stmt->bindParam(":matricula", $this->matricula);
        $stmt->bindParam(":modelo", $this->modelo);
        $stmt->bindParam(":marca", $this->marca);
        $stmt->bindParam(":anio", $this->anio, PDO::PARAM_INT);
        $stmt->bindParam(":id_cliente", $this->id_cliente);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}
