<?php
require_once '../config/config.php';

class RepuestoController
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    private function requireAuth(): void
    {
        if (!isset($_SESSION['id_usuario'])) {
            http_response_code(401);
            echo json_encode(["message" => "No autorizado."]);
            exit;
        }
    }

    public function handleRequest(string $method): void
    {
        $this->requireAuth();

        switch ($method) {
            case 'GET':    $this->read();   break;
            case 'POST':   $this->create(); break;
            case 'PUT':    $this->update(); break;
            case 'DELETE': $this->delete(); break;
            default:
                http_response_code(405);
                echo json_encode(["message" => "Método no permitido."]);
        }
    }

    private function read(): void
    {
        $stmt = $this->db->prepare(
            "SELECT id_repuesto, nombre_pieza, marca, stock_actual, stock_minimo, precio_unitario
             FROM REPUESTOS
             ORDER BY nombre_pieza ASC"
        );
        $stmt->execute();

        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['id_repuesto']    = (int)$row['id_repuesto'];
            $row['stock_actual']   = (int)$row['stock_actual'];
            $row['stock_minimo']   = (int)$row['stock_minimo'];
            $row['precio_unitario'] = (float)$row['precio_unitario'];
            $rows[] = $row;
        }

        http_response_code(200);
        echo json_encode($rows);
    }

    private function create(): void
    {
        $data = json_decode(file_get_contents('php://input'));

        if (empty($data->nombre_pieza) || !isset($data->precio_unitario)) {
            http_response_code(400);
            echo json_encode(["message" => "nombre_pieza y precio_unitario son obligatorios."]);
            return;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO REPUESTOS (nombre_pieza, marca, stock_actual, stock_minimo, precio_unitario)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            trim($data->nombre_pieza),
            $data->marca          ?? null,
            (int)($data->stock_actual  ?? 0),
            (int)($data->stock_minimo  ?? 5),
            (float)$data->precio_unitario,
        ]);

        http_response_code(201);
        echo json_encode([
            "message"    => "Repuesto creado.",
            "id_repuesto" => (int)$this->db->lastInsertId()
        ]);
    }

    private function update(): void
    {
        $data = json_decode(file_get_contents('php://input'));

        if (empty($data->id_repuesto)) {
            http_response_code(400);
            echo json_encode(["message" => "id_repuesto es obligatorio."]);
            return;
        }

        $stmt = $this->db->prepare(
            "UPDATE REPUESTOS
             SET nombre_pieza = ?, precio_unitario = ?, stock_actual = ?, stock_minimo = ?, marca = ?
             WHERE id_repuesto = ?"
        );
        $stmt->execute([
            $data->nombre_pieza    ?? '',
            (float)($data->precio_unitario ?? 0),
            (int)($data->stock_actual      ?? 0),
            (int)($data->stock_minimo      ?? 5),
            $data->marca           ?? null,
            (int)$data->id_repuesto,
        ]);

        http_response_code(200);
        echo json_encode(["message" => "Repuesto actualizado."]);
    }

    private function delete(): void
    {
        $data = json_decode(file_get_contents('php://input'));

        if (empty($data->id_repuesto)) {
            http_response_code(400);
            echo json_encode(["message" => "id_repuesto es obligatorio."]);
            return;
        }

        $stmt = $this->db->prepare("DELETE FROM REPUESTOS WHERE id_repuesto = ?");
        $stmt->execute([(int)$data->id_repuesto]);

        http_response_code(200);
        echo json_encode(["message" => "Repuesto eliminado."]);
    }
}
