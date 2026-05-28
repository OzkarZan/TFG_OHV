<?php

class Reparacion {
    private $conn;
    private $table_name = "REPARACIONES";

    public $id_reparacion;
    public $id_cita;
    public $modelo_auto;
    public $matricula;
    public $descripcion_motivo;
    public $diagnostico;
    public $estado_presupuesto;
    public $estado;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readAll() {
        $query = "SELECT r.*, u.nombre_completo as nombre_cliente 
                  FROM " . $this->table_name . " r 
                  LEFT JOIN VEHICULOS v ON r.matricula = v.matricula 
                  LEFT JOIN CLIENTES c ON v.id_cliente = c.id_cliente 
                  LEFT JOIN USUARIOS u ON c.id_usuario = u.id_usuario 
                  ORDER BY r.id_reparacion DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                 (modelo_auto, matricula, descripcion_motivo, estado_presupuesto, estado) 
                 VALUES (:modelo_auto, :matricula, :descripcion_motivo, :estado_presupuesto, :estado)";
        
        $stmt = $this->conn->prepare($query);

        $this->modelo_auto = htmlspecialchars(strip_tags($this->modelo_auto));
        $this->matricula = htmlspecialchars(strip_tags($this->matricula));
        $this->descripcion_motivo = htmlspecialchars(strip_tags($this->descripcion_motivo));
        $this->estado_presupuesto = htmlspecialchars(strip_tags($this->estado_presupuesto));
        $this->estado = htmlspecialchars(strip_tags($this->estado));

        $stmt->bindParam(":modelo_auto", $this->modelo_auto);
        $stmt->bindParam(":matricula", $this->matricula);
        $stmt->bindParam(":descripcion_motivo", $this->descripcion_motivo);
        $stmt->bindParam(":estado_presupuesto", $this->estado_presupuesto);
        $stmt->bindParam(":estado", $this->estado);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                 SET modelo_auto = :modelo_auto, matricula = :matricula, descripcion_motivo = :descripcion_motivo, 
                     estado_presupuesto = :estado_presupuesto, estado = :estado, diagnostico = :diagnostico 
                 WHERE id_reparacion = :id_reparacion";
        
        $stmt = $this->conn->prepare($query);

        $this->modelo_auto = htmlspecialchars(strip_tags($this->modelo_auto));
        $this->matricula = htmlspecialchars(strip_tags($this->matricula));
        $this->descripcion_motivo = htmlspecialchars(strip_tags($this->descripcion_motivo));
        $this->estado_presupuesto = htmlspecialchars(strip_tags($this->estado_presupuesto));
        $this->estado = htmlspecialchars(strip_tags($this->estado));
        $this->diagnostico = htmlspecialchars(strip_tags($this->diagnostico));

        $stmt->bindParam(":modelo_auto", $this->modelo_auto);
        $stmt->bindParam(":matricula", $this->matricula);
        $stmt->bindParam(":descripcion_motivo", $this->descripcion_motivo);
        $stmt->bindParam(":estado_presupuesto", $this->estado_presupuesto);
        $stmt->bindParam(":estado", $this->estado);
        $stmt->bindParam(":diagnostico", $this->diagnostico);
        $stmt->bindParam(":id_reparacion", $this->id_reparacion);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id_reparacion = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_reparacion);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function getPresupuesto() {
        require_once 'Presupuesto.php';
        $presupuesto = new Presupuesto($this->conn);
        return $presupuesto->readByReparacion($this->id_reparacion);
    }
}
