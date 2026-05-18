<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once __DIR__ . '/../backend/config/config.php';

$database = new Database();
$db = $database->getConnection();
if (!$db) {
    http_response_code(500);
    echo json_encode(["message" => "Error de conexión a la base de datos."]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : 'ver_estado_coche';
$body = json_decode(file_get_contents('php://input'), true) ?: [];

switch ($action) {
    case 'ver_estado_coche':
        handleVerEstadoCoche($db);
        break;

    case 'listar_citas':
        handleListarCitas($db);
        break;

    case 'buscar_cita':
        handleBuscarCita($db);
        break;

    case 'reservar':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(["message" => "Método no permitido para reservar. Usa POST."]);
            break;
        }
        handleReservar($db, $body);
        break;

    case 'modificar':
        if ($method !== 'PUT') {
            http_response_code(405);
            echo json_encode(["message" => "Método no permitido para modificar. Usa PUT."]);
            break;
        }
        handleModificar($db, $body);
        break;

    case 'cancelar':
        if ($method !== 'DELETE') {
            http_response_code(405);
            echo json_encode(["message" => "Método no permitido para cancelar. Usa DELETE."]);
            break;
        }
        handleCancelar($db, $body);
        break;

    default:
        http_response_code(400);
        echo json_encode(["message" => "Acción desconocida: " . htmlspecialchars($action)]);
        break;
}

function handleVerEstadoCoche($db) {
    $matricula = isset($_GET['matricula']) ? trim($_GET['matricula']) : null;
    $email = isset($_GET['email']) ? trim($_GET['email']) : null;

    if (!$matricula && !$email) {
        http_response_code(400);
        echo json_encode(["message" => "Proporciona matrícula o email para ver el estado del coche."]);
        return;
    }

    if ($email) {
        $cliente = getClientePorEmail($db, $email);
        if (!$cliente) {
            http_response_code(404);
            echo json_encode(["message" => "Cliente no encontrado."]);
            return;
        }
        $matricula = getMatriculaPorCliente($db, $cliente['id_cliente']);
        if (!$matricula) {
            http_response_code(404);
            echo json_encode(["message" => "No se encontró vehículo asociado al cliente."]);
            return;
        }
    }

    $vehiculo = getVehiculoPorMatricula($db, $matricula);
    if (!$vehiculo) {
        http_response_code(404);
        echo json_encode(["message" => "Vehículo no encontrado."]);
        return;
    }

    $cita = getUltimaCitaVehiculo($db, $vehiculo['id_vehiculo']);
    $reparacion = getUltimaReparacionVehiculo($db, $vehiculo['id_vehiculo']);

    http_response_code(200);
    echo json_encode([
        "vehiculo" => $vehiculo,
        "cita" => $cita,
        "reparacion" => $reparacion,
    ]);
}

function handleListarCitas($db) {
    $email = isset($_GET['email']) ? trim($_GET['email']) : null;
    if (!$email) {
        http_response_code(400);
        echo json_encode(["message" => "Proporciona email para listar citas del cliente."]);
        return;
    }

    $cliente = getClientePorEmail($db, $email);
    if (!$cliente) {
        http_response_code(404);
        echo json_encode(["message" => "Cliente no encontrado."]);
        return;
    }

    $query = "SELECT c.id_cita, c.fecha_hora, c.motivo, c.estado, c.es_emergencia, v.matricula, v.marca, v.modelo
              FROM CITAS c
              LEFT JOIN VEHICULOS v ON c.id_vehiculo = v.id_vehiculo
              WHERE c.id_cliente = :id_cliente
              ORDER BY c.fecha_hora DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_cliente', $cliente['id_cliente'], PDO::PARAM_INT);
    $stmt->execute();

    $citas = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $row['id_cita'] = (int)$row['id_cita'];
        $row['es_emergencia'] = (bool)$row['es_emergencia'];
        $citas[] = $row;
    }

    http_response_code(200);
    echo json_encode($citas);
}

