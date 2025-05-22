<?php
// Mostrar errores durante el desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Cargar db.php (raíz), que incluye config.php (raíz) para constantes y crea $pdo
require_once __DIR__ . '/../db.php'; 

// Verificar si el usuario está autenticado
function isAuthenticated() {
    return isset($_SESSION['admin_authenticated']) && $_SESSION['admin_authenticated'] === true;
}

// Alias para compatibilidad o preferencia
function isLoggedIn() {
    return isAuthenticated();
}

// Función para requerir autenticación
function requireAuth() {
    if (!isAuthenticated()) {
        header('Location: login.php');
        exit;
    }
}

// Función para verificar si el usuario es administrador
// Por ahora, si está autenticado, se considera admin.
// Se puede expandir con roles si es necesario (ej. $_SESSION['admin_role'] === 'superadmin')
function isAdmin() {
    // Asumiendo que si admin_id está en sesión, es un admin válido.
    // La tabla `admins` tiene `is_super_admin`, podríamos querer usar eso o un rol en la sesión.
    // Para la funcionalidad básica de "es un admin logueado", isAuthenticated es suficiente.
    return isAuthenticated(); 
}

// Las constantes como SITE_NAME, STORE_ADDRESS, WHATSAPP_PHONE_NUMBER, etc., 
// deberían estar definidas por config.php (raíz) a partir de .env.php.
// Ya no se carga .env.php directamente aquí ni se usa el array $config.

// Configuración de la tienda usando constantes globales
if (!defined('STORE_NAME')) define('STORE_NAME', defined('SITE_NAME') ? SITE_NAME : 'Freddy\'s Pizza');
if (!defined('STORE_ADDRESS')) define('STORE_ADDRESS', defined('SITE_STORE_ADDRESS') ? SITE_STORE_ADDRESS : 'Fray Daniel Mireles 2514, Leon, Guanajuato, Mexico'); // Asumiendo que .env usa SITE_STORE_ADDRESS
if (!defined('STORE_PHONE')) define('STORE_PHONE', defined('WHATSAPP_PHONE_NUMBER') ? WHATSAPP_PHONE_NUMBER : '4775780426');
if (!defined('STORE_EMAIL')) define('STORE_EMAIL', defined('SITE_EMAIL') ? SITE_EMAIL : 'freddyspizzaoficial@gmail.com');

// Configuración de envío
if (!defined('DEFAULT_DELIVERY_RADIUS')) define('DEFAULT_DELIVERY_RADIUS', defined('SITE_DEFAULT_DELIVERY_RADIUS') ? SITE_DEFAULT_DELIVERY_RADIUS : 5);
if (!defined('FREE_DELIVERY_THRESHOLD')) define('FREE_DELIVERY_THRESHOLD', defined('SITE_FREE_DELIVERY_THRESHOLD') ? SITE_FREE_DELIVERY_THRESHOLD : 200);
if (!defined('BASE_DELIVERY_FEE')) define('BASE_DELIVERY_FEE', defined('SITE_BASE_DELIVERY_FEE') ? SITE_BASE_DELIVERY_FEE : 20);
if (!defined('EXTRA_KM_FEE')) define('EXTRA_KM_FEE', defined('SITE_EXTRA_KM_FEE') ? SITE_EXTRA_KM_FEE : 10);

// WHATSAPP_API_KEY, SMTP_HOST, etc., también deberían ser constantes definidas por config.php (raíz)
// No es necesario redefinirlas aquí si ya son globales.

// Función para registrar actividad
function logActivity($action, $details = '') {
    global $pdo; 
    if (!isset($pdo)) { 
        error_log("logActivity Error: \$pdo no está disponible.");
        return;
    }
    try {
        $query = "INSERT INTO admin_logs (action, details, ip_address, user_agent, created_at) VALUES (:action, :details, :ip_address, :user_agent, NOW())";
        $stmt = $pdo->prepare($query);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt->bindParam(':action', $action, PDO::PARAM_STR);
        $stmt->bindParam(':details', $details, PDO::PARAM_STR);
        $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
        $stmt->bindParam(':user_agent', $user_agent, PDO::PARAM_STR);
        $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error en logActivity: " . $e->getMessage());
    }
}

