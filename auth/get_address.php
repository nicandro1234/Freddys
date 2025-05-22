<?php
// Habilitar logs para depuración
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../db.php'; // db.php está en la raíz
require_once __DIR__ . '/functions.php'; // Corregido: functions.php está en el mismo directorio auth/

// error_log('[get_address.php FINAL] Session user_id: ' . ($_SESSION['user']['id'] ?? 'No definido'));

try {
    if (!isset($_SESSION['user']['id'])) {
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado', 'address' => null]);
        exit;
    }
    $user_google_id = $_SESSION['user']['id'];

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        echo json_encode(['success' => false, 'message' => 'ID de dirección no válido', 'address' => null]);
        exit;
    }
    $address_id = (int)$_GET['id'];

    // Opción A: Seleccionar solo los campos que existen en la tabla `addresses`.
    $sql = "SELECT id, user_id, address_line, city, state, zip_code, country, is_favorite, created_at, updated_at 
            FROM addresses 
            WHERE id = :address_id AND user_id = :user_google_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':address_id' => $address_id,
        ':user_google_id' => $user_google_id
    ]);
    
    $address = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$address) {
        echo json_encode(['success' => false, 'message' => 'Dirección no encontrada o no pertenece al usuario', 'address' => null]);
        exit;
    }

    $address['is_favorite'] = (bool)$address['is_favorite'];

    echo json_encode([
        'success' => true,
        'address' => $address
    ]);

} catch (PDOException $e) {
    error_log("[get_address.php] PDOException: " . $e->getMessage()); 
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos al obtener la dirección.', // Mensaje genérico para el cliente
        'address' => null
    ]);
} catch (Exception $e) {
    error_log("[get_address.php] Exception: " . $e->getMessage()); 
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener la dirección: ' . esc_html($e->getMessage()),
        'address' => null
    ]);
} 