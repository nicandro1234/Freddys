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
// error_log("[delete_phone.php PDO] INICIO DEL SCRIPT - " . date("Y-m-d H:i:s"));

try {
    if (!isset($_SESSION['user']['id'])) {
        // error_log("[delete_phone.php PDO] Usuario no autenticado.");
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }
    $user_google_id = $_SESSION['user']['id'];

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        // error_log("[delete_phone.php PDO] ID de teléfono no proporcionado o no válido.");
        echo json_encode(['success' => false, 'message' => 'ID de teléfono no válido']);
        exit;
    }
    $phone_id = (int)$_GET['id'];

    // error_log("[delete_phone.php PDO] Solicitud para eliminar teléfono ID: $phone_id, Usuario: $user_google_id");

    $pdo->beginTransaction();

    // Opcional: Verificar si el teléfono pertenece al usuario antes de eliminar
    // $sql_check = "SELECT id FROM user_phones WHERE id = :phone_id AND user_id = :user_google_id";
    // $stmt_check = $pdo->prepare($sql_check);
    // $stmt_check->execute([':phone_id' => $phone_id, ':user_google_id' => $user_google_id]);
    // if ($stmt_check->fetch() === false) {
    //     $pdo->rollBack();
    //     error_log("[delete_phone.php PDO] Teléfono no encontrado o no pertenece al usuario (pre-verificación).");
    //     echo json_encode(['success' => false, 'message' => 'Teléfono no encontrado o no pertenece al usuario.']);
    //     exit;
    // }

    $sql_delete = "DELETE FROM user_phones WHERE id = :phone_id AND user_id = :user_google_id";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute([
        ':phone_id' => $phone_id,
        ':user_google_id' => $user_google_id
    ]);

    if ($stmt_delete->rowCount() > 0) {
        $pdo->commit();
        // error_log("[delete_phone.php PDO] Teléfono eliminado correctamente.");
        echo json_encode(['success' => true, 'message' => 'Teléfono eliminado correctamente']);
    } else {
        $pdo->rollBack(); // Si no se afectaron filas, el teléfono no existía para ese usuario o ya fue borrado
        // error_log("[delete_phone.php PDO] No se eliminó el teléfono. Puede que no exista o no pertenezca al usuario.");
        echo json_encode(['success' => false, 'message' => 'No se eliminó el teléfono. Puede que no exista o no pertenezca al usuario.']);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        // error_log("[delete_phone.php PDO] Transacción revertida (rollback) debido a PDOException.");
    }
    // error_log("[delete_phone.php PDO] PDOException: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos al eliminar el teléfono.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        // error_log("[delete_phone.php PDO] Transacción revertida (rollback) debido a Exception.");
    }
    // error_log("[delete_phone.php PDO] Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el teléfono: ' . esc_html($e->getMessage())]);
}
?> 