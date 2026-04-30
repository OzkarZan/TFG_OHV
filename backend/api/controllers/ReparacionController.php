<?php
require_once '../config/config.php';
require_once 'models/Reparacion.php';

class ReparacionController {
    private $db;
    private $reparacion;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->reparacion = new Reparacion($this->db);
    }

    private function requireAuth() {
        if (!isset($_SESSION['id_usuario'])) {
            http_response_code(401);
            echo json_encode(["message" => "No autorizado."]);
            exit;
        }
    }

    public function handleRequest($method) {
        $this->requireAuth();

        switch ($method) {
            case 'GET':
                $this->read();
                break;
            case 'POST':
                $this->create();
                break;
            case 'PUT':
                $this->update();
                break;
            case 'DELETE':
                $this->delete();
                break;
            default:
                http_response_code(405);
                echo json_encode(["message" => "Método no permitido"]);
                break;
        }
    }

    private function read() {
        $stmt = $this->reparacion->readAll();
        $arr = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($arr, $row);
        }

        http_response_code(200);
        echo json_encode($arr);
    }

    private function create() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->modelo_auto) && !empty($data->matricula) && !empty($data->descripcion_motivo)) {
            $this->reparacion->modelo_auto = $data->modelo_auto;
            $this->reparacion->matricula = $data->matricula;
            $this->reparacion->descripcion_motivo = $data->descripcion_motivo;
            $this->reparacion->estado_presupuesto = $data->estado_presupuesto ?? 'Pendiente';
            $this->reparacion->estado = $data->estado ?? 'En Proceso';

            if ($this->reparacion->create()) {
                http_response_code(201);
                echo json_encode(["message" => "Reparación creada con éxito."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo crear la reparación."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Faltan campos obligatorios (Modelo, Matrícula, Motivo)."]);
        }
    }

    private function update() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id_reparacion)) {
            $this->reparacion->id_reparacion = $data->id_reparacion;
            $this->reparacion->modelo_auto = $data->modelo_auto;
            $this->reparacion->matricula = $data->matricula;
            $this->reparacion->descripcion_motivo = $data->descripcion_motivo;
            $this->reparacion->diagnostico = $data->diagnostico ?? null;
            $this->reparacion->estado_presupuesto = $data->estado_presupuesto ?? 'Pendiente';
            $this->reparacion->estado = $data->estado ?? 'En Proceso';

            if ($this->reparacion->update()) {
                http_response_code(200);
                echo json_encode(["message" => "Reparación actualizada con éxito."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo actualizar la reparación."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Falta ID de reparación."]);
        }
    }

    private function delete() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id_reparacion)) {
            $this->reparacion->id_reparacion = $data->id_reparacion;

            if ($this->reparacion->delete()) {
                http_response_code(200);
                echo json_encode(["message" => "Reparación eliminada con éxito."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo eliminar la reparación."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Falta ID."]);
        }
    }
}
