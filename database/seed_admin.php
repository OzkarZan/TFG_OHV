<?php
/**
 * Seed del usuario administrador inicial.
 * Ejecutar una sola vez dentro del contenedor backend:
 *
 *   docker exec -it autosync_backend php /var/www/html/../../database/seed_admin.php
 *
 * O desde la raíz del proyecto con Docker Compose:
 *
 *   docker compose exec backend php /var/www/html/api/../../../database/seed_admin.php
 *
 * Cambia la contraseña tras el primer login.
 */

require_once __DIR__ . '/../backend/config/config.php';

$db = (new Database())->getConnection();

$email    = 'admin@autosync.local';
$nombre   = 'Administrador';
$password = 'Admin@AutoSync2024!';
$rol      = 'admin';

// Comprobar si ya existe
$check = $db->prepare("SELECT id_usuario FROM USUARIOS WHERE email = ?");
$check->execute([$email]);
if ($check->rowCount() > 0) {
    echo "El usuario admin ya existe ({$email}).\n";
    exit;
}

$hash = password_hash($password, PASSWORD_BCRYPT);
$stmt = $db->prepare(
    "INSERT INTO USUARIOS (email, password_hash, rol, nombre_completo) VALUES (?, ?, ?, ?)"
);
$stmt->execute([$email, $hash, $rol, $nombre]);

echo "Admin creado correctamente.\n";
echo "  Email:      {$email}\n";
echo "  Contraseña: {$password}\n";
echo "  CAMBIA LA CONTRASEÑA TRAS EL PRIMER LOGIN.\n";
