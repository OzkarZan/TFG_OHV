<?php
require_once '../config/config.php';

/**
 * Webhook seguro para BotPress Cloud.
 * Todas las acciones requieren { "token": "BOTPRESS_WEBHOOK_TOKEN", "action": "...", ...datos }
 *
 * Acciones disponibles:
 *   check_coche       → { matricula }
 *   validar_cliente   → { email } — Valida si cliente existe ANTES de pedir fecha
 *   reservar_cita     → { nombre, email, fecha_hora (YYYY-MM-DD HH:MM), descripcion }
 *   mis_citas         → { email }
 *   cancelar_cita     → { email, id_cita }
 *   contacto          → { nombre, email, mensaje }
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
            case 'validar_cliente':
                $this->validateClienteEmail($body);
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

    public function validateClienteEmail(array $body): void
    {
        $email = strtolower(trim($body['email'] ?? ''));
        if (!$email) {
            http_response_code(400);
            echo json_encode(["ok" => false, "message" => "Email es obligatorio."]);
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT cl.id_cliente, u.nombre_completo FROM CLIENTES cl
             JOIN USUARIOS u ON cl.id_usuario = u.id_usuario
             WHERE LOWER(u.email) = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            echo json_encode([
                "ok"      => false,
                "exists"  => false,
                "message" => "No encontré ningún cliente registrado con el email {$email}. Por favor regístrate primero en https://autosynctfg.site/index.html"
            ]);
            return;
        }

        echo json_encode([
            "ok"     => true,
            "exists" => true,
            "id_cliente"       => $cliente['id_cliente'],
            "nombre_completo"  => $cliente['nombre_completo'],
            "message" => "Cliente encontrado. Procede a ingresar la fecha y hora."
        ]);
    }

    private function reservarCita(array $body): void
    {
        $nombre      = trim($body['nombre']      ?? '');
        $email       = strtolower(trim($body['email']      ?? ''));
        $fecha_hora  = trim($body['fecha_hora']  ?? '');
        $descripcion = trim($body['descripcion'] ?? 'Solicitud desde chatbot');

        if (!$email || !$fecha_hora) {
            http_response_code(400);
            echo json_encode(["ok" => false, "message" => "Email y fecha_hora son obligatorios."]);
            return;
        }

        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $fecha_hora)
           ?: \DateTime::createFromFormat('Y-m-d H:i',   $fecha_hora);

        if (!$dt) {
            echo json_encode(["ok" => false, "message" => "Formato de fecha incorrecto. Usa YYYY-MM-DD HH:MM."]);
            return;
        }

        if ($dt < new \DateTime()) {
            echo json_encode(["ok" => false, "message" => "No puedes reservar una cita en el pasado."]);
            return;
        }

        $diaSemana = (int)$dt->format('N');
        $hora      = (int)$dt->format('H');
        $minutos   = (int)$dt->format('i');

        if ($diaSemana === 7) {
            echo json_encode(["ok" => false, "message" => "El taller está cerrado los domingos. Puedes reservar de lunes a viernes de 08:00 a 18:00, o los sábados de 09:00 a 13:00."]);
            return;
        }

        $dentroHorario = false;
        if ($diaSemana <= 5 && $hora >= 8 && ($hora < 18 || ($hora === 18 && $minutos === 0))) {
            $dentroHorario = true;
        } elseif ($diaSemana === 6 && $hora >= 9 && ($hora < 13 || ($hora === 13 && $minutos === 0))) {
            $dentroHorario = true;
        }

        if (!$dentroHorario) {
            $horario = $diaSemana <= 5 ? 'de 08:00 a 18:00' : 'de 09:00 a 13:00';
            $dia     = $diaSemana <= 5 ? 'los días laborables' : 'los sábados';
            echo json_encode(["ok" => false, "message" => "Fuera de horario. El taller atiende {$dia} {$horario}."]);
            return;
        }

        // ── Verificar disponibilidad (máx. 3 citas por franja de 1 hora) ────
        $franjaInicio = $dt->format('Y-m-d H:00:00');
        $franjaFin    = $dt->format('Y-m-d H:59:59');

        $stmtDisp = $this->db->prepare(
            "SELECT COUNT(*) FROM CITAS
             WHERE fecha_hora BETWEEN ? AND ?
               AND estado != 'Cancelada'"
        );
        $stmtDisp->execute([$franjaInicio, $franjaFin]);
        $citasEnFranja = (int)$stmtDisp->fetchColumn();

        if ($citasEnFranja >= 3) {
            $horaStr = $dt->format('H:00');
            $sigHora = $dt->modify('+1 hour')->format('H:00');
            echo json_encode(["ok" => false, "message" => "No hay disponibilidad en la franja de las {$horaStr} a las {$sigHora}. Por favor elige otro horario."]);
            return;
        }

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

        $insert = $this->db->prepare(
            "INSERT INTO CITAS (id_cliente, fecha_hora, motivo, estado)
             VALUES (?, ?, ?, 'Pendiente')"
        );
        $insert->execute([$cliente['id_cliente'], $fecha_hora, $descripcion]);
        $id_cita = $this->db->lastInsertId();

        require_once 'helpers/MailHelper.php';
        (new MailHelper())->sendCitaConfirmacion($email, $nombre ?: $email, $fecha_hora, $descripcion);

        echo json_encode([
            "ok"       => true,
            "id_cita"  => $id_cita,
            "message"  => "Cita reservada para el {$fecha_hora}. El taller confirmará tu cita por email."
        ]);
    }

    private function misCitas(array $body): void
    {
        $email = strtolower(trim($body['email'] ?? ''));
        if (!$email) {
            http_response_code(400);
            echo json_encode(["error" => "email is required"]);
            return;
        }

        $stmtCliente = $this->db->prepare(
            "SELECT cl.id_cliente FROM CLIENTES cl
             JOIN USUARIOS u ON cl.id_usuario = u.id_usuario
             WHERE LOWER(u.email) = ?"
        );
        $stmtCliente->execute([$email]);
        $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            echo json_encode([
                "found" => false,
                "citas" => []
            ]);
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT c.id_cita, c.fecha_hora, c.motivo, c.estado,
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
            "found" => true,
            "citas" => $citas
        ]);
    }

    private function cancelarCita(array $body): void
    {
        $email   = strtolower(trim($body['email']   ?? ''));
        $id_cita = (int)($body['id_cita'] ?? 0);

        if (!$email || !$id_cita) {
            http_response_code(400);
            echo json_encode(["ok" => false, "message" => "Email e id_cita son obligatorios."]);
            return;
        }

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
