<?php

class Presupuesto {
    private $conn;
    private $table_name = "PRESUPUESTOS";

    public $id_presupuesto;
    public $id_reparacion;
    public $total_piezas;
    public $total_mano_obra;
    public $gran_total;
    public $fecha_emision;
    public $estado;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                 (id_reparacion, total_piezas, total_mano_obra, gran_total, fecha_emision, estado) 
                 VALUES (:id_reparacion, :total_piezas, :total_mano_obra, :gran_total, :fecha_emision, :estado)";
        
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id_reparacion", $this->id_reparacion);
        $stmt->bindParam(":total_piezas", $this->total_piezas);
        $stmt->bindParam(":total_mano_obra", $this->total_mano_obra);
        $stmt->bindParam(":gran_total", $this->gran_total);
        $stmt->bindParam(":fecha_emision", $this->fecha_emision);
        $stmt->bindParam(":estado", $this->estado);

        if ($stmt->execute()) {
            $this->id_presupuesto = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function readByReparacion() {
        // Busca el cliente a través de la matrícula del vehículo,
        // sin depender de que la reparación esté vinculada a una cita.
        $query = "SELECT p.*, r.modelo_auto, r.matricula, u.nombre_completo as cliente_nombre
                  FROM " . $this->table_name . " p
                  LEFT JOIN REPARACIONES r ON p.id_reparacion = r.id_reparacion
                  LEFT JOIN VEHICULOS v ON r.matricula = v.matricula
                  LEFT JOIN CLIENTES c ON v.id_cliente = c.id_cliente
                  LEFT JOIN USUARIOS u ON c.id_usuario = u.id_usuario
                  WHERE p.id_reparacion = ? LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_reparacion);
        $stmt->execute();
        return $stmt;
    }

    public function readAll() {
        $query = "SELECT p.*, r.matricula, r.modelo_auto
                  FROM " . $this->table_name . " p
                  LEFT JOIN REPARACIONES r ON p.id_reparacion = r.id_reparacion
                  ORDER BY p.fecha_emision DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function readByClienteId($id_cliente) {
        $query = "SELECT p.*, r.matricula, r.modelo_auto, r.descripcion_motivo
                  FROM " . $this->table_name . " p
                  LEFT JOIN REPARACIONES r ON p.id_reparacion = r.id_reparacion
                  LEFT JOIN VEHICULOS v ON r.matricula = v.matricula
                  WHERE v.id_cliente = ?
                  ORDER BY p.fecha_emision DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_cliente);
        $stmt->execute();
        return $stmt;
    }
}
