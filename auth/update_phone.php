<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../db.php'; // db.php está en la raíz
require_once __DIR__ . '/functions.php'; // Corregido: functions.php está en el mismo directorio auth/

// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/php-error.log');
// error_log("[update_phone.php PDO] INICIO DEL SCRIPT - " . date("Y-m-d H:i:s"));

try {
    if (!isset($_SESSION['user']['id'])) {
        // error_log("[update_phone.php PDO] Usuario no autenticado.");
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }
    $user_google_id = $_SESSION['user']['id'];

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        // error_log("[update_phone.php PDO] ID de teléfono no proporcionado o no es numérico.");
        echo json_encode(['success' => false, 'message' => 'ID de teléfono no válido']);
        exit;
    }
    $phone_id = (int)$_GET['id'];

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || empty($data['phone_number'])) {
        // error_log("[update_phone.php PDO] Datos no recibidos, mal formados o número de teléfono faltante.");
        echo json_encode(['success' => false, 'message' => 'Número de teléfono es obligatorio.']);
        exit;
    }

    $phone_number = $data['phone_number'];
    if (strlen($phone_number) < 7 || strlen($phone_number) > 20) {
        // error_log("[update_phone.php PDO] Número de teléfono no válido: " . $phone_number);
        echo json_encode(['success' => false, 'message' => 'Número de teléfono no válido.']);
        exit;
    }

    $is_favorite_from_data = isset($data['is_default']) ? (bool)$data['is_default'] : (isset($data['is_favorite']) ? (bool)$data['is_favorite'] : false);

    $pdo->beginTransaction();
    // error_log("[update_phone.php PDO] Transacción iniciada para actualizar teléfono ID: $phone_id, Usuario: $user_google_id");

    // Verificar si el teléfono pertenece al usuario
    $sql_check = "SELECT id FROM user_phones WHERE id = :phone_id AND user_id = :user_google_id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':phone_id' => $phone_id, ':user_google_id' => $user_google_id]);
    if ($stmt_check->fetch() === false) {
        $pdo->rollBack();
        // error_log("[update_phone.php PDO] Teléfono no encontrado o no pertenece al usuario.");
        echo json_encode(['success' => false, 'message' => 'Teléfono no encontrado o no pertenece al usuario.']);
        exit;
    }

    if ($is_favorite_from_data) {
        // error_log("[update_phone.php PDO] Marcando otros teléfonos como no favoritos.");
        $sql_update_favorites = "UPDATE user_phones SET is_favorite = 0 WHERE user_id = :user_google_id AND id != :phone_id";
        $stmt_update_favorites = $pdo->prepare($sql_update_favorites);
        $stmt_update_favorites->execute([':user_google_id' => $user_google_id, ':phone_id' => $phone_id]);
        // error_log("[update_phone.php PDO] Filas afectadas al desmarcar favoritos: " . $stmt_update_favorites->rowCount());
    }

    // error_log("[update_phone.php PDO] Actualizando teléfono.");
    $sql_update = "UPDATE user_phones SET 
                        phone_number = :phone_number, 
                        is_favorite = :is_favorite, 
                        updated_at = NOW()
                    WHERE id = :phone_id AND user_id = :user_google_id";
    
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([
        ':phone_number' => $phone_number,
        ':is_favorite' => $is_favorite_from_data ? 1 : 0,
        ':phone_id' => $phone_id,
        ':user_google_id' => $user_google_id
    ]);
    // error_log("[update_phone.php PDO] Filas afectadas por la actualización: " . $stmt_update->rowCount());

    $pdo->commit();
    // error_log("[update_phone.php PDO] Transacción completada (commit).");

    echo json_encode(['success' => true, 'message' => 'Teléfono actualizado correctamente', 'is_favorite' => $is_favorite_from_data]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        // error_log("[update_phone.php PDO] Transacción revertida (rollback) debido a PDOException.");
    }
    // error_log("[update_phone.php PDO] PDOException: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos al actualizar el teléfono.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        // error_log("[update_phone.php PDO] Transacción revertida (rollback) debido a Exception.");
    }
    // error_log("[update_phone.php PDO] Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el teléfono: ' . esc_html($e->getMessage())]);
}
?> 