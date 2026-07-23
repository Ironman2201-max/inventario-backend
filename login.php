<?php
// ✅ CORS — Control de accesos sin bloqueos para Angular
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

    if (!empty($data->correo) && !empty($data->password)) {
        try {
            $correo = trim(strtolower($data->correo));

            // 🔍 Agregamos 'cedula' en el SELECT
            $query = "SELECT id, nombre, cedula, correo, password, rol FROM usuarios WHERE correo = :correo LIMIT 1";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(":correo", $correo);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Verificar la contraseña encriptada con BCRYPT
                if (password_verify($data->password, $user['password'])) {
                    
                    // Removemos la contraseña por seguridad antes de responder
                    unset($user['password']);
                    
                    http_response_code(200);
                    echo json_encode([
                        "message" => "Acceso concedido.",
                        "usuario" => $user // 👈 Ahora $user ya contiene: id, nombre, cedula, correo y rol
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode(["message" => "La contraseña ingresada es incorrecta."]);
                }
            } else {
                http_response_code(404);
                echo json_encode(["message" => "El usuario no existe en el sistema."]);
            }

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error de base de datos en XAMPP: " . $e->getMessage()]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Datos incompletos. Por favor ingresa correo y contraseña."]);
    }
} else {
    http_response_code(405);
    echo json_encode(["message" => "Método HTTP no permitido."]);
}
?>