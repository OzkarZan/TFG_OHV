<?php

class Presupuesto {
    private $conn;
    private $table_name = "PRESUPUESTOS";

    public $id_presupuesto;
    public $id_reparacion;
    public $id_mecanico;
    public $id_cliente;
    public $id_vehiculo;
    public $km;
    public $color;
    public $total_piezas;
    public $total_mano_obra;
    public $servicios_terceros;
    public $gran_total;
    public $fecha_emision;
    public $estado;
    public $notas;

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

    public function createStandalone() {
        $query = "INSERT INTO " . $this->table_name . "
                  (id_cliente, id_vehiculo, id_mecanico, km, color,
                   total_piezas, total_mano_obra, servicios_terceros, gran_total,
                   fecha_emision, estado, notas)
                  VALUES
                  (:id_cliente, :id_vehiculo, :id_mecanico, :km, :color,
                   :total_piezas, :total_mano_obra, :servicios_terceros, :gran_total,
                   :fecha_emision, :estado, :notas)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_cliente",          $this->id_cliente);
        $stmt->bindParam(":id_vehiculo",         $this->id_vehiculo);
        $stmt->bindParam(":id_mecanico",         $this->id_mecanico);
        $stmt->bindParam(":km",                  $this->km);
        $stmt->bindParam(":color",               $this->color);
        $stmt->bindParam(":total_piezas",        $this->total_piezas);
        $stmt->bindParam(":total_mano_obra",     $this->total_mano_obra);
        $stmt->bindParam(":servicios_terceros",  $this->servicios_terceros);
        $stmt->bindParam(":gran_total",          $this->gran_total);
        $stmt->bindParam(":fecha_emision",       $this->fecha_emision);
        $stmt->bindParam(":estado",              $this->estado);
        $stmt->bindParam(":notas",               $this->notas);

        if ($stmt->execute()) {
            $this->id_presupuesto = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function addDetalle($id_presupuesto, $tipo, $desc, $qty, $price) {
        $query = "INSERT INTO PRESUPUESTO_DETALLES
                  (id_presupuesto, tipo_item, descripcion, cantidad, precio_unitario)
                  VALUES (:id_presupuesto, :tipo_item, :descripcion, :cantidad, :precio_unitario)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id_presupuesto",  $id_presupuesto);
        $stmt->bindParam(":tipo_item",       $tipo);
        $stmt->bindParam(":descripcion",     $desc);
        $stmt->bindParam(":cantidad",        $qty);
        $stmt->bindParam(":precio_unitario", $price);
        return $stmt->execute();
    }

    public function readAllFull() {
        $query = "SELECT
            p.id_presupuesto, p.fecha_emision, p.gran_total, p.estado,
            p.id_reparacion, p.id_mecanico, p.id_cliente as p_id_cliente,
            p.servicios_terceros,
            COALESCE(
                (SELECT u.nombre_completo FROM USUARIOS u
                 JOIN CLIENTES c ON u.id_usuario=c.id_usuario
                 WHERE c.id_cliente=p.id_cliente),
                (SELECT u.nombre_completo FROM USUARIOS u
                 JOIN CLIENTES c ON u.id_usuario=c.id_usuario
                 JOIN VEHICULOS v ON c.id_cliente=v.id_cliente
                 JOIN REPARACIONES r ON v.matricula=r.matricula
                 WHERE r.id_reparacion=p.id_reparacion LIMIT 1)
            ) as cliente_nombre,
            COALESCE(
                (SELECT CONCAT(v.matricula, ' - ', v.modelo) FROM VEHICULOS v WHERE v.id_vehiculo=p.id_vehiculo),
                (SELECT CONCAT(r.matricula, ' - ', r.modelo_auto) FROM REPARACIONES r WHERE r.id_reparacion=p.id_reparacion)
            ) as vehiculo_str,
            (SELECT u.nombre_completo FROM USUARIOS u
             JOIN MECANICOS m ON u.id_usuario=m.id_usuario
             WHERE m.id_mecanico=p.id_mecanico) as mecanico_nombre
        FROM " . $this->table_name . " p
        ORDER BY p.fecha_emision DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function readByIdFull($id_presupuesto) {
        $query = "SELECT
            p.*,
            COALESCE(
                (SELECT u.nombre_completo FROM USUARIOS u
                 JOIN CLIENTES c ON u.id_usuario=c.id_usuario
                 WHERE c.id_cliente=p.id_cliente),
                (SELECT u.nombre_completo FROM USUARIOS u
                 JOIN CLIENTES c ON u.id_usuario=c.id_usuario
                 JOIN VEHICULOS v ON c.id_cliente=v.id_cliente
                 JOIN REPARACIONES r ON v.matricula=r.matricula
                 WHERE r.id_reparacion=p.id_reparacion LIMIT 1)
            ) as cliente_nombre,
            COALESCE(
                (SELECT c.telefono FROM CLIENTES c WHERE c.id_cliente=p.id_cliente),
                (SELECT c.telefono FROM CLIENTES c
                 JOIN VEHICULOS v ON c.id_cliente=v.id_cliente
                 JOIN REPARACIONES r ON v.matricula=r.matricula
                 WHERE r.id_reparacion=p.id_reparacion LIMIT 1)
            ) as cliente_telefono,
            COALESCE(
                (SELECT c.direccion FROM CLIENTES c WHERE c.id_cliente=p.id_cliente),
                (SELECT c.direccion FROM CLIENTES c
                 JOIN VEHICULOS v ON c.id_cliente=v.id_cliente
                 JOIN REPARACIONES r ON v.matricula=r.matricula
                 WHERE r.id_reparacion=p.id_reparacion LIMIT 1)
            ) as cliente_direccion,
            COALESCE(
                (SELECT v.matricula FROM VEHICULOS v WHERE v.id_vehiculo=p.id_vehiculo),
                (SELECT r.matricula FROM REPARACIONES r WHERE r.id_reparacion=p.id_reparacion)
            ) as veh_matricula,
            COALESCE(
                (SELECT v.modelo FROM VEHICULOS v WHERE v.id_vehiculo=p.id_vehiculo),
                (SELECT r.modelo_auto FROM REPARACIONES r WHERE r.id_reparacion=p.id_reparacion)
            ) as veh_modelo,
            COALESCE(
                (SELECT v.marca FROM VEHICULOS v WHERE v.id_vehiculo=p.id_vehiculo),
                ''
            ) as veh_marca,
            COALESCE(
                (SELECT v.anio FROM VEHICULOS v WHERE v.id_vehiculo=p.id_vehiculo),
                NULL
            ) as veh_anio,
            (SELECT u.nombre_completo FROM USUARIOS u
             JOIN MECANICOS m ON u.id_usuario=m.id_usuario
             WHERE m.id_mecanico=p.id_mecanico) as mecanico_nombre
        FROM " . $this->table_name . " p
        WHERE p.id_presupuesto = ?
        LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_presupuesto);
        $stmt->execute();
        return $stmt;
    }

    public function readDetallesByPresupuesto($id_presupuesto) {
        $query = "SELECT * FROM PRESUPUESTO_DETALLES WHERE id_presupuesto = ? ORDER BY id_detalle ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_presupuesto);
        $stmt->execute();
        return $stmt;
    }

    public function deleteByPresupuesto($id_presupuesto) {
        $query = "DELETE FROM " . $this->table_name . " WHERE id_presupuesto = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_presupuesto);
        return $stmt->execute();
    }

    public function readByReparacion() {
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

    public function readAllSimple() {
        $query = "SELECT p.*, r.matricula, r.modelo_auto
                  FROM " . $this->table_name . " p
                  LEFT JOIN REPARACIONES r ON p.id_reparacion = r.id_reparacion
                  ORDER BY p.fecha_emision DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function readAll() {
        return $this->readAllSimple();
    }

    public function readByClienteId($id_cliente) {
        $query = "SELECT p.id_presupuesto, p.fecha_emision, p.gran_total, p.estado,
                    COALESCE(
                        (SELECT CONCAT(v2.matricula, ' - ', v2.modelo) FROM VEHICULOS v2 WHERE v2.id_vehiculo = p.id_vehiculo),
                        (SELECT CONCAT(r2.matricula, ' - ', r2.modelo_auto) FROM REPARACIONES r2 WHERE r2.id_reparacion = p.id_reparacion)
                    ) as vehiculo_str
                  FROM " . $this->table_name . " p
                  WHERE p.id_cliente = ?
                     OR p.id_reparacion IN (
                         SELECT r.id_reparacion FROM REPARACIONES r
                         JOIN VEHICULOS v ON r.matricula = v.matricula
                         WHERE v.id_cliente = ?
                     )
                  ORDER BY p.fecha_emision DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id_cliente);
        $stmt->bindParam(2, $id_cliente);
        $stmt->execute();
        return $stmt;
    }
}
