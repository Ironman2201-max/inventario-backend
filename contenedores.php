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
        !empty($data->code) &&
        !empty($data->type) &&
        !empty($data->warehouse) &&
        !empty($data->slot)
    ) {
        try {
            $query = "INSERT INTO containers (code, type, status, customs_status, warehouse, slot, entry_date) 
                      VALUES (:code, :type, :status, :customs_status, :warehouse, :slot, NOW())";
            
            $stmt = $pdo->prepare($query);

            $code = strtoupper(trim($data->code)); 
            
            // Validación de ENUM de tipo
            $tiposPermitidos = ['Dry Van', 'Reefer', 'High Cube', 'Vacío', 'Tránsito'];
            $type = in_array($data->type, $tiposPermitidos) ? $data->type : 'Dry Van';

            // Validación de ENUM de estado
            $estadosPermitidos = ['Operativo', 'Mantenimiento', 'Dañado'];
            $status = in_array($data->status, $estadosPermitidos) ? $data->status : 'Operativo';

            $customs_status = $data->customs_status ? 1 : 0; 
            $warehouse = $data->warehouse;
            $slot = $data->slot;

            $stmt->bindParam(':code', $code);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':customs_status', $customs_status);
            $stmt->bindParam(':warehouse', $warehouse);
            $stmt->bindParam(':slot', $slot);

            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode([
                    "message" => "Contenedor registrado con éxito.",
                    "container_id" => $pdo->lastInsertId()
                ]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "No se pudo ingresar el contenedor."]);
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                http_response_code(400);
                echo json_encode(["message" => "El contenedor ya está activo en el patio."]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Error en el servidor: " . $e->getMessage()]);
            }
        }
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Datos de ingreso incompletos."]);
    }
}

if ($method === 'GET') {
    try {
        // 🔍 FILTRADO CLAVE: Solo contenedores que no han salido (exit_date es NULL)
        $query = "SELECT * FROM containers WHERE exit_date IS NULL ORDER BY id DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        
        $contenedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode($contenedores);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Error al consultar inventario: " . $e->getMessage()]);
    }
}
?>