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

// 📥 EMITIR Y VALIDAR FACTURA ELECTRÓNICA ANTE LA DIAN (POST)
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));

    if (
        !empty($data->container_id) && 
        !empty($data->user_id) && 
        !empty($data->customer_name) && 
        !empty($data->customer_nit) && 
        isset($data->subtotal)
    ) {
        
        $subtotal = floatval($data->subtotal);
        $tax = $subtotal * 0.19; // IVA del 19%
        $total = $subtotal + $tax;
        $legalOrg = !empty($data->legal_organization_id) ? $data->legal_organization_id : 2; // 2 = Persona Natural

        // 🔑 1. SOLICITAR ACCESO OAUTH2 A FACTUS (Igual a getAccessToken en Laravel)
        $ch_auth = curl_init();
        curl_setopt_array($ch_auth, [
            CURLOPT_URL => "https://api-sandbox.factus.com.co/oauth/token",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_SSL_VERIFYPEER => false, // Evita fallos de SSL en local
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type'    => 'password',
                'client_id'     => 'a11c5cb8-203f-4b24-acf1-93df70027320',
                'client_secret' => 'QYSJ4VoBxi7ubxzaCSOMIBOFyxrQHIezNEwCJLCw',
                'username'      => 'sandbox@factus.com.co',
                'password'      => 'sandbox2024%'
            ])
        ]);
        
        $auth_res = json_decode(curl_exec($ch_auth));
        curl_close($ch_auth);
        
        if (!isset($auth_res->access_token)) {
            http_response_code(500);
            echo json_encode(["message" => "Error de autenticación con las credenciales de Factus."]);
            exit();
        }
        
        $token = $auth_res->access_token;
        $reference_code = "FAC-" . time() . "-" . uniqid();

        // 📝 2. PREPARAR EL JSON CON LA ESTRUCTURA QUE ADMITE FACTUS Y LA DIAN
        $factura_dian = [
            "document"           => "01",
            "numbering_range_id" => 8, // Rango por defecto de Sandbox
            "reference_code"     => $reference_code,
            "observation"        => "Factura por Servicio de Patio / Contenedor ID: " . $data->container_id,
            "payment_method_code"=> "10", // Efectivo
            "customer" => [
                "identification"             => $data->customer_nit,
                "names"                      => $data->customer_name,
                "address"                    => !empty($data->address) ? $data->address : "Zona Portuaria, Buenaventura",
                "email"                      => !empty($data->email) ? $data->email : "correo@cliente.com",
                "phone"                      => !empty($data->phone) ? $data->phone : "3000000000",
                "legal_organization_id"      => $legalOrg,
                "tribute_id"                 => "21", // IVA
                "identification_document_id" => !empty($data->identification_document_id) ? $data->identification_document_id : 3,
                "municipality_id"            => !empty($data->municipality_id) ? $data->municipality_id : 148 // Buenaventura ID
            ],
            "items" => [
                [
                    "code_reference"   => "REF-LOG1",
                    "name"             => "Servicio de Operación Logística y Almacenamiento de Contenedor",
                    "quantity"         => 1,
                    "discount_rate"    => "0.00",
                    "price"            => $subtotal,
                    "tax_rate"         => "19.00",
                    "unit_measure_id"  => 70, // Unidad
                    "standard_code_id" => 1,
                    "is_excluded"      => 0,
                    "tribute_id"       => 1
                ]
            ]
        ];

        // Añadir Dígito de Verificación si es Persona Jurídica (1)
        if ($legalOrg == 1 && !empty($data->dv)) {
            $factura_dian['customer']['dv'] = $data->dv;
        }

        // 🚀 3. ENVIAR FACTURA ELECTRÓNICA AL ENDPOINT /v1/bills/validate
        $ch_invoice = curl_init();
        curl_setopt_array($ch_invoice, [
            CURLOPT_URL => "https://api-sandbox.factus.com.co/v1/bills/validate",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS => json_encode($factura_dian),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $token,
                "Content-Type: application/json",
                "Accept: application/json"
            ]
        ]);
        
        $invoice_res = json_decode(curl_exec($ch_invoice), true);
        curl_close($ch_invoice);

        // 💾 4. VALIDAR RESPUESTA EXITOSA DE LA DIAN Y GUARDAR LOCALMENTE
        if (isset($invoice_res['data']['bill'])) {
            $bill = $invoice_res['data']['bill'];
            
            try {
                $query_local = "INSERT INTO invoices (container_id, user_id, reference_code, number, cufe, customer_name, customer_nit, total, public_url, status) 
                                VALUES (:container_id, :user_id, :reference_code, :number, :cufe, :customer_name, :customer_nit, :total, :public_url, 'validada')";
                
                $stmt = $pdo->prepare($query_local);
                $stmt->execute([
                    ':container_id'  => intval($data->container_id),
                    ':user_id'       => intval($data->user_id),
                    ':reference_code'=> $bill['reference_code'],
                    ':number'        => $bill['number'],
                    ':cufe'          => $bill['cufe'] ?? 'N/A',
                    ':customer_name' => $data->customer_name,
                    ':customer_nit'  => $data->customer_nit,
                    ':total'         => $bill['total'],
                    ':public_url'    => $bill['public_url'] ?? ''
                ]);

                http_response_code(201);
                echo json_encode([
                    "status" => "OK",
                    "message" => "¡Factura electrónica validada con éxito por la DIAN!",
                    "number" => $bill['number'],
                    "cufe" => $bill['cufe'] ?? 'N/A',
                    "public_url" => $bill['public_url'] ?? ''
                ]);

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(["message" => "Factura autorizada en Factus pero falló el registro local en MySQL: " . $e->getMessage()]);
            }
        } else {
            http_response_code(400);
            echo json_encode([
                "message" => "Factus o la DIAN rechazaron los datos.",
                "detalles" => $invoice_res
            ]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Datos incompletos para realizar la liquidación fiscal."]);
    }
}

// 📤 OBTENER HISTORIAL DE FACTURAS DIAN EMITIDAS (GET)
if ($method === 'GET') {
    try {
        $query = "SELECT i.*, c.code AS container_code FROM invoices i 
                  INNER JOIN containers c ON i.container_id = c.id 
                  ORDER BY i.id DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Error al traer facturas: " . $e->getMessage()]);
    }
}
?>