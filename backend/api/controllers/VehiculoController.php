<?php
require_once '../config/config.php';
require_once 'models/Vehiculo.php';

class VehiculoController {
    private $db;
    private $vehiculo;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->vehiculo = new Vehiculo($this->db);
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
            default:
                http_response_code(405);
                echo json_encode(["message" => "Método no permitido"]);
                break;
        }
    }

    private function read() {
        if (isset($_GET['id_cliente'])) {
            $this->vehiculo->id_cliente = $_GET['id_cliente'];
            $stmt = $this->vehiculo->readByCliente();
        } else {
            $stmt = $this->vehiculo->readAll();
        }

        $arr = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($arr, $row);
        }

        http_response_code(200);
        echo json_encode($arr);
    }

    private function create() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->matricula) && !empty($data->modelo) && !empty($data->id_cliente)) {
            $this->vehiculo->matricula = $data->matricula;
            $this->vehiculo->modelo = $data->modelo;
            $this->vehiculo->marca = $data->marca ?? null;
            $this->vehiculo->anio = $data->anio ?? null;
            $this->vehiculo->id_cliente = $data->id_cliente;

            if ($this->vehiculo->create()) {
                http_response_code(201);
                echo json_encode(["message" => "Vehículo creado con éxito."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo crear el vehículo (quizás la matrícula ya exista)."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Datos incompletos para crear vehículo."]);
        }
    }
}
