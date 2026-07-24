<?php
// ✅ CORS — Configuración de cabeceras para Angular
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

// ------------------------------------------------------------------
// 📥 1. EMITIR Y VALIDAR FACTURA ELECTRÓNICA ANTE LA DIAN (POST)
// ------------------------------------------------------------------
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"));

    if (
        !empty($data->container_id) &&
        !empty($data->user_id) &&
        !empty($data->customer_name) &&
        !empty($data->customer_nit) &&
        isset($data->subtotal)
    ) {

        $user_id      = intval($data->user_id);
        $container_id = intval($data->container_id);

        // ✅ 0. VALIDAR QUE EL USUARIO EXISTA EN LA BD LOCAL
        try {
            $stmt_user = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id");
            $stmt_user->execute([':id' => $user_id]);
            if (!$stmt_user->fetch()) {
                http_response_code(422);
                echo json_encode(["message" => "El usuario indicado (ID: $user_id) no existe en el sistema local."]);
                $pdo = null;
                exit();
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error al validar el usuario local: " . $e->getMessage()]);
            $pdo = null;
            exit();
        }

        // En facturacion.php reemplazar el bloque de validación del contenedor por este:

        // ✅ 0.1 VALIDAR QUE EL CONTENEDOR EXISTA Y TENGA SALIDA REGISTRADA
        try {
            $stmt_container = $pdo->prepare("SELECT id, exit_date FROM containers WHERE id = :id");
            $stmt_container->execute([':id' => $container_id]);
            $contenedor = $stmt_container->fetch();

            if (!$contenedor) {
                http_response_code(422);
                echo json_encode(["message" => "El contenedor indicado (ID: $container_id) no existe en el sistema."]);
                $pdo = null;
                exit();
            }

            if (is_null($contenedor['exit_date'])) {
                http_response_code(422);
                echo json_encode([
                    "message" => "INCOMPATIBLE: El contenedor aún está activo en patio. Debe registrar su SALIDA en el módulo de despacho antes de emitir la factura fiscal."
                ]);
                $pdo = null;
                exit();
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(["message" => "Error al validar el estado del contenedor: " . $e->getMessage()]);
            $pdo = null;
            exit();
        }

        // Configuración de montos y tipos de personería
        $subtotal = floatval($data->subtotal);
        $tax      = $subtotal * 0.19; // IVA 19%
        $total    = $subtotal + $tax;
        $legalOrg = !empty($data->legal_organization_id) ? intval($data->legal_organization_id) : 2; // 2 = Persona Natural

        // ✅ 0.2 LIMPIEZA Y VALIDACIÓN DEL FORMATO DEL NIT / CÉDULA
        $nit_limpio = preg_replace('/[^0-9]/', '', $data->customer_nit);
        if (strlen($nit_limpio) < 5 || strlen($nit_limpio) > 15) {
            http_response_code(422);
            echo json_encode([
                "message" => "El NIT/Cédula del cliente debe tener entre 5 y 15 dígitos numéricos (sin puntos, guiones o espacios)."
            ]);
            $pdo = null;
            exit();
        }
        $data->customer_nit = $nit_limpio;

        // 🔑 1. SOLICITAR ACCESO OAUTH2 A FACTUS
        $ch_auth = curl_init();
        curl_setopt_array($ch_auth, [
            CURLOPT_URL            => "https://api-sandbox.factus.com.co/oauth/token",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'password',
                'client_id'     => getenv('FACTUS_CLIENT_ID') ?: 'a11c5cb8-203f-4b24-acf1-93df70027320',
                'client_secret' => getenv('FACTUS_CLIENT_SECRET') ?: 'QYSJ4VoBxi7ubxzaCSOMIBOFyxrQHIezNEwCJLCw',
                'username'      => getenv('FACTUS_USERNAME') ?: 'sandbox@factus.com.co',
                'password'      => getenv('FACTUS_PASSWORD') ?: 'sandbox2024%'
            ])
        ]);

        $raw_auth_res = curl_exec($ch_auth);
        $curl_auth_err = curl_error($ch_auth);
        $auth_http_code = curl_getinfo($ch_auth, CURLINFO_HTTP_CODE);
        curl_close($ch_auth);

        if ($curl_auth_err) {
            http_response_code(500);
            echo json_encode([
                "message" => "Error de conexión cURL al intentar autenticar con Factus: " . $curl_auth_err
            ]);
            $pdo = null;
            exit();
        }

        $auth_res = json_decode($raw_auth_res);

        if ($auth_http_code !== 200 || !isset($auth_res->access_token)) {
            http_response_code(500);
            echo json_encode([
                "message"          => "Fallo de autenticación con el servidor de Factus (HTTP $auth_http_code).",
                "respuesta_factus" => $auth_res ?? $raw_auth_res
            ]);
            $pdo = null;
            exit();
        }

        $token = $auth_res->access_token;
        $reference_code = "FAC-" . time() . "-" . uniqid();

        // 📝 2. PREPARAR EL ESTRUCTURA JSON QUE ADMITE FACTUS Y LA DIAN
        $factura_dian = [
            "document"            => "01",
            "numbering_range_id"  => 8,
            "reference_code"      => $reference_code,
            "observation"         => "Factura por Servicio de Patio / Contenedor ID: " . $container_id,
            "payment_method_code" => "10",
            "customer" => [
                "identification"             => $data->customer_nit,
                "names"                      => trim($data->customer_name),
                "address"                    => !empty($data->address) ? trim($data->address) : "Zona Portuaria, Buenaventura",
                "email"                      => !empty($data->email) ? trim($data->email) : "correo@cliente.com",
                "phone"                      => !empty($data->phone) ? trim($data->phone) : "3000000000",
                "legal_organization_id"      => $legalOrg,
                "tribute_id"                 => "21",
                "identification_document_id" => !empty($data->identification_document_id) ? intval($data->identification_document_id) : ($legalOrg === 1 ? 3 : 1),
                "municipality_id"            => !empty($data->municipality_id) ? intval($data->municipality_id) : 148
            ],
            "items" => [
                [
                    "code_reference"   => "REF-LOG1",
                    "name"             => "Servicio de Operación Logística y Almacenamiento de Contenedor",
                    "quantity"         => 1,
                    "discount_rate"    => "0.00",
                    "price"            => $subtotal,
                    "tax_rate"         => "19.00",
                    "unit_measure_id"  => 70,
                    "standard_code_id" => 1,
                    "is_excluded"      => 0,
                    "tribute_id"       => 1
                ]
            ]
        ];

        // Se agrega el Dígito de Verificación solo si es Persona Jurídica
        if ($legalOrg === 1 && !empty($data->dv)) {
            $factura_dian['customer']['dv'] = trim($data->dv);
        }

        // 🚀 3. TRANSMITIR EL JSON A FACTUS EN EL ENDPOINT /v1/bills/validate
        $ch_invoice = curl_init();
        curl_setopt_array($ch_invoice, [
            CURLOPT_URL            => "https://api-sandbox.factus.com.co/v1/bills/validate",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_POSTFIELDS     => json_encode($factura_dian),
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer " . $token,
                "Content-Type: application/json",
                "Accept: application/json"
            ]
        ]);

        $raw_invoice_res = curl_exec($ch_invoice);
        $curl_inv_err = curl_error($ch_invoice);
        $inv_http_code = curl_getinfo($ch_invoice, CURLINFO_HTTP_CODE);
        curl_close($ch_invoice);

        if ($curl_inv_err) {
            http_response_code(500);
            echo json_encode([
                "message" => "Error cURL al transmitir la factura a Factus: " . $curl_inv_err
            ]);
            $pdo = null;
            exit();
        }

        $invoice_res = json_decode($raw_invoice_res, true);

        // 💾 4. GUARDAR FACTURA EN BASE DE DATOS SI FACTUS RESPONDIÓ EXITOSAMENTE
        if (isset($invoice_res['data']['bill'])) {
            $bill = $invoice_res['data']['bill'];

            try {
                $query_local = "INSERT INTO invoices (container_id, user_id, reference_code, number, cufe, customer_name, customer_nit, total, public_url, status)
                                VALUES (:container_id, :user_id, :reference_code, :number, :cufe, :customer_name, :customer_nit, :total, :public_url, 'validada')";
                $stmt = $pdo->prepare($query_local);
                $stmt->execute([
                    ':container_id'  => $container_id,
                    ':user_id'       => $user_id,
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
                    "status"     => "OK",
                    "message"    => "¡Factura electrónica validada con éxito por la DIAN!",
                    "number"     => $bill['number'],
                    "cufe"       => $bill['cufe'] ?? 'N/A',
                    "public_url" => $bill['public_url'] ?? ''
                ]);

            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    "message"        => "La factura fue aprobada por la DIAN pero falló al guardarse localmente en MySQL.",
                    "reference_code" => $bill['reference_code'],
                    "number"         => $bill['number'],
                    "cufe"           => $bill['cufe'] ?? 'N/A',
                    "error_local"    => $e->getMessage()
                ]);
            }
        } else {
            // 🛑 Respuesta de Rechazo de Factus/DIAN
            http_response_code(400);
            echo json_encode([
                "message"         => "Factus o la DIAN rechazaron la factura electrónica (HTTP $inv_http_code).",
                "detalles_factus" => $invoice_res ?? $raw_invoice_res
            ]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Datos incompletos para realizar la facturación."]);
    }

    $pdo = null;
    exit();
}

// ------------------------------------------------------------------
// 📤 2. OBTENER HISTORIAL DE FACTURAS DIAN EMITIDAS (GET)
// ------------------------------------------------------------------
if ($method === 'GET') {
    try {
        $query = "SELECT i.*, c.code AS container_code, u.nombre AS operador_nombre
                  FROM invoices i
                  INNER JOIN containers c ON i.container_id = c.id
                  LEFT JOIN usuarios u ON i.user_id = u.id
                  ORDER BY i.id DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Error al obtener el historial de facturas: " . $e->getMessage()]);
    }

    $pdo = null;
    exit();
}
?>