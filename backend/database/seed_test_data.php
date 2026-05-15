<?php
/**
 * Seed de datos de prueba completo.
 *
 * Ejecutar desde el contenedor backend:
 *   docker compose exec -T backend php /var/www/html/database/seed_test_data.php
 *
 * Seguro de re-ejecutar: usa INSERT IGNORE / comprobaciones previas.
 * Contraseña de todos los usuarios de prueba: Test1234!
 */

require_once __DIR__ . '/../config/config.php';
$db = (new Database())->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$PASSWORD = password_hash('Test1234!', PASSWORD_BCRYPT);

// ─────────────────────────────────────────────────────────────
// 1. TALLER
// ─────────────────────────────────────────────────────────────
$db->exec("INSERT IGNORE INTO TALLER (id_taller, nombre, cif, direccion, telefono, horario)
           VALUES (1, 'AutoSync Taller Mecánico', 'B12345678',
                   'Calle Industria 42, 28001 Madrid', '910123456',
                   'Lunes-Viernes 08:00-18:00')");
echo "✓ Taller\n";

// ─────────────────────────────────────────────────────────────
// 2. MECÁNICOS
// ─────────────────────────────────────────────────────────────
$mecDefs = [
    ['carlos.garcia@autosync.local',  'Carlos García Ruiz',    'Motor y Transmisión'],
    ['laura.martinez@autosync.local', 'Laura Martínez López',  'Electrónica y Diagnóstico'],
    ['miguel.sanchez@autosync.local', 'Miguel Sánchez Torres', 'Carrocería y Pintura'],
];
$mecIds = []; // [carlos, laura, miguel]
foreach ($mecDefs as [$email, $nombre, $esp]) {
    $s = $db->prepare("SELECT id_usuario FROM USUARIOS WHERE email = ?");
    $s->execute([$email]);
    if ($s->rowCount() > 0) {
        $uid = (int)$s->fetchColumn();
    } else {
        $db->prepare("INSERT INTO USUARIOS (email,password_hash,rol,nombre_completo) VALUES (?,?,'mecanico',?)")
           ->execute([$email, $PASSWORD, $nombre]);
        $uid = (int)$db->lastInsertId();
    }
    $s2 = $db->prepare("SELECT id_mecanico FROM MECANICOS WHERE id_usuario = ?");
    $s2->execute([$uid]);
    if ($s2->rowCount() === 0) {
        $db->prepare("INSERT INTO MECANICOS (id_usuario,especialidad,id_taller) VALUES (?,?,1)")
           ->execute([$uid, $esp]);
    }
    $s3 = $db->prepare("SELECT id_mecanico FROM MECANICOS WHERE id_usuario = ?");
    $s3->execute([$uid]);
    $mecIds[] = (int)$s3->fetchColumn();
}
echo "✓ Mecánicos (IDs: " . implode(', ', $mecIds) . ")\n";

$horarios = [
    [1,'08:00:00','18:00:00'],[2,'08:00:00','18:00:00'],[3,'08:00:00','18:00:00'],
    [4,'08:00:00','18:00:00'],[5,'08:00:00','18:00:00'],[6,'09:00:00','14:00:00'],
];
foreach ($mecIds as $mid) {
    foreach ($horarios as [$d, $i, $f]) {
        $db->prepare("INSERT IGNORE INTO MECANICO_HORARIOS (id_mecanico,dia_semana,hora_inicio,hora_fin) VALUES (?,?,?,?)")
           ->execute([$mid, $d, $i, $f]);
    }
}
echo "✓ Horarios asignados\n";

[$mec_carlos, $mec_laura, $mec_miguel] = $mecIds;

// ─────────────────────────────────────────────────────────────
// 3. CLIENTES + VEHÍCULOS
// ─────────────────────────────────────────────────────────────
$clienteDefs = [
    [
        'email'=>'ana.fernandez@mail.com', 'nombre'=>'Ana Fernández Gómez',
        'tel'=>'611 234 567', 'dir'=>'Calle Mayor 15, 28001 Madrid',
        'vehiculos'=>[['3456ABC','Serie 3','BMW',2020],['7890DEF','Focus','Ford',2018]],
    ],
    [
        'email'=>'roberto.perez@mail.com', 'nombre'=>'Roberto Pérez García',
        'tel'=>'622 345 678', 'dir'=>'Avda. Diagonal 200, 08013 Barcelona',
        'vehiculos'=>[['1122GHI','Corolla','Toyota',2021]],
    ],
    [
        'email'=>'maria.lopez@mail.com', 'nombre'=>'María López Hernández',
        'tel'=>'633 456 789', 'dir'=>'Calle Colón 8, 46004 Valencia',
        'vehiculos'=>[['5566JKL','Golf VII','Volkswagen',2019],['9900MNO','Polo','Volkswagen',2022]],
    ],
    [
        'email'=>'javier.torres@mail.com', 'nombre'=>'Javier Torres Ruiz',
        'tel'=>'644 567 890', 'dir'=>'Calle Sierpes 34, 41001 Sevilla',
        'vehiculos'=>[['3344PQR','A4','Audi',2022]],
    ],
    [
        'email'=>'elena.castro@mail.com', 'nombre'=>'Elena Castro Moreno',
        'tel'=>'655 678 901', 'dir'=>'Gran Vía 10, 48001 Bilbao',
        'vehiculos'=>[['7788STU','León','Seat',2017],['2233VWX','Mégane','Renault',2020]],
    ],
];

$clMap = []; // email => id_cliente
$vMap  = []; // matricula => id_vehiculo

foreach ($clienteDefs as $c) {
    $s = $db->prepare("SELECT id_usuario FROM USUARIOS WHERE email = ?");
    $s->execute([$c['email']]);
    if ($s->rowCount() > 0) {
        $uid = (int)$s->fetchColumn();
    } else {
        $db->prepare("INSERT INTO USUARIOS (email,password_hash,rol,nombre_completo) VALUES (?,?,'cliente',?)")
           ->execute([$c['email'], $PASSWORD, $c['nombre']]);
        $uid = (int)$db->lastInsertId();
    }
    $s2 = $db->prepare("SELECT id_cliente FROM CLIENTES WHERE id_usuario = ?");
    $s2->execute([$uid]);
    if ($s2->rowCount() === 0) {
        $db->prepare("INSERT INTO CLIENTES (id_usuario,telefono,direccion) VALUES (?,?,?)")
           ->execute([$uid, $c['tel'], $c['dir']]);
    }
    $s3 = $db->prepare("SELECT id_cliente FROM CLIENTES WHERE id_usuario = ?");
    $s3->execute([$uid]);
    $cid = (int)$s3->fetchColumn();
    $clMap[$c['email']] = $cid;

    foreach ($c['vehiculos'] as [$mat, $modelo, $marca, $anio]) {
        $sv = $db->prepare("SELECT id_vehiculo FROM VEHICULOS WHERE matricula = ?");
        $sv->execute([$mat]);
        if ($sv->rowCount() === 0) {
            $db->prepare("INSERT INTO VEHICULOS (matricula,modelo,marca,anio,id_cliente) VALUES (?,?,?,?,?)")
               ->execute([$mat, $modelo, $marca, $anio, $cid]);
        }
        $sv2 = $db->prepare("SELECT id_vehiculo FROM VEHICULOS WHERE matricula = ?");
        $sv2->execute([$mat]);
        $vMap[$mat] = (int)$sv2->fetchColumn();
    }
}
echo "✓ Clientes + Vehículos\n";

// Aliases cómodos
$cl_ana     = $clMap['ana.fernandez@mail.com'];
$cl_roberto = $clMap['roberto.perez@mail.com'];
$cl_maria   = $clMap['maria.lopez@mail.com'];
$cl_javier  = $clMap['javier.torres@mail.com'];
$cl_elena   = $clMap['elena.castro@mail.com'];

// ─────────────────────────────────────────────────────────────
// 4. REPUESTOS / INVENTARIO
// ─────────────────────────────────────────────────────────────
$repuestosDefs = [
    ['Filtro de aceite',               'Mann',        12,  5,   8.50],
    ['Filtro de aire',                 'Bosch',        8,  5,  12.90],
    ['Filtro de habitáculo',           'Mahle',        6,  5,   9.75],
    ['Pastillas de freno delanteras',  'Brembo',       4,  4,  38.00],
    ['Pastillas de freno traseras',    'Brembo',       3,  4,  32.00],
    ['Disco de freno delantero',       'Textar',       2,  2,  65.00],
    ['Correa de distribución',         'Gates',        5,  3,  45.00],
    ['Kit tensor correa distribución', 'Gates',        2,  2,  28.00],
    ['Aceite motor 5W-30 (5L)',        'Castrol',     20, 10,  32.00],
    ['Líquido refrigerante (1L)',      'Total',       10,  5,   6.50],
    ['Líquido de frenos DOT 4',        'ATE',          7,  5,   8.00],
    ['Bujía de encendido',             'NGK',         16,  8,   4.50],
    ['Bobina de encendido',            'Bosch',        0,  2,  55.00],
    ['Amortiguador delantero',         'Monroe',       0,  2, 120.00],
    ['Sensor de temperatura',          'Hella',        1,  2,  22.00],
    ['Correa de accesorios',           'Continental',  3,  2,  18.50],
    ['Batería 60Ah',                   'Varta',        2,  2, 105.00],
    ['Lámpara H7 (par)',               'Philips',      8,  5,   9.90],
];

$repMap = []; // nombre_pieza => id_repuesto
foreach ($repuestosDefs as [$nombre, $marca, $stock, $min, $precio]) {
    $s = $db->prepare("SELECT id_repuesto FROM REPUESTOS WHERE nombre_pieza = ? AND marca = ?");
    $s->execute([$nombre, $marca]);
    if ($s->rowCount() === 0) {
        $db->prepare("INSERT INTO REPUESTOS (nombre_pieza,marca,stock_actual,stock_minimo,precio_unitario) VALUES (?,?,?,?,?)")
           ->execute([$nombre, $marca, $stock, $min, $precio]);
    }
    $s2 = $db->prepare("SELECT id_repuesto FROM REPUESTOS WHERE nombre_pieza = ? AND marca = ?");
    $s2->execute([$nombre, $marca]);
    $repMap[$nombre] = (int)$s2->fetchColumn();
}
echo "✓ Repuestos (" . count($repMap) . " piezas)\n";

// ─────────────────────────────────────────────────────────────
// Helpers de fecha
// ─────────────────────────────────────────────────────────────
function dt(int $offsetDays, string $time): string {
    return date('Y-m-d', strtotime("$offsetDays days")) . ' ' . $time;
}

// Helper: busca o inserta una cita; retorna id_cita
function upsertCita(PDO $db, array $data): int {
    $s = $db->prepare("SELECT id_cita FROM CITAS WHERE id_cliente=? AND id_vehiculo=? AND motivo=?");
    $s->execute([$data['id_cliente'], $data['id_vehiculo'], $data['motivo']]);
    if ($s->rowCount() > 0) return (int)$s->fetchColumn();

    $db->prepare("INSERT INTO CITAS (fecha_hora,motivo,estado,prioridad,es_emergencia,id_cliente,id_vehiculo,id_taller,id_mecanico)
                  VALUES (?,?,?,?,?,?,?,1,?)")
       ->execute([
           $data['fecha_hora'], $data['motivo'], $data['estado'],
           $data['prioridad'], $data['emergencia'] ? 1 : 0,
           $data['id_cliente'], $data['id_vehiculo'], $data['id_mecanico'],
       ]);
    return (int)$db->lastInsertId();
}

// Helper: busca o inserta una reparación; retorna id_reparacion
function upsertReparacion(PDO $db, array $data): int {
    $s = $db->prepare("SELECT id_reparacion FROM REPARACIONES WHERE matricula=? AND descripcion_motivo=?");
    $s->execute([$data['matricula'], $data['descripcion']]);
    if ($s->rowCount() > 0) return (int)$s->fetchColumn();

    $db->prepare("INSERT INTO REPARACIONES
                  (id_cita,modelo_auto,matricula,descripcion_motivo,diagnostico,fecha_entrada,fecha_salida,km_entrada,estado_presupuesto,estado,precio_final)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([
           $data['id_cita'], $data['modelo'], $data['matricula'],
           $data['descripcion'], $data['diagnostico'],
           $data['fecha_entrada'], $data['fecha_salida'],
           $data['km'], $data['estado_presupuesto'], $data['estado'], $data['precio_final'],
       ]);
    return (int)$db->lastInsertId();
}

// Helper: busca o inserta presupuesto; retorna id_presupuesto
function upsertPresupuesto(PDO $db, array $data): int {
    if ($data['id_reparacion']) {
        $s = $db->prepare("SELECT id_presupuesto FROM PRESUPUESTOS WHERE id_reparacion=?");
        $s->execute([$data['id_reparacion']]);
        if ($s->rowCount() > 0) return (int)$s->fetchColumn();
    }
    $db->prepare("INSERT INTO PRESUPUESTOS
                  (id_reparacion,id_mecanico,id_cliente,id_vehiculo,km,color,total_piezas,total_mano_obra,servicios_terceros,gran_total,fecha_emision,estado,notas)
                  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
       ->execute([
           $data['id_reparacion'], $data['id_mecanico'], $data['id_cliente'], $data['id_vehiculo'],
           $data['km'], $data['color'],
           $data['total_piezas'], $data['total_mano_obra'], $data['total_terceros'], $data['gran_total'],
           $data['fecha_emision'], $data['estado'], $data['notas'],
       ]);
    return (int)$db->lastInsertId();
}

function addDetalle(PDO $db, int $idPres, string $tipo, ?int $idRep, string $desc, float $qty, float $precio): void {
    $s = $db->prepare("SELECT id_detalle FROM PRESUPUESTO_DETALLES WHERE id_presupuesto=? AND descripcion=?");
    $s->execute([$idPres, $desc]);
    if ($s->rowCount() > 0) return;
    $db->prepare("INSERT INTO PRESUPUESTO_DETALLES (id_presupuesto,tipo_item,id_repuesto,descripcion,cantidad,precio_unitario)
                  VALUES (?,?,?,?,?,?)")
       ->execute([$idPres, $tipo, $idRep, $desc, $qty, $precio]);
}

// ─────────────────────────────────────────────────────────────
// 5. CITAS
// ─────────────────────────────────────────────────────────────
echo "\n--- Citas ---\n";

// HOY
$c1 = upsertCita($db, [
    'fecha_hora'=>dt(0,'09:00:00'), 'motivo'=>'Revisión General 60.000 km',
    'estado'=>'Confirmada', 'prioridad'=>'Media', 'emergencia'=>false,
    'id_cliente'=>$cl_ana, 'id_vehiculo'=>$vMap['3456ABC'], 'id_mecanico'=>$mec_carlos,
]);
$c2 = upsertCita($db, [
    'fecha_hora'=>dt(0,'10:30:00'), 'motivo'=>'Cambio de aceite y filtros',
    'estado'=>'Confirmada', 'prioridad'=>'Baja', 'emergencia'=>false,
    'id_cliente'=>$cl_roberto, 'id_vehiculo'=>$vMap['1122GHI'], 'id_mecanico'=>$mec_laura,
]);
$c3 = upsertCita($db, [
    'fecha_hora'=>dt(0,'11:00:00'), 'motivo'=>'Ruido extraño al frenar',
    'estado'=>'Confirmada', 'prioridad'=>'Alta', 'emergencia'=>false,
    'id_cliente'=>$cl_maria, 'id_vehiculo'=>$vMap['5566JKL'], 'id_mecanico'=>$mec_miguel,
]);
$c4 = upsertCita($db, [
    'fecha_hora'=>dt(0,'12:30:00'), 'motivo'=>'Inspección pre-ITV',
    'estado'=>'Pendiente', 'prioridad'=>'Media', 'emergencia'=>false,
    'id_cliente'=>$cl_javier, 'id_vehiculo'=>$vMap['3344PQR'], 'id_mecanico'=>$mec_carlos,
]);
$c5 = upsertCita($db, [
    'fecha_hora'=>dt(0,'16:00:00'), 'motivo'=>'Avería arranque en frío',
    'estado'=>'Pendiente', 'prioridad'=>'Alta', 'emergencia'=>true,
    'id_cliente'=>$cl_elena, 'id_vehiculo'=>$vMap['7788STU'], 'id_mecanico'=>null,
]);
$c6 = upsertCita($db, [
    'fecha_hora'=>dt(0,'17:00:00'), 'motivo'=>'Cambio pastillas de freno delanteras',
    'estado'=>'Pendiente', 'prioridad'=>'Media', 'emergencia'=>false,
    'id_cliente'=>$cl_ana, 'id_vehiculo'=>$vMap['7890DEF'], 'id_mecanico'=>$mec_miguel,
]);

// MAÑANA
upsertCita($db, [
    'fecha_hora'=>dt(1,'10:00:00'), 'motivo'=>'Revisión frenos completa',
    'estado'=>'Pendiente', 'prioridad'=>'Media', 'emergencia'=>false,
    'id_cliente'=>$cl_roberto, 'id_vehiculo'=>$vMap['1122GHI'], 'id_mecanico'=>$mec_miguel,
]);
upsertCita($db, [
    'fecha_hora'=>dt(1,'11:30:00'), 'motivo'=>'Revisión general anual',
    'estado'=>'Pendiente', 'prioridad'=>'Baja', 'emergencia'=>false,
    'id_cliente'=>$cl_maria, 'id_vehiculo'=>$vMap['9900MNO'], 'id_mecanico'=>$mec_laura,
]);

// +3 DÍAS
upsertCita($db, [
    'fecha_hora'=>dt(3,'09:30:00'), 'motivo'=>'Cambio de aceite y filtro',
    'estado'=>'Pendiente', 'prioridad'=>'Baja', 'emergencia'=>false,
    'id_cliente'=>$cl_javier, 'id_vehiculo'=>$vMap['3344PQR'], 'id_mecanico'=>$mec_carlos,
]);
upsertCita($db, [
    'fecha_hora'=>dt(3,'15:00:00'), 'motivo'=>'Diagnóstico electrónico general',
    'estado'=>'Pendiente', 'prioridad'=>'Media', 'emergencia'=>false,
    'id_cliente'=>$cl_elena, 'id_vehiculo'=>$vMap['2233VWX'], 'id_mecanico'=>$mec_laura,
]);

// +5 DÍAS
upsertCita($db, [
    'fecha_hora'=>dt(5,'10:00:00'), 'motivo'=>'Control post-revisión distribución',
    'estado'=>'Pendiente', 'prioridad'=>'Baja', 'emergencia'=>false,
    'id_cliente'=>$cl_ana, 'id_vehiculo'=>$vMap['3456ABC'], 'id_mecanico'=>$mec_carlos,
]);

// PASADAS (canceladas / finalizadas)
upsertCita($db, [
    'fecha_hora'=>dt(-7,'09:00:00'), 'motivo'=>'Revisión suspensión trasera',
    'estado'=>'Cancelada', 'prioridad'=>'Media', 'emergencia'=>false,
    'id_cliente'=>$cl_maria, 'id_vehiculo'=>$vMap['5566JKL'], 'id_mecanico'=>$mec_miguel,
]);
$c_pasada_roberto = upsertCita($db, [
    'fecha_hora'=>dt(-10,'10:00:00'), 'motivo'=>'Revisión 50.000 km completa',
    'estado'=>'Confirmada', 'prioridad'=>'Media', 'emergencia'=>false,
    'id_cliente'=>$cl_roberto, 'id_vehiculo'=>$vMap['1122GHI'], 'id_mecanico'=>$mec_laura,
]);
$c_pasada_elena = upsertCita($db, [
    'fecha_hora'=>dt(-5,'11:00:00'), 'motivo'=>'Desgaste pastillas de freno',
    'estado'=>'Confirmada', 'prioridad'=>'Alta', 'emergencia'=>false,
    'id_cliente'=>$cl_elena, 'id_vehiculo'=>$vMap['7788STU'], 'id_mecanico'=>$mec_miguel,
]);
$c_pasada_javier = upsertCita($db, [
    'fecha_hora'=>dt(-15,'09:00:00'), 'motivo'=>'Cambio batería descargada',
    'estado'=>'Confirmada', 'prioridad'=>'Alta', 'emergencia'=>true,
    'id_cliente'=>$cl_javier, 'id_vehiculo'=>$vMap['3344PQR'], 'id_mecanico'=>$mec_carlos,
]);
echo "✓ Citas creadas\n";

// ─────────────────────────────────────────────────────────────
// 6. REPARACIONES + MECÁNICOS ASIGNADOS
// ─────────────────────────────────────────────────────────────
echo "\n--- Reparaciones ---\n";

// REP 1: BMW Ana — En Proceso — presupuesto Aprobado
$r1 = upsertReparacion($db, [
    'id_cita'=>$c1, 'modelo'=>'BMW Serie 3', 'matricula'=>'3456ABC',
    'descripcion'=>'Sustitución correa de distribución y tensor',
    'diagnostico'=>'Correa con fisuras visibles. Tensador con juego excesivo. Cambio preventivo urgente.',
    'fecha_entrada'=>dt(0,'09:15:00'), 'fecha_salida'=>null,
    'km'=>62400, 'estado_presupuesto'=>'Aprobado', 'estado'=>'En Proceso', 'precio_final'=>null,
]);
$db->prepare("INSERT IGNORE INTO REPARACION_MECANICO (id_reparacion,id_mecanico) VALUES (?,?)")->execute([$r1,$mec_carlos]);

// REP 2: VW Golf María — Esperando Piezas — presupuesto Enviado
$r2 = upsertReparacion($db, [
    'id_cita'=>$c3, 'modelo'=>'Volkswagen Golf VII', 'matricula'=>'5566JKL',
    'descripcion'=>'Sensor de temperatura motor averiado',
    'diagnostico'=>'Código OBD P0115. Sensor NTC abierto. Requiere sustitución inmediata.',
    'fecha_entrada'=>dt(0,'11:10:00'), 'fecha_salida'=>null,
    'km'=>48200, 'estado_presupuesto'=>'Pendiente', 'estado'=>'Esperando Piezas', 'precio_final'=>null,
]);
$db->prepare("INSERT IGNORE INTO REPARACION_MECANICO (id_reparacion,id_mecanico) VALUES (?,?)")->execute([$r2,$mec_laura]);

// REP 3: Toyota Roberto — Finalizada — presupuesto Aprobado
$r3 = upsertReparacion($db, [
    'id_cita'=>$c_pasada_roberto, 'modelo'=>'Toyota Corolla', 'matricula'=>'1122GHI',
    'descripcion'=>'Revisión 50.000 km completa',
    'diagnostico'=>'Revisión completa realizada. Aceite y filtros en mal estado. Todo sustituido. Vehículo en perfecto estado.',
    'fecha_entrada'=>dt(-10,'10:10:00'), 'fecha_salida'=>dt(-10,'12:30:00'),
    'km'=>50100, 'estado_presupuesto'=>'Aprobado', 'estado'=>'Finalizada', 'precio_final'=>93.40,
]);
$db->prepare("INSERT IGNORE INTO REPARACION_MECANICO (id_reparacion,id_mecanico) VALUES (?,?)")->execute([$r3,$mec_laura]);

// REP 4: Seat León Elena — En Proceso — presupuesto Pendiente
$r4 = upsertReparacion($db, [
    'id_cita'=>$c_pasada_elena, 'modelo'=>'Seat León', 'matricula'=>'7788STU',
    'descripcion'=>'Pastillas de freno desgastadas delantera y trasera',
    'diagnostico'=>'Espesor pastillas delanteras: 2mm (límite 3mm). Traseras: 1.5mm. Sustitución urgente.',
    'fecha_entrada'=>dt(-5,'11:15:00'), 'fecha_salida'=>null,
    'km'=>73600, 'estado_presupuesto'=>'Pendiente', 'estado'=>'En Proceso', 'precio_final'=>null,
]);
$db->prepare("INSERT IGNORE INTO REPARACION_MECANICO (id_reparacion,id_mecanico) VALUES (?,?)")->execute([$r4,$mec_miguel]);

// REP 5: Audi A4 Javier — Finalizada — presupuesto Aprobado
$r5 = upsertReparacion($db, [
    'id_cita'=>$c_pasada_javier, 'modelo'=>'Audi A4', 'matricula'=>'3344PQR',
    'descripcion'=>'Cambio batería descargada',
    'diagnostico'=>'Batería de 5 años. Capacidad al 30%. Sustitución por batería Varta 60Ah.',
    'fecha_entrada'=>dt(-15,'09:10:00'), 'fecha_salida'=>dt(-15,'10:00:00'),
    'km'=>89300, 'estado_presupuesto'=>'Aprobado', 'estado'=>'Finalizada', 'precio_final'=>120.00,
]);
$db->prepare("INSERT IGNORE INTO REPARACION_MECANICO (id_reparacion,id_mecanico) VALUES (?,?)")->execute([$r5,$mec_carlos]);

echo "✓ Reparaciones (IDs: $r1, $r2, $r3, $r4, $r5)\n";

// ─────────────────────────────────────────────────────────────
// 7. PRESUPUESTOS + DETALLES
// ─────────────────────────────────────────────────────────────
echo "\n--- Presupuestos ---\n";

// PRES 1: BMW Serie 3 (r1) — Aprobado
$p1 = upsertPresupuesto($db, [
    'id_reparacion'=>$r1, 'id_mecanico'=>$mec_carlos, 'id_cliente'=>$cl_ana, 'id_vehiculo'=>$vMap['3456ABC'],
    'km'=>62400, 'color'=>'Blanco Alpino',
    'total_piezas'=>73.00, 'total_mano_obra'=>160.00, 'total_terceros'=>0,
    'gran_total'=>233.00, 'fecha_emision'=>date('Y-m-d'), 'estado'=>'Aprobado',
    'notas'=>'El cliente aprobó el presupuesto vía llamada. Trabajo comenzado.',
]);
addDetalle($db,$p1,'Repuesto',$repMap['Correa de distribución'],'Correa de distribución Gates',1,45.00);
addDetalle($db,$p1,'Repuesto',$repMap['Kit tensor correa distribución'],'Kit tensor Gates',1,28.00);
addDetalle($db,$p1,'Mano de Obra',null,'Mano de obra — cambio distribución (2h)',2,80.00);

// PRES 2: VW Golf (r2) — Enviado
$p2 = upsertPresupuesto($db, [
    'id_reparacion'=>$r2, 'id_mecanico'=>$mec_laura, 'id_cliente'=>$cl_maria, 'id_vehiculo'=>$vMap['5566JKL'],
    'km'=>48200, 'color'=>'Gris Oscuro',
    'total_piezas'=>22.00, 'total_mano_obra'=>60.00, 'total_terceros'=>0,
    'gran_total'=>82.00, 'fecha_emision'=>date('Y-m-d'), 'estado'=>'Enviado',
    'notas'=>'Pendiente confirmación cliente. Pieza en pedido.',
]);
addDetalle($db,$p2,'Repuesto',$repMap['Sensor de temperatura'],'Sensor temperatura NTC Hella',1,22.00);
addDetalle($db,$p2,'Mano de Obra',null,'Mano de obra — diagnóstico + sustitución (1h)',1,60.00);

// PRES 3: Toyota Corolla (r3) — Aprobado + Finalizado
$p3 = upsertPresupuesto($db, [
    'id_reparacion'=>$r3, 'id_mecanico'=>$mec_laura, 'id_cliente'=>$cl_roberto, 'id_vehiculo'=>$vMap['1122GHI'],
    'km'=>50100, 'color'=>'Blanco Perla',
    'total_piezas'=>53.40, 'total_mano_obra'=>40.00, 'total_terceros'=>0,
    'gran_total'=>93.40, 'fecha_emision'=>date('Y-m-d', strtotime('-10 days')), 'estado'=>'Aprobado',
    'notas'=>'Revisión 50.000 km completada satisfactoriamente.',
]);
addDetalle($db,$p3,'Repuesto',$repMap['Aceite motor 5W-30 (5L)'],'Aceite Castrol 5W-30 5L',1,32.00);
addDetalle($db,$p3,'Repuesto',$repMap['Filtro de aceite'],'Filtro aceite Mann',1,8.50);
addDetalle($db,$p3,'Repuesto',$repMap['Filtro de aire'],'Filtro aire Bosch',1,12.90);
addDetalle($db,$p3,'Mano de Obra',null,'Mano de obra — revisión 50.000 km (1h)',1,40.00);

// PRES 4: Seat León (r4) — Borrador
$p4 = upsertPresupuesto($db, [
    'id_reparacion'=>$r4, 'id_mecanico'=>$mec_miguel, 'id_cliente'=>$cl_elena, 'id_vehiculo'=>$vMap['7788STU'],
    'km'=>73600, 'color'=>'Rojo Pasión',
    'total_piezas'=>70.00, 'total_mano_obra'=>120.00, 'total_terceros'=>0,
    'gran_total'=>190.00, 'fecha_emision'=>date('Y-m-d'), 'estado'=>'Borrador',
    'notas'=>'Pendiente de aprobación. Se ha informado al cliente.',
]);
addDetalle($db,$p4,'Repuesto',$repMap['Pastillas de freno delanteras'],'Pastillas freno delanteras Brembo',1,38.00);
addDetalle($db,$p4,'Repuesto',$repMap['Pastillas de freno traseras'],'Pastillas freno traseras Brembo',1,32.00);
addDetalle($db,$p4,'Mano de Obra',null,'Mano de obra — cambio pastillas (2h)',2,60.00);

// PRES 5: Audi A4 (r5) — Aprobado + Finalizado
$p5 = upsertPresupuesto($db, [
    'id_reparacion'=>$r5, 'id_mecanico'=>$mec_carlos, 'id_cliente'=>$cl_javier, 'id_vehiculo'=>$vMap['3344PQR'],
    'km'=>89300, 'color'=>'Negro Brillante',
    'total_piezas'=>105.00, 'total_mano_obra'=>15.00, 'total_terceros'=>0,
    'gran_total'=>120.00, 'fecha_emision'=>date('Y-m-d', strtotime('-15 days')), 'estado'=>'Aprobado',
    'notas'=>'Servicio urgente. Batería cambiada en el acto.',
]);
addDetalle($db,$p5,'Repuesto',$repMap['Batería 60Ah'],'Batería Varta 60Ah',1,105.00);
addDetalle($db,$p5,'Mano de Obra',null,'Mano de obra — cambio batería (0.5h)',0.5,30.00);

// PRES 6: Presupuesto standalone — Javier / Audi — revisión preventiva
$p6 = upsertPresupuesto($db, [
    'id_reparacion'=>null, 'id_mecanico'=>$mec_carlos, 'id_cliente'=>$cl_javier, 'id_vehiculo'=>$vMap['3344PQR'],
    'km'=>89500, 'color'=>'Negro Brillante',
    'total_piezas'=>18.50, 'total_mano_obra'=>40.00, 'total_terceros'=>0,
    'gran_total'=>58.50, 'fecha_emision'=>date('Y-m-d'), 'estado'=>'Borrador',
    'notas'=>'Presupuesto preventivo para próxima revisión.',
]);
addDetalle($db,$p6,'Repuesto',$repMap['Correa de accesorios'],'Correa accesorios Continental',1,18.50);
addDetalle($db,$p6,'Mano de Obra',null,'Mano de obra — inspección general (1h)',1,40.00);

echo "✓ Presupuestos (IDs: $p1, $p2, $p3, $p4, $p5, $p6)\n";

// ─────────────────────────────────────────────────────────────
echo "\n========================================\n";
echo "Seed completado correctamente.\n";
echo "Contrasena de todos los usuarios: Test1234!\n";
echo "========================================\n";
echo "\nResumen:\n";
echo "  Mecanicos : " . implode(', ', array_column($mecDefs, 0)) . "\n";
echo "  Clientes  : 5 (ana, roberto, maria, javier, elena)\n";
echo "  Vehiculos : 8\n";
echo "  Repuestos : " . count($repMap) . "\n";
echo "  Citas     : 14 (6 hoy + 2 mañana + 2 en 3 dias + 1 en 5 dias + 3 pasadas)\n";
echo "  Reparaciones: 5 (2 En Proceso, 1 Esperando Piezas, 2 Finalizadas)\n";
echo "  Presupuestos: 6 (2 Aprobados+finaliz, 1 Enviado, 1 Borrador, 1 Aprobado, 1 standalone)\n";
