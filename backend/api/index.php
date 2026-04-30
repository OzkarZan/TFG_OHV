<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuración de Sesiones
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Cambiar a 1 en producción con HTTPS
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', 1);
session_start();

include_once '../config/config.php';

$request_uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Parse URI
$parsed_uri = parse_url($request_uri);
$path = $parsed_uri['path'];

// Remove /api from path
$path = str_replace('/api/', '/', $path);

// Simple Router
if (preg_match('/^\/auth\/login$/', $path)) {
    require_once 'controllers/AuthController.php';
    $controller = new AuthController();
    $controller->login();
} elseif (preg_match('/^\/auth\/register$/', $path)) {
    require_once 'controllers/AuthController.php';
    $controller = new AuthController();
    $controller->register();
} elseif (preg_match('/^\/auth\/logout$/', $path)) {
    require_once 'controllers/AuthController.php';
    $controller = new AuthController();
    $controller->logout();
} elseif (preg_match('/^\/auth\/me$/', $path)) {
    require_once 'controllers/AuthController.php';
    $controller = new AuthController();
    $controller->me();
} elseif (preg_match('/^\/citas$/', $path)) {
    require_once 'controllers/CitaController.php';
    $controller = new CitaController();
    $controller->handleRequest($method);
} elseif (preg_match('/^\/reparaciones$/', $path)) {
    require_once 'controllers/ReparacionController.php';
    $controller = new ReparacionController();
    $controller->handleRequest($method);
} elseif (preg_match('/^\/presupuestos(\/generar_pdf)?$/', $path)) {
    require_once 'controllers/PresupuestoController.php';
    $controller = new PresupuestoController();
    $controller->handleRequest($method, $path);
} elseif (preg_match('/^\/clientes$/', $path)) {
    require_once 'controllers/ClienteController.php';
    $controller = new ClienteController();
    $controller->handleRequest($method);
} else {
    http_response_code(404);
    echo json_encode(["message" => "Endpoint no encontrado"]);
}