// Función para obtener el estado actual de la tienda
function getStoreStatus() {
    global $pdo; 
    if (!isset($pdo)) { 
        error_log("getStoreStatus Error: \$pdo no está disponible.");
        return ['is_open' => false, 'details' => null, 'last_updated' => date('Y-m-d H:i:s')];
    }
    $day = strtolower(date('l'));
    $currentTime = time();
    $currentTimeFormatted = date('Y-m-d H:i:s', $currentTime);
    error_log("[getStoreStatus] Verificando estado para día: {$day}, Hora actual: {$currentTimeFormatted} (Timestamp: {$currentTime})");

    try {
        $query = "SELECT is_closed, open_time, close_time FROM store_hours WHERE day_of_week = :day LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':day', $day, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            error_log("[getStoreStatus] Datos DB para {$day}: is_closed={$row['is_closed']}, open_time={$row['open_time']}, close_time={$row['close_time']}");
            $isOpen = false;
            $reason = ""; // Para log
            if (!$row['is_closed']) {
                $reason = "Tienda no marcada como cerrada hoy.";
                $openTimeStr = $row['open_time'];
                $closeTimeStr = $row['close_time'];
                $openTime = strtotime($openTimeStr);
                $closeTime = strtotime($closeTimeStr);
                error_log("[getStoreStatus] Timestamps: open={$openTime} ('{$openTimeStr}'), close={$closeTime} ('{$closeTimeStr}')");

                if ($openTime !== false && $closeTime !== false) {
                    // Caso normal (ej. abre 14:00, cierra 23:00)
                    if ($openTime <= $closeTime) { 
                         if ($currentTime >= $openTime && $currentTime <= $closeTime) {
                             $isOpen = true;
                             $reason .= " Hora actual ({$currentTimeFormatted}) está entre {$openTimeStr} y {$closeTimeStr}.";
                         } else {
                              $reason .= " Hora actual ({$currentTimeFormatted}) FUERA del rango {$openTimeStr} - {$closeTimeStr}.";
                         }
                    // Caso cierre después de medianoche (ej. abre 14:00, cierra 02:00)
                    } else { 
                        if ($currentTime >= $openTime || $currentTime <= $closeTime) {
                            $isOpen = true;
                            $reason .= " Cierre post-medianoche. Hora actual ({$currentTimeFormatted}) es después de {$openTimeStr} o antes de {$closeTimeStr}.";
                        } else {
                             $reason .= " Cierre post-medianoche. Hora actual ({$currentTimeFormatted}) FUERA del rango {$openTimeStr} - {$closeTimeStr}.";
                        }
                    }
                } else {
                    $reason .= " Formato de hora inválido en DB (open: {$openTimeStr}, close: {$closeTimeStr}).";
                }
            } else {
                 $reason = "Tienda marcada como cerrada hoy (is_closed = 1).";
            }
            error_log("[getStoreStatus] Determinación final: " . ($isOpen ? 'ABIERTA' : 'CERRADA') . ". Razón: {$reason}");
            return [
                'is_open' => $isOpen,
                'details' => $row,
                'last_updated' => date('Y-m-d H:i:s')
            ];
        } else {
            error_log("[getStoreStatus] No se encontraron datos de horario para el día: {$day}");
        }
    } catch (PDOException $e) {
        error_log("Error PDO en getStoreStatus: " . $e->getMessage());
    }
    // Fallback si no hay datos o error
    error_log("[getStoreStatus] Fallback: Retornando CERRADA.");
    return ['is_open' => false, 'details' => null, 'last_updated' => date('Y-m-d H:i:s')];
}

