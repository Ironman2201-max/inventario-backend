<?php
// Cambiar el asterisco por tu dominio real si quieres restringir el acceso solo a tu frontend:
header("Access-Control-Allow-Origin: https://sgp.seminario1.eleueleo.com");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Manejo del método OPTIONS (Preflight request de Angular)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'conexion.php';

$method = $_SERVER['REQUEST_METHOD'];

// 📥 1. REGISTRAR UN NUEVO CONTENEDOR (POST)
if ($method === 'POST') {
    // Leer los datos JSON que envía Angular
    $data = json_decode(file_get_contents("php://input"));

    // Validar que los campos obligatorios del negocio no vengan vacíos
    if (
        !empty($data->code) &&
        !empty($data->type) &&
        !empty($data->warehouse) &&
        !empty($data->slot)
    ) {
        try {
            // Preparar el INSERT con las columnas exactas de tu tabla 'containers'
            $query = "INSERT INTO containers (code, type, status, customs_status, warehouse, slot, entry_date) 
                      VALUES (:code, :type, :status, :customs_status, :warehouse, :slot, NOW())";
            
            $stmt = $pdo->prepare($query);

            // Sanitizar y enlazar los parámetros
            $code = strtoupper(trim($data->code)); // Forzar mayúsculas en el código ISO
            $type = $data->type;
            $status = !empty($data->status) ? $data->status : 'Operativo';
            
            // Convertir el booleano de Angular (true/false) a un entero para MySQL (1/0)
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
                // 🔑 CAPTURA DEL ID RECIÉN GENERADO
                // Esto es fundamental para que Angular encadene el registro del movimiento GPS
                $lastId = $pdo->lastInsertId();

                http_response_code(201);
                echo json_encode([
                    "message" => "Contenedor registrado con éxito.",
                    "container_id" => $lastId
                ]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "No se pudo registrar el contenedor en el patio."]);
            }

        } catch (PDOException $e) {
            // Manejar errores de clave duplicada (Por si intentan ingresar un código de contenedor que ya existe)
            if ($e->getCode() == 23000) {
                http_response_code(400);
                echo json_encode(["message" => "Error: El código de contenedor ya se encuentra registrado en el patio."]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Error en el servidor: " . $e->getMessage()]);
            }
        }
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Datos incompletos. No se puede procesar el ingreso físico."]);
    }
}

// 📤 2. CONSULTAR CONTENEDORES ACTIVOS EN PATIO (GET)
if ($method === 'GET') {
    try {
        $query = "SELECT * FROM containers ORDER BY id DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        
        $contenedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        http_response_code(200);
        echo json_encode($contenedores);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Error al consultar el inventario de contenedores: " . $e->getMessage()]);
    }
}
?>
