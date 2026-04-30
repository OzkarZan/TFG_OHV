<?php
require_once '../config/config.php';
require_once 'models/Usuario.php';

class AuthController {
    private $db;
    private $user;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new Usuario($this->db);
    }

    public function login() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->email) && !empty($data->password)) {
            $this->user->email = $data->email;

            if ($this->user->emailExists() && password_verify($data->password, $this->user->password_hash)) {
                // Generar ID de sesión seguro
                session_regenerate_id(true);
                $_SESSION['id_usuario'] = $this->user->id_usuario;
                $_SESSION['rol'] = $this->user->rol;
                $_SESSION['nombre_completo'] = $this->user->nombre_completo;

                http_response_code(200);
                echo json_encode([
                    "message" => "Login exitoso.",
                    "rol" => $this->user->rol,
                    "nombre_completo" => $this->user->nombre_completo
                ]);
            } else {
                http_response_code(401);
                echo json_encode(["message" => "Credenciales incorrectas."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Datos incompletos."]);
        }
    }

    public function register() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->nombre) && !empty($data->email) && !empty($data->password)) {
            $this->user->email = $data->email;
            
            if ($this->user->emailExists()) {
                http_response_code(400);
                echo json_encode(["message" => "El correo electrónico ya está registrado."]);
                return;
            }

            $this->user->nombre_completo = $data->nombre;
            $this->user->password_hash = password_hash($data->password, PASSWORD_BCRYPT);
            
            // Lógica de registro para Empleado
            if (isset($data->rol) && $data->rol === 'empleado') {
                if (isset($data->access_code) && $data->access_code === '[admin]') {
                    $this->user->rol = 'empleado';
                } else {
                    http_response_code(401);
                    echo json_encode(["message" => "Código de acceso de taller incorrecto."]);
                    return;
                }
            } else {
                $this->user->rol = 'cliente';
            }

            if ($this->user->create()) {
                // Enviar Correo de bienvenida
                require_once 'helpers/MailHelper.php';
                $mail = new MailHelper();
                
                if ($this->user->rol === 'empleado') {
                    $mail->sendWelcomeEmployee($this->user->email, $this->user->nombre_completo);
                } else {
                    $mail->sendWelcomeClient($this->user->email, $this->user->nombre_completo);
                }

                http_response_code(201);
                echo json_encode(["message" => "Usuario creado con éxito."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo registrar el usuario."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Datos incompletos para el registro."]);
        }
    }

    public function logout() {
        session_unset();
        session_destroy();
        http_response_code(200);
        echo json_encode(["message" => "Sesión cerrada correctamente."]);
    }

    public function me() {
        if (isset($_SESSION['id_usuario'])) {
            http_response_code(200);
            echo json_encode([
                "id_usuario" => $_SESSION['id_usuario'],
                "nombre_completo" => $_SESSION['nombre_completo'],
                "rol" => $_SESSION['rol']
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["message" => "No autenticado."]);
        }
    }
}
