<?php
require_once '../config/config.php';
require_once 'models/Reparacion.php';
require_once 'models/Cita.php';
require_once 'models/Vehiculo.php';
require_once 'models/Presupuesto.php';
require_once 'libs/fpdf.php';

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
        // Obtener id_cliente a partir del id_usuario en sesión
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

        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(["message" => "Método no permitido."]);
            return;
        }

        // GET /api/mi-area — devuelve reparaciones, citas y vehículos del cliente
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

    private function misPresupuestos(int $id_cliente): void
    {
        // Descarga PDF si se pasa ?id_reparacion, si no devuelve lista JSON
        if (isset($_GET['id_reparacion'])) {
            // Verificar que la reparación pertenece al cliente antes de generar el PDF
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

        $presupuesto = new Presupuesto($this->db);
        $stmt = $presupuesto->readByClienteId($id_cliente);
        http_response_code(200);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function latin(string $str): string
    {
        return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
    }

    private function streamPDF(array $row): void
    {
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
}
