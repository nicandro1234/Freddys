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
// error_log("[update_address.php PDO] INICIO DEL SCRIPT - " . date("Y-m-d H:i:s"));

try {
    if (!isset($_SESSION['user']['id'])) {
        // error_log("[update_address.php PDO] Usuario no autenticado.");
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }
    $user_google_id = $_SESSION['user']['id'];

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        // error_log("[update_address.php PDO] ID de dirección no proporcionado o no es numérico.");
        echo json_encode(['success' => false, 'message' => 'ID de dirección no válido']);
        exit;
    }
    $address_id = (int)$_GET['id'];

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        // error_log("[update_address.php PDO] Datos no recibidos o mal formados.");
        echo json_encode(['success' => false, 'message' => 'Datos no recibidos o mal formados']);
        exit;
    }

    // Validación de campos (Opción A: no se esperan street, number_int_ext, colonia)
    $required_fields = ['address_line', 'city', 'state', 'zip_code', 'country'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) { 
            // error_log("[update_address.php PDO] Campo requerido faltante: $field");
            echo json_encode(['success' => false, 'message' => "El campo '{$field}' es obligatorio."]);
            exit;
        }
    }
    
    $is_favorite_from_data = isset($data['is_default']) ? (bool)$data['is_default'] : (isset($data['is_favorite']) ? (bool)$data['is_favorite'] : false);

    $pdo->beginTransaction();
    // error_log("[update_address.php PDO] Transacción iniciada para actualizar dirección ID: $address_id, Usuario: $user_google_id");

    // Verificar si la dirección pertenece al usuario
    $sql_check = "SELECT id FROM addresses WHERE id = :address_id AND user_id = :user_google_id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->execute([':address_id' => $address_id, ':user_google_id' => $user_google_id]);
    if ($stmt_check->fetch() === false) {
        $pdo->rollBack(); // No es necesario si aún no se han hecho cambios, pero seguro
        // error_log("[update_address.php PDO] Dirección no encontrada o no pertenece al usuario.");
        echo json_encode(['success' => false, 'message' => 'Dirección no encontrada o no pertenece al usuario.']);
        exit;
    }

    if ($is_favorite_from_data) {
        // error_log("[update_address.php PDO] Marcando otras direcciones como no favoritas.");
        $sql_update_favorites = "UPDATE addresses SET is_favorite = 0 WHERE user_id = :user_google_id AND id != :address_id";
        $stmt_update_favorites = $pdo->prepare($sql_update_favorites);
        $stmt_update_favorites->execute([':user_google_id' => $user_google_id, ':address_id' => $address_id]);
        // error_log("[update_address.php PDO] Filas afectadas al desmarcar favoritos: " . $stmt_update_favorites->rowCount());
    }

    // error_log("[update_address.php PDO] Actualizando dirección.");
    $sql_update = "UPDATE addresses SET 
                        address_line = :address_line, 
                        city = :city, 
                        state = :state, 
                        zip_code = :zip_code, 
                        country = :country, 
                        is_favorite = :is_favorite, 
                        updated_at = NOW()
                    WHERE id = :address_id AND user_id = :user_google_id";
    
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([
        ':address_line' => $data['address_line'],
        ':city' => $data['city'],
        ':state' => $data['state'],
        ':zip_code' => $data['zip_code'],
        ':country' => $data['country'],
        ':is_favorite' => $is_favorite_from_data ? 1 : 0,
        ':address_id' => $address_id,
        ':user_google_id' => $user_google_id
    ]);
    // error_log("[update_address.php PDO] Filas afectadas por la actualización: " . $stmt_update->rowCount());

    $pdo->commit();
    // error_log("[update_address.php PDO] Transacción completada (commit).");

    echo json_encode(['success' => true, 'message' => 'Dirección actualizada correctamente', 'is_favorite' => $is_favorite_from_data]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        // error_log("[update_address.php PDO] Transacción revertida (rollback) debido a PDOException.");
    }
    // error_log("[update_address.php PDO] PDOException: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos al actualizar la dirección.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        // error_log("[update_address.php PDO] Transacción revertida (rollback) debido a Exception.");
    }
    // error_log("[update_address.php PDO] Exception: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al actualizar la dirección: ' . esc_html($e->getMessage())]);
}
?> 