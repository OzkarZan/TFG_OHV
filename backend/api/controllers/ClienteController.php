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

    private function create() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->nombre_completo) && !empty($data->correo) && !empty($data->matricula) && !empty($data->modelo)) {
            require_once 'models/Usuario.php';
            $user = new Usuario($this->db);
            $user->email = $data->correo;
            
            if ($user->emailExists()) {
                http_response_code(400);
                echo json_encode(["message" => "El correo electrónico ya está registrado."]);
                return;
            }

            $user->nombre_completo = $data->nombre_completo;
            $password = "AutoSync" . rand(1000, 9999) . "!";
            $user->password_hash = password_hash($password, PASSWORD_BCRYPT);
            $user->rol = 'cliente';

            if ($user->create()) {
                $this->cliente->id_usuario = $user->id_usuario;
                $this->cliente->telefono = $data->telefono ?? null;
                $this->cliente->direccion = $data->direccion ?? null;

                if ($this->cliente->create()) {
                    require_once 'models/Vehiculo.php';
                    $vehiculo = new Vehiculo($this->db);
                    $vehiculo->id_cliente = $this->cliente->id_cliente;
                    $vehiculo->matricula = $data->matricula;
                    $vehiculo->modelo = $data->modelo;
                    $vehiculo->marca = $data->marca ?? null;
                    $vehiculo->anio = $data->anio ?? null;
                    
                    if ($vehiculo->create()) {
                        require_once 'helpers/MailHelper.php';
                        $mail = new MailHelper();
                        $mail->sendWelcomeClient($user->email, $user->nombre_completo);

                        http_response_code(201);
                        echo json_encode(["message" => "Cliente y vehículo creados con éxito."]);
                    } else {
                        http_response_code(503);
                        echo json_encode(["message" => "El cliente se creó, pero falló la creación del vehículo (¿matrícula duplicada?)."]);
                    }
                } else {
                    http_response_code(503);
                    echo json_encode(["message" => "No se pudo crear los detalles del cliente."]);
                }
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo crear el usuario del cliente."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Datos incompletos para crear el cliente y su vehículo. Asegúrate de incluir la matrícula y modelo del coche."]);
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
