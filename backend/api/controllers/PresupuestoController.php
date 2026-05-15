<?php
require_once '../config/config.php';
require_once 'models/Presupuesto.php';
require_once 'libs/fpdf.php';

class PresupuestoController {
    private $db;
    private $presupuesto;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->presupuesto = new Presupuesto($this->db);
    }

    private function requireAuth() {
        if (!isset($_SESSION['id_usuario'])) {
            http_response_code(401);
            echo json_encode(["message" => "No autorizado."]);
            exit;
        }
    }

    public function handleRequest($method, $path) {
        $this->requireAuth();

        if ($method === 'GET') {
            if (isset($_GET['id_presupuesto'])) {
                $this->descargarPDFById((int)$_GET['id_presupuesto']);
            } elseif (isset($_GET['id_reparacion'])) {
                $this->descargarPDF((int)$_GET['id_reparacion']);
            } else {
                $this->readAllFull();
            }
        } elseif ($method === 'POST') {
            $data = json_decode(file_get_contents("php://input"));
            if (!empty($data->standalone)) {
                $this->createStandalone($data);
            } else {
                $this->create($data);
            }
        } elseif ($method === 'DELETE') {
            $this->deletePresupuesto();
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Metodo no permitido"]);
        }
    }

    private function readAllFull() {
        try {
            $stmt = $this->presupuesto->readAllFull();
            $arr = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                array_push($arr, $row);
            }
            http_response_code(200);
            echo json_encode($arr);
        } catch (Exception $e) {
            http_response_code(503);
            echo json_encode(["message" => "Error al consultar presupuestos. Asegúrate de haber ejecutado la migración add_presupuestos_standalone.sql. Detalle: " . $e->getMessage()]);
        }
    }

    private function create($data = null) {
        if ($data === null) {
            $data = json_decode(file_get_contents("php://input"));
        }

        if (!empty($data->id_reparacion) && isset($data->total_piezas) && isset($data->total_mano_obra)) {
            $this->presupuesto->id_reparacion = $data->id_reparacion;
            $this->presupuesto->total_piezas = $data->total_piezas;
            $this->presupuesto->total_mano_obra = $data->total_mano_obra;
            $this->presupuesto->gran_total = $data->total_piezas + $data->total_mano_obra;
            $this->presupuesto->fecha_emision = date('Y-m-d');
            $this->presupuesto->estado = 'Borrador';

            if ($this->presupuesto->create()) {
                http_response_code(201);
                echo json_encode(["message" => "Presupuesto creado con exito."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo crear el presupuesto."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Datos incompletos."]);
        }
    }

    private function createStandalone($data) {
        if (empty($data->id_cliente) || empty($data->id_vehiculo)) {
            http_response_code(400);
            echo json_encode(["message" => "Se requiere id_cliente e id_vehiculo."]);
            return;
        }

        $lineas = isset($data->lineas) ? $data->lineas : [];

        // Compute totals from line items
        $total_piezas = 0;
        $total_mano_obra = 0;
        foreach ($lineas as $linea) {
            $importe = floatval($linea->cantidad ?? 1) * floatval($linea->precio_unitario ?? 0);
            if (($linea->tipo ?? '') === 'Repuesto') {
                $total_piezas += $importe;
            } else {
                $total_mano_obra += $importe;
            }
        }

        // Allow manual override of mano_obra if passed (for non-line-item labour)
        if (isset($data->total_mano_obra) && floatval($data->total_mano_obra) > 0 && $total_mano_obra == 0) {
            $total_mano_obra = floatval($data->total_mano_obra);
        }

        $servicios_terceros = floatval($data->servicios_terceros ?? 0);
        $gran_total = $total_piezas + $total_mano_obra + $servicios_terceros;

        $this->presupuesto->id_cliente         = intval($data->id_cliente);
        $this->presupuesto->id_vehiculo        = intval($data->id_vehiculo);
        $this->presupuesto->id_mecanico        = !empty($data->id_mecanico) ? intval($data->id_mecanico) : null;
        $this->presupuesto->km                 = !empty($data->km) ? intval($data->km) : null;
        $this->presupuesto->color              = isset($data->color) ? trim($data->color) : null;
        $this->presupuesto->total_piezas       = $total_piezas;
        $this->presupuesto->total_mano_obra    = $total_mano_obra;
        $this->presupuesto->servicios_terceros = $servicios_terceros;
        $this->presupuesto->gran_total         = $gran_total;
        $this->presupuesto->fecha_emision      = date('Y-m-d');
        $this->presupuesto->estado             = 'Borrador';
        $this->presupuesto->notas              = isset($data->notas) ? trim($data->notas) : null;

        if ($this->presupuesto->createStandalone()) {
            $id_presupuesto = $this->presupuesto->id_presupuesto;

            // Insert line items
            foreach ($lineas as $linea) {
                $tipo  = ($linea->tipo ?? 'Mano de Obra') === 'Repuesto' ? 'Repuesto' : 'Mano de Obra';
                $desc  = trim($linea->descripcion ?? '');
                $qty   = floatval($linea->cantidad ?? 1);
                $price = floatval($linea->precio_unitario ?? 0);
                if ($desc !== '') {
                    $this->presupuesto->addDetalle($id_presupuesto, $tipo, $desc, $qty, $price);
                }
            }

            http_response_code(201);
            echo json_encode([
                "message"        => "Presupuesto creado con exito.",
                "id_presupuesto" => $id_presupuesto
            ]);
        } else {
            http_response_code(503);
            echo json_encode(["message" => "No se pudo crear el presupuesto."]);
        }
    }

    private function deletePresupuesto() {
        $data = json_decode(file_get_contents("php://input"));
        if (empty($data->id_presupuesto)) {
            http_response_code(400);
            echo json_encode(["message" => "Se requiere id_presupuesto."]);
            return;
        }
        $id = intval($data->id_presupuesto);
        if ($this->presupuesto->deleteByPresupuesto($id)) {
            http_response_code(200);
            echo json_encode(["message" => "Presupuesto eliminado."]);
        } else {
            http_response_code(503);
            echo json_encode(["message" => "No se pudo eliminar el presupuesto."]);
        }
    }

    private function latin($str) {
        return mb_convert_encoding((string)$str, 'ISO-8859-1', 'UTF-8');
    }

    private function descargarPDFById($id_presupuesto) {
        try {
            $stmt = $this->presupuesto->readByIdFull($id_presupuesto);
        } catch (Exception $e) {
            http_response_code(503);
            echo json_encode(["message" => "Error al generar PDF: " . $e->getMessage()]);
            return;
        }

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(["message" => "Presupuesto no encontrado."]);
            return;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        try {
            $detalles_stmt = $this->presupuesto->readDetallesByPresupuesto($id_presupuesto);
            $detalles      = $detalles_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $detalles = [];
        }

        try {
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetMargins(15, 15, 15);   // must be BEFORE AddPage
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        // ── Header ──
        $pdf->SetFillColor(0, 62, 133);
        $pdf->Rect(0, 0, 210, 28, 'F');

        $pdf->SetY(8);
        $pdf->SetFont('Helvetica', 'B', 22);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 10, 'AUTOSYNC', 0, 1, 'C');

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(200, 220, 255);
        $pdf->Cell(0, 6, $this->latin('Taller Mecánico'), 0, 1, 'C');

        $pdf->SetY(32);
        $pdf->SetTextColor(0, 0, 0);

        // Presupuesto number (top right area)
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->SetTextColor(0, 62, 133);
        $pdf->Cell(0, 8, $this->latin('Presupuesto De Reparacion'), 0, 1, 'R');

        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetTextColor(50, 50, 50);

        // ── Client / Vehicle Info ──
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Ln(2);

        $fechaStr = $row['fecha_emision'] ? date('d/m/Y', strtotime($row['fecha_emision'])) : date('d/m/Y');
        $this->pdfInfoRow($pdf, 'Fecha:', $fechaStr);

        $clienteNombre = $row['cliente_nombre'] ?? 'Cliente';
        $clienteTel    = $row['cliente_telefono'] ?? '';
        $this->pdfTwoCol($pdf, 'Cliente:', $this->latin($clienteNombre), $this->latin('Telefono:'), $this->latin($clienteTel));

        $clienteDir = $row['cliente_direccion'] ?? '';
        $this->pdfInfoRow($pdf, $this->latin('Dirección:'), $this->latin($clienteDir));

        $vehMatricula = $row['veh_matricula'] ?? '';
        $vehMarca     = $row['veh_marca']     ?? '';
        $vehColor     = $row['color']         ?? '';
        $vehAnio      = $row['veh_anio']      ?? '';
        $vehModelo    = $row['veh_modelo']    ?? '';
        $kmStr        = $row['km']            !== null ? $row['km'] . ' km' : '';

        $this->pdfThreeCol($pdf,
            $this->latin('Vehículo:'), $this->latin($vehModelo),
            'Marca:', $this->latin($vehMarca),
            'Color:', $this->latin($vehColor)
        );
        $this->pdfThreeCol($pdf,
            $this->latin('Año:'), $this->latin((string)$vehAnio),
            $this->latin('Matrícula:'), $this->latin($vehMatricula),
            'KM:', $this->latin($kmStr)
        );

        $pdf->Ln(4);

        // ── Line items table header ──
        $pdf->SetFillColor(0, 62, 133);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(130, 8, $this->latin('SERVICIO / DESCRIPCIÓN'), 1, 0, 'C', true);
        $pdf->Cell(20, 8, 'Cant.', 1, 0, 'C', true);
        $pdf->Cell(20, 8, $this->latin('P.Unit'), 1, 0, 'C', true);
        $pdf->Cell(25, 8, 'Importe', 1, 1, 'C', true);

        // ── Line items rows ──
        $pdf->SetFont('Helvetica', '', 9);
        $fill = false;
        foreach ($detalles as $d) {
            $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
            $pdf->SetTextColor(30, 30, 30);
            $importe = floatval($d['cantidad']) * floatval($d['precio_unitario']);
            $tipoStr = $d['tipo_item'] === 'Repuesto' ? '[R]' : '[M]';
            $desc = $tipoStr . ' ' . $d['descripcion'];
            // Truncate long descriptions
            if (strlen($desc) > 65) $desc = substr($desc, 0, 62) . '...';
            $pdf->Cell(130, 7, $this->latin($desc), 1, 0, 'L', true);
            $pdf->Cell(20,  7, number_format(floatval($d['cantidad']), 2), 1, 0, 'C', true);
            $pdf->Cell(20,  7, $this->latin(number_format(floatval($d['precio_unitario']), 2) . ' EUR'), 1, 0, 'R', true);
            $pdf->Cell(25,  7, $this->latin(number_format($importe, 2) . ' EUR'), 1, 1, 'R', true);
            $fill = !$fill;
        }

        if (empty($detalles)) {
            $pdf->SetTextColor(120, 120, 120);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Cell(195, 7, $this->latin('Sin líneas de detalle'), 1, 1, 'C', true);
        }

        $pdf->Ln(3);

        // ── Summary ──
        $pageW = 195; // usable width
        $labelW = 80;
        $valW   = 35;
        $xStart = 15 + ($pageW - $labelW - $valW);

        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(50, 50, 50);

        $this->pdfSummaryRow($pdf, $xStart, $labelW, $valW, 'Material / Repuestos:', floatval($row['total_piezas']));
        $this->pdfSummaryRow($pdf, $xStart, $labelW, $valW, 'Mano de Obra:', floatval($row['total_mano_obra']));
        $this->pdfSummaryRow($pdf, $xStart, $labelW, $valW, 'Servicios Terceros:', floatval($row['servicios_terceros'] ?? 0));

        // Gran Total
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetFillColor(0, 62, 133);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetX($xStart);
        $pdf->Cell($labelW, 9, 'GRAN TOTAL:', 1, 0, 'R', true);
        $pdf->Cell($valW,   9, $this->latin(number_format(floatval($row['gran_total']), 2) . ' EUR'), 1, 1, 'R', true);

        // ── Notas ──
        if (!empty($row['notas'])) {
            $pdf->Ln(4);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->Cell(0, 6, 'Notas:', 0, 1, 'L');
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->MultiCell(0, 5, $this->latin($row['notas']), 1, 'L');
        }

        // ── Mechanic footer ──
        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(80, 80, 80);
        $mecNombre = $row['mecanico_nombre'] ?? '';
        $pdf->Cell(0, 6, $this->latin('Elaborado por: ' . ($mecNombre ?: 'Taller AutoSync')), 0, 1, 'L');

        $pdf->SetFont('Helvetica', 'I', 8);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 5, $this->latin('AutoSync - Taller Mecánico'), 0, 1, 'C');

        // Get PDF as raw bytes — FPDF does NOT set headers with 'S'
        $pdfContent = $pdf->Output('S');

        // Flush every output buffer level so nothing contaminates the binary stream
        while (ob_get_level()) ob_end_clean();
        header_remove();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="Presupuesto_' . $id_presupuesto . '.pdf"');
        header('Content-Length: ' . strlen($pdfContent));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        echo $pdfContent;
        exit;

        } catch (Exception $e) {
            while (ob_get_level()) ob_end_clean();
            header_remove();
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(["message" => "Error al generar PDF: " . $e->getMessage()]);
            exit;
        }
    }

    // Helper: single label+value row
    private function pdfInfoRow($pdf, $label, $value) {
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(40, 6, $label, 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 6, $value, 0, 1, 'L');
    }

    // Helper: two label+value pairs in one row
    private function pdfTwoCol($pdf, $l1, $v1, $l2, $v2) {
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(25, 6, $l1, 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(65, 6, $v1, 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(30, 6, $l2, 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 6, $v2, 0, 1, 'L');
    }

    // Helper: three label+value pairs in one row
    private function pdfThreeCol($pdf, $l1, $v1, $l2, $v2, $l3, $v3) {
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(20, 6, $l1, 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(40, 6, $v1, 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(20, 6, $l2, 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(40, 6, $v2, 0, 0, 'L');
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(15, 6, $l3, 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 6, $v3, 0, 1, 'L');
    }

    // Helper: right-aligned summary row
    private function pdfSummaryRow($pdf, $xStart, $labelW, $valW, $label, $amount) {
        $pdf->SetX($xStart);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell($labelW, 7, $this->latin($label), 1, 0, 'R');
        $pdf->Cell($valW,   7, $this->latin(number_format($amount, 2) . ' EUR'), 1, 1, 'R');
    }

    private function descargarPDF($id_reparacion) {
        $this->presupuesto->id_reparacion = $id_reparacion;
        $stmt = $this->presupuesto->readByReparacion();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Override Content-Type for PDF
            header('Content-Type: application/pdf');

            $pdf = new FPDF();
            $pdf->AddPage();

            $pdf->SetFont('Helvetica', 'B', 20);
            $pdf->SetTextColor(0, 98, 160);
            $pdf->Cell(0, 10, 'AutoSync', 0, 1, 'C');

            $pdf->SetFont('Helvetica', '', 12);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->Cell(0, 8, $this->latin('Taller Mecánico de Precisión'), 0, 1, 'C');
            $pdf->Ln(10);

            $pdf->SetFont('Helvetica', 'B', 16);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 10, 'PRESUPUESTO DE REPARACION', 0, 1, 'C');
            $pdf->Ln(10);

            $pdf->SetFont('Helvetica', '', 12);
            $pdf->Cell(50, 8, 'Fecha:', 0, 0);
            $pdf->Cell(0, 8, $row['fecha_emision'], 0, 1);

            $pdf->Cell(50, 8, 'Cliente:', 0, 0);
            $pdf->Cell(0, 8, $this->latin($row['cliente_nombre'] ?? 'Cliente General'), 0, 1);

            $pdf->Cell(50, 8, $this->latin('Vehículo:'), 0, 0);
            $pdf->Cell(0, 8, $this->latin($row['modelo_auto'] . ' (' . $row['matricula'] . ')'), 0, 1);
            $pdf->Ln(10);

            $pdf->SetFont('Helvetica', 'B', 12);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(140, 10, $this->latin('Descripción'), 1, 0, 'C', true);
            $pdf->Cell(50, 10, 'Importe', 1, 1, 'C', true);

            $pdf->SetFont('Helvetica', '', 12);
            $pdf->Cell(140, 10, 'Total Repuestos y Piezas', 1, 0, 'L');
            $pdf->Cell(50, 10, $this->latin('EUR ' . number_format($row['total_piezas'], 2)), 1, 1, 'R');

            $pdf->Cell(140, 10, 'Total Mano de Obra', 1, 0, 'L');
            $pdf->Cell(50, 10, $this->latin('EUR ' . number_format($row['total_mano_obra'], 2)), 1, 1, 'R');

            $pdf->SetFont('Helvetica', 'B', 14);
            $pdf->Cell(140, 10, 'GRAN TOTAL', 1, 0, 'R');
            $pdf->SetTextColor(0, 128, 0);
            $pdf->Cell(50, 10, $this->latin('EUR ' . number_format($row['gran_total'], 2)), 1, 1, 'R');

            $pdf->Output('I', 'Presupuesto_' . $row['matricula'] . '.pdf');
            exit;
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Presupuesto no encontrado para esta reparacion."]);
        }
    }
}
