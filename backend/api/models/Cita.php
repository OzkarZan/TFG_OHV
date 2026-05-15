<?php

class Cita {
    private $conn;
    private $table_name = "CITAS";

    public $id_cita;
    public $fecha_hora;
    public $motivo;
    public $estado;
    public $prioridad;
    public $es_emergencia;
    public $id_cliente;
    public $id_vehiculo;
    public $id_taller;
    public $id_mecanico;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function readAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY fecha_hora ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                 (fecha_hora, motivo, estado, prioridad, es_emergencia, id_cliente, id_vehiculo, id_taller, id_mecanico)
                 VALUES (:fecha_hora, :motivo, :estado, :prioridad, :es_emergencia, :id_cliente, :id_vehiculo, :id_taller, :id_mecanico)";
        $stmt = $this->conn->prepare($query);

        $this->motivo = htmlspecialchars(strip_tags($this->motivo));
        $this->estado = htmlspecialchars(strip_tags($this->estado));
        $this->prioridad = htmlspecialchars(strip_tags($this->prioridad));

        $stmt->bindParam(":fecha_hora", $this->fecha_hora);
        $stmt->bindParam(":motivo", $this->motivo);
        $stmt->bindParam(":estado", $this->estado);
        $stmt->bindParam(":prioridad", $this->prioridad);
        $stmt->bindParam(":es_emergencia", $this->es_emergencia);
        $stmt->bindParam(":id_cliente", $this->id_cliente);
        $stmt->bindParam(":id_vehiculo", $this->id_vehiculo);
        $stmt->bindParam(":id_taller", $this->id_taller);
        $stmt->bindParam(":id_mecanico", $this->id_mecanico);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . "
                 SET fecha_hora = :fecha_hora, motivo = :motivo, estado = :estado, prioridad = :prioridad,
                     es_emergencia = :es_emergencia, id_mecanico = :id_mecanico
                 WHERE id_cita = :id_cita";
        $stmt = $this->conn->prepare($query);

        $this->motivo = htmlspecialchars(strip_tags($this->motivo));
        $this->estado = htmlspecialchars(strip_tags($this->estado));
        $this->prioridad = htmlspecialchars(strip_tags($this->prioridad));

        $stmt->bindParam(":fecha_hora", $this->fecha_hora);
        $stmt->bindParam(":motivo", $this->motivo);
        $stmt->bindParam(":estado", $this->estado);
        $stmt->bindParam(":prioridad", $this->prioridad);
        $stmt->bindParam(":es_emergencia", $this->es_emergencia);
        $stmt->bindParam(":id_mecanico", $this->id_mecanico);
        $stmt->bindParam(":id_cita", $this->id_cita);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id_cita = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_cita);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}
