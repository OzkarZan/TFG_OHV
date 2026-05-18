<?php
/**
 * Botpress Chatbot API - Gestionar Clientes
 * 
 * Acciones disponibles:
 * - listar: obtiene todos los clientes
 * - obtener: obtiene un cliente por email
 * 
 * Ejemplo:
 * http://autosynctfg.site/chatbot/gestionar_clientes.php?action=listar
 * http://autosynctfg.site/chatbot/gestionar_clientes.php?action=obtener&email=usuario@example.com
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
        handleListarClientes($db);
        break;

    case 'obtener':
        handleObtenerCliente($db);
        break;

    default:
        http_response_code(400);
        echo json_encode(["message" => "Acción desconocida: " . htmlspecialchars($action)]);
        break;
}

// ============================================
// FUNCIONES
// ============================================

function handleListarClientes($db) {
    $query = "SELECT id_cliente, nombre, email FROM CLIENTES ORDER BY nombre";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $clientes = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['id_cliente'] = (int)$row['id_cliente'];
        $clientes[] = $row;
    }
    
    http_response_code(200);
    echo json_encode($clientes);
}

function handleObtenerCliente($db) {
    if (empty($_GET['email'])) {
        http_response_code(400);
        echo json_encode(["message" => "Parámetro 'email' requerido."]);
        return;
    }

    $email = $_GET['email'];
    $query = "SELECT id_cliente, nombre, email FROM CLIENTES WHERE email = :email LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $row['id_cliente'] = (int)$row['id_cliente'];
        http_response_code(200);
        echo json_encode($row);
    } else {
        http_response_code(404);
        echo json_encode(["message" => "Cliente no encontrado."]);
    }
}
?>