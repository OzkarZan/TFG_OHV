<?php
require_once '../config/config.php';
require_once 'models/Reparacion.php';
require_once 'models/Cita.php';
require_once 'models/Vehiculo.php';

/**
 * Endpoints del área de cliente autenticado.
 * Solo devuelve datos pertenecientes al propio cliente.
 */
class MiAreaController
{
    private $db;

    public function __construct()
    {
        $database  = new Database();
        $this->db  = $database->getConnection();
    }

    private function requireCliente(): int
    {
        if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'cliente') {
            http_response_code(403);
            echo json_encode(["message" => "Acceso denegado."]);
            exit;
        }
        $stmt = $this->db->prepare(
            "SELECT id_cliente FROM CLIENTES WHERE id_usuario = ?"
        );
        $stmt->execute([$_SESSION['id_usuario']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(403);
            echo json_encode(["message" => "Perfil de cliente no encontrado."]);
            exit;
        }
        return (int)$row['id_cliente'];
    }

    public function handleRequest(string $method, string $path): void
    {
        $id_cliente = $this->requireCliente();

        if ($method === 'GET' && strpos($path, '/presupuestos') !== false) {
            $this->misPresupuestos($id_cliente);
            return;
        }

        if ($method === 'POST' && strpos($path, '/cita') !== false) {
            $this->crearCita($id_cliente);
            return;
        }

        if ($method === 'POST' && strpos($path, '/vehiculo') !== false) {
            $this->agregarVehiculo($id_cliente);
            return;
        }

        if ($method === 'DELETE' && strpos($path, '/cita') !== false) {
            $this->cancelarCita($id_cliente);
            return;
        }

        if ($method === 'PUT' && strpos($path, '/presupuestos') !== false) {
            $this->responderPresupuesto($id_cliente);
            return;
        }

        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(["message" => "Método no permitido."]);
            return;
        }

