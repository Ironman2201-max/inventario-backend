<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Si es una petición OPTIONS (Preflight), responder 200 y cortar ejecución
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));

    if (!empty($data->nombre) && !empty($data->correo) && !empty($data->password)) {
        
        // Verificar si el correo ya existe
        $check_query = "SELECT id FROM usuarios WHERE correo = :correo LIMIT 1";
        $stmt_check = $pdo->prepare($check_query);
        $stmt_check->bindParam(":correo", $data->correo);
        $stmt_check->execute();

        if ($stmt_check->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(["message" => "El correo ya está registrado."]);
            exit();
        }

        // Insertar usuario
        $query = "INSERT INTO usuarios (nombre, correo, password, rol) VALUES (:nombre, :correo, :password, :rol)";
        $stmt = $pdo->prepare($query);

        $stmt->bindParam(":nombre", $data->nombre);
        $stmt->bindParam(":correo", $data->correo);
        
        // Encriptar contraseña
        $password_hashed = password_hash($data->password, PASSWORD_BCRYPT);
        $stmt->bindParam(":password", $password_hashed);
        
        // Asignar rol por defecto 'user' si no se envía uno válido
        $rol = (isset($data->rol) && ($data->rol === 'admin' || $data->rol === 'user')) ? $data->rol : 'user';
        $stmt->bindParam(":rol", $rol);

        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode(["message" => "Usuario registrado exitosamente."]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "No se pudo registrar el usuario."]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Datos incompletos."]);
    }
}
?>
