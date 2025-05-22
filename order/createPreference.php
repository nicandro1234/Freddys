<?php
// --- DEBUGGING: Mostrar errores (REMOVER EN PRODUCCIÓN) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- FIN DEBUGGING ---

// --- INTENTAR RESETEAR OPCACHE ---
if (function_exists('opcache_reset')) {
    opcache_reset();
    error_log('[MP CreatePreference] opcache_reset() llamado.');
} else {
    error_log('[MP CreatePreference] opcache_reset() no disponible.');
}
// --- FIN OPCACHE RESET ---

// Indicar que la respuesta será JSON
header('Content-Type: application/json');

// Permitir CORS (ajusta según tus necesidades de seguridad)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    // Puedes restringir orígenes específicos en producción
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}
// Access-Control headers are received during OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}

error_log('[MP CreatePreference] Script iniciado.'); // Log inicio

// --- VERIFICACIÓN EXPLÍCITA DEL AUTOLOADER ---
$autoloaderPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloaderPath)) {
    error_log('[MP CreatePreference] Verificación: vendor/autoload.php SÍ existe en: ' . $autoloaderPath);
} else {
    error_log('[MP CreatePreference] Verificación: vendor/autoload.php NO existe en: ' . $autoloaderPath);
    http_response_code(500);
    echo json_encode(['error' => 'Error interno crítico: Autoloader no encontrado.']);
    exit;
}
// --- FIN VERIFICACIÓN ---

// Incluir el autoloader de Composer y la configuración
try {
    require_once $autoloaderPath;
    error_log('[MP CreatePreference] Autoloader incluido correctamente (require_once ejecutado).');

    // --- VERIFICAR EXISTENCIA DE CLASE INMEDIATAMENTE ---
    if (class_exists('\MercadoPago\SDK', false)) {
        error_log('[MP CreatePreference] Verificación Post-Autoload: Clase \MercadoPago\SDK SÍ encontrada por class_exists().');
    } else {
        error_log('[MP CreatePreference] Verificación Post-Autoload: Clase \MercadoPago\SDK NO encontrada por class_exists(). ¡Este es el problema!');
        // Loguear include_path por si acaso
        error_log('[MP CreatePreference] Include Path: ' . get_include_path());
        // Intentar cargarla manualmente para ver si da otro error?
        // $sdkPath = __DIR__ . '/../vendor/mercadopago/dx-php/src/MercadoPago/SDK.php';
        // if(file_exists($sdkPath)) { require_once $sdkPath; error_log('SDK.php incluido manualmente'); }
    }
    // --- FIN VERIFICACIÓN CLASE ---

    require_once __DIR__ . '/../config.php'; // Carga las constantes como MP_ACCESS_TOKEN
    error_log('[MP CreatePreference] config.php incluido.');
} catch (Throwable $e) { // Capturar errores de include/require
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor al cargar dependencias.', 'details' => $e->getMessage()]);
    error_log('[MP CreatePreference] Error fatal al incluir archivos: ' . $e->getMessage());
    exit;
}

// Verificar que el Access Token esté definido
if (!defined('MP_ACCESS_TOKEN') || empty(MP_ACCESS_TOKEN)) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor: Access Token no configurado.']);
    error_log('[MP CreatePreference] MP_ACCESS_TOKEN no está definido.');
    exit;
}
error_log('[MP CreatePreference] Access Token verificado.');

// Leer el cuerpo de la solicitud JSON
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Validar datos de entrada básicos y SANITIZAR
if (!$data || !isset($data['cart']) || !is_array($data['cart']) || empty($data['cart']) || !isset($data['name']) || !isset($data['address']) || !isset($data['phone'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos incompletos o inválidos.']);
    error_log('[MP CreatePreference] Datos de entrada inválidos: ' . $json_data);
    exit;
}
error_log('[MP CreatePreference] Datos de entrada recibidos.');

$cart_items = $data['cart'];
$customer_name = htmlspecialchars($data['name'], ENT_QUOTES, 'UTF-8');
$customer_address = htmlspecialchars($data['address'], ENT_QUOTES, 'UTF-8');
$customer_phone = preg_replace('/[^0-9+\-\s()]/', '', $data['phone']);

// --- NUEVO: Obtener datos de envío y programación ---
$delivery_type = $data['delivery_type'] ?? 'asap';
$scheduled_datetime_str = $data['scheduled_datetime'] ?? null;
$delivery_fee = isset($data['delivery_fee']) ? floatval($data['delivery_fee']) : 0.00;
$delivery_zone_id = isset($data['delivery_zone_id']) ? intval($data['delivery_zone_id']) : null;
$distance_km = isset($data['distance_km']) ? floatval($data['distance_km']) : null;
// --- FIN NUEVO ---

error_log('[MP CreatePreference] Datos sanitizados y de entrega/programación obtenidos.');

\MercadoPago\SDK::setAccessToken(MP_ACCESS_TOKEN);
$preference = new \MercadoPago\Preference();

$items_array = [];
$total_amount_items = 0;
foreach ($cart_items as $cart_item) {
    if (!isset($cart_item['name']) || !isset($cart_item['quantity']) || !isset($cart_item['price'])) {
        error_log('[MP CreatePreference] Item inválido saltado: ' . json_encode($cart_item));
        continue; 
    }
    
    $item = new \MercadoPago\Item(); // Usar nombre completo
    $item->id = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $cart_item['originalId'] ?? uniqid('item_', true)), 0, 36); // ID único más robusto
    $item->title = mb_substr(htmlspecialchars($cart_item['name'], ENT_QUOTES, 'UTF-8'), 0, 250); // Sanitizar y limitar título
    $item->quantity = intval($cart_item['quantity']);
    $item->unit_price = floatval($cart_item['price']);
    $item->currency_id = "MXN"; 
    $items_array[] = $item;
    $total_amount_items += $item->quantity * $item->unit_price;
}

