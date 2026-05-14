<?php
require_once '../config/config.php';

/**
 * Webhook seguro para BotPress Cloud.
 * BotPress llama a POST /api/botpress-webhook con { "token": "...", "matricula": "..." }
 * y obtiene JSON estructurado (sin HTML) que el bot puede leer y formatar.
 *
 * Configura el token en el .env como BOTPRESS_WEBHOOK_TOKEN y en los secretos de BotPress.
 */
class BotpressWebhookController
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function handleRequest(string $method): void
    {
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(["error" => "Method not allowed"]);
            return;
        }
        $this->consultar();
    }

    private function consultar(): void
    {
        $body = json_decode(file_get_contents('php://input'), true);

        // Token validation — constant-time comparison prevents timing attacks
        $expected = getenv('BOTPRESS_WEBHOOK_TOKEN');
        $received = trim($body['token'] ?? '');
        if (!$expected || !$received || !hash_equals($expected, $received)) {
            http_response_code(401);
            echo json_encode(["error" => "Unauthorized"]);
            return;
        }

        $matricula = strtoupper(trim($body['matricula'] ?? ''));
        if ($matricula === '') {
            http_response_code(400);
            echo json_encode(["error" => "matricula is required"]);
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT r.estado, r.estado_presupuesto, r.descripcion_motivo,
                    r.diagnostico, r.fecha_entrada, r.fecha_salida, r.modelo_auto
             FROM REPARACIONES r
             WHERE UPPER(r.matricula) = ?
             ORDER BY
               CASE r.estado
                 WHEN 'En Proceso'       THEN 0
                 WHEN 'Esperando Piezas' THEN 1
                 WHEN 'Finalizada'       THEN 2
               END ASC,
               r.id_reparacion DESC
             LIMIT 1"
        );
        $stmt->execute([$matricula]);

        if ($stmt->rowCount() === 0) {
            echo json_encode([
                "found"     => false,
                "matricula" => $matricula,
                "message"   => "No se encontró ninguna reparación registrada para la matrícula {$matricula}."
            ]);
            return;
        }

        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            "found"               => true,
            "matricula"           => $matricula,
            "modelo"              => $r['modelo_auto'],
            "estado"              => $r['estado'],
            "estado_presupuesto"  => $r['estado_presupuesto'],
            "motivo"              => $r['descripcion_motivo'] ?? null,
            "diagnostico"         => $r['diagnostico']        ?? null,
            "fecha_entrada"       => $r['fecha_entrada']      ?? null,
            "fecha_salida"        => $r['fecha_salida']       ?? null,
        ]);
    }
}
