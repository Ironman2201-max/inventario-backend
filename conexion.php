<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Datos de MySQL en el VPS / Local
//$host     = "localhost";
//$db_name  = "semi1_sgp_prod";
//$username = "semi1_sgp";
//$password = '$3m1nar10Sgp';

// Datos de MySQL en el VPS
$host = "localhost";
$db_name = "angular_auth_db";
$username = "root";
$password = ''; 

try {
    $pdo = new PDO("mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Configurar fetch por defecto como array asociativo
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $exception) {
    http_response_code(500);
    echo json_encode(["error" => "Error de conexión: " . $exception->getMessage()]);
    exit();
}

// Función auxiliar para cerrar la conexión de forma limpia en otros archivos
function cerrarConexion(&$pdo) {
    $pdo = null;
}
?>


