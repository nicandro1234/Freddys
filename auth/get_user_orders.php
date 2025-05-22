<?php
// Mostrar errores durante el desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../db.php'; // Corregido: db.php está en la raíz (un nivel arriba de auth)
require_once __DIR__ . '/functions.php'; // functions.php está en el mismo directorio auth/

// Habilitar logs para depuración (puedes comentar esto en producción)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
// error_log("--- Nueva petición a get_user_orders.php (PDO) ---");

try {
    // Verificar autenticación
    if (!isset($_SESSION['user']['id'])) {
        // error_log("Error: Usuario no autenticado (PDO).");
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado', 'orders' => []]);
        exit;
    }
    $user_google_id = $_SESSION['user']['id'];
    // error_log("Usuario autenticado ID (PDO): " . $user_google_id);

    $status_filter = $_GET['status_filter'] ?? 'all'; 
    // error_log("Filtro recibido (PDO): " . $status_filter);

    // Base de la consulta
    // Nota: En la tabla 'orders', la columna 'user_id' debe almacenar el google_id del usuario.
    $sql = "SELECT id, created_at as order_date, total_amount, status, order_details, shipping_address, payment_method, delivery_fee, estimated_delivery_time 
            FROM orders 
            WHERE user_id = :user_google_id";

    $params = [':user_google_id' => $user_google_id]; 

    // Añadir filtro de estado si no es 'all'
    if ($status_filter !== 'all') {
        $db_status = '';
        switch (strtolower($status_filter)) { // Convertir a minúsculas para ser más flexible
            case 'pending': $db_status = 'pending'; break;
            case 'processing': $db_status = 'processing'; break; // Añadido
            case 'shipped': $db_status = 'shipped'; break; 
            case 'delivered': $db_status = 'delivered'; break;
            case 'completed': $db_status = 'completed'; break; // 'completed' es un estado válido en la BD
            case 'cancelled': $db_status = 'cancelled'; break;
            default:
                // error_log("ADVERTENCIA (PDO): Filtro de estado desconocido: " . $status_filter);
                break;
        }

        if (!empty($db_status)) {
            // error_log("Filtro mapeado a valor BD (PDO): " . $db_status);
            $sql .= " AND status = :status_val";
            $params[':status_val'] = $db_status; 
        }
    }

    $sql .= " ORDER BY created_at DESC"; 
    // error_log("SQL Final (PDO): " . $sql);
    // error_log("Parámetros para execute (PDO): " . print_r($params, true));

    // Preparar y ejecutar la consulta
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    // error_log("Consulta PDO ejecutada correctamente.");

    $orders_raw = $stmt->fetchAll(); // PDO::FETCH_ASSOC es el modo por defecto
    $orders_formatted = [];

    foreach ($orders_raw as $row) {
        // Formatear la fecha
        try {
            $date = new DateTime($row['order_date']);
            $row['order_date_formatted'] = $date->format('d/m/Y H:i'); // Formato común
        } catch (Exception $e) {
            $row['order_date_formatted'] = 'Fecha inválida';
        }
        
        // Decodificar los detalles de la orden (order_details)
        $order_items = json_decode($row['order_details'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // error_log("Error decoding JSON for order ID " . $row['id'] . ": " . json_last_error_msg());
            $order_items = []; // O manejar como prefieras, ej. { "error": "Detalles no disponibles" }
        }
        $row['items'] = $order_items; // Cambiar 'order_details' a 'items' para el frontend si es más claro
        // unset($row['order_details']); // Opcional: remover la cadena JSON original

        // Decodificar shipping_address si existe y es JSON
        if (isset($row['shipping_address'])) {
            $shipping_info = json_decode($row['shipping_address'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row['shipping_address_details'] = $shipping_info;
            } else {
                // Si no es JSON o hay error, mantener el string original o un valor por defecto
                $row['shipping_address_details'] = ['address' => $row['shipping_address']]; 
            }
        }

        // Formatear el total
        $row['total_amount_formatted'] = number_format((float)$row['total_amount'], 2, '.', ',');
        $row['delivery_fee_formatted'] = number_format((float)($row['delivery_fee'] ?? 0), 2, '.', ',');
        
        // Traducir el estado (opcional, el frontend también podría hacerlo)
        // Mantendremos el estado original de la BD y añadiremos uno traducido si es necesario.
        $row['status_original'] = $row['status'];
        switch(strtolower($row['status'])) {
            case 'pending': $row['status_translated'] = 'Pendiente'; break;
            case 'processing': $row['status_translated'] = 'En preparación'; break;
            case 'shipped': $row['status_translated'] = 'En camino'; break; // O 'Listo para recoger'
            case 'delivered': $row['status_translated'] = 'Entregado'; break;
            case 'completed': $row['status_translated'] = 'Completado'; break;
            case 'cancelled': $row['status_translated'] = 'Cancelado'; break;
            default: $row['status_translated'] = ucfirst($row['status']); break;
        }

        $orders_formatted[] = $row;
    }
    // error_log("Número de pedidos encontrados (PDO): " . count($orders_formatted));
    
    echo json_encode([
        'success' => true,
        'orders' => $orders_formatted
    ]);

} catch (PDOException $e) {
    // error_log("PDO EXCEPCIÓN en get_user_orders.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos al obtener pedidos.', // No mostrar $e->getMessage() en producción
        'orders' => []
    ]);
} catch (Exception $e) {
    // error_log("EXCEPCIÓN General en get_user_orders.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error general al obtener pedidos: ' . esc_html($e->getMessage()),
        'orders' => []
    ]);
} finally {
    // La conexión PDO se cierra automáticamente cuando el script termina.
    // No es necesario $stmt->close(); explícitamente con fetchAll() si no se reutiliza $stmt.
    // error_log("--- Fin de petición a get_user_orders.php (PDO) ---\n");
}
?> 