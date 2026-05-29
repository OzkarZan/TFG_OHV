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
        $query = "SELECT c.id_cliente, c.id_usuario, c.telefono, c.direccion,
                         u.nombre_completo, u.email as correo,
                         GROUP_CONCAT(CONCAT(v.matricula, ' - ', v.modelo) ORDER BY v.id_vehiculo SEPARATOR ' | ') as vehiculos
                  FROM " . $this->table_name . " c
                  LEFT JOIN USUARIOS u ON c.id_usuario = u.id_usuario
                  LEFT JOIN VEHICULOS v ON c.id_cliente = v.id_cliente
                  GROUP BY c.id_cliente, c.id_usuario, c.telefono, c.direccion, u.nombre_completo, u.email
                  ORDER BY u.nombre_completo ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (id_usuario, telefono, direccion) VALUES (:id_usuario, :telefono, :direccion)";
        $stmt = $this->conn->prepare($query);

        $this->telefono = htmlspecialchars(strip_tags($this->telefono ?? ''));
        $this->direccion = htmlspecialchars(strip_tags($this->direccion ?? ''));

        $stmt->bindParam(":id_usuario", $this->id_usuario);
        $stmt->bindParam(":telefono", $this->telefono);
        $stmt->bindParam(":direccion", $this->direccion);

        if ($stmt->execute()) {
            $this->id_cliente = $this->conn->lastInsertId();
            return true;
        }
        return false;
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
        $stmt = $this->conn->prepare("SELECT id_usuario FROM CLIENTES WHERE id_cliente = ?");
        $stmt->execute([$this->id_cliente]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;

        // Obtener matrículas del cliente para borrar reparaciones dependientes
        $stmtV = $this->conn->prepare("SELECT matricula FROM VEHICULOS WHERE id_cliente = ?");
        $stmtV->execute([$this->id_cliente]);
        $matriculas = $stmtV->fetchAll(PDO::FETCH_COLUMN);

        foreach ($matriculas as $matricula) {
            $stmtR = $this->conn->prepare("SELECT id_reparacion FROM REPARACIONES WHERE matricula = ?");
            $stmtR->execute([$matricula]);
            foreach ($stmtR->fetchAll(PDO::FETCH_COLUMN) as $id_rep) {
                $this->conn->prepare("DELETE FROM PRESUPUESTOS WHERE id_reparacion = ?")->execute([$id_rep]);
                $this->conn->prepare("DELETE FROM REPARACION_MECANICO WHERE id_reparacion = ?")->execute([$id_rep]);
                $this->conn->prepare("DELETE FROM REPARACION_REPUESTOS WHERE id_reparacion = ?")->execute([$id_rep]);
            }
            $this->conn->prepare("DELETE FROM REPARACIONES WHERE matricula = ?")->execute([$matricula]);
        }

        // CITAS no tiene CASCADE desde CLIENTES
        $this->conn->prepare("DELETE FROM CITAS WHERE id_cliente = ?")->execute([$this->id_cliente]);

        // Borrar usuario: CASCADE elimina CLIENTES → VEHICULOS
        $this->conn->prepare("DELETE FROM USUARIOS WHERE id_usuario = ?")->execute([$row['id_usuario']]);
        return true;
    }
}
