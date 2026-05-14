<?php
require_once '../config/config.php';
require_once 'models/Cita.php';

class CitaController {
    private $db;
    private $cita;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->cita = new Cita($this->db);
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
        $stmt = $this->cita->readAll();
        $citas_arr = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($citas_arr, $row);
        }

        http_response_code(200);
        echo json_encode($citas_arr);
    }

    private function create() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->fecha_hora) && !empty($data->motivo) && !empty($data->id_cliente) && !empty($data->id_vehiculo)) {
            $this->cita->fecha_hora = $data->fecha_hora;
            $this->cita->motivo = $data->motivo;
            $this->cita->estado = $data->estado ?? 'Pendiente';
            $this->cita->prioridad = $data->prioridad ?? 'Media';
            $this->cita->es_emergencia = $data->es_emergencia ?? 0;
            $this->cita->id_cliente = $data->id_cliente;
            $this->cita->id_vehiculo = $data->id_vehiculo;
            $this->cita->id_taller = $data->id_taller ?? null;

            if ($this->cita->create()) {
                http_response_code(201);
                echo json_encode(["message" => "Cita creada con éxito."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo crear la cita."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Datos incompletos. Cliente y Vehículo son obligatorios."]);
        }
    }

    private function update() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id_cita)) {
            $this->cita->id_cita = $data->id_cita;
            $this->cita->fecha_hora = $data->fecha_hora ?? null;
            $this->cita->motivo = $data->motivo ?? null;
            $this->cita->estado = $data->estado ?? null;
            $this->cita->prioridad = $data->prioridad ?? null;
            $this->cita->es_emergencia = $data->es_emergencia ?? null;
            $this->cita->id_cliente = $data->id_cliente ?? null;
            $this->cita->id_vehiculo = $data->id_vehiculo ?? null;
            $this->cita->id_taller = $data->id_taller ?? null;

            if ($this->cita->update()) {
                http_response_code(200);
                echo json_encode(["message" => "Cita actualizada."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo actualizar."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Falta ID."]);
        }
    }

    private function delete() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id_cita)) {
            $this->cita->id_cita = $data->id_cita;

            if ($this->cita->delete()) {
                http_response_code(200);
                echo json_encode(["message" => "Cita eliminada con éxito."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo eliminar la cita."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Falta ID de cita."]);
        }
    }
}
