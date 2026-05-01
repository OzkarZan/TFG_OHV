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
include_once '../models/Cliente.php';

$database = new Database();
$db = $database->getConnection();

if(!$db) { exit(); }

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->nombre) && !empty($data->email) && !empty($data->password)) {
    $cliente = new Cliente($db);
    $result = $cliente->register($data->nombre, $data->email);

    if ($result['success']) {
        http_response_code(201);
        echo json_encode(array(
            "message" => "Cuenta creada con éxito. Auto-logueando...",
            "token" => $result['token'],
            "id_cliente" => $result['id_cliente'],
            "nombre" => $result['nombre']
        ));
    } else {
        http_response_code(409);
        echo json_encode(array("message" => $result['message']));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Campos obligatorios incompletos. Revisa tu nombre, correo y contraseña."));
}
?>