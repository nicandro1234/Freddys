<?php
session_start();
header('Content-Type: application/json');

require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['phone_number'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Si se marca como favorito, quitar el favorito de los demás teléfonos
    if (isset($data['is_favorite']) && $data['is_favorite']) {
        $stmt = $conn->prepare("
            UPDATE user_phones 
            SET is_favorite = 0 
            WHERE user_id = ? AND is_favorite = 1
        ");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
    }
    
    // Insertar nuevo teléfono
    $stmt = $conn->prepare("
        INSERT INTO user_phones (
            user_id,
            phone_number,
            is_favorite
        ) VALUES (?, ?, ?)
    ");
    
    $is_favorite = isset($data['is_favorite']) ? (int)$data['is_favorite'] : 0;
    
    $stmt->bind_param(
        "isi",
        $_SESSION['user_id'],
        $data['phone_number'],
        $is_favorite
    );
    
    $stmt->execute();
    
    // Confirmar transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Teléfono guardado correctamente'
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    error_log("Error al guardar teléfono: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar el teléfono'
    ]);
}
?> 