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

        if ($method === 'POST' && strpos($path, '/generar_pdf') !== false) {
            $this->generarPDF();
        } else if ($method === 'POST') {
            $this->create();
        } else if ($method === 'GET') {
            // Check if it's a specific PDF request
            if(isset($_GET['id_reparacion'])) {
                $this->descargarPDF($_GET['id_reparacion']);
            }
        } else {
            http_response_code(405);
            echo json_encode(["message" => "Método no permitido"]);
        }
    }

    private function create() {
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id_reparacion) && isset($data->total_piezas) && isset($data->total_mano_obra)) {
            $this->presupuesto->id_reparacion = $data->id_reparacion;
            $this->presupuesto->total_piezas = $data->total_piezas;
            $this->presupuesto->total_mano_obra = $data->total_mano_obra;
            $this->presupuesto->gran_total = $data->total_piezas + $data->total_mano_obra;
            $this->presupuesto->fecha_emision = date('Y-m-d');
            $this->presupuesto->estado = 'Borrador';

            if ($this->presupuesto->create()) {
                http_response_code(201);
                echo json_encode(["message" => "Presupuesto creado con éxito."]);
            } else {
                http_response_code(503);
                echo json_encode(["message" => "No se pudo crear el presupuesto."]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Datos incompletos."]);
        }
    }

    private function descargarPDF($id_reparacion) {
        $this->presupuesto->id_reparacion = $id_reparacion;
        $stmt = $this->presupuesto->readByReparacion();
        
        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Crear PDF
            $pdf = new FPDF();
            $pdf->AddPage();
            
            // Header
            $pdf->SetFont('Arial', 'B', 20);
            $pdf->SetTextColor(0, 98, 160); // AutoSync Blue
            $pdf->Cell(0, 10, 'AutoSync', 0, 1, 'C');
            
            $pdf->SetFont('Arial', '', 12);
            $pdf->SetTextColor(50, 50, 50);
            $pdf->Cell(0, 8, utf8_decode('Taller Mecánico de Precisión'), 0, 1, 'C');
            $pdf->Ln(10);

            // Título
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 10, 'PRESUPUESTO DE REPARACION', 0, 1, 'C');
            $pdf->Ln(10);

            // Datos
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(50, 8, 'Fecha:', 0, 0);
            $pdf->Cell(0, 8, $row['fecha_emision'], 0, 1);
            
            $pdf->Cell(50, 8, 'Cliente:', 0, 0);
            $pdf->Cell(0, 8, utf8_decode($row['cliente_nombre'] ?? 'Cliente General'), 0, 1);
            
            $pdf->Cell(50, 8, utf8_decode('Vehículo:'), 0, 0);
            $pdf->Cell(0, 8, utf8_decode($row['modelo_auto'] . ' (' . $row['matricula'] . ')'), 0, 1);
            
            $pdf->Ln(10);

            // Tabla de Costos
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell(140, 10, utf8_decode('Descripción'), 1, 0, 'C', true);
            $pdf->Cell(50, 10, 'Importe', 1, 1, 'C', true);

            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(140, 10, 'Total Repuestos y Piezas', 1, 0, 'L');
            $pdf->Cell(50, 10, utf8_decode('€ ' . number_format($row['total_piezas'], 2)), 1, 1, 'R');

            $pdf->Cell(140, 10, 'Total Mano de Obra', 1, 0, 'L');
            $pdf->Cell(50, 10, utf8_decode('€ ' . number_format($row['total_mano_obra'], 2)), 1, 1, 'R');

            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(140, 10, 'GRAN TOTAL', 1, 0, 'R');
            $pdf->SetTextColor(0, 128, 0);
            $pdf->Cell(50, 10, utf8_decode('€ ' . number_format($row['gran_total'], 2)), 1, 1, 'R');

            $pdf->Output('I', 'Presupuesto_' . $row['matricula'] . '.pdf');
            exit;
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Presupuesto no encontrado para esta reparación."]);
        }
    }
}