function handleBuscarCita($db) {
    $idCita = isset($_GET['id_cita']) ? (int)$_GET['id_cita'] : null;
    $email = isset($_GET['email']) ? trim($_GET['email']) : null;

    if ($idCita) {
        $query = "SELECT c.id_cita, c.fecha_hora, c.motivo, c.estado, c.es_emergencia, v.matricula, v.marca, v.modelo, cl.nombre AS cliente_nombre, cl.email AS cliente_email
                  FROM CITAS c
                  LEFT JOIN VEHICULOS v ON c.id_vehiculo = v.id_vehiculo
                  LEFT JOIN CLIENTES cl ON c.id_cliente = cl.id_cliente
                  WHERE c.id_cita = :id_cita LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_cita', $idCita, PDO::PARAM_INT);
    } elseif ($email) {
        $cliente = getClientePorEmail($db, $email);
        if (!$cliente) {
            http_response_code(404);
            echo json_encode(["message" => "Cliente no encontrado."]);
            return;
        }
        $query = "SELECT c.id_cita, c.fecha_hora, c.motivo, c.estado, c.es_emergencia, v.matricula, v.marca, v.modelo
                  FROM CITAS c
                  LEFT JOIN VEHICULOS v ON c.id_vehiculo = v.id_vehiculo
                  WHERE c.id_cliente = :id_cliente
                  ORDER BY c.fecha_hora DESC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id_cliente', $cliente['id_cliente'], PDO::PARAM_INT);
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Proporciona id_cita o email para buscar la cita."]);
        return;
    }

    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $row['id_cita'] = (int)$row['id_cita'];
        $row['es_emergencia'] = (bool)$row['es_emergencia'];
        http_response_code(200);
        echo json_encode($row);
    } else {
        http_response_code(404);
        echo json_encode(["message" => "Cita no encontrada."]);
    }
}

function handleReservar($db, $body) {
    $email = isset($body['email']) ? trim($body['email']) : null;
    $matricula = isset($body['matricula']) ? trim($body['matricula']) : null;
    $fechaHora = isset($body['fecha_hora']) ? trim($body['fecha_hora']) : null;
    $motivo = isset($body['motivo']) ? trim($body['motivo']) : '';
    $marca = isset($body['marca']) ? trim($body['marca']) : null;
    $modelo = isset($body['modelo']) ? trim($body['modelo']) : null;
    $esEmergencia = isset($body['es_emergencia']) ? (bool)$body['es_emergencia'] : false;

    if (!$email || !$matricula || !$fechaHora) {
        http_response_code(400);
        echo json_encode(["message" => "email, matricula y fecha_hora son obligatorios para reservar."]);
        return;
    }

    $cliente = getClientePorEmail($db, $email);
    if (!$cliente) {
        $cliente = crearCliente($db, $email, $body['nombre'] ?? null);
    }

    $vehiculo = getVehiculoPorMatricula($db, $matricula);
    if (!$vehiculo) {
        $vehiculo = crearVehiculo($db, $matricula, $marca, $modelo, null, $cliente['id_cliente']);
    }

    $query = "INSERT INTO CITAS (fecha_hora, motivo, estado, es_emergencia, id_cliente, id_vehiculo, id_taller)
              VALUES (:fecha_hora, :motivo, 'Pendiente', :es_emergencia, :id_cliente, :id_vehiculo, 1)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':fecha_hora', $fechaHora);
    $stmt->bindParam(':motivo', $motivo);
    $stmt->bindParam(':es_emergencia', $esEmergencia, PDO::PARAM_BOOL);
    $stmt->bindParam(':id_cliente', $cliente['id_cliente'], PDO::PARAM_INT);
    $stmt->bindParam(':id_vehiculo', $vehiculo['id_vehiculo'], PDO::PARAM_INT);

    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode([
            "message" => "Cita reservada correctamente.",
            "id_cita" => (int)$db->lastInsertId(),
            "cliente" => $cliente,
            "vehiculo" => $vehiculo,
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Error al crear la cita."]);
    }
}

function handleModificar($db, $body) {
    $idCita = isset($body['id_cita']) ? (int)$body['id_cita'] : null;
    $fechaHora = isset($body['fecha_hora']) ? trim($body['fecha_hora']) : null;
    $motivo = isset($body['motivo']) ? trim($body['motivo']) : null;
    $estado = isset($body['estado']) ? trim($body['estado']) : null;

    if (!$idCita) {
        http_response_code(400);
        echo json_encode(["message" => "id_cita es obligatorio para modificar."]);
        return;
    }

    $fields = [];
    $params = [':id_cita' => $idCita];

    if ($fechaHora) {
        $fields[] = "fecha_hora = :fecha_hora";
        $params[':fecha_hora'] = $fechaHora;
    }
    if ($motivo !== null) {
        $fields[] = "motivo = :motivo";
        $params[':motivo'] = $motivo;
    }
    if ($estado) {
        $fields[] = "estado = :estado";
        $params[':estado'] = $estado;
    }

    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(["message" => "No hay datos para modificar."]);
        return;
    }

    $query = "UPDATE CITAS SET " . implode(', ', $fields) . " WHERE id_cita = :id_cita";
    $stmt = $db->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(["message" => "Cita modificada correctamente."]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Error al modificar la cita."]);
    }
}

