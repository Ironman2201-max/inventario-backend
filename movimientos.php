<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'conexion.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));

    if (
        isset($data->container_id) && $data->container_id !== '' &&
        isset($data->user_id) && $data->user_id !== '' &&
        !empty($data->movement_type) &&
        isset($data->latitude) &&
        isset($data->longitude)
    ) {
        try {
            $container_id  = intval($data->container_id);
            $user_id       = intval($data->user_id);
            $movement_type = strtoupper(trim($data->movement_type));
            $latitude      = floatval($data->latitude);
            $longitude     = floatval($data->longitude);
            $observations  = isset($data->observations) ? trim($data->observations) : NULL; // 👈 Captura de observación
            $hours_elapsed = 0;

            // SI ES SALIDA (EXIT), ACTUALIZAMOS exit_date EN EL CONTENEDOR
            if ($movement_type === 'EXIT') {
                $timeQuery = "SELECT TIMESTAMPDIFF(HOUR, entry_date, NOW()) AS horas FROM containers WHERE id = :container_id";
                $timeStmt = $pdo->prepare($timeQuery);
                $timeStmt->execute([':container_id' => $container_id]);
                $timeResult = $timeStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($timeResult && isset($timeResult['horas'])) {
                    $hours_elapsed = intval($timeResult['horas']);
                }

                $updateQuery = "UPDATE containers SET exit_date = NOW() WHERE id = :container_id";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->execute([':container_id' => $container_id]);
            }

            // INSERT EN LA TABLA movements INCLUYENDO OBSERVATIONS
            $query = "INSERT INTO movements (container_id, user_id, movement_type, latitude, longitude, observations, created_at) 
                      VALUES (:container_id, :user_id, :movement_type, :latitude, :longitude, :observations, NOW())";
            
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':container_id', $container_id);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':movement_type', $movement_type);
            $stmt->bindParam(':latitude', $latitude);
            $stmt->bindParam(':longitude', $longitude);
            $stmt->bindParam(':observations', $observations);

            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode([
                    "message"       => "Movimiento y despacho registrados con éxito.",
                    "movement_type" => $movement_type,
                    "hours_elapsed" => $hours_elapsed
                ]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "No se pudo guardar la traza del movimiento."]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error en la base de datos: " . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Datos incompletos en la petición."]);
    }
}

if ($method === 'GET') {
    try {
        // En la consulta GET incluimos m.observations
        $query = "SELECT 
                    m.id, 
                    m.container_id,
                    c.code AS container_code, 
                    u.nombre AS operator_name,
                    u.cedula AS operator_cedula,
                    m.movement_type, 
                    m.latitude, 
                    m.longitude, 
                    m.observations,
                    m.created_at,
                    c.entry_date,
                    c.exit_date,
                    TIMESTAMPDIFF(HOUR, c.entry_date, COALESCE(c.exit_date, NOW())) AS hours_elapsed
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
        echo json_encode(["message" => "Error al obtener historial: " . $e->getMessage()]);
    }
}
?>