<?php
// ✅ CORS — Comunicación fluida con el frontend de Angular
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));

    $nombre   = isset($data->nombre) ? trim($data->nombre) : '';
    $cedula   = isset($data->cedula) ? trim($data->cedula) : '';
    $correo   = isset($data->correo) ? trim(strtolower($data->correo)) : '';
    $password = isset($data->password) ? trim($data->password) : '';

    // 🛑 1. Validar que no haya campos vacíos
    if (!empty($nombre) && !empty($cedula) && !empty($correo) && !empty($password)) {

        // 🆔 2. Validar que la cédula tenga exactamente 10 dígitos numéricos
        if (!preg_match('/^[0-9]{10}$/', $cedula)) {
            http_response_code(400);
            echo json_encode(["message" => "La cédula debe contener exactamente 10 dígitos numéricos."]);
            exit();
        }

        try {
            // 🔍 3. Validar duplicados de Correo o Cédula
            $check_query = "SELECT id FROM usuarios WHERE correo = :correo OR cedula = :cedula LIMIT 1";
            $stmt_check = $pdo->prepare($check_query);
            $stmt_check->bindParam(":correo", $correo);
            $stmt_check->bindParam(":cedula", $cedula);
            $stmt_check->execute();

            if ($stmt_check->rowCount() > 0) {
                http_response_code(400);
                echo json_encode(["message" => "El correo o la cédula ya se encuentran registrados."]);
                exit();
            }

            // 💾 4. Insertar nuevo usuario con la cédula incluida
            $query = "INSERT INTO usuarios (nombre, cedula, correo, password, rol) 
                      VALUES (:nombre, :cedula, :correo, :password, :rol)";
            $stmt = $pdo->prepare($query);

            $password_hashed = password_hash($password, PASSWORD_BCRYPT);
            
            // Forzar el rol a valores permitidos
            $rol = (isset($data->rol) && ($data->rol === 'admin' || $data->rol === 'user')) 
                ? $data->rol 
                : 'user';

            $stmt->bindParam(":nombre", $nombre);
            $stmt->bindParam(":cedula", $cedula);
            $stmt->bindParam(":correo", $correo);
            $stmt->bindParam(":password", $password_hashed);
            $stmt->bindParam(":rol", $rol);

            if ($stmt->execute()) {
                http_response_code(201);
                echo json_encode(["message" => "Usuario registrado exitosamente."]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "No se pudo registrar el usuario."]);
            }

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error interno en el servidor: " . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Todos los campos (nombre, cédula, correo y contraseña) son obligatorios."]);
    }
} else {
    http_response_code(405);
    echo json_encode(["message" => "Método no permitido."]);
}
?>