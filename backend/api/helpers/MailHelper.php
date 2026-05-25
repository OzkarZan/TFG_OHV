<?php
require_once __DIR__ . '/../libs/SmtpMailer.php';

class MailHelper
{
    private string $from_email;
    private string $from_name;
    private SmtpMailer $mailer;

    public function __construct()
    {
        $this->from_email = getenv('MAIL_FROM')      ?: 'noreply@autosync.local';
        $this->from_name  = getenv('MAIL_FROM_NAME') ?: 'AutoSync';
        $this->mailer     = new SmtpMailer();
    }

    public function sendWelcomeClient(string $to_email, string $to_name): bool
    {
        $subject = '¡Bienvenido a AutoSync!';
        $body    = $this->template(
            'Bienvenido a AutoSync',
            "#0062a0",
            "Hola <strong>{$to_name}</strong>,",
            "Te damos la bienvenida a AutoSync. Gracias por registrarte en nuestra plataforma.",
            "A partir de ahora podrás hacer un seguimiento detallado del estado de tu vehículo y tus reparaciones en tiempo real."
        );
        return $this->send($to_email, $subject, $body);
    }

    public function sendWelcomeEmployee(string $to_email, string $to_name): bool
    {
        $subject = 'Confirmación de Cuenta de Empleado - AutoSync';
        $body    = $this->template(
            'AutoSync Workspace',
            "#1a1a2e",
            "Hola <strong>{$to_name}</strong>,",
            "Tu registro como empleado ha sido validado exitosamente usando el código de acceso del taller.",
            "Ya puedes acceder a tu panel de control para gestionar citas, presupuestos y reparaciones."
        );
        return $this->send($to_email, $subject, $body);
    }

    public function sendPasswordReset(string $to_email, string $to_name, string $token): bool
    {
        $app_url = rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/');
        $link    = "{$app_url}/reset-password.html?token={$token}";

        $subject = 'Restablecer contraseña - AutoSync';
        $body    = $this->template(
            'Restablecer contraseña',
            "#0062a0",
            "Hola <strong>{$to_name}</strong>,",
            "Recibimos una solicitud para restablecer la contraseña de tu cuenta.",
            "Haz clic en el botón de abajo para crear una nueva contraseña. El enlace caduca en <strong>1 hora</strong>.",
            $link,
            'Restablecer contraseña'
        );
        return $this->send($to_email, $subject, $body);
    }

    public function sendCitaConfirmacion(string $to_email, string $to_name, string $fecha_hora, string $motivo): bool
    {
        $subject = 'Cita confirmada - AutoSync';
        $body    = $this->template(
            'Cita Confirmada',
            '#0062a0',
            "Hola <strong>{$to_name}</strong>,",
            "Tu cita ha sido registrada correctamente.",
            "📅 <strong>Fecha y hora:</strong> {$fecha_hora}<br>💬 <strong>Motivo:</strong> " . ($motivo ?: 'Sin especificar')
        );
        return $this->send($to_email, $subject, $body);
    }

    public function sendContactForm(string $from_name, string $from_email, string $asunto, string $mensaje): bool
    {
        $subject = "[AutoSync Contacto] {$asunto}";
        $safe_msg = nl2br(htmlspecialchars($mensaje));
        $body = $this->template(
            'Nuevo mensaje de contacto',
            '#0062a0',
            "Mensaje de <strong>{$from_name}</strong>:",
            "Email de contacto: <a href='mailto:{$from_email}'>{$from_email}</a>",
            $safe_msg
        );
        return $this->send('autosyncohv@gmail.com', $subject, $body);
    }

    // ---------- privados ----------

    private function send(string $to, string $subject, string $body): bool
    {
        $ok = $this->mailer->send($to, $subject, $body, $this->from_email, $this->from_name);
        if (!$ok) {
            error_log("MailHelper: no se pudo enviar email a {$to} (asunto: {$subject})");
        }
        return $ok;
    }

    private function template(
        string $title,
        string $headerColor,
        string $greeting,
        string $line1,
        string $line2,
        string $btnUrl = '',
        string $btnLabel = ''
    ): string {
        $btn = $btnUrl ? "
            <div style='text-align:center;margin-top:24px;'>
                <a href='{$btnUrl}'
                   style='background:{$headerColor};color:#fff;padding:12px 28px;
                          text-decoration:none;border-radius:6px;font-weight:bold;
                          display:inline-block;'>
                    {$btnLabel}
                </a>
            </div>" : '';

        return "
        <html>
        <body style='font-family:Arial,sans-serif;color:#333;background:#f4f4f4;margin:0;padding:0;'>
          <div style='max-width:580px;margin:32px auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);'>
            <div style='background:{$headerColor};padding:24px;text-align:center;'>
                <h2 style='color:#fff;margin:0;'>{$title}</h2>
            </div>
            <div style='padding:28px 32px;'>
                <p>{$greeting}</p>
                <p>{$line1}</p>
                <p>{$line2}</p>
                {$btn}
                <br>
                <p style='color:#888;font-size:13px;'>Si no solicitaste este correo, ignóralo.</p>
                <p>Saludos,<br><strong>El equipo de AutoSync</strong></p>
            </div>
            <div style='background:#f9f9f9;padding:12px;text-align:center;font-size:12px;color:#aaa;'>
                © " . date('Y') . " AutoSync
            </div>
          </div>
        </body>
        </html>";
    }
}
