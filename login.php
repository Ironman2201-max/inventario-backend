<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'conexion.php';
require_once __DIR__ . '/vendor/autoload.php'; // Composer autoload para firebase/php-jwt

use Firebase\JWT\JWT;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"));

    if (!empty($data->correo) && !empty($data->password)) {

        $query = "SELECT id, nombre, correo, password, rol FROM usuarios WHERE correo = :correo LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(":correo", $data->correo);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($data->password, $row['password'])) {

                unset($row['password']);

                // Generar el JWT con los datos mínimos necesarios
                $secret = getenv('JWT_SECRET');
                if (!$secret) {
                    http_response_code(500);
                    echo json_encode(["message" => "Error de configuración del servidor."]);
                    exit();
                }

                $issuedAt = time();
                $expira   = $issuedAt + 3600; // 1 hora

                $payload = [
                    "iat" => $issuedAt,
                    "exp" => $expira,
                    "id"  => $row['id'],
                    "rol" => $row['rol']
                ];

                $jwtToken = JWT::encode($payload, $secret, 'HS256');

                http_response_code(200);
                echo json_encode([
                    "message" => "Acceso concedido.",
                    "usuario" => $row,
                    "token"   => $jwtToken
                ]);
            } else {
                http_response_code(401);
                echo json_encode(["message" => "Contraseña incorrecta."]);
            }
        } else {
            http_response_code(404);
            echo json_encode(["message" => "El usuario no existe."]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Datos incompletos."]);
    }
}
?>