function handleCancelar($db, $body) {
    $idCita = isset($body['id_cita']) ? (int)$body['id_cita'] : null;
    if (!$idCita) {
        http_response_code(400);
        echo json_encode(["message" => "id_cita es obligatorio para cancelar."]);
        return;
    }

    $query = "UPDATE CITAS SET estado = 'Cancelada' WHERE id_cita = :id_cita";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_cita', $idCita, PDO::PARAM_INT);

    if ($stmt->execute()) {
        http_response_code(200);
        echo json_encode(["message" => "Cita cancelada correctamente."]);
    } else {
        http_response_code(500);
        echo json_encode(["message" => "Error al cancelar la cita."]);
    }
}

function getClientePorEmail($db, $email) {
    $query = "SELECT id_cliente, nombre, telefono, email FROM CLIENTES WHERE email = :email LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function crearCliente($db, $email, $nombre = null) {
    $query = "INSERT INTO CLIENTES (nombre, email) VALUES (:nombre, :email)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':nombre', $nombre);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    return [
        'id_cliente' => (int)$db->lastInsertId(),
        'nombre' => $nombre,
        'email' => $email,
    ];
}

function getMatriculaPorCliente($db, $idCliente) {
    $query = "SELECT matricula FROM VEHICULOS WHERE id_cliente = :id_cliente ORDER BY id_vehiculo DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_cliente', $idCliente, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row['matricula'] ?? null;
}

function getVehiculoPorMatricula($db, $matricula) {
    $query = "SELECT id_vehiculo, matricula, modelo, marca, anio, id_cliente FROM VEHICULOS WHERE matricula = :matricula LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':matricula', $matricula, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $row['id_vehiculo'] = (int)$row['id_vehiculo'];
        return $row;
    }
    return null;
}

function crearVehiculo($db, $matricula, $marca, $modelo, $anio, $idCliente) {
    $query = "INSERT INTO VEHICULOS (matricula, marca, modelo, anio, id_cliente) VALUES (:matricula, :marca, :modelo, :anio, :id_cliente)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':matricula', $matricula, PDO::PARAM_STR);
    $stmt->bindParam(':marca', $marca);
    $stmt->bindParam(':modelo', $modelo);
    $stmt->bindValue(':anio', $anio ?: null, PDO::PARAM_INT);
    $stmt->bindParam(':id_cliente', $idCliente, PDO::PARAM_INT);
    $stmt->execute();
    return [
        'id_vehiculo' => (int)$db->lastInsertId(),
        'matricula' => $matricula,
        'marca' => $marca,
        'modelo' => $modelo,
        'anio' => $anio,
        'id_cliente' => $idCliente,
    ];
}

function getUltimaCitaVehiculo($db, $idVehiculo) {
    $query = "SELECT id_cita, fecha_hora, motivo, estado, es_emergencia FROM CITAS WHERE id_vehiculo = :id_vehiculo ORDER BY fecha_hora DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_vehiculo', $idVehiculo, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $row['id_cita'] = (int)$row['id_cita'];
        $row['es_emergencia'] = (bool)$row['es_emergencia'];
        return $row;
    }
    return null;
}

function getUltimaReparacionVehiculo($db, $idVehiculo) {
    $query = "SELECT r.id_reparacion, r.diagnostico, r.fecha_entrada, r.fecha_salida, r.estado, r.precio_final
              FROM REPARACIONES r
              JOIN PRESUPUESTOS p ON r.id_presupuesto = p.id_presupuesto
              JOIN CITAS c ON p.id_cita = c.id_cita
              WHERE c.id_vehiculo = :id_vehiculo
              ORDER BY r.fecha_entrada DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id_vehiculo', $idVehiculo, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $row['id_reparacion'] = (int)$row['id_reparacion'];
        return $row;
    }
    return null;
}
?>