<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : 'listar';

switch ($action) {
    case 'listar':
        $faqs = [
            [
                'pregunta' => '¿Cómo reservo una cita?',
                'respuesta' => 'Para reservar una cita dime tu email, matrícula y fecha preferida. Yo lo registraré con el taller.'
            ],
            [
                'pregunta' => '¿Puedo modificar o cancelar mi cita?',
                'respuesta' => 'Sí. Indica tu número de cita y la nueva fecha o avísame si quieres cancelar.'
            ],
            [
                'pregunta' => '¿Cómo veo el estado de mi coche?',
                'respuesta' => 'Dame la matrícula o tu correo registrado y te digo el estado actual del coche en el taller.'
            ],
            [
                'pregunta' => '¿Cuáles son los horarios del taller?',
                'respuesta' => 'Nuestro taller está abierto de lunes a viernes de 08:00 a 18:00 y sábados de 09:00 a 14:00.'
            ],
            [
                'pregunta' => '¿Cómo hablo con el taller?',
                'respuesta' => 'Puedes pedir que un asesor te contacte por teléfono o dejar tu consulta aquí y te atendemos rápido.'
            ]
        ];
        http_response_code(200);
        echo json_encode($faqs);
        break;

    default:
        http_response_code(400);
        echo json_encode(["message" => "Acción desconocida: " . htmlspecialchars($action)]);
        break;
}
?>