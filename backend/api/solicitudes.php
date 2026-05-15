<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_start();

if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode(["message" => "No autorizado."]);
    exit;
}

include_once '../config/config.php';
$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$data = null;
if (in_array($method, ['POST', 'PUT'])) {
    $data = json_decode(file_get_contents("php://input"));
}

if ($method === 'GET') {
    $stmt = $db->prepare(
        "SELECT s.id_solicitud, s.id_repuesto, s.nombre_pieza, s.cantidad,
                s.estado, s.fecha_solicitud, s.notas,
                r.stock_actual
         FROM SOLICITUDES_PIEZAS s
         LEFT JOIN REPUESTOS r ON s.id_repuesto = r.id_repuesto
         ORDER BY FIELD(s.estado,'Pendiente','Pedido','Recibido'), s.fecha_solicitud DESC"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['id_solicitud']  = (int)$row['id_solicitud'];
        $row['id_repuesto']   = $row['id_repuesto'] ? (int)$row['id_repuesto'] : null;
        $row['cantidad']      = (float)$row['cantidad'];
        $row['stock_actual']  = $row['stock_actual'] !== null ? (int)$row['stock_actual'] : null;
    }
    http_response_code(200);
    echo json_encode($rows);

} elseif ($method === 'POST') {
    if (empty($data->nombre_pieza) || empty($data->cantidad)) {
        http_response_code(400);
        echo json_encode(["message" => "nombre_pieza y cantidad son requeridos."]);
        exit;
    }
    $id_repuesto = !empty($data->id_repuesto) ? intval($data->id_repuesto) : null;
    $notas       = $data->notas ?? null;
    $stmt = $db->prepare(
        "INSERT INTO SOLICITUDES_PIEZAS (id_repuesto, nombre_pieza, cantidad, estado, fecha_solicitud, notas)
         VALUES (:id_repuesto, :nombre_pieza, :cantidad, 'Pendiente', CURDATE(), :notas)"
    );
    $stmt->bindParam(':id_repuesto',  $id_repuesto);
    $stmt->bindParam(':nombre_pieza', $data->nombre_pieza);
    $stmt->bindParam(':cantidad',     $data->cantidad);
    $stmt->bindParam(':notas',        $notas);
    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["message" => "Solicitud creada.", "id_solicitud" => (int)$db->lastInsertId()]);
    } else {
        http_response_code(503);
        echo json_encode(["message" => "Error creando solicitud."]);
    }

} elseif ($method === 'PUT') {
    if (empty($data->id_solicitud) || empty($data->estado)) {
        http_response_code(400);
        echo json_encode(["message" => "id_solicitud y estado son requeridos."]);
        exit;
    }
    $valid = ['Pendiente', 'Pedido', 'Recibido'];
    if (!in_array($data->estado, $valid)) {
        http_response_code(400);
        echo json_encode(["message" => "Estado inválido."]);
        exit;
    }
    $stmt = $db->prepare("UPDATE SOLICITUDES_PIEZAS SET estado = :estado WHERE id_solicitud = :id");
    $stmt->bindParam(':estado', $data->estado);
    $stmt->bindParam(':id',     $data->id_solicitud);
    if ($stmt->execute()) {
        // When received, add quantity back to inventory stock
        if ($data->estado === 'Recibido' && !empty($data->id_repuesto) && !empty($data->cantidad)) {
            $db->prepare("UPDATE REPUESTOS SET stock_actual = stock_actual + ? WHERE id_repuesto = ?")
               ->execute([(float)$data->cantidad, (int)$data->id_repuesto]);
        }
        http_response_code(200);
        echo json_encode(["message" => "Solicitud actualizada."]);
    } else {
        http_response_code(503);
        echo json_encode(["message" => "Error actualizando solicitud."]);
    }

} else {
    http_response_code(405);
    echo json_encode(["message" => "Método no permitido."]);
}
?>
