<?php
/**
 * Botpress Chatbot API - Gestionar Repuestos
 * 
 * Acciones disponibles:
 * - listar: obtiene todos los repuestos
 * - buscar: busca repuestos por nombre o marca
 * - bajo_stock: obtiene repuestos con stock bajo
 * - obtener: obtiene un repuesto por ID
 * 
 * Ejemplo:
 * http://autosynctfg.site/chatbot/gestionar_repuestos.php?action=listar
 * http://autosynctfg.site/chatbot/gestionar_repuestos.php?action=buscar&term=freno
 * http://autosynctfg.site/chatbot/gestionar_repuestos.php?action=bajo_stock&threshold=5
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Incluir configuración de base de datos
include_once __DIR__ . '/../backend/config/config.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(["message" => "Error de conexión a la base de datos."]);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : 'listar';

switch ($action) {
    case 'listar':
        handleListarRepuestos($db);
        break;

    case 'buscar':
        handleBuscarRepuestos($db);
        break;

    case 'bajo_stock':
        handleBajoStock($db);
        break;

    case 'obtener':
        handleObtenerRepuesto($db);
        break;

    default:
        http_response_code(400);
        echo json_encode(["message" => "Acción desconocida: " . htmlspecialchars($action)]);
        break;
}

// ============================================
// FUNCIONES
// ============================================

function handleListarRepuestos($db) {
    $query = "SELECT id_repuesto, nombre_pieza, marca, stock_actual, stock_minimo, precio_unitario 
              FROM REPUESTOS ORDER BY nombre_pieza";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $repuestos = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['id_repuesto'] = (int)$row['id_repuesto'];
        $row['stock_actual'] = (int)$row['stock_actual'];
        $row['stock_minimo'] = (int)$row['stock_minimo'];
        $row['precio_unitario'] = (float)$row['precio_unitario'];
        $repuestos[] = $row;
    }
    
    http_response_code(200);
    echo json_encode($repuestos);
}

function handleBuscarRepuestos($db) {
    $term = isset($_GET['term']) ? trim($_GET['term']) : '';
    
    if (empty($term)) {
        http_response_code(400);
        echo json_encode(["message" => "Parámetro 'term' requerido."]);
        return;
    }

    $query = "SELECT id_repuesto, nombre_pieza, marca, stock_actual, stock_minimo, precio_unitario 
              FROM REPUESTOS 
              WHERE nombre_pieza LIKE :term OR marca LIKE :term 
              ORDER BY nombre_pieza";
    $stmt = $db->prepare($query);
    $searchTerm = '%' . $term . '%';
    $stmt->bindParam(':term', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();
    
    $repuestos = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['id_repuesto'] = (int)$row['id_repuesto'];
        $row['stock_actual'] = (int)$row['stock_actual'];
        $row['stock_minimo'] = (int)$row['stock_minimo'];
        $row['precio_unitario'] = (float)$row['precio_unitario'];
        $repuestos[] = $row;
    }
    
    http_response_code(200);
    echo json_encode($repuestos);
}

function handleBajoStock($db) {
    $threshold = isset($_GET['threshold']) ? (int)$_GET['threshold'] : 5;

    $query = "SELECT id_repuesto, nombre_pieza, marca, stock_actual, stock_minimo, precio_unitario 
              FROM REPUESTOS 
              WHERE stock_actual <= :threshold 
              ORDER BY stock_actual ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':threshold', $threshold, PDO::PARAM_INT);
    $stmt->execute();
    
    $repuestos = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['id_repuesto'] = (int)$row['id_repuesto'];
        $row['stock_actual'] = (int)$row['stock_actual'];
        $row['stock_minimo'] = (int)$row['stock_minimo'];
        $row['precio_unitario'] = (float)$row['precio_unitario'];
        $repuestos[] = $row;
    }
    
    http_response_code(200);
    echo json_encode($repuestos);
}

function handleObtenerRepuesto($db) {
    if (empty($_GET['id'])) {
        http_response_code(400);
        echo json_encode(["message" => "Parámetro 'id' requerido."]);
        return;
    }

    $id = (int)$_GET['id'];
    $query = "SELECT id_repuesto, nombre_pieza, marca, stock_actual, stock_minimo, precio_unitario 
              FROM REPUESTOS 
              WHERE id_repuesto = :id 
              LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $row['id_repuesto'] = (int)$row['id_repuesto'];
        $row['stock_actual'] = (int)$row['stock_actual'];
        $row['stock_minimo'] = (int)$row['stock_minimo'];
        $row['precio_unitario'] = (float)$row['precio_unitario'];
        http_response_code(200);
        echo json_encode($row);
    } else {
        http_response_code(404);
        echo json_encode(["message" => "Repuesto no encontrado."]);
    }
}
?>