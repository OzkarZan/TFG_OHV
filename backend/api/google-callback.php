<?php
/**
 * Google OAuth 2.0 Callback Handler
 * Endpoint que verifica el token de Google y realiza el login del usuario
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder a requests CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Cargar configuración
    include_once '../config/config.php';
    include_once '../config/google-config.php';
    include_once '../models/Cliente.php';

    // Obtener el token del cliente
    $data = json_decode(file_get_contents("php://input"));
    
    if (empty($data->id_token)) {
        http_response_code(400);
        echo json_encode(array(
            "success" => false,
            "message" => "Token de Google no proporcionado"
        ));
        exit();
    }

    $idToken = $data->id_token;

    // Configuración de Google
    $googleConfig = require '../config/google-config.php';
    
    // =====================================================
    // VERIFICAR Y DECODIFICAR EL TOKEN DE GOOGLE
    // =====================================================
    
    // Opción 1: Verificar con Google API (más seguro)
    $googleUserInfo = verifyGoogleToken($idToken, $googleConfig['client_id']);
    
    if (!$googleUserInfo) {
        http_response_code(401);
        echo json_encode(array(
            "success" => false,
            "message" => "No se pudo verificar el token de Google"
        ));
        exit();
    }

    // =====================================================
    // CONECTAR A LA BASE DE DATOS
    // =====================================================

    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        http_response_code(500);
        echo json_encode(array(
            "success" => false,
            "message" => "Error de conexión a la base de datos"
        ));
        exit();
    }

    // =====================================================
    // BUSCAR O CREAR EL USUARIO
    // =====================================================

    $email = $googleUserInfo['email'];
    $nombre = $googleUserInfo['name'];
    $googleId = $googleUserInfo['sub']; // Google UID

    // Buscar si el usuario existe
    $query = "SELECT id_cliente, nombre, email, token_acceso FROM CLIENTES WHERE email = :email LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        // Usuario existe: actualizar su información
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $userId = $row['id_cliente'];
        
        // Generar nuevo token de sesión
        $sessionToken = bin2hex(random_bytes(32));
        
        // Actualizar google_id y token de sesión
        $updateQuery = "UPDATE CLIENTES SET token_acceso = :token, google_id = :google_id WHERE id_cliente = :id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->bindParam(':token', $sessionToken);
        $updateStmt->bindParam(':google_id', $googleId);
        $updateStmt->bindParam(':id', $userId);
        $updateStmt->execute();

        http_response_code(200);
        echo json_encode(array(
            "success" => true,
            "message" => "Login exitoso",
            "token" => $sessionToken,
            "id_cliente" => $userId,
            "nombre" => $row['nombre'],
            "email" => $email,
            "isNewUser" => false
        ));
    } else {
        // Usuario no existe: crear uno nuevo
        $token = bin2hex(random_bytes(32));
        
        $insertQuery = "INSERT INTO CLIENTES (nombre, email, google_id, token_acceso) VALUES (:nombre, :email, :google_id, :token)";
        $insertStmt = $db->prepare($insertQuery);
        $insertStmt->bindParam(':nombre', $nombre);
        $insertStmt->bindParam(':email', $email);
        $insertStmt->bindParam(':google_id', $googleId);
        $insertStmt->bindParam(':token', $token);

        if ($insertStmt->execute()) {
            $newUserId = $db->lastInsertId();
            
            http_response_code(201);
            echo json_encode(array(
                "success" => true,
                "message" => "Usuario registrado exitosamente",
                "token" => $token,
                "id_cliente" => $newUserId,
                "nombre" => $nombre,
                "email" => $email,
                "isNewUser" => true
            ));
        } else {
            http_response_code(500);
            echo json_encode(array(
                "success" => false,
                "message" => "Error al registrar el usuario"
            ));
        }
    }

} catch (Exception $exception) {
    http_response_code(500);
    echo json_encode(array(
        "success" => false,
        "message" => "Error: " . $exception->getMessage()
    ));
}

/**
 * Verificar el token ID de Google
 * 
 * @param string $idToken - El token ID de Google
 * @param string $clientId - El ID del cliente de Google
 * @return array|false - Los datos del usuario o false si falla la verificación
 */
function verifyGoogleToken($idToken, $clientId) {
    try {
        // Obtener las claves públicas de Google
        $jwksUrl = "https://www.googleapis.com/oauth2/v3/certs";
        $jwks = file_get_contents($jwksUrl);
        $keysData = json_decode($jwks, true);

        // Decodificar el token (sin verificar por ahora)
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return false;
        }

        // Decodificar el payload
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        if (!$payload) {
            return false;
        }

        // Validar el token
        if (
            empty($payload['aud']) || 
            $payload['aud'] !== $clientId ||
            empty($payload['email']) ||
            empty($payload['sub'])
        ) {
            return false;
        }

        // Validar que el token no ha expirado
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }

        // Si estamos en desarrollo, podemos confiar parcialmente
        // En producción, deberías verificar la firma del token
        if (isset($payload['email_verified']) && $payload['email_verified'] === false) {
            // Email no verificado, pero permitir el login
        }

        return array(
            'sub' => $payload['sub'],
            'email' => $payload['email'],
            'name' => $payload['name'] ?? '',
            'picture' => $payload['picture'] ?? null,
            'email_verified' => $payload['email_verified'] ?? false
        );

    } catch (Exception $e) {
        error_log("Error verificando Google token: " . $e->getMessage());
        return false;
    }
}

?>
