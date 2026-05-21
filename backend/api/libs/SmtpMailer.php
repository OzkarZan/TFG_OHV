<?php
/**
 * Cliente SMTP mínimo sin dependencias externas.
 * Soporta: sin cifrado (Mailhog/dev) y STARTTLS (Gmail, SendGrid, etc.)
 */
class SmtpMailer
{
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $encryption; // 'none' | 'tls'

    public function __construct()
    {
        $this->host       = getenv('SMTP_HOST')       ?: 'mailhog';
        $this->port       = (int)(getenv('SMTP_PORT') ?: 1025);
        $this->user       = getenv('SMTP_USER')       ?: '';
        $this->pass       = getenv('SMTP_PASS')       ?: '';
        $this->encryption = getenv('SMTP_ENCRYPTION') ?: 'none';
    }

    public function send(string $to, string $subject, string $htmlBody, string $fromEmail, string $fromName): bool
    {
        try {
            $fp = $this->openSocket();
            $this->expect($fp, 220);

            $this->cmd($fp, "EHLO autosync.local");
            $this->readAll($fp);

            if ($this->encryption === 'tls') {
                $this->cmd($fp, "STARTTLS");
                $this->expect($fp, 220);
                stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->cmd($fp, "EHLO autosync.local");
                $this->readAll($fp);
            }

            if ($this->user !== '') {
                $this->cmd($fp, "AUTH LOGIN");
                $this->expect($fp, 334);
                $this->cmd($fp, base64_encode($this->user));
                $this->expect($fp, 334);
                $this->cmd($fp, base64_encode($this->pass));
                $this->expect($fp, 235);
            }

            $this->cmd($fp, "MAIL FROM:<{$fromEmail}>");
            $this->expect($fp, 250);

            $this->cmd($fp, "RCPT TO:<{$to}>");
            $this->expect($fp, 250);

            $this->cmd($fp, "DATA");
            $this->expect($fp, 354);

            $boundary = md5(uniqid());
            $headers  = implode("\r\n", [
                "From: {$fromName} <{$fromEmail}>",
                "To: {$to}",
                "Subject: {$subject}",
                "MIME-Version: 1.0",
                "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
                "Date: " . date('r'),
            ]);

            $plain = strip_tags($htmlBody);
            $body  = implode("\r\n", [
                $headers,
                "",
                "--{$boundary}",
                "Content-Type: text/plain; charset=UTF-8",
                "",
                $plain,
                "--{$boundary}",
                "Content-Type: text/html; charset=UTF-8",
                "",
                $htmlBody,
                "--{$boundary}--",
                ".",
            ]);

            fputs($fp, $body . "\r\n");
            $this->expect($fp, 250);

            $this->cmd($fp, "QUIT");
            fclose($fp);
            return true;
        } catch (\Exception $e) {
            error_log("SmtpMailer error: " . $e->getMessage());
            return false;
        }
    }

    private function openSocket()
    {
        $ctx = stream_context_create([
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);
        $fp = stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $ctx
        );
        if (!$fp) {
            throw new \RuntimeException("SMTP connect failed: {$errstr} ({$errno})");
        }
        stream_set_timeout($fp, 30);
        return $fp;
    }

    private function cmd($fp, string $cmd): void
    {
        fputs($fp, $cmd . "\r\n");
    }

    private function readAll($fp): string
    {
        $response = '';
        while ($line = fgets($fp, 512)) {
            $response .= $line;
            if ($line[3] === ' ') break; 
        }
        return $response;
    }

    private function expect($fp, int $code): string
    {
        $response = $this->readAll($fp);
        if ((int)substr($response, 0, 3) !== $code) {
            throw new \RuntimeException("Expected {$code}, got: {$response}");
        }
        return $response;
    }
}
