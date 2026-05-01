<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../config/config.php';
$database = new Database();
$db = $database->getConnection();

if(!$db) { exit(); }

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->nombre) && !empty($data->email) && !empty($data->password)) {
    // 1. Evitar Duplicaciones de Correo
    $checkQuery = "SELECT id_cliente FROM CLIENTES WHERE email = :email LIMIT 1";
    $stmtCheck = $db->prepare($checkQuery);
    $stmtCheck->bindParam(':email', $data->email);
    $stmtCheck->execute();
    
    if($stmtCheck->rowCount() > 0) {
        http_response_code(409); // 409 Conflict
        echo json_encode(array("message" => "El correo ingresado ya existe. Por favor, inicia sesión con él."));
    } else {
        // 2. Insertar Nuevo Cliente
        $insertQuery = "INSERT INTO CLIENTES (nombre, email, token_acceso) VALUES (:nombre, :email, :token)";
        $insStmt = $db->prepare($insertQuery);
        $token = bin2hex(random_bytes(16)); // Generar un token único
        
        $insStmt->bindParam(':nombre', $data->nombre);
        $insStmt->bindParam(':email', $data->email);
        $insStmt->bindParam(':token', $token);
        
        if($insStmt->execute()) {
            http_response_code(201);
            echo json_encode(array(
                "message" => "Cuenta creada con éxito. Auto-logueando...",
                "token" => $token,
                "id_cliente" => $db->lastInsertId(),
                "nombre" => $data->nombre
            ));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Error base de datos. No se pudo registrar la cuenta."));
        }
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Campos obligatorios incompletos. Revisa tu nombre, correo y contraseña."));
}
?>
