<?php
// 1. Cabeceras de control de acceso (CORS) para comunicarse con Angular sin bloqueos
// Cambiar el asterisco por tu dominio real si quieres restringir el acceso solo a tu frontend:
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder con éxito inmediato a la petición de verificación "Preflight" (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'conexion.php';

$method = $_SERVER['REQUEST_METHOD'];

// 📥 REGISTRAR MOVIMIENTO Y TRAZA GPS (POST)
if ($method === 'POST') {
    // Capturar y decodificar el JSON enviado por Angular
    $data = json_decode(file_get_contents("php://input"));

    // Validar de manera estricta que vengan los datos obligatorios del satélite y negocio
    if (
        !empty($data->container_id) &&
        !empty($data->user_id) &&
        !empty($data->movement_type) &&
        isset($data->latitude) &&
        isset($data->longitude)
    ) {
        try {
            $query = "INSERT INTO movements (container_id, user_id, movement_type, latitude, longitude, created_at) 
                      VALUES (:container_id, :user_id, :movement_type, :latitude, :longitude, NOW())";
            
            $stmt = $pdo->prepare($query);

            // Forzar tipos de datos correctos (enteros para IDs y decimales flotantes para el GPS)
            $container_id = intval($data->container_id);
            $user_id = intval($data->user_id);
            $movement_type = $data->movement_type; // 'ENTRY' o 'EXIT'
            $latitude = floatval($data->latitude);
            $longitude = floatval($data->longitude);

            $stmt->bindParam(':container_id', $container_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':movement_type', $movement_type);
            $stmt->bindParam(':latitude', $latitude);
            $stmt->bindParam(':longitude', $longitude);

            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(["message" => "Traza logística y coordenadas GPS guardadas en MySQL con éxito."]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Error interno: No se pudo registrar la traza de movimiento."]);
            }

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error en la base de datos de XAMPP: " . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Error: Datos de telemetría o identificación incompletos en la petición."]);
    }
}

// 📤 OBTENER HISTORIAL LOGÍSTICO (GET)
if ($method === 'GET') {
    try {
        // Consulta avanzada uniendo tablas para futuros reportes de patio
        $query = "SELECT m.id, c.code AS container_code, u.nombre AS operator_name, 
                         m.movement_type, m.latitude, m.longitude, m.created_at 
                  FROM movements m
                  INNER JOIN containers c ON m.container_id = c.id
                  INNER JOIN usuarios u ON m.user_id = u.id
                  ORDER BY m.id DESC";
                  
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        
        $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode($movimientos);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Error al consultar el historial: " . $e->getMessage()]);
    }
}
?>
