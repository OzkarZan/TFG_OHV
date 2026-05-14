<?php
require_once '../config/config.php';
require_once '../helpers/MailHelper.php';

class ContactoController
{
    public function handleRequest(string $method): void
    {
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(["message" => "Método no permitido."]);
            return;
        }
        $this->enviar();
    }

    private function enviar(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $nombre  = trim($data['nombre']  ?? '');
        $email   = trim($data['email']   ?? '');
        $asunto  = trim($data['asunto']  ?? '');
        $mensaje = trim($data['mensaje'] ?? '');

        if (!$nombre || !$email || !$asunto || !$mensaje) {
            http_response_code(400);
            echo json_encode(["message" => "Todos los campos son requeridos."]);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["message" => "El email no tiene un formato válido."]);
            return;
        }

        $mail = new MailHelper();
        $ok   = $mail->sendContactForm($nombre, $email, $asunto, $mensaje);

        if ($ok) {
            http_response_code(200);
            echo json_encode(["message" => "Mensaje enviado correctamente."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "No se pudo enviar el mensaje. Inténtalo más tarde."]);
        }
    }
}
