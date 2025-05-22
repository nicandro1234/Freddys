<?php
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/php-error.log'); // Usar ruta relativa
// error_reporting(E_ALL);
// ini_set('display_errors', 0); // No mostrar errores en producción
// error_log("[get_phone.php PDO] INICIO DEL SCRIPT - " . date("Y-m-d H:i:s"));

session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../db.php'; // db.php está en la raíz
require_once __DIR__ . '/functions.php'; // Corregido: functions.php está en el mismo directorio auth/

try {
    if (!isset($_SESSION['user']['id'])) {
        // error_log("[get_phone.php PDO] Usuario no autenticado.");
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado', 'phone' => null]);
        exit;
    }
    $user_google_id = $_SESSION['user']['id'];

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        // error_log("[get_phone.php PDO] ID de teléfono no válido o no proporcionado.");
        echo json_encode(['success' => false, 'message' => 'ID de teléfono no válido', 'phone' => null]);
        exit;
    }
    $phone_id = (int)$_GET['id'];

    // error_log("[get_phone.php PDO] Consultando teléfono ID: $phone_id para usuario Google ID: $user_google_id");

    $sql = "SELECT id, user_id, phone_number, is_favorite, created_at, updated_at 
            FROM user_phones 
            WHERE id = :phone_id AND user_id = :user_google_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':phone_id' => $phone_id,
        ':user_google_id' => $user_google_id
    ]);
    
    $phone = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$phone) {
        // error_log("[get_phone.php PDO] Teléfono no encontrado o no pertenece al usuario.");
        echo json_encode(['success' => false, 'message' => 'Teléfono no encontrado o no pertenece al usuario', 'phone' => null]);
        exit;
    }

    // Convertir is_favorite a booleano
    $phone['is_favorite'] = (bool)$phone['is_favorite'];

    // error_log("[get_phone.php PDO] Teléfono encontrado: " . json_encode($phone));
    echo json_encode([
        'success' => true,
        'phone' => $phone
    ]);

} catch (PDOException $e) {
    // error_log("[get_phone.php PDO] PDOException: " . $e->getMessage());
    // http_response_code(500); // El cliente espera JSON, no solo código de error
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos al obtener el teléfono.', // Mensaje genérico
        'phone' => null
    ]);
} catch (Exception $e) {
    // error_log("[get_phone.php PDO] Exception: " . $e->getMessage());
    // http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener el teléfono: ' . esc_html($e->getMessage()),
        'phone' => null
    ]);
}
?> 