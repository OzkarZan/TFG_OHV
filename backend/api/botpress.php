<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../config/config.php';
$database = new Database();
$db = $database->getConnection();
if (!$db) {
    exit();
}

action:
$action = isset($_GET['action']) ? $_GET['action'] : 'list_repuestos';

switch ($action) {
    case 'list_repuestos':
        $query = "SELECT id_repuesto, nombre_pieza, marca, stock_actual, stock_minimo, precio_unitario FROM REPUESTOS ORDER BY nombre_pieza";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['id_repuesto'] = (int)$row['id_repuesto'];
            $row['stock_actual'] = (int)$row['stock_actual'];
            $row['stock_minimo'] = (int)$row['stock_minimo'];
            $row['precio_unitario'] = (float)$row['precio_unitario'];
            $result[] = $row;
        }
        echo json_encode($result);
        break;

    case 'low_stock':
        $threshold = isset($_GET['threshold']) ? (int)$_GET['threshold'] : 5;
        $query = "SELECT id_repuesto, nombre_pieza, marca, stock_actual, stock_minimo, precio_unitario FROM REPUESTOS WHERE stock_actual <= :threshold ORDER BY stock_actual ASC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':threshold', $threshold, PDO::PARAM_INT);
        $stmt->execute();
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['id_repuesto'] = (int)$row['id_repuesto'];
            $row['stock_actual'] = (int)$row['stock_actual'];
            $row['stock_minimo'] = (int)$row['stock_minimo'];
            $row['precio_unitario'] = (float)$row['precio_unitario'];
            $result[] = $row;
        }
        echo json_encode($result);
        break;

    case 'search_repuesto':
        $term = isset($_GET['term']) ? trim($_GET['term']) : '';
        $query = "SELECT id_repuesto, nombre_pieza, marca, stock_actual, stock_minimo, precio_unitario FROM REPUESTOS WHERE nombre_pieza LIKE :term OR marca LIKE :term ORDER BY nombre_pieza";
        $stmt = $db->prepare($query);
        $searchTerm = '%' . $term . '%';
        $stmt->bindParam(':term', $searchTerm, PDO::PARAM_STR);
        $stmt->execute();
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['id_repuesto'] = (int)$row['id_repuesto'];
            $row['stock_actual'] = (int)$row['stock_actual'];
            $row['stock_minimo'] = (int)$row['stock_minimo'];
            $row['precio_unitario'] = (float)$row['precio_unitario'];
            $result[] = $row;
        }
        echo json_encode($result);
        break;

    case 'get_repuesto':
        if (empty($_GET['id'])) {
            http_response_code(400);
            echo json_encode(["message" => "Falta el parámetro id para get_repuesto."]);
            break;
        }
        $id = (int)$_GET['id'];
        $query = "SELECT id_repuesto, nombre_pieza, marca, stock_actual, stock_minimo, precio_unitario FROM REPUESTOS WHERE id_repuesto = :id LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['id_repuesto'] = (int)$row['id_repuesto'];
            $row['stock_actual'] = (int)$row['stock_actual'];
            $row['stock_minimo'] = (int)$row['stock_minimo'];
            $row['precio_unitario'] = (float)$row['precio_unitario'];
            echo json_encode($row);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Repuesto no encontrado."]);
        }
        break;

    case 'list_clientes':
        $query = "SELECT id_cliente, nombre, email FROM CLIENTES ORDER BY nombre";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['id_cliente'] = (int)$row['id_cliente'];
            $result[] = $row;
        }
        echo json_encode($result);
        break;

    case 'get_cliente':
        if (empty($_GET['email'])) {
            http_response_code(400);
            echo json_encode(["message" => "Falta el parámetro email para get_cliente."]);
            break;
        }
        $email = $_GET['email'];
        $query = "SELECT id_cliente, nombre, email FROM CLIENTES WHERE email = :email LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['id_cliente'] = (int)$row['id_cliente'];
            echo json_encode($row);
        } else {
            http_response_code(404);
            echo json_encode(["message" => "Cliente no encontrado."]);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(["message" => "Acción desconocida: " . $action]);
        break;
}
?>