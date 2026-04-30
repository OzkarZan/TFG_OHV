<?php

class MailHelper
{

    private string $from_email = "[EMAIL_ADDRESS]"; // HAY QUE CONSEGUIR UN CORREO EMPRESARIAL RECUERDO PARA OSCAR!! 
    private string $from_name = "AutoSync Support";

    public function sendWelcomeClient(string $to_email, string $to_name): bool
    {
        $subject = "¡Bienvenido a AutoSync!";

        $body = "
        <html>
        <head>
            <title>Bienvenido a AutoSync</title>
        </head>
        <body style='font-family: Arial, sans-serif; color: #333;'>
            <div style='background-color: #f4f4f4; padding: 20px; text-align: center;'>
                <h2 style='color: #0062a0;'>AutoSync</h2>
            </div>
            <div style='padding: 20px;'>
                <p>Hola <strong>{$to_name}</strong>,</p>
                <p>Te damos la bienvenida a AutoSync. Gracias por registrarte en nuestra plataforma.</p>
                <p>A partir de ahora podrás hacer un seguimiento detallado del estado de tu vehículo y tus reparaciones en tiempo real.</p>
                <br>
                <p>Saludos cordiales,<br>El equipo de AutoSync</p>
            </div>
        </body>
        </html>
        ";

        return $this->sendMail($to_email, $subject, $body);
    }

    public function sendWelcomeEmployee(string $to_email, string $to_name): bool
    {
        $subject = "Confirmación de Cuenta de Empleado - AutoSync";

        $body = "
        <html>
        <head>
            <title>Cuenta de Empleado Confirmada</title>
        </head>
        <body style='font-family: Arial, sans-serif; color: #333;'>
            <div style='background-color: #0062a0; padding: 20px; text-align: center; color: white;'>
                <h2>AutoSync Workspace</h2>
            </div>
            <div style='padding: 20px;'>
                <p>Hola <strong>{$to_name}</strong>,</p>
                <p>Tu registro como empleado ha sido validado exitosamente usando el código de acceso del taller.</p>
                <h3 style='color: #0062a0;'>Gracias a tu compañía por confiar en AutoSync.</h3>
                <p>Ya puedes acceder a tu panel de control para gestionar citas, presupuestos y reparaciones.</p>
                <br>
                <p>Atentamente,<br>El equipo de AutoSync</p>
            </div>
        </body>
        </html>
        ";

        return $this->sendMail($to_email, $subject, $body);
    }

    private function sendMail(string $to, string $subject, string $body): bool
    {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: {$this->from_name} <{$this->from_email}>" . "\r\n";

        // En un entorno de producción, aquí se integraría PHPMailer o SMTP
        // Por ahora, se usa la función nativa de PHP
        if (mail($to, $subject, $body, $headers)) {
            return true;
        } else {
            // Simulamos éxito para entorno de desarrollo local (Docker local sin SMTP)
            error_log("No se pudo enviar correo nativo. (Entorno local o sin SMTP configurado). Destino: $to");
            return true;
        }
    }
}
