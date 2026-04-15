<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Escape rápido para peticiones Preflight CORS del navegador
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../config/config.php';

$database = new Database();
$db = $database->getConnection();

// Verificar la conexión
if(!$db) {
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->email)) {
    // Check if user exists
    $query = "SELECT id_cliente, nombre, email, token_acceso FROM CLIENTES WHERE email = :email LIMIT 0,1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $data->email);
    $stmt->execute();
    $num = $stmt->rowCount();

    if($num > 0) {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        // User exists, generate a token for this session
        $token = bin2hex(random_bytes(16));
        
        // Google auth is optional for now
        $google_id = !empty($data->google_id) ? $data->google_id : null;
        
        $updateQuery = "UPDATE CLIENTES SET token_acceso = :token, google_id = :google_id WHERE id_cliente = :id";
        $updStmt = $db->prepare($updateQuery);
        $updStmt->bindParam(':token', $token);
        $updStmt->bindParam(':google_id', $google_id);
        $updStmt->bindParam(':id', $row['id_cliente']);
        $updStmt->execute();

        http_response_code(200);
        echo json_encode(array(
            "message" => "Login exitoso.",
            "token" => $token,
            "id_cliente" => $row['id_cliente'],
            "nombre" => $row['nombre']
        ));
    } else {
        // Acceso denegado: El correo no existe
        http_response_code(401);
        echo json_encode(array("message" => "Acceso denegado. Este correo no se encuentra registrado en el sistema."));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Datos incompletos. Se requiere introducir un email."));
}
?>
