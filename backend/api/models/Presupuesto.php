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
        $query = "SELECT p.*, r.modelo_auto, r.matricula, c.nombre as cliente_nombre 
                  FROM " . $this->table_name . " p
                  LEFT JOIN REPARACIONES r ON p.id_reparacion = r.id_reparacion
                  LEFT JOIN CITAS cita ON r.id_cita = cita.id_cita
                  LEFT JOIN CLIENTES cli ON cita.id_cliente = cli.id_cliente
                  LEFT JOIN USUARIOS c ON cli.id_usuario = c.id_usuario
                  WHERE p.id_reparacion = ? LIMIT 0,1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id_reparacion);
        $stmt->execute();
        return $stmt;
    }
}
