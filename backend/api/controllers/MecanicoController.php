<?php
require_once '../config/config.php';

class MecanicoController
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    private function requireAuth(): void
    {
        if (!isset($_SESSION['id_usuario'])) {
            http_response_code(401);
            echo json_encode(["message" => "No autorizado."]);
            exit;
        }
    }

    public function handleRequest(string $method): void
    {
        $this->requireAuth();
        switch ($method) {
            case 'GET':    $this->read();   break;
            case 'POST':   $this->create(); break;
            case 'PUT':    $this->update(); break;
            case 'DELETE': $this->delete(); break;
            default:
                http_response_code(405);
                echo json_encode(["message" => "Método no permitido."]);
        }
    }

    public function handleHorarios(string $method): void
    {
        $this->requireAuth();
        switch ($method) {
            case 'GET':    $this->getHorarios();   break;
            case 'POST':   $this->upsertHorario(); break;
            case 'DELETE': $this->deleteHorario(); break;
            default:
                http_response_code(405);
                echo json_encode(["message" => "Método no permitido."]);
        }
    }

    // ── GET /mecanicos  or  GET /mecanicos?action=disponibilidad|detalle ──

    private function read(): void
    {
        $action = $_GET['action'] ?? '';

        if ($action === 'disponibilidad') {
            $this->checkDisponibilidad();
            return;
        }

        if ($action === 'detalle') {
            $this->getDetalle();
            return;
        }

        $stmt = $this->db->prepare("
            SELECT m.id_mecanico, m.especialidad, m.id_taller,
                   u.id_usuario, u.nombre_completo, u.email
            FROM MECANICOS m
            JOIN USUARIOS u ON u.id_usuario = m.id_usuario
            ORDER BY u.nombre_completo ASC
        ");
        $stmt->execute();
        http_response_code(200);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function checkDisponibilidad(): void
    {
        $id_mecanico = intval($_GET['id_mecanico'] ?? 0);
        $fecha_hora  = $_GET['fecha_hora'] ?? '';

        if (!$id_mecanico || !$fecha_hora) {
            http_response_code(400);
            echo json_encode(["message" => "Faltan parámetros."]);
            return;
        }

        $ts = strtotime($fecha_hora);
        if (!$ts) {
            http_response_code(400);
            echo json_encode(["message" => "Fecha inválida."]);
            return;
        }

        $dia_semana = (int) date('N', $ts); // 1=Lun … 7=Dom
        $hora       = date('H:i:s', $ts);

        // 1. Check mechanic has a schedule this day
        $stmtH = $this->db->prepare("
            SELECT hora_inicio, hora_fin
            FROM MECANICO_HORARIOS
            WHERE id_mecanico = :id AND dia_semana = :dia
        ");
        $stmtH->execute([':id' => $id_mecanico, ':dia' => $dia_semana]);
        $horario = $stmtH->fetch(PDO::FETCH_ASSOC);

        if (!$horario) {
            http_response_code(200);
            echo json_encode(["disponible" => false, "motivo" => "El mecánico no trabaja ese día."]);
            return;
        }

        // 2. Check hour falls within schedule
        if ($hora < $horario['hora_inicio'] || $hora >= $horario['hora_fin']) {
            $inicio = substr($horario['hora_inicio'], 0, 5);
            $fin    = substr($horario['hora_fin'], 0, 5);
            http_response_code(200);
            echo json_encode([
                "disponible" => false,
                "motivo"     => "Fuera del horario del mecánico ({$inicio}–{$fin})."
            ]);
            return;
        }

        // 3. Check no overlapping appointment (±59 min window)
        $stmtC = $this->db->prepare("
            SELECT COUNT(*) AS cnt
            FROM CITAS
            WHERE id_mecanico = :id
              AND estado != 'Cancelada'
              AND fecha_hora BETWEEN DATE_SUB(:fh, INTERVAL 59 MINUTE)
                                AND DATE_ADD(:fh, INTERVAL 59 MINUTE)
        ");
        $stmtC->execute([':id' => $id_mecanico, ':fh' => $fecha_hora]);
        $row = $stmtC->fetch(PDO::FETCH_ASSOC);

        if ((int) $row['cnt'] > 0) {
            http_response_code(200);
            echo json_encode(["disponible" => false, "motivo" => "El mecánico ya tiene una cita en esa franja."]);
            return;
        }

        http_response_code(200);
        echo json_encode(["disponible" => true]);
    }

    private function getDetalle(): void
    {
        $id_mecanico = intval($_GET['id_mecanico'] ?? 0);
        if (!$id_mecanico) {
            http_response_code(400);
            echo json_encode(["message" => "Falta id_mecanico."]);
            return;
        }

        // Citas assigned this week
        $stmtC = $this->db->prepare("
            SELECT c.id_cita, c.fecha_hora, c.motivo, c.estado,
                   v.matricula, v.modelo, v.marca
            FROM CITAS c
            LEFT JOIN VEHICULOS v ON v.id_vehiculo = c.id_vehiculo
            WHERE c.id_mecanico = :id
              AND YEARWEEK(c.fecha_hora, 1) = YEARWEEK(CURDATE(), 1)
            ORDER BY c.fecha_hora ASC
        ");
        $stmtC->execute([':id' => $id_mecanico]);
        $citas = $stmtC->fetchAll(PDO::FETCH_ASSOC);

        // Presupuestos via REPARACION_MECANICO
        $stmtP = $this->db->prepare("
            SELECT p.id_presupuesto, p.gran_total, p.estado, p.fecha_emision,
                   r.matricula, r.modelo_auto
            FROM PRESUPUESTOS p
            JOIN REPARACIONES r      ON r.id_reparacion = p.id_reparacion
            JOIN REPARACION_MECANICO rm ON rm.id_reparacion = r.id_reparacion
            WHERE rm.id_mecanico = :id
            ORDER BY p.fecha_emision DESC
            LIMIT 20
        ");
        $stmtP->execute([':id' => $id_mecanico]);
        $presupuestos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

        http_response_code(200);
        echo json_encode(compact('citas', 'presupuestos'));
    }

    // ── POST /mecanicos ──

    private function create(): void
    {
        $data = json_decode(file_get_contents("php://input"), true);

        $nombre       = trim($data['nombre_completo'] ?? '');
        $email        = trim($data['email'] ?? '');
        $password     = $data['password'] ?? '';
        $especialidad = trim($data['especialidad'] ?? '');
        $id_taller    = !empty($data['id_taller']) ? intval($data['id_taller']) : null;

        if (!$nombre || !$email || !$password) {
            http_response_code(400);
            echo json_encode(["message" => "Nombre, email y contraseña son obligatorios."]);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["message" => "Email inválido."]);
            return;
        }

        $stmtCheck = $this->db->prepare("SELECT id_usuario FROM USUARIOS WHERE email = :email");
        $stmtCheck->execute([':email' => $email]);
        if ($stmtCheck->fetch()) {
            http_response_code(409);
            echo json_encode(["message" => "El correo ya está registrado."]);
            return;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $this->db->beginTransaction();

            $stmtU = $this->db->prepare("
                INSERT INTO USUARIOS (email, password_hash, rol, nombre_completo)
                VALUES (:email, :hash, 'mecanico', :nombre)
            ");
            $stmtU->execute([':email' => $email, ':hash' => $hash, ':nombre' => $nombre]);
            $id_usuario = $this->db->lastInsertId();

            $stmtM = $this->db->prepare("
                INSERT INTO MECANICOS (id_usuario, especialidad, id_taller)
                VALUES (:id_usuario, :especialidad, :id_taller)
            ");
            $stmtM->execute([
                ':id_usuario'   => $id_usuario,
                ':especialidad' => $especialidad,
                ':id_taller'    => $id_taller
            ]);

            $this->db->commit();
            http_response_code(201);
            echo json_encode(["message" => "Mecánico registrado correctamente."]);
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("MecanicoController::create error: " . $e->getMessage());
            http_response_code(503);
            echo json_encode(["message" => "Error al crear el mecánico."]);
        }
    }

    // ── PUT /mecanicos ──

    private function update(): void
    {
        $data        = json_decode(file_get_contents("php://input"), true);
        $id_mecanico = intval($data['id_mecanico'] ?? 0);
        $especialidad = trim($data['especialidad'] ?? '');
        $nombre       = trim($data['nombre_completo'] ?? '');

        if (!$id_mecanico) {
            http_response_code(400);
            echo json_encode(["message" => "Falta ID de mecánico."]);
            return;
        }

        $this->db->prepare("
            UPDATE MECANICOS SET especialidad = :esp WHERE id_mecanico = :id
        ")->execute([':esp' => $especialidad, ':id' => $id_mecanico]);

        if ($nombre) {
            $this->db->prepare("
                UPDATE USUARIOS u
                JOIN MECANICOS m ON m.id_usuario = u.id_usuario
                SET u.nombre_completo = :nombre
                WHERE m.id_mecanico = :id
            ")->execute([':nombre' => $nombre, ':id' => $id_mecanico]);
        }

        http_response_code(200);
        echo json_encode(["message" => "Mecánico actualizado."]);
    }

    // ── DELETE /mecanicos ──

    private function delete(): void
    {
        $data        = json_decode(file_get_contents("php://input"), true);
        $id_mecanico = intval($data['id_mecanico'] ?? 0);

        if (!$id_mecanico) {
            http_response_code(400);
            echo json_encode(["message" => "Falta ID."]);
            return;
        }

        $stmtGet = $this->db->prepare("SELECT id_usuario FROM MECANICOS WHERE id_mecanico = :id");
        $stmtGet->execute([':id' => $id_mecanico]);
        $row = $stmtGet->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(["message" => "Mecánico no encontrado."]);
            return;
        }

        // Nullify FK before cascade to avoid constraint error
        $this->db->prepare("UPDATE CITAS SET id_mecanico = NULL WHERE id_mecanico = :id")
                  ->execute([':id' => $id_mecanico]);

        // ON DELETE CASCADE handles MECANICOS and MECANICO_HORARIOS rows
        $this->db->prepare("DELETE FROM USUARIOS WHERE id_usuario = :id")
                  ->execute([':id' => $row['id_usuario']]);

        http_response_code(200);
        echo json_encode(["message" => "Mecánico eliminado."]);
    }

    // ── GET /mecanico-horarios?id_mecanico=X ──

    private function getHorarios(): void
    {
        $id_mecanico = intval($_GET['id_mecanico'] ?? 0);
        if (!$id_mecanico) {
            http_response_code(400);
            echo json_encode(["message" => "Falta id_mecanico."]);
            return;
        }

        $stmt = $this->db->prepare("
            SELECT id_horario, dia_semana, hora_inicio, hora_fin
            FROM MECANICO_HORARIOS
            WHERE id_mecanico = :id
            ORDER BY dia_semana ASC
        ");
        $stmt->execute([':id' => $id_mecanico]);
        http_response_code(200);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // ── POST /mecanico-horarios  (upsert) ──

    private function upsertHorario(): void
    {
        $data        = json_decode(file_get_contents("php://input"), true);
        $id_mecanico = intval($data['id_mecanico'] ?? 0);
        $dia_semana  = intval($data['dia_semana'] ?? 0);
        $hora_inicio = $data['hora_inicio'] ?? '';
        $hora_fin    = $data['hora_fin'] ?? '';

        if (!$id_mecanico || !$dia_semana || !$hora_inicio || !$hora_fin) {
            http_response_code(400);
            echo json_encode(["message" => "Todos los campos son obligatorios."]);
            return;
        }

        if ($hora_inicio >= $hora_fin) {
            http_response_code(400);
            echo json_encode(["message" => "La hora de inicio debe ser anterior a la hora de fin."]);
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO MECANICO_HORARIOS (id_mecanico, dia_semana, hora_inicio, hora_fin)
            VALUES (:id, :dia, :inicio, :fin)
            ON DUPLICATE KEY UPDATE hora_inicio = VALUES(hora_inicio), hora_fin = VALUES(hora_fin)
        ");
        $stmt->execute([
            ':id'     => $id_mecanico,
            ':dia'    => $dia_semana,
            ':inicio' => $hora_inicio,
            ':fin'    => $hora_fin
        ]);

        http_response_code(200);
        echo json_encode(["message" => "Horario guardado."]);
    }

    // ── DELETE /mecanico-horarios ──

    private function deleteHorario(): void
    {
        $data        = json_decode(file_get_contents("php://input"), true);
        $id_mecanico = intval($data['id_mecanico'] ?? 0);
        $dia_semana  = intval($data['dia_semana'] ?? 0);

        if (!$id_mecanico || !$dia_semana) {
            http_response_code(400);
            echo json_encode(["message" => "Faltan parámetros."]);
            return;
        }

        $this->db->prepare("
            DELETE FROM MECANICO_HORARIOS WHERE id_mecanico = :id AND dia_semana = :dia
        ")->execute([':id' => $id_mecanico, ':dia' => $dia_semana]);

        http_response_code(200);
        echo json_encode(["message" => "Horario eliminado."]);
    }
}
