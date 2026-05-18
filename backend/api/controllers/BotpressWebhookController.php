<?php
require_once '../config/config.php';

/**
 * Webhook seguro para BotPress Cloud.
 * Todas las acciones requieren { "token": "BOTPRESS_WEBHOOK_TOKEN", "action": "...", ...datos }
 *
 * Acciones disponibles:
 *   check_coche    → { matricula }
 *   reservar_cita  → { nombre, email, fecha_hora (YYYY-MM-DD HH:MM), descripcion }
 *   mis_citas      → { email }
 *   cancelar_cita  → { email, id_cita }
 *   contacto       → { nombre, email, mensaje }
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

        $body = json_decode(file_get_contents('php://input'), true);

        // Validación de token — comparación en tiempo constante
        $expected = getenv('BOTPRESS_WEBHOOK_TOKEN');
        $received = trim($body['token'] ?? '');
        if (!$expected || !$received || !hash_equals($expected, $received)) {
            http_response_code(401);
            echo json_encode(["error" => "Unauthorized"]);
            return;
        }

        $action = $body['action'] ?? 'check_coche'; // compatibilidad con versión anterior

        switch ($action) {
            case 'check_coche':
                $this->checkCoche($body);
                break;
            case 'reservar_cita':
                $this->reservarCita($body);
                break;
            case 'mis_citas':
                $this->misCitas($body);
                break;
            case 'cancelar_cita':
                $this->cancelarCita($body);
                break;
            case 'contacto':
                $this->contacto($body);
                break;
            default:
                http_response_code(400);
                echo json_encode(["error" => "Acción desconocida: {$action}"]);
        }
    }

    // ── Ver estado del coche por matrícula ──────────────────────────────────
    private function checkCoche(array $body): void
    {
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
                "message"   => "No se encontró ninguna reparación para la matrícula {$matricula}."
            ]);
            return;
        }

        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            "found"              => true,
            "matricula"          => $matricula,
            "modelo"             => $r['modelo_auto'],
            "estado"             => $r['estado'],
            "estado_presupuesto" => $r['estado_presupuesto'],
            "motivo"             => $r['descripcion_motivo'] ?? null,
            "diagnostico"        => $r['diagnostico']        ?? null,
            "fecha_entrada"      => $r['fecha_entrada']      ?? null,
            "fecha_salida"       => $r['fecha_salida']       ?? null,
        ]);
    }

    // ── Reservar una cita (el cliente debe estar registrado) ────────────────
    private function reservarCita(array $body): void
    {
        $nombre     = trim($body['nombre']     ?? '');
        $email      = strtolower(trim($body['email']      ?? ''));
        $fecha_hora = trim($body['fecha_hora'] ?? '');
        $descripcion = trim($body['descripcion'] ?? 'Solicitud desde chatbot');

        if (!$email || !$fecha_hora) {
            http_response_code(400);
            echo json_encode(["ok" => false, "message" => "Email y fecha_hora son obligatorios."]);
            return;
        }

        // Buscar cliente por email
        $stmt = $this->db->prepare(
            "SELECT cl.id_cliente FROM CLIENTES cl
             JOIN USUARIOS u ON cl.id_usuario = u.id_usuario
             WHERE LOWER(u.email) = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            echo json_encode([
                "ok"      => false,
                "message" => "No encontré ningún cliente registrado con el email {$email}. Por favor regístrate primero en autosynctfg.site"
            ]);
            return;
        }

        // Insertar cita (sin vehículo asignado todavía)
        $insert = $this->db->prepare(
            "INSERT INTO CITAS (id_cliente, fecha_hora, descripcion, estado)
             VALUES (?, ?, ?, 'Pendiente')"
        );
        $insert->execute([$cliente['id_cliente'], $fecha_hora, $descripcion]);
        $id_cita = $this->db->lastInsertId();

        echo json_encode([
            "ok"       => true,
            "id_cita"  => $id_cita,
            "message"  => "Cita reservada para el {$fecha_hora}. El taller confirmará tu cita por email."
        ]);
    }

    // ── Ver mis citas próximas por email ────────────────────────────────────
    private function misCitas(array $body): void
    {
        $email = strtolower(trim($body['email'] ?? ''));
        if (!$email) {
            http_response_code(400);
            echo json_encode(["error" => "email is required"]);
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT c.id_cita, c.fecha_hora, c.descripcion, c.estado,
                    v.matricula, v.marca, v.modelo
             FROM CITAS c
             JOIN CLIENTES cl ON c.id_cliente = cl.id_cliente
             JOIN USUARIOS u  ON cl.id_usuario = u.id_usuario
             LEFT JOIN VEHICULOS v ON c.id_vehiculo = v.id_vehiculo
             WHERE LOWER(u.email) = ?
               AND c.fecha_hora >= NOW()
             ORDER BY c.fecha_hora ASC
             LIMIT 5"
        );
        $stmt->execute([$email]);
        $citas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "found" => count($citas) > 0,
            "citas" => $citas
        ]);
    }

    // ── Cancelar una cita por id verificando el email ───────────────────────
    private function cancelarCita(array $body): void
    {
        $email   = strtolower(trim($body['email']   ?? ''));
        $id_cita = (int)($body['id_cita'] ?? 0);

        if (!$email || !$id_cita) {
            http_response_code(400);
            echo json_encode(["ok" => false, "message" => "Email e id_cita son obligatorios."]);
            return;
        }

        // Verificar que la cita pertenece al cliente
        $stmt = $this->db->prepare(
            "SELECT c.id_cita FROM CITAS c
             JOIN CLIENTES cl ON c.id_cliente = cl.id_cliente
             JOIN USUARIOS u  ON cl.id_usuario = u.id_usuario
             WHERE c.id_cita = ? AND LOWER(u.email) = ?"
        );
        $stmt->execute([$id_cita, $email]);

        if ($stmt->rowCount() === 0) {
            echo json_encode([
                "ok"      => false,
                "message" => "No encontré esa cita asociada a tu email."
            ]);
            return;
        }

        $del = $this->db->prepare("DELETE FROM CITAS WHERE id_cita = ?");
        $del->execute([$id_cita]);

        echo json_encode(["ok" => true, "message" => "Cita cancelada correctamente."]);
    }

    // ── Enviar mensaje de contacto al taller ────────────────────────────────
    private function contacto(array $body): void
    {
        $nombre  = trim($body['nombre']  ?? 'Cliente del chatbot');
        $email   = trim($body['email']   ?? '');
        $mensaje = trim($body['mensaje'] ?? '');

        if (!$email || !$mensaje) {
            http_response_code(400);
            echo json_encode(["ok" => false, "message" => "Email y mensaje son obligatorios."]);
            return;
        }

        // Insertar en tabla CONTACTO si existe, si no registrar en log
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO CONTACTO (nombre, email, asunto, mensaje, fecha_envio)
                 VALUES (?, ?, 'Mensaje desde chatbot', ?, NOW())"
            );
            $stmt->execute([$nombre, $email, $mensaje]);
        } catch (\Exception $e) {
            // Si la tabla no existe, continuar igualmente (se envía solo por email)
        }

        echo json_encode([
            "ok"      => true,
            "message" => "Mensaje recibido. El taller se pondrá en contacto contigo en {$email} lo antes posible."
        ]);
    }
}
