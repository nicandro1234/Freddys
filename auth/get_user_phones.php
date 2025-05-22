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

// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/php-error.log');
// error_log("--- Petición a get_user_phones.php (PDO) ---");

try {
    if (!isset($_SESSION['user']['id'])) {
        // error_log("Usuario no autenticado en get_user_phones.php");
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado', 'phones' => []]);
        exit;
    }
    $user_google_id = $_SESSION['user']['id'];

    // En la tabla 'user_phones', la columna 'user_id' debe almacenar el google_id del usuario.
    $sql = "SELECT id, user_id, phone_number, is_favorite, created_at, updated_at 
            FROM user_phones 
            WHERE user_id = :user_google_id 
            ORDER BY is_favorite DESC, updated_at DESC, created_at DESC"; // Ordenar por favoritas y luego más recientes
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_google_id' => $user_google_id]);
    $phones = $stmt->fetchAll(); // PDO::FETCH_ASSOC es el modo por defecto

    // Convertir is_favorite a booleano si es necesario para el frontend
    foreach ($phones as &$phone) { // Pasar por referencia para modificar el array original
        $phone['is_favorite'] = (bool)$phone['is_favorite'];
    }
    unset($phone); // Romper la referencia del último elemento

    echo json_encode([
        'success' => true,
        'phones' => $phones
    ]);

} catch (PDOException $e) {
    // error_log("PDOException en get_user_phones.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos al obtener teléfonos.', // No mostrar $e->getMessage() en producción
        'phones' => []
    ]);
} catch (Exception $e) {
    // error_log("Exception en get_user_phones.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener teléfonos: ' . esc_html($e->getMessage()),
        'phones' => []
    ]);
}
?> 