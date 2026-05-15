<?php
// router.php
// Enrutador para el servidor de desarrollo integrado de PHP (php -S localhost:3000 router.php)

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Si la solicitud comienza con /api/, redirigir al index.php del backend
if (strpos($uri, '/api/') === 0) {
    // El script index.php del backend se encarga de procesar la ruta
    $_SERVER['SCRIPT_NAME'] = '/backend/api/index.php';
    chdir(__DIR__ . '/backend/api');
    require_once __DIR__ . '/backend/api/index.php';
    return true;
}

// Si se solicita la raíz, servir el index.html del frontend
if ($uri === '/' || $uri === '/index.html') {
    require_once __DIR__ . '/frontend/index.html';
    return true;
}

// Si el archivo o directorio existe, servirlo directamente (CSS, JS, imágenes)
if (file_exists(__DIR__ . $uri) && is_file(__DIR__ . $uri)) {
    return false;
}

// Si no se encuentra, por defecto devuelve 404
return false;
