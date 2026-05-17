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
                if ($this->user->rol === 'cliente') {
                    require_once 'models/Cliente.php';
                    $cliente = new Cliente($this->db);
                    $cliente->id_usuario = $this->user->id_usuario;
                    // Los administradores podrían enviar teléfono y dirección, si no, se guardan vacíos
                    $cliente->telefono = $data->telefono ?? null;
                    $cliente->direccion = $data->direccion ?? null;
                    $cliente->create();
                }

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

    public function forgotPassword() {
        $data = json_decode(file_get_contents("php://input"));
        if (empty($data->email)) {
            http_response_code(400);
            echo json_encode(["message" => "Email requerido."]);
            return;
        }

        $this->user->email = $data->email;
        if (!$this->user->emailExists()) {
            // No revelar si el email existe o no
            http_response_code(200);
            echo json_encode(["message" => "Si el correo existe recibirás un enlace para restablecer tu contraseña."]);
            return;
        }

        $token   = bin2hex(random_bytes(32));
        $expira  = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $this->db->prepare(
            "INSERT INTO RESET_TOKENS (id_usuario, token, expira_en) VALUES (?, ?, ?)"
        );
        $stmt->execute([$this->user->id_usuario, $token, $expira]);

        require_once 'helpers/MailHelper.php';
        $mail = new MailHelper();
        $mail->sendPasswordReset($this->user->email, $this->user->nombre_completo, $token);

        http_response_code(200);
        echo json_encode(["message" => "Si el correo existe recibirás un enlace para restablecer tu contraseña."]);
    }

    public function resetPassword() {
        $data = json_decode(file_get_contents("php://input"));
        if (empty($data->token) || empty($data->password)) {
            http_response_code(400);
            echo json_encode(["message" => "Token y nueva contraseña son requeridos."]);
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT id_usuario FROM RESET_TOKENS
             WHERE token = ? AND expira_en > NOW() AND usado = 0"
        );
        $stmt->execute([$data->token]);

        if ($stmt->rowCount() === 0) {
            http_response_code(400);
            echo json_encode(["message" => "Token inválido o expirado."]);
            return;
        }

        $row  = $stmt->fetch(PDO::FETCH_ASSOC);
        $hash = password_hash($data->password, PASSWORD_BCRYPT);

        $this->db->prepare(
            "UPDATE USUARIOS SET password_hash = ? WHERE id_usuario = ?"
        )->execute([$hash, $row['id_usuario']]);

        $this->db->prepare(
            "UPDATE RESET_TOKENS SET usado = 1 WHERE token = ?"
        )->execute([$data->token]);

        http_response_code(200);
        echo json_encode(["message" => "Contraseña actualizada correctamente."]);
    }

    public function googleLogin() {
        $data = json_decode(file_get_contents("php://input"));
        if (empty($data->credential)) {
            http_response_code(400);
            echo json_encode(["message" => "Token de Google requerido."]);
            return;
        }

        // Verify the ID token with Google's tokeninfo endpoint
        $token    = $data->credential;
        $url      = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($token);
        $response = @file_get_contents($url);

        if ($response === false) {
            http_response_code(502);
            echo json_encode(["message" => "No se pudo verificar el token de Google."]);
            return;
        }

        $payload = json_decode($response, true);

        // Validate audience matches our Client ID
        $expectedAud = getenv('GOOGLE_CLIENT_ID');
        if (empty($payload['email_verified']) || $payload['email_verified'] !== 'true') {
            http_response_code(401);
            echo json_encode(["message" => "Email de Google no verificado."]);
            return;
        }
        if ($expectedAud && isset($payload['aud']) && $payload['aud'] !== $expectedAud) {
            http_response_code(401);
            echo json_encode(["message" => "Token no válido para esta aplicación."]);
            return;
        }

        $email  = $payload['email'];
        $nombre = $payload['name'] ?? $email;

        // Find or create user
        $this->user->email = $email;
        if ($this->user->emailExists()) {
            $id_usuario      = $this->user->id_usuario;
            $rol             = $this->user->rol;
            $nombre_completo = $this->user->nombre_completo;
        } else {
            // Create new client account (Google users are always clients)
            $this->user->nombre_completo = $nombre;
            $this->user->password_hash   = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
            $this->user->rol             = 'cliente';

            if (!$this->user->create()) {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo crear la cuenta."]);
                return;
            }

            $id_usuario      = $this->user->id_usuario;
            $rol             = 'cliente';
            $nombre_completo = $nombre;

            // Create CLIENTES profile
            require_once 'models/Cliente.php';
            $cliente             = new Cliente($this->db);
            $cliente->id_usuario = $id_usuario;
            $cliente->telefono   = null;
            $cliente->direccion  = null;
            $cliente->create();

            // Welcome email
            require_once 'helpers/MailHelper.php';
            (new MailHelper())->sendWelcomeClient($email, $nombre_completo);
        }

        session_regenerate_id(true);
        $_SESSION['id_usuario']      = $id_usuario;
        $_SESSION['rol']             = $rol;
        $_SESSION['nombre_completo'] = $nombre_completo;

        http_response_code(200);
        echo json_encode([
            "message"         => "Login con Google exitoso.",
            "rol"             => $rol,
            "nombre_completo" => $nombre_completo,
        ]);
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
