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
// error_log("[save_address.php PDO] INICIO DEL SCRIPT - " . date("Y-m-d H:i:s"));

try {
    if (!isset($_SESSION['user']['id'])) {
        // error_log("[save_address.php PDO] Usuario no autenticado.");
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
        exit;
    }
    $user_google_id = $_SESSION['user']['id'];

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        // error_log("[save_address.php PDO] No se recibieron datos JSON o están mal formados.");
        echo json_encode(['success' => false, 'message' => 'Datos no recibidos o mal formados.']);
        exit;
    }

    // Validación de campos (mejorada)
    $required_fields = ['address_line', 'city', 'state', 'zip_code', 'country'];
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            // error_log("[save_address.php PDO] Campo requerido faltante: $field");
            echo json_encode(['success' => false, 'message' => "El campo '{$field}' es obligatorio."]);
            exit;
        }
    }

    $is_favorite_from_data = isset($data['is_default']) ? (bool)$data['is_default'] : (isset($data['is_favorite']) ? (bool)$data['is_favorite'] : false);

    $pdo->beginTransaction();
    // error_log("[save_address.php PDO] Transacción iniciada para usuario: $user_google_id");

    if ($is_favorite_from_data) {
        // error_log("[save_address.php PDO] Marcando otras direcciones como no favoritas.");
        $sql_update_favorites = "UPDATE addresses SET is_favorite = 0 WHERE user_id = :user_google_id";
        $stmt_update_favorites = $pdo->prepare($sql_update_favorites);
        $stmt_update_favorites->execute([':user_google_id' => $user_google_id]);
        // error_log("[save_address.php PDO] Filas afectadas al desmarcar favoritos: " . $stmt_update_favorites->rowCount());
    }

    // error_log("[save_address.php PDO] Insertando nueva dirección.");
    $sql_insert = "INSERT INTO addresses (user_id, address_line, city, state, zip_code, country, is_favorite) 
                     VALUES (:user_id, :address_line, :city, :state, :zip_code, :country, :is_favorite)";
    $stmt_insert = $pdo->prepare($sql_insert);
    
    $stmt_insert->execute([
        ':user_id' => $user_google_id,
        ':address_line' => $data['address_line'],
        ':city' => $data['city'],
        ':state' => $data['state'],
        ':zip_code' => $data['zip_code'],
        ':country' => $data['country'],
        ':is_favorite' => $is_favorite_from_data ? 1 : 0 // PDO espera 0 o 1 para booleano en algunas configs
    ]);

    $new_address_id = $pdo->lastInsertId();
    // error_log("[save_address.php PDO] Nueva dirección insertada ID: $new_address_id");

    $pdo->commit();
    // error_log("[save_address.php PDO] Transacción completada (commit).");

    echo json_encode([
        'success' => true,
        'message' => 'Dirección guardada correctamente',
        'address_id' => $new_address_id,
        'is_favorite' => $is_favorite_from_data // Devolver el estado de favorito
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        // error_log("[save_address.php PDO] Transacción revertida (rollback) debido a PDOException.");
    }
    // error_log("[save_address.php PDO] PDOException: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos al guardar la dirección.' // Mensaje genérico
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        // error_log("[save_address.php PDO] Transacción revertida (rollback) debido a Exception.");
    }
    // error_log("[save_address.php PDO] Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar la dirección: ' . esc_html($e->getMessage())
    ]);
}
?> 