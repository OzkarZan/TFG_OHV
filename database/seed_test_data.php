<?php
/**
 * Seed de datos de prueba: Inventario, Mecánicos y Clientes.
 *
 * Ejecutar desde la raíz del proyecto:
 *   docker compose exec backend php /var/www/html/../../../database/seed_test_data.php
 *
 * Es seguro ejecutarlo varias veces (usa INSERT IGNORE / verificación previa).
 * Contraseña de todos los usuarios de prueba: Test1234!
 */

require_once __DIR__ . '/../backend/config/config.php';
$db = (new Database())->getConnection();

$PASSWORD = password_hash('Test1234!', PASSWORD_BCRYPT);

// ─────────────────────────────────────────────────────────────
// 1. TALLER (necesario para mecánicos)
// ─────────────────────────────────────────────────────────────
$db->exec("INSERT IGNORE INTO TALLER (id_taller, nombre, cif, direccion, telefono, horario)
           VALUES (1, 'AutoSync Taller Mecánico', 'B12345678',
                   'Calle Industria 42, 28001 Madrid', '910123456',
                   'Lunes-Viernes 08:00-18:00')");
echo "✓ Taller creado / ya existía\n";

// ─────────────────────────────────────────────────────────────
// 2. MECÁNICOS (3)
// ─────────────────────────────────────────────────────────────
$mecanicos = [
    ['carlos.garcia@autosync.local',  'Carlos García Ruiz',      'Motor y Transmisión'],
    ['laura.martinez@autosync.local', 'Laura Martínez López',    'Electrónica y Diagnóstico'],
    ['miguel.sanchez@autosync.local', 'Miguel Sánchez Torres',   'Carrocería y Pintura'],
];

$mecanicoIds = [];
foreach ($mecanicos as [$email, $nombre, $especialidad]) {
    $check = $db->prepare("SELECT id_usuario FROM USUARIOS WHERE email = ?");
    $check->execute([$email]);
    if ($check->rowCount() > 0) {
        $id_usuario = (int)$check->fetchColumn();
        echo "  – Mecánico ya existe: $nombre\n";
    } else {
        $db->prepare("INSERT INTO USUARIOS (email, password_hash, rol, nombre_completo) VALUES (?,?,'mecanico',?)")
           ->execute([$email, $PASSWORD, $nombre]);
        $id_usuario = (int)$db->lastInsertId();
    }
    // Crear perfil mecánico si no existe
    $check2 = $db->prepare("SELECT id_mecanico FROM MECANICOS WHERE id_usuario = ?");
    $check2->execute([$id_usuario]);
    if ($check2->rowCount() === 0) {
        $db->prepare("INSERT INTO MECANICOS (id_usuario, especialidad, id_taller) VALUES (?,?,1)")
           ->execute([$id_usuario, $especialidad]);
    }
    $m = $db->prepare("SELECT id_mecanico FROM MECANICOS WHERE id_usuario = ?");
    $m->execute([$id_usuario]);
    $mecanicoIds[] = (int)$m->fetchColumn();
}
echo "✓ " . count($mecanicoIds) . " mecánicos listos (IDs: " . implode(', ', $mecanicoIds) . ")\n";

// Horarios L-V 08-18, S 09-14
$horarios = [
    [1,'08:00:00','18:00:00'],[2,'08:00:00','18:00:00'],[3,'08:00:00','18:00:00'],
    [4,'08:00:00','18:00:00'],[5,'08:00:00','18:00:00'],[6,'09:00:00','14:00:00'],
];
foreach ($mecanicoIds as $idMec) {
    foreach ($horarios as [$dia, $ini, $fin]) {
        $db->prepare("INSERT IGNORE INTO MECANICO_HORARIOS (id_mecanico,dia_semana,hora_inicio,hora_fin)
                      VALUES (?,?,?,?)")->execute([$idMec, $dia, $ini, $fin]);
    }
}
echo "✓ Horarios asignados\n";

// ─────────────────────────────────────────────────────────────
// 3. CLIENTES (5) con vehículos
// ─────────────────────────────────────────────────────────────
$clientes = [
    [
        'email'    => 'ana.fernandez@mail.com',
        'nombre'   => 'Ana Fernández Gómez',
        'telefono' => '611 234 567',
        'direccion'=> 'Calle Mayor 15, 28001 Madrid',
        'vehiculos'=> [
            ['3456ABC', 'Serie 3',  'BMW',        2020],
            ['7890DEF', 'Focus',    'Ford',        2018],
        ],
    ],
    [
        'email'    => 'roberto.perez@mail.com',
        'nombre'   => 'Roberto Pérez García',
        'telefono' => '622 345 678',
        'direccion'=> 'Avda. Diagonal 200, 08013 Barcelona',
        'vehiculos'=> [
            ['1122GHI', 'Corolla',  'Toyota',      2021],
        ],
    ],
    [
        'email'    => 'maria.lopez@mail.com',
        'nombre'   => 'María López Hernández',
        'telefono' => '633 456 789',
        'direccion'=> 'Calle Colón 8, 46004 Valencia',
        'vehiculos'=> [
            ['5566JKL', 'Golf VII', 'Volkswagen',  2019],
            ['9900MNO', 'Polo',     'Volkswagen',  2022],
        ],
    ],
    [
        'email'    => 'javier.torres@mail.com',
        'nombre'   => 'Javier Torres Ruiz',
        'telefono' => '644 567 890',
        'direccion'=> 'Calle Sierpes 34, 41001 Sevilla',
        'vehiculos'=> [
            ['3344PQR', 'A4',       'Audi',        2022],
        ],
    ],
    [
        'email'    => 'elena.castro@mail.com',
        'nombre'   => 'Elena Castro Moreno',
        'telefono' => '655 678 901',
        'direccion'=> 'Gran Vía 10, 48001 Bilbao',
        'vehiculos'=> [
            ['7788STU', 'León',     'Seat',        2017],
            ['2233VWX', 'Mégane',   'Renault',     2020],
        ],
    ],
];

$clienteCount = 0;
$vehiculoCount = 0;
foreach ($clientes as $c) {
    $check = $db->prepare("SELECT id_usuario FROM USUARIOS WHERE email = ?");
    $check->execute([$c['email']]);
    if ($check->rowCount() > 0) {
        $id_usuario = (int)$check->fetchColumn();
        echo "  – Cliente ya existe: {$c['nombre']}\n";
    } else {
        $db->prepare("INSERT INTO USUARIOS (email, password_hash, rol, nombre_completo) VALUES (?,?,'cliente',?)")
           ->execute([$c['email'], $PASSWORD, $c['nombre']]);
        $id_usuario = (int)$db->lastInsertId();
        $clienteCount++;
    }
    // Perfil cliente
    $check2 = $db->prepare("SELECT id_cliente FROM CLIENTES WHERE id_usuario = ?");
    $check2->execute([$id_usuario]);
    if ($check2->rowCount() === 0) {
        $db->prepare("INSERT INTO CLIENTES (id_usuario, telefono, direccion) VALUES (?,?,?)")
           ->execute([$id_usuario, $c['telefono'], $c['direccion']]);
    }
    $cl = $db->prepare("SELECT id_cliente FROM CLIENTES WHERE id_usuario = ?");
    $cl->execute([$id_usuario]);
    $id_cliente = (int)$cl->fetchColumn();

    // Vehículos
    foreach ($c['vehiculos'] as [$mat, $modelo, $marca, $anio]) {
        $vCheck = $db->prepare("SELECT id_vehiculo FROM VEHICULOS WHERE matricula = ?");
        $vCheck->execute([$mat]);
        if ($vCheck->rowCount() === 0) {
            $db->prepare("INSERT INTO VEHICULOS (matricula, modelo, marca, anio, id_cliente) VALUES (?,?,?,?,?)")
               ->execute([$mat, $modelo, $marca, $anio, $id_cliente]);
            $vehiculoCount++;
        }
    }
}
echo "✓ Clientes: $clienteCount nuevos | Vehículos: $vehiculoCount nuevos\n";

// ─────────────────────────────────────────────────────────────
// 4. REPUESTOS / INVENTARIO (18 piezas)
// ─────────────────────────────────────────────────────────────
$repuestos = [
    // [nombre_pieza,               marca,        stock_actual, stock_minimo, precio_unitario]
    ['Filtro de aceite',             'Mann',              12,    5,    8.50],
    ['Filtro de aire',               'Bosch',              8,    5,   12.90],
    ['Filtro de habitáculo',         'Mahle',              6,    5,    9.75],
    ['Pastillas de freno delanteras','Brembo',             4,    4,   38.00],
    ['Pastillas de freno traseras',  'Brembo',             3,    4,   32.00],
    ['Disco de freno delantero',     'Textar',             2,    2,   65.00],
    ['Correa de distribución',       'Gates',              5,    3,   45.00],
    ['Kit tensor correa distribución','Gates',             2,    2,   28.00],
    ['Aceite motor 5W-30 (5L)',      'Castrol',           20,   10,   32.00],
    ['Líquido refrigerante (1L)',    'Total',             10,    5,    6.50],
    ['Líquido de frenos DOT 4',      'ATE',                7,    5,    8.00],
    ['Bujía de encendido',           'NGK',               16,    8,    4.50],
    ['Bobina de encendido',          'Bosch',              0,    2,   55.00],
    ['Amortiguador delantero',       'Monroe',             0,    2,  120.00],
    ['Sensor de temperatura',        'Hella',              1,    2,   22.00],
    ['Correa de accesorios',         'Continental',        3,    2,   18.50],
    ['Batería 60Ah',                 'Varta',              2,    2,  105.00],
    ['Lámpara H7 (par)',             'Philips',            8,    5,    9.90],
];

$repCount = 0;
foreach ($repuestos as [$nombre, $marca, $stock, $minimo, $precio]) {
    $check = $db->prepare("SELECT id_repuesto FROM REPUESTOS WHERE nombre_pieza = ? AND marca = ?");
    $check->execute([$nombre, $marca]);
    if ($check->rowCount() === 0) {
        $db->prepare("INSERT INTO REPUESTOS (nombre_pieza, marca, stock_actual, stock_minimo, precio_unitario)
                      VALUES (?,?,?,?,?)")->execute([$nombre, $marca, $stock, $minimo, $precio]);
        $repCount++;
    }
}
echo "✓ Repuestos: $repCount nuevos en inventario\n";

// ─────────────────────────────────────────────────────────────
echo "\n════════════════════════════════════════\n";
echo "Seed completado.\n";
echo "Contraseña de todos los usuarios: Test1234!\n";
echo "════════════════════════════════════════\n\n";
echo "Mecánicos creados:\n";
foreach ($mecanicos as [$email, $nombre, $esp]) {
    echo "  $nombre ($email) — $esp\n";
}
echo "\nClientes creados:\n";
foreach ($clientes as $c) {
    $vList = implode(', ', array_map(fn($v) => "{$v[2]} {$v[1]} ({$v[0]})", $c['vehiculos']));
    echo "  {$c['nombre']} ({$c['email']}) — $vList\n";
}
