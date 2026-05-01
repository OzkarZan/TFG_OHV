<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$google_config = require __DIR__ . '/../config/google-config.php';

echo json_encode([
    'client_id' => $google_config['client_id']
]);
?>