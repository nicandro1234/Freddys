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
// error_log("[delete_address.php PDO] INICIO DEL SCRIPT - " . date("Y-m-d H:i:s"));

try {
    if (!isset($_SESSION['user']['id'])) {
        // error_log("[delete_address.php PDO] Usuario no autenticado.");
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }
    $user_google_id = $_SESSION['user']['id'];

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        // error_log("[delete_address.php PDO] ID de dirección no proporcionado o no válido.");
        echo json_encode(['success' => false, 'message' => 'ID de dirección no válido']);
        exit;
    }
    $address_id = (int)$_GET['id'];

    // error_log("[delete_address.php PDO] Solicitud para eliminar dirección ID: $address_id, Usuario: $user_google_id");

    $pdo->beginTransaction();

    // Opcional: Verificar si la dirección pertenece al usuario antes de eliminar
    // $sql_check = "SELECT id FROM addresses WHERE id = :address_id AND user_id = :user_google_id";
    // $stmt_check = $pdo->prepare($sql_check);
    // $stmt_check->execute([':address_id' => $address_id, ':user_google_id' => $user_google_id]);
    // if ($stmt_check->fetch() === false) {
    //     $pdo->rollBack();
    //     error_log("[delete_address.php PDO] Dirección no encontrada o no pertenece al usuario (pre-verificación).");
    //     echo json_encode(['success' => false, 'message' => 'Dirección no encontrada o no pertenece al usuario.']);
    //     exit;
    // }

    $sql_delete = "DELETE FROM addresses WHERE id = :address_id AND user_id = :user_google_id";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->execute([
        ':address_id' => $address_id,
        ':user_google_id' => $user_google_id
    ]);

    if ($stmt_delete->rowCount() > 0) {
        $pdo->commit();
        // error_log("[delete_address.php PDO] Dirección eliminada correctamente.");
        echo json_encode(['success' => true, 'message' => 'Dirección eliminada correctamente']);
    } else {
        $pdo->rollBack(); // Si no se afectaron filas, la dirección no existía para ese usuario o ya fue borrada
        // error_log("[delete_address.php PDO] No se eliminó la dirección. Puede que no exista o no pertenezca al usuario.");
        echo json_encode(['success' => false, 'message' => 'No se eliminó la dirección. Puede que no exista o no pertenezca al usuario.']);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        // error_log("[delete_address.php PDO] Transacción revertida (rollback) debido a PDOException.");
    }
    // error_log("[delete_address.php PDO] PDOException: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos al eliminar la dirección.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack(); // Aunque improbable aquí si no es PDOException
        // error_log("[delete_address.php PDO] Transacción revertida (rollback) debido a Exception.");
    }
    // error_log("[delete_address.php PDO] Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar la dirección: ' . esc_html($e->getMessage())]);
}
?> 