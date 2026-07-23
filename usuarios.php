<?php
// ✅ CORS — Debe ser lo primero antes de cualquier lógica
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'conexion.php';

$method = $_SERVER['REQUEST_METHOD'];

// 1. OBTENER TODOS LOS USUARIOS (GET)
if ($method === 'GET') {
    try {
        // 🔍 Incluimos la columna 'cedula'
        $query = "SELECT id, nombre, cedula, correo, rol FROM usuarios ORDER BY id DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        http_response_code(200);
        echo json_encode($usuarios);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Error al obtener usuarios: " . $e->getMessage()]);
    }
}

// 2. ACTUALIZAR EL ROL DE UN USUARIO (PUT)
if ($method === 'PUT') {
    $data = json_decode(file_get_contents("php://input"));

    if (!empty($data->id) && !empty($data->rol)) {
        try {
            // Validamos que el rol coincida con el ENUM ('admin', 'user')
            $rolPermitido = ($data->rol === 'admin' || $data->rol === 'user') ? $data->rol : null;

            if (!$rolPermitido) {
                http_response_code(400);
                echo json_encode(["message" => "El rol especificado no es válido para el sistema."]);
                exit();
            }

            $query = "UPDATE usuarios SET rol = :rol WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':rol', $rolPermitido);
            $stmt->bindParam(':id', $data->id);

            if ($stmt->execute()) {
                http_response_code(200);
                echo json_encode(["message" => "Rol actualizado con éxito."]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "No se pudo actualizar el rol."]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error de actualización: " . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Datos incompletos."]);
    }
}
?>