<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../db.php'; // Contiene la conexión $pdo
require_once __DIR__ . '/functions.php'; // Corregido: functions.php está en el mismo directorio auth/

// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/php-error.log');
// error_log("[save_phone.php PDO] INICIO DEL SCRIPT - " . date("Y-m-d H:i:s"));

try {
    if (!isset($_SESSION['user']['id'])) {
        // error_log("[save_phone.php PDO] Usuario no autenticado.");
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }
    $user_google_id = $_SESSION['user']['id'];

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['phone_number'])) {
        // error_log("[save_phone.php PDO] Datos no recibidos, mal formados o número de teléfono faltante.");
        echo json_encode(['success' => false, 'message' => 'Número de teléfono es obligatorio.']);
        exit;
    }

    $phone_number = $data['phone_number'];
    // Validación simple del número de teléfono (se puede mejorar con regex)
    if (strlen($phone_number) < 7 || strlen($phone_number) > 20) { // Ajustar límites según necesidad
        // error_log("[save_phone.php PDO] Número de teléfono no válido: " . $phone_number);
        echo json_encode(['success' => false, 'message' => 'Número de teléfono no válido.']);
        exit;
    }

    $is_favorite_from_data = isset($data['is_default']) ? (bool)$data['is_default'] : (isset($data['is_favorite']) ? (bool)$data['is_favorite'] : false);

    $pdo->beginTransaction();
    // error_log("[save_phone.php PDO] Transacción iniciada para usuario: $user_google_id");

    if ($is_favorite_from_data) {
        // error_log("[save_phone.php PDO] Marcando otros teléfonos como no favoritos.");
        $sql_update_favorites = "UPDATE user_phones SET is_favorite = 0 WHERE user_id = :user_google_id";
        $stmt_update_favorites = $pdo->prepare($sql_update_favorites);
        $stmt_update_favorites->execute([':user_google_id' => $user_google_id]);
        // error_log("[save_phone.php PDO] Filas afectadas al desmarcar favoritos: " . $stmt_update_favorites->rowCount());
    }

    // error_log("[save_phone.php PDO] Insertando nuevo teléfono: " . $phone_number);
    $sql_insert = "INSERT INTO user_phones (user_id, phone_number, is_favorite) 
                     VALUES (:user_id, :phone_number, :is_favorite)";
    $stmt_insert = $pdo->prepare($sql_insert);
    
    $stmt_insert->execute([
        ':user_id' => $user_google_id,
        ':phone_number' => $phone_number,
        ':is_favorite' => $is_favorite_from_data ? 1 : 0
    ]);

    $new_phone_id = $pdo->lastInsertId();
    // error_log("[save_phone.php PDO] Nuevo teléfono insertado ID: $new_phone_id");

    $pdo->commit();
    // error_log("[save_phone.php PDO] Transacción completada (commit).");

    echo json_encode([
        'success' => true,
        'message' => 'Teléfono guardado correctamente',
        'phone_id' => $new_phone_id,
        'is_favorite' => $is_favorite_from_data
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        // error_log("[save_phone.php PDO] Transacción revertida (rollback) debido a PDOException.");
    }
    // error_log("[save_phone.php PDO] PDOException: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos al guardar el teléfono.'
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        // error_log("[save_phone.php PDO] Transacción revertida (rollback) debido a Exception.");
    }
    // error_log("[save_phone.php PDO] Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar el teléfono: ' . esc_html($e->getMessage())
    ]);
}
?> 