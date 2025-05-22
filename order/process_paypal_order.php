<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../admin/adminconfig.php'; // Para $pdo y configuraciones
require_once __DIR__ . '/../admin/classes/Order.php'; // Asumiendo que tienes una clase Order
require_once __DIR__ . '/../admin/classes/SendWhatsapp.php'; // Para enviar notificaciones

$response = ['success' => false, 'message' => 'Error desconocido al procesar el pedido.', 'order_id' => null, 'redirect_url' => null];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validar datos esenciales
if (empty($input['paypal_order_id']) || empty($input['cart']) || empty($input['customer_name']) || empty($input['customer_address']) || empty($input['customer_phone']) || !isset($input['total_amount'])) {
    $response['message'] = 'Datos incompletos para procesar el pedido.';
    echo json_encode($response);
    exit;
}

$paypal_order_id = filter_var($input['paypal_order_id'], FILTER_SANITIZE_STRING); // Se usará para log o referencia, no para columna BD directa por ahora
$paypal_status = filter_var($input['paypal_status'], FILTER_SANITIZE_STRING); // Se usará para log o referencia
$cart_items = $input['cart']; 
$customer_name = filter_var($input['customer_name'], FILTER_SANITIZE_STRING);
$customer_address = filter_var($input['customer_address'], FILTER_SANITIZE_STRING);
$customer_phone = filter_var($input['customer_phone'], FILTER_SANITIZE_STRING);
$total_amount = filter_var($input['total_amount'], FILTER_VALIDATE_FLOAT);

$delivery_type = $input['delivery_type'] ?? 'asap';
$scheduled_datetime_str = $input['scheduled_datetime'] ?? null;

$is_scheduled = ($delivery_type === 'scheduled' && !empty($scheduled_datetime_str)) ? 1 : 0;
$estimated_delivery_time = null;

if ($is_scheduled) {
    try {
        $scheduled_dt_obj = new DateTime($scheduled_datetime_str);
        $estimated_delivery_time = $scheduled_dt_obj->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        $response['message'] = 'Fecha de programación inválida.';
        error_log("Error al parsear scheduled_datetime en process_paypal_order: " . $e->getMessage());
        echo json_encode($response);
        exit;
    }
}

$delivery_fee = isset($input['delivery_fee']) ? filter_var($input['delivery_fee'], FILTER_VALIDATE_FLOAT) : 0.00;
$delivery_zone_id = isset($input['delivery_zone_id']) ? filter_var($input['delivery_zone_id'], FILTER_VALIDATE_INT) : null;
$distance_km = isset($input['distance_km']) ? filter_var($input['distance_km'], FILTER_VALIDATE_FLOAT) : null;

$order_details_json = json_encode(['items' => $cart_items, 'paypal_order_id' => $paypal_order_id, 'paypal_status' => $paypal_status]); // Incluir IDs de PayPal en details

try {
    $pdo->beginTransaction();

    $sql = "INSERT INTO orders (
                user_id, order_details, total_amount, status, payment_method, 
                shipping_address, shipping_phone, 
                is_scheduled, estimated_delivery_time,
                delivery_fee, delivery_zone_id, distance_km, 
                created_at, updated_at
            ) VALUES (
                :user_id, :order_details, :total_amount, :status, :payment_method, 
                :shipping_address, :shipping_phone,
                :is_scheduled, :estimated_delivery_time,
                :delivery_fee, :delivery_zone_id, :distance_km,
                NOW(), NOW()
            )";
    
    $stmt = $pdo->prepare($sql);

    session_start();
    $user_id = $_SESSION['user_id'] ?? null; 
    $order_status = 'processing';

    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
    $stmt->bindParam(':order_details', $order_details_json, PDO::PARAM_STR);
    $stmt->bindParam(':total_amount', $total_amount, PDO::PARAM_STR);
    $stmt->bindParam(':status', $order_status, PDO::PARAM_STR);
    $stmt->bindParam(':payment_method', 'paypal', PDO::PARAM_STR);
    $stmt->bindParam(':shipping_address', $customer_address, PDO::PARAM_STR);
    $stmt->bindParam(':shipping_phone', $customer_phone, PDO::PARAM_STR);
    $stmt->bindParam(':is_scheduled', $is_scheduled, PDO::PARAM_INT);
    $stmt->bindParam(':estimated_delivery_time', $estimated_delivery_time, $is_scheduled ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindParam(':delivery_fee', $delivery_fee, PDO::PARAM_STR);
    $stmt->bindParam(':delivery_zone_id', $delivery_zone_id, $delivery_zone_id ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->bindParam(':distance_km', $distance_km, $distance_km ? PDO::PARAM_STR : PDO::PARAM_NULL);

    if ($stmt->execute()) {
        $order_id = $pdo->lastInsertId();
        $pdo->commit();

        $response['success'] = true;
        $response['message'] = 'Pedido procesado exitosamente con PayPal.';
        $response['order_id'] = $order_id;
        $response['redirect_url'] = '/order_confirmation.php?order_id=' . $order_id . '&payment_method=paypal';

        $whatsapp_message = "Nuevo pedido #{$order_id} (PayPal):
";
        $whatsapp_message .= "Cliente: {$customer_name}
";
        $whatsapp_message .= "Tel: {$customer_phone}
";
        $whatsapp_message .= "Dirección: {$customer_address}
";
        $whatsapp_message .= "Total: $" . number_format($total_amount, 2) . "
";
        if ($is_scheduled && $estimated_delivery_time) {
            $whatsapp_message .= "Programado para: " . date('d/m/Y h:i A', strtotime($estimated_delivery_time)) . "
";
        } else {
            $whatsapp_message .= "Tipo de entrega: Lo antes posible
";
        }
        $whatsapp_message .= "Detalles:
";
        foreach ($cart_items as $item) {
            $whatsapp_message .= "- {$item['quantity']}x {$item['name']} ($" . number_format($item['price'], 2) . " c/u)
";
        }
        if ($delivery_fee > 0) {
            $whatsapp_message .= "Costo de envío: $" . number_format($delivery_fee, 2) . "
";
        }
        $whatsapp_message .= "ID Transacción PayPal: {$paypal_order_id}";

        if (class_exists('SendWhatsapp')) {
            $whatsappSender = new SendWhatsapp();
            $adminPhoneNumber = ADMIN_WHATSAPP_NUMBER ?? '';
            if ($adminPhoneNumber) {
                 $whatsapp_sent = $whatsappSender->send($adminPhoneNumber, $whatsapp_message);
                 if (!$whatsapp_sent) {
                     error_log("Error al enviar WhatsApp para pedido {$order_id}");
                 }
            } else {
                error_log("Número de WhatsApp del administrador no configurado. No se envió notificación para pedido {$order_id}.");
            }
        } else {
            error_log("Clase SendWhatsapp no encontrada. No se envió notificación para pedido {$order_id}.");
        }

    } else {
        $pdo->rollBack();
        $response['message'] = 'Error al guardar el pedido en la base de datos.';
        error_log("Error en PDO execute para PayPal: " . implode(", ", $stmt->errorInfo()));
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error de base de datos: ' . $e->getMessage();
    error_log("PDOException en process_paypal_order: " . $e->getMessage());
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $response['message'] = 'Error general del servidor: ' . $e->getMessage();
    error_log("Exception en process_paypal_order: " . $e->getMessage());
}

echo json_encode($response);
exit;
?> 