// Función para actualizar el estado de la tienda (globalmente, no por día)
// Esta función debe interactuar con una tabla/configuración global de estado de tienda, no store_hours directamente.
// Crearemos una nueva tabla llamada `store_settings` para esto.
// Por ahora, esta función queda pendiente de refactorizar si se quiere un toggle global.
// La lógica actual en admin_header.php y hours.php maneja el is_closed por día.
/*
function updateGlobalStoreStatus($isOpen) {
    global $pdo;
    if (!isset($pdo)) return false;
    try {
        // Ejemplo: UPDATE store_settings SET setting_value = :status WHERE setting_key = 'store_open'
        // Esto requiere una tabla `store_settings` con (setting_key, setting_value)
        logActivity('update_global_store_status', 'Estado global actualizado a: ' . ($isOpen ? 'Abierto' : 'Cerrado'));
        return true;
    } catch (PDOException $e) {
        error_log("Error en updateGlobalStoreStatus: " . $e->getMessage());
    }
    return false;
}
*/

// updateStoreStatus por día ya está bien definida y es usada por hours.php
// La función updateStoreStatus que estaba aquí y modificaba store_hours para el día actual,
// es la que se usa en admin_header.php. Se mantiene.
function updateStoreStatus($isOpen) {
    global $pdo; 
    if (!isset($pdo)) {
        error_log("updateStoreStatus Error: \$pdo no está disponible.");
        return false;
    }
    try {
        // Actualizar para TODOS los días si se quiere un toggle global, o solo el día actual.
        // La lógica actual en el header parece implicar el día actual.
        $query = "UPDATE store_hours SET is_closed = :is_closed, updated_at = NOW() WHERE day_of_week = :day_of_week";
        $stmt = $pdo->prepare($query);
        $isClosedParam = $isOpen ? 0 : 1;
        $currentDay = strtolower(date('l'));
        $stmt->bindParam(':is_closed', $isClosedParam, PDO::PARAM_INT);
        $stmt->bindParam(':day_of_week', $currentDay, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            logActivity('store_status_toggled', 'Estado de la tienda para ' . ucfirst($currentDay) . ' actualizado a: ' . ($isOpen ? 'Abierto' : 'Cerrado'));
            return true;
        }
    } catch (PDOException $e) {
        error_log("Error en updateStoreStatus (diario): " . $e->getMessage());
    }
    return false;
}

// Función para obtener pedidos pendientes
// NOTA: orders.user_id es VARCHAR(255) (google_id), users.id es INT. El JOIN original fallaría.
// Se necesita un JOIN users.google_id = orders.user_id
function getPendingOrders() {
    global $pdo; // Usar $pdo
    if (!$pdo) {
        error_log("getPendingOrders Error: \$pdo no está disponible.");
        return [];
    }
    try {
        // Corregido el JOIN para usar users.google_id
        $query = "SELECT o.*, u.name as user_name, u.email as user_email 
                  FROM orders o 
                  LEFT JOIN users u ON o.user_id = u.google_id 
                  WHERE o.status IN ('pending', 'processing', 'shipped', 'ready')  -- Añadido 'ready' si es un estado válido
                  ORDER BY o.created_at DESC";
        $stmt = $pdo->query($query); // Para consultas SELECT sin parámetros directos, query() es una opción
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error en getPendingOrders: " . $e->getMessage());
        return [];
    }
}

// Función para actualizar el estado de un pedido
function updateOrderStatus($orderId, $status) {
    global $pdo; // Usar $pdo
    if (!$pdo) {
        error_log("updateOrderStatus Error: \$pdo no está disponible.");
        return false;
    }
    try {
        $query = "UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :order_id";
        $stmt = $pdo->prepare($query);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            logActivity('update_order_status', "Pedido #$orderId actualizado a: $status");
            return true;
        }
    } catch (PDOException $e) {
        error_log("Error en updateOrderStatus: " . $e->getMessage());
    }
    return false;
} 