        $this->overview($id_cliente);
    }

    private function overview(int $id_cliente): void
    {
        http_response_code(200);
        echo json_encode([
            'reparaciones' => $this->fetchReparaciones($id_cliente),
            'citas'        => $this->fetchCitas($id_cliente),
            'vehiculos'    => $this->fetchVehiculos($id_cliente),
        ]);
    }

    private function fetchReparaciones(int $id_cliente): array
    {
        $stmt = $this->db->prepare(
            "SELECT r.id_reparacion, r.matricula, r.modelo_auto,
                    r.descripcion_motivo, r.diagnostico,
                    r.estado, r.estado_presupuesto,
                    r.fecha_entrada, r.fecha_salida
             FROM REPARACIONES r
             INNER JOIN VEHICULOS v ON r.matricula = v.matricula
             WHERE v.id_cliente = ?
             ORDER BY r.id_reparacion DESC"
        );
        $stmt->execute([$id_cliente]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchCitas(int $id_cliente): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.id_cita, c.fecha_hora, c.motivo, c.estado, c.prioridad, c.es_emergencia,
                    v.matricula, v.modelo, v.marca
             FROM CITAS c
             LEFT JOIN VEHICULOS v ON c.id_vehiculo = v.id_vehiculo
             WHERE c.id_cliente = ?
             ORDER BY c.fecha_hora DESC"
        );
        $stmt->execute([$id_cliente]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchVehiculos(int $id_cliente): array
    {
        $stmt = $this->db->prepare(
            "SELECT id_vehiculo, matricula, modelo, marca, anio
             FROM VEHICULOS
             WHERE id_cliente = ?
             ORDER BY id_vehiculo ASC"
        );
        $stmt->execute([$id_cliente]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function responderPresupuesto(int $id_cliente): void
    {
        $data           = json_decode(file_get_contents('php://input'), true);
        $id_presupuesto = (int)($data['id_presupuesto'] ?? 0);
        $accion         = trim($data['accion'] ?? '');

        if (!$id_presupuesto || !in_array($accion, ['aceptar', 'rechazar'], true)) {
            http_response_code(400);
            echo json_encode(["message" => "id_presupuesto y accion (aceptar/rechazar) son obligatorios."]);
            return;
        }

        // Verificar que el presupuesto pertenece al cliente
        $stmt = $this->db->prepare(
            "SELECT p.id_presupuesto, p.estado, p.id_reparacion
             FROM PRESUPUESTOS p
             WHERE p.id_presupuesto = ?
               AND (
                   p.id_cliente = ?
                   OR p.id_reparacion IN (
                       SELECT r.id_reparacion FROM REPARACIONES r
                       JOIN VEHICULOS v ON r.matricula = v.matricula
                       WHERE v.id_cliente = ?
                   )
               )"
        );
        $stmt->execute([$id_presupuesto, $id_cliente, $id_cliente]);
        $pres = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pres) {
            http_response_code(403);
            echo json_encode(["message" => "No tienes acceso a este presupuesto."]);
            return;
        }

        if (in_array($pres['estado'], ['Aprobado', 'Rechazado'], true)) {
            http_response_code(409);
            echo json_encode(["message" => "Este presupuesto ya fue respondido."]);
            return;
        }

        $nuevoEstado = $accion === 'aceptar' ? 'Aprobado'    : 'Rechazado';
        $estadoRep   = $accion === 'aceptar' ? 'Aprobado'    : 'No Aprobado';

        $this->db->prepare("UPDATE PRESUPUESTOS SET estado = ? WHERE id_presupuesto = ?")
            ->execute([$nuevoEstado, $id_presupuesto]);

        if ($pres['id_reparacion']) {
            $this->db->prepare("UPDATE REPARACIONES SET estado_presupuesto = ? WHERE id_reparacion = ?")
                ->execute([$estadoRep, (int)$pres['id_reparacion']]);
        }

        http_response_code(200);
        echo json_encode(["message" => "Presupuesto " . ($accion === 'aceptar' ? 'aceptado' : 'rechazado') . " correctamente."]);
    }

    private function misPresupuestos(int $id_cliente): void
    {
        if (isset($_GET['id_reparacion'])) {
            $check = $this->db->prepare(
                "SELECT r.id_reparacion FROM REPARACIONES r
                 INNER JOIN VEHICULOS v ON r.matricula = v.matricula
                 WHERE r.id_reparacion = ? AND v.id_cliente = ?"
            );
            $check->execute([(int)$_GET['id_reparacion'], $id_cliente]);
            if ($check->rowCount() === 0) {
                http_response_code(403);
                echo json_encode(["message" => "No tienes acceso a este presupuesto."]);
                return;
            }
            $presupuesto = new Presupuesto($this->db);
            $presupuesto->id_reparacion = (int)$_GET['id_reparacion'];
            $stmt = $presupuesto->readByReparacion();
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(["message" => "Presupuesto no disponible todavía."]);
                return;
            }
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->streamPDF($row);
            return;
        }

        try {
            $stmt = $this->db->prepare(
                "SELECT p.id_presupuesto, p.fecha_emision, p.gran_total, p.estado,
                        COALESCE(
                            CONCAT(v.matricula, ' - ', v.modelo),
                            CONCAT(r.matricula, ' - ', r.modelo_auto)
                        ) AS vehiculo_str
                 FROM PRESUPUESTOS p
                 LEFT JOIN VEHICULOS v ON v.id_vehiculo = p.id_vehiculo
                 LEFT JOIN REPARACIONES r ON r.id_reparacion = p.id_reparacion
                 LEFT JOIN VEHICULOS vr ON vr.matricula = r.matricula
                 WHERE p.id_cliente = ?
                    OR vr.id_cliente = ?
                 ORDER BY p.fecha_emision DESC"
            );
            $stmt->execute([$id_cliente, $id_cliente]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            http_response_code(200);
            echo json_encode($rows, JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error al obtener presupuestos: " . $e->getMessage()]);
        }
    }

    private function latin(string $str): string
    {
        return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
    }

    private function streamPDF(array $row): void
    {
        require_once 'libs/fpdf.php';
        $pdf = new FPDF();
        $pdf->AddPage();

        $pdf->SetFont('Arial', 'B', 20);
        $pdf->SetTextColor(0, 98, 160);
        $pdf->Cell(0, 10, 'AutoSync', 0, 1, 'C');

        $pdf->SetFont('Arial', '', 12);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell(0, 8, $this->latin('Taller Mecánico de Precisión'), 0, 1, 'C');
        $pdf->Ln(10);

        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 10, 'PRESUPUESTO DE REPARACION', 0, 1, 'C');
        $pdf->Ln(10);

        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(50, 8, 'Fecha:', 0, 0);
        $pdf->Cell(0, 8, $row['fecha_emision'], 0, 1);

        $pdf->Cell(50, 8, $this->latin('Vehículo:'), 0, 0);
        $pdf->Cell(0, 8, $this->latin($row['modelo_auto'] . ' (' . $row['matricula'] . ')'), 0, 1);
        $pdf->Ln(10);

        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(140, 10, $this->latin('Descripción'), 1, 0, 'C', true);
        $pdf->Cell(50, 10, 'Importe', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(140, 10, 'Total Repuestos y Piezas', 1, 0, 'L');
        $pdf->Cell(50, 10, $this->latin('€ ' . number_format($row['total_piezas'], 2)), 1, 1, 'R');
        $pdf->Cell(140, 10, 'Total Mano de Obra', 1, 0, 'L');
        $pdf->Cell(50, 10, $this->latin('€ ' . number_format($row['total_mano_obra'], 2)), 1, 1, 'R');

        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(140, 10, 'GRAN TOTAL', 1, 0, 'R');
        $pdf->SetTextColor(0, 128, 0);
        $pdf->Cell(50, 10, $this->latin('€ ' . number_format($row['gran_total'], 2)), 1, 1, 'R');

        $pdf->Output('I', 'Presupuesto_' . $row['matricula'] . '.pdf');
        exit;
    }

    private function crearCita(int $id_cliente): void
    {
        $data       = json_decode(file_get_contents('php://input'), true);
        $fecha_hora = trim($data['fecha_hora'] ?? '');
        $motivo     = trim($data['motivo']     ?? '');
        $id_vehiculo = isset($data['id_vehiculo']) && $data['id_vehiculo'] ? (int)$data['id_vehiculo'] : null;

        if (!$fecha_hora || !$motivo) {
            http_response_code(400);
            echo json_encode(['message' => 'La fecha y el motivo son obligatorios.']);
            return;
        }

        // Verificar que el vehículo pertenece al cliente
        if ($id_vehiculo) {
            $sv = $this->db->prepare("SELECT id_vehiculo FROM VEHICULOS WHERE id_vehiculo = ? AND id_cliente = ?");
            $sv->execute([$id_vehiculo, $id_cliente]);
            if (!$sv->fetch()) {
                http_response_code(403);
                echo json_encode(['message' => 'Vehículo no pertenece a este cliente.']);
                return;
            }
        }

        $stmt = $this->db->prepare(
            "INSERT INTO CITAS (id_cliente, id_vehiculo, fecha_hora, motivo, estado, prioridad)
             VALUES (?, ?, ?, ?, 'Pendiente', 'Media')"
        );
        $stmt->execute([$id_cliente, $id_vehiculo, $fecha_hora, $motivo]);

        http_response_code(201);
        echo json_encode(['message' => 'Cita solicitada correctamente. El taller la confirmará pronto.', 'id_cita' => $this->db->lastInsertId()]);
    }

    private function cancelarCita(int $id_cliente): void
    {
        $data    = json_decode(file_get_contents('php://input'), true);
        $id_cita = (int)($data['id_cita'] ?? 0);

        $stmt = $this->db->prepare(
            "UPDATE CITAS SET estado = 'Cancelada'
             WHERE id_cita = ? AND id_cliente = ? AND estado IN ('Pendiente', 'Confirmada')"
        );
        $stmt->execute([$id_cita, $id_cliente]);

        if ($stmt->rowCount() === 0) {
            http_response_code(400);
            echo json_encode(['message' => 'No se puede cancelar esta cita.']);
            return;
        }
        echo json_encode(['message' => 'Cita cancelada.']);
    }

    private function agregarVehiculo(int $id_cliente): void
    {
        $data      = json_decode(file_get_contents('php://input'), true);
        $matricula = strtoupper(trim($data['matricula'] ?? ''));
        $marca     = trim($data['marca']     ?? '');
        $modelo    = trim($data['modelo']    ?? '');
        $anio      = isset($data['anio']) && $data['anio'] ? (int)$data['anio'] : null;

        if (!$matricula || !$modelo) {
            http_response_code(400);
            echo json_encode(['message' => 'Matrícula y modelo son obligatorios.']);
            return;
        }

        $check = $this->db->prepare("SELECT id_vehiculo FROM VEHICULOS WHERE matricula = ?");
        $check->execute([$matricula]);
        if ($check->fetch()) {
            http_response_code(409);
            echo json_encode(['message' => "La matrícula {$matricula} ya está registrada."]);
            return;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO VEHICULOS (id_cliente, matricula, marca, modelo, anio) VALUES (?,?,?,?,?)"
        );
        $stmt->execute([$id_cliente, $matricula, $marca, $modelo, $anio]);

        http_response_code(201);
        echo json_encode(['message' => 'Vehículo añadido correctamente.', 'id_vehiculo' => $this->db->lastInsertId()]);
    }
}
