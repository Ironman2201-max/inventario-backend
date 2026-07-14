<?php

// ✅ CORS — debe ser lo primero, antes de require y de cualquier lógica
// Cambiar el asterisco por tu dominio real si quieres restringir el acceso solo a tu frontend:
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");



// ✅ Preflight — Angular manda OPTIONS antes del PUT real, hay que responderlo
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
require_once 'conexion.php';

$method = $_SERVER['REQUEST_METHOD'];

// 1. OBTENER TODOS LOS USUARIOS (GET)
if ($method === 'GET') {
    try {
        // Traemos el ID, nombre, correo y rol (evitando traer las contraseñas por seguridad)
        $query = "SELECT id, nombre, correo, rol FROM usuarios ORDER BY id DESC";
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
            $query = "UPDATE usuarios SET rol = :rol WHERE id = :id";
            $stmt = $pdo->prepare($query);
            
            $stmt->bindParam(':rol', $data->rol);
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
            echo json_encode(["message" => "Error en la base de datos: " . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Datos incompletos para actualizar."]);
    }
}
?>