// --- NUEVO: Considerar la tarifa de envío en el total para MP si es necesario ---
// Por lo general, MercadoPago prefiere que el total de los items coincida con el monto a pagar.
// Si la tarifa de envío se cobra aparte o se maneja como un item más, ajustar aquí.
// Por ahora, asumimos que la tarifa de envío NO se añade como un item separado a MP,
// sino que el total del pedido ya la incluye y se pasa al webhook para el guardado final.
// $total_amount_for_mp = $total_amount_items + $delivery_fee; // Ejemplo si se necesitara sumar aquí

if (empty($items_array)) {
    http_response_code(400);
    echo json_encode(['error' => 'El carrito está vacío o los items son inválidos.']);
     error_log('[MP CreatePreference] No se pudieron formatear items válidos desde: ' . json_encode($cart_items));
    exit;
}
error_log('[MP CreatePreference] Items formateados: ' . count($items_array));

$preference->items = $items_array;

$payer = new \MercadoPago\Payer(); // Usar nombre completo
$name_parts = explode(' ', $customer_name, 2);
$payer->name = $name_parts[0];
$payer->surname = $name_parts[1] ?? ''; 
$payer->phone = array(
    "number" => $customer_phone
);
$preference->payer = $payer;
error_log('[MP CreatePreference] Payer configurado.');

// --- NUEVO: Añadir metadata con información de programación y envío ---
$metadata_array = [
    'customer_name' => $customer_name, // Guardar nombre aquí para el webhook
    'customer_phone' => $customer_phone, // Guardar teléfono aquí para el webhook
    'customer_address' => $customer_address, // Guardar dirección aquí para el webhook
    'delivery_type' => $delivery_type,
    'scheduled_datetime' => $scheduled_datetime_str, // Enviar como string
    'delivery_fee' => $delivery_fee,
    'delivery_zone_id' => $delivery_zone_id,
    'distance_km' => $distance_km,
    'original_cart_items' => $cart_items // Enviar el carrito original también por si se necesita en el webhook
];
$preference->metadata = $metadata_array;
error_log('[MP CreatePreference] Metadata configurada: ' . json_encode($metadata_array));
// --- FIN NUEVO ---

// --- Opcional: URLs de Redirección --- 
/*
$preference->back_urls = array(
    "success" => "https://freddys.com.mx/pago_exitoso.php",
    "failure" => "https://freddys.com.mx/pago_fallido.php",
    "pending" => "https://freddys.com.mx/pago_pendiente.php"
);
$preference->auto_return = "approved"; // Retornar automáticamente solo si el pago es aprobado
*/

// --- Opcional: URL de Notificaciones (Webhook) --- 
// Para recibir notificaciones del estado del pago en tu backend
// $preference->notification_url = "https://freddys.com.mx/webhook_mercado_pago.php";

// --- Opcional: Datos Adicionales --- 
// $preference->external_reference = "PEDIDO_INTERNO_123"; // ID de tu sistema

try {
    error_log('[MP CreatePreference] Intentando guardar preferencia...');
    $preference->save();
    error_log('[MP CreatePreference] Preferencia guardada (o intentado). Verificando ID...');

    // --- Verificación Robusta del ID --- 
    if (isset($preference->id) && !empty($preference->id)) { 
        // Éxito: El ID existe y no está vacío
        echo json_encode(['preferenceId' => $preference->id]);
        error_log('[MP CreatePreference] Éxito: Preferencia creada: ID ' . $preference->id);
    } else {
        // Error: No se obtuvo un ID válido
        http_response_code(500);
        // Loguear la última respuesta SOLO si existe, para evitar warnings
        $errorDetails = 'ID de preferencia no encontrado o vacío.';
        if (isset($preference->last_response)) {
            $errorDetails .= ' Respuesta de MP: ' . json_encode($preference->last_response);
            error_log('[MP CreatePreference] Error al guardar preferencia. Respuesta de MP: ' . json_encode($preference->last_response));
        } else {
            error_log('[MP CreatePreference] Error al guardar preferencia. ID no encontrado y $last_response no definida.');
        }
        echo json_encode(['error' => 'No se pudo obtener el ID de la preferencia desde Mercado Pago.', 'details' => $errorDetails]);
    }
    // --- Fin Verificación Robusta ---

} catch (\Throwable $e) { // Usar barra inicial para Throwable global
    http_response_code(500);
    $error_message = 'Error al comunicarse con Mercado Pago: ' . $e->getMessage();
    echo json_encode(['error' => $error_message]);
    error_log('[MP CreatePreference] Excepción al guardar preferencia: ' . $e->getMessage() . "\nStack trace:\n" . $e->getTraceAsString());
}

?> 