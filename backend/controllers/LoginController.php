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
include_once '../models/Cliente.php';

$database = new Database();
$db = $database->getConnection();

// Verificar la conexión
if(!$db) {
    exit();
}

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->email)) {
    $cliente = new Cliente($db);
    $result = $cliente->login($data->email, !empty($data->google_id) ? $data->google_id : null);

    if ($result['success']) {
        http_response_code(200);
        echo json_encode(array(
            "message" => "Login exitoso.",
            "token" => $result['token'],
            "id_cliente" => $result['id_cliente'],
            "nombre" => $result['nombre']
        ));
    } else {
        http_response_code(401);
        echo json_encode(array("message" => $result['message']));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Datos incompletos. Se requiere introducir un email."));
}
?>