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

    if (!empty($data->correo) && !empty($data->password)) {
        
        $query = "SELECT id, nombre, correo, password, rol FROM usuarios WHERE correo = :correo LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(":correo", $data->correo);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verificar contraseña
            if (password_verify($data->password, $row['password'])) {
                
                // No enviamos la contraseña de vuelta al cliente
                unset($row['password']);
                
                http_response_code(200);
                echo json_encode([
                    "message" => "Acceso concedido.",
                    "usuario" => $row
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
