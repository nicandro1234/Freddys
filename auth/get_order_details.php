<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Para depuración
// ini_set('display_errors', 1);
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/php-error.log');
// error_reporting(E_ALL);
// error_log("[get_order_details.php PDO] INICIO DEL SCRIPT - " . date("Y-m-d H:i:s"));

require_once __DIR__ . '/../db.php'; // db.php está en la raíz
require_once __DIR__ . '/functions.php'; // Corregido: functions.php está en el mismo directorio auth/

try {
    if (!isset($_SESSION['user']['id'])) {
        // error_log("[get_order_details.php PDO] Usuario no autenticado.");
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado', 'order' => null]);
        exit;
    }
    $user_google_id = $_SESSION['user']['id'];

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        // error_log("[get_order_details.php PDO] ID de pedido no válido.");
        echo json_encode(['success' => false, 'message' => 'ID de pedido no válido', 'order' => null]);
        exit;
    }
    $order_id = (int)$_GET['id'];

    // error_log("[get_order_details.php PDO] Consultando pedido ID: $order_id para usuario Google ID: $user_google_id");

    $sql = "SELECT 
                id,
                created_at as order_date_raw, -- Mantener el raw para DateTime
                total_amount,
                status,
                payment_method,
                shipping_address, -- Asumimos que es un JSON o un string que decodificaremos después
                shipping_phone,
                order_details, -- JSON de ítems y posiblemente otra info
                estimated_delivery_time, -- Añadido, puede ser NULL
                delivery_fee -- Añadido, puede ser NULL
            FROM orders 
            WHERE id = :order_id AND user_id = :user_google_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':order_id' => $order_id,
        ':user_google_id' => $user_google_id
    ]);
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        // error_log("[get_order_details.php PDO] Pedido no encontrado o no pertenece al usuario.");
        echo json_encode(['success' => false, 'message' => 'Pedido no encontrado o no pertenece al usuario', 'order' => null]);
        exit;
    }

    // Formatear la fecha
    try {
        $date = new DateTime($order['order_date_raw']);
        $order['order_date_formatted'] = $date->format('d/m/Y H:i');
    } catch (Exception $e) {
        $order['order_date_formatted'] = 'Fecha inválida';
    }

    // Decodificar order_details (ítems del pedido)
    $order['items'] = []; // Inicializar como array vacío
    if (!empty($order['order_details'])) {
        $decoded_items = json_decode($order['order_details'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($decoded_items['items']) && is_array($decoded_items['items'])) {
                $order['items'] = $decoded_items['items'];
            } elseif (is_array($decoded_items)) { // Si el JSON raíz es el array de items
                $order['items'] = $decoded_items;
            }
        } else {
            // error_log("[get_order_details.php PDO] Error decodificando JSON de order_details para pedido ID " . $order['id'] . ": " . json_last_error_msg());
        }
    }
    // unset($order['order_details']); // Opcional

    // Decodificar shipping_address si es JSON
    $order['shipping_address_details'] = null; // Inicializar
    if (isset($order['shipping_address']) && !empty(trim($order['shipping_address']))) {
        $shipping_info_decoded = json_decode($order['shipping_address'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($shipping_info_decoded)) {
            // Si es un JSON y tiene 'address_line' o podemos construir una.
            if (!empty($shipping_info_decoded['address_line'])) {
                 $order['shipping_address_details'] = $shipping_info_decoded; // Asumimos que ya tiene la estructura deseada.
            } else {
                // Intenta construir una cadena descriptiva a partir de componentes comunes si existen
                $address_parts = [];
                if (!empty($shipping_info_decoded['street'])) $address_parts[] = $shipping_info_decoded['street'];
                if (!empty($shipping_info_decoded['number_ext'])) $address_parts[] = $shipping_info_decoded['number_ext']; // o number_int_ext
                if (!empty($shipping_info_decoded['colonia'])) $address_parts[] = $shipping_info_decoded['colonia'];
                if (!empty($shipping_info_decoded['city'])) $address_parts[] = $shipping_info_decoded['city'];
                if (!empty($shipping_info_decoded['state'])) $address_parts[] = $shipping_info_decoded['state'];
                if (!empty($shipping_info_decoded['zip_code'])) $address_parts[] = $shipping_info_decoded['zip_code'];
                
                if (!empty($address_parts)) {
                    $order['shipping_address_details'] = ['address_line' => implode(', ', $address_parts)];
                } else {
                     // Si no se pueden construir partes, usar el string original si el JSON no fue útil
                     $order['shipping_address_details'] = ['address_line' => $order['shipping_address']];
                }
            }
        } else {
            // No es un JSON válido, tratar como un string de dirección simple
            $order['shipping_address_details'] = ['address_line' => $order['shipping_address']];
        }
    }

    // Formatear montos
    $order['total_amount_formatted'] = number_format((float)$order['total_amount'], 2, '.', ',');
    $order['delivery_fee_formatted'] = number_format((float)($order['delivery_fee'] ?? 0), 2, '.', ',');

    // Traducir el estado
    $order['status_original'] = $order['status'];
    switch(strtolower($order['status'])) {
        case 'pending': $order['status_translated'] = 'Pendiente'; break;
        case 'processing': $order['status_translated'] = 'En preparación'; break;
        case 'shipped': $order['status_translated'] = 'En camino'; break;
        case 'delivered': $order['status_translated'] = 'Entregado'; break;
        case 'completed': $order['status_translated'] = 'Completado'; break;
        case 'cancelled': $order['status_translated'] = 'Cancelado'; break;
        default: $order['status_translated'] = ucfirst($order['status']); break;
    }
    
    // error_log("[get_order_details.php PDO] Detalles del pedido recuperados: " . json_encode($order));
    echo json_encode([
        'success' => true,
        'order' => $order
    ]);

} catch (PDOException $e) {
    // error_log("[get_order_details.php PDO] PDOException: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos al obtener los detalles del pedido.',
        'order' => null
    ]);
} catch (Exception $e) {
    // error_log("[get_order_details.php PDO] Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener los detalles del pedido: ' . esc_html($e->getMessage()),
        'order' => null
    ]);
}
?> 