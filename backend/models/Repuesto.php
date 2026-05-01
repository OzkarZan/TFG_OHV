<?php
class Repuesto {
    private $conn;
    private $table_name = "REPUESTOS";

    public $id_repuesto;
    public $nombre_pieza;
    public $marca;
    public $stock_actual;
    public $stock_minimo;
    public $precio_unitario;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readAll() {
        $query = "SELECT id_repuesto, nombre_pieza, marca, stock_actual, stock_minimo, precio_unitario FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function create($nombre_pieza, $marca, $stock_actual, $stock_minimo, $precio_unitario) {
        $query = "INSERT INTO " . $this->table_name . " (nombre_pieza, marca, stock_actual, stock_minimo, precio_unitario) 
                  VALUES (:nombre_pieza, :marca, :stock_actual, :stock_minimo, :precio_unitario)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nombre_pieza', $nombre_pieza);
        $stmt->bindParam(':marca', $marca);
        $stmt->bindParam(':stock_actual', $stock_actual);
        $stmt->bindParam(':stock_minimo', $stock_minimo);
        $stmt->bindParam(':precio_unitario', $precio_unitario);

        if ($stmt->execute()) {
            return array("success" => true, "id_repuesto" => $this->conn->lastInsertId());
        } else {
            return array("success" => false, "message" => "No se pudo crear el repuesto.");
        }
    }

    public function update($id_repuesto, $nombre_pieza, $precio_unitario, $stock_actual) {
        $query = "UPDATE " . $this->table_name . " SET nombre_pieza = :nombre, precio_unitario = :precio, stock_actual = :stock WHERE id_repuesto = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':nombre', $nombre_pieza);
        $stmt->bindParam(':precio', $precio_unitario);
        $stmt->bindParam(':stock', $stock_actual);
        $stmt->bindParam(':id', $id_repuesto);

        if ($stmt->execute()) {
            return array("success" => true);
        } else {
            return array("success" => false, "message" => "Ocurrió un error en la actualización.");
        }
    }

    public function delete($id_repuesto) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id_repuesto = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id_repuesto);

        if ($stmt->execute()) {
            return array("success" => true);
        } else {
            return array("success" => false, "message" => "Error al ejecutar la eliminación.");
        }
    }
}
?>