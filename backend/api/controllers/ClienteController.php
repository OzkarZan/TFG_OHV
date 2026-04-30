<?php
require_once '../config/config.php';
require_once 'models/Cliente.php';

class ClienteController {
    private $db;
    private $cliente;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->cliente = new Cliente($this->db);
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
        $stmt = $this->cliente->readAll();
        $arr = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($arr, $row);
        }

        http_response_code(200);
        echo json_encode($arr);
    }

    private function update() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id_cliente)) {
            $this->cliente->id_cliente = $data->id_cliente;
            $this->cliente->telefono = $data->telefono ?? null;
            $this->cliente->direccion = $data->direccion ?? null;

            if ($this->cliente->update()) {
                http_response_code(200);
                echo json_encode(["message" => "Cliente actualizado con éxito."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo actualizar el cliente."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Falta ID de cliente."]);
        }
    }

    private function delete() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id_cliente)) {
            $this->cliente->id_cliente = $data->id_cliente;

            if ($this->cliente->delete()) {
                http_response_code(200);
                echo json_encode(["message" => "Cliente (y usuario) eliminado con éxito."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo eliminar el cliente."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Falta ID."]);
        }
    }
}
