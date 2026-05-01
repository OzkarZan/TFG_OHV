<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Escape rápido para peticiones Preflight CORS del navegador
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once '../config/config.php';

$database = new Database();
$db = $database->getConnection();

// Verificar conexión
if(!$db) { exit(); }

$method = $_SERVER['REQUEST_METHOD'];
// Solo decodificaremos json en métodos donde esperamos body (POST, PUT, DELETE)
$data = null;
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $data = json_decode(file_get_contents("php://input"));
}

if ($method == 'GET') {
    $query = "SELECT id_repuesto, nombre_pieza, marca, stock_actual, stock_minimo, precio_unitario FROM REPUESTOS";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $num = $stmt->rowCount();

    if($num > 0) {
        $repuestos_arr = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            // Cast numéricos correctamente para evitar conflictos de parseo en JS
            $row['id_repuesto'] = (int)$row['id_repuesto'];
            $row['stock_actual'] = (int)$row['stock_actual'];
            $row['stock_minimo'] = (int)$row['stock_minimo'];
            $row['precio_unitario'] = (float)$row['precio_unitario'];
            array_push($repuestos_arr, $row);
        }
        http_response_code(200);
        echo json_encode($repuestos_arr);
    } else {
        http_response_code(200); // 200 OK array vacío
        echo json_encode(array());
    }
} elseif ($method == 'POST') {
    if(!empty($data->nombre_pieza) && isset($data->precio_unitario)) {
        $query = "INSERT INTO REPUESTOS (nombre_pieza, marca, stock_actual, stock_minimo, precio_unitario) 
                  VALUES (:nombre_pieza, :marca, :stock_actual, :stock_minimo, :precio_unitario)";
        $stmt = $db->prepare($query);
        
        $marca = isset($data->marca) ? $data->marca : null;
        $stock_actual = isset($data->stock_actual) ? $data->stock_actual : 0;
        $stock_minimo = isset($data->stock_minimo) ? $data->stock_minimo : 5;
        
        $stmt->bindParam(':nombre_pieza', $data->nombre_pieza);
        $stmt->bindParam(':marca', $marca);
        $stmt->bindParam(':stock_actual', $stock_actual);
        $stmt->bindParam(':stock_minimo', $stock_minimo);
        $stmt->bindParam(':precio_unitario', $data->precio_unitario);
        
        if($stmt->execute()) {
            http_response_code(201);
            echo json_encode(array(
                "message" => "Repuesto creado exitosamente.",
                "id_repuesto" => $db->lastInsertId()
            ));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "No se pudo crear el repuesto."));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "Datos incompletos para POST."));
    }
} elseif ($method == 'PUT') {
    if(!empty($data->id_repuesto)) {
        $query = "UPDATE REPUESTOS SET nombre_pieza = :nombre, precio_unitario = :precio, stock_actual = :stock WHERE id_repuesto = :id";
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':nombre', $data->nombre_pieza);
        $stmt->bindParam(':precio', $data->precio_unitario);
        $stmt->bindParam(':stock', $data->stock_actual);
        $stmt->bindParam(':id', $data->id_repuesto);
        
        if($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "Repuesto actualizado exitosamente."));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Ocurrió un error en la actualización."));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "Falta la variable id_repuesto para proceder con PUT."));
    }
} elseif ($method == 'DELETE') {
    if(!empty($data->id_repuesto)) {
        $query = "DELETE FROM REPUESTOS WHERE id_repuesto = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $data->id_repuesto);
        
        if($stmt->execute()) {
            http_response_code(200);
            echo json_encode(array("message" => "Repuesto eliminado correctamente."));
        } else {
            http_response_code(503);
            echo json_encode(array("message" => "Error al ejecutar la eliminación."));
        }
    } else {
        http_response_code(400);
        echo json_encode(array("message" => "Falta la id_repuesto a eliminar."));
    }
} else {
    http_response_code(405);
    echo json_encode(array("message" => "Método no permitido."));
}
?>
