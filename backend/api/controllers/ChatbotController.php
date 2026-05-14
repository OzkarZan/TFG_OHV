<?php
require_once '../config/config.php';

/**
 * Chatbot público: consulta el estado de una reparación por matrícula.
 * No requiere autenticación — solo lectura de datos no sensibles.
 */
class ChatbotController
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
            echo json_encode(["message" => "Método no permitido."]);
            return;
        }
        $this->consultar();
    }

    private function consultar(): void
    {
        $data = json_decode(file_get_contents("php://input"));
        $matricula = trim($data->matricula ?? '');

        if ($matricula === '') {
            http_response_code(400);
            echo json_encode(["respuesta" => "Por favor, escribe la matrícula de tu vehículo."]);
            return;
        }

        // Buscar la reparación más reciente (no finalizada primero, si no la última)
        $stmt = $this->db->prepare(
            "SELECT r.estado, r.estado_presupuesto, r.descripcion_motivo,
                    r.diagnostico, r.fecha_entrada, r.fecha_salida,
                    r.modelo_auto
             FROM REPARACIONES r
             WHERE UPPER(r.matricula) = UPPER(?)
             ORDER BY
               CASE r.estado
                 WHEN 'En Proceso'       THEN 0
                 WHEN 'Esperando Piezas' THEN 1
                 WHEN 'Finalizada'       THEN 2
               END ASC,
               r.id_reparacion DESC
             LIMIT 1"
        );
        $stmt->execute([strtoupper($matricula)]);

        if ($stmt->rowCount() === 0) {
            echo json_encode([
                "respuesta" => "No encontré ninguna reparación registrada para la matrícula <strong>" .
                    htmlspecialchars(strtoupper($matricula)) . "</strong>. " .
                    "Comprueba que la has escrito correctamente o contacta con el taller."
            ]);
            return;
        }

        $r  = $stmt->fetch(PDO::FETCH_ASSOC);
        $esc = fn($s) => htmlspecialchars((string)$s);

        $emojis = [
            'En Proceso'       => '🔧',
            'Esperando Piezas' => '📦',
            'Finalizada'       => '✅',
        ];
        $emoji = $emojis[$r['estado']] ?? '⚙️';

        $msg  = "<strong>{$emoji} {$esc($r['modelo_auto'])} ({$esc(strtoupper($matricula))})</strong><br>";
        $msg .= "Estado: <strong>{$esc($r['estado'])}</strong><br>";

        if ($r['descripcion_motivo']) {
            $msg .= "Motivo: {$esc($r['descripcion_motivo'])}<br>";
        }
        if ($r['diagnostico']) {
            $msg .= "Diagnóstico: {$esc($r['diagnostico'])}<br>";
        }
        if ($r['fecha_entrada']) {
            $msg .= "Entrada: {$esc($r['fecha_entrada'])}<br>";
        }
        if ($r['estado'] === 'Finalizada' && $r['fecha_salida']) {
            $msg .= "Entrega: {$esc($r['fecha_salida'])}<br>";
        } elseif ($r['estado'] === 'Finalizada') {
            $msg .= "¡Tu vehículo ya está listo para recoger! 🎉<br>";
        }

        echo json_encode(["respuesta" => $msg]);
    }
}
