<?php
session_start();
header('Content-Type: application/json');

require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['address_line']) || !isset($data['city']) || !isset($data['country'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Iniciar transacción
    $conn->begin_transaction();
    
    // Si se marca como favorita, quitar el favorito de las demás direcciones
    if (isset($data['is_favorite']) && $data['is_favorite']) {
        $stmt = $conn->prepare("
            UPDATE addresses 
            SET is_favorite = 0 
            WHERE user_id = ? AND is_favorite = 1
        ");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
    }
    
    // Insertar nueva dirección
    $stmt = $conn->prepare("
        INSERT INTO addresses (
            user_id,
            address_line,
            city,
            state,
            zip_code,
            country,
            is_favorite
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $is_favorite = isset($data['is_favorite']) ? (int)$data['is_favorite'] : 0;
    
    $stmt->bind_param(
        "isssssi",
        $_SESSION['user_id'],
        $data['address_line'],
        $data['city'],
        $data['state'] ?? null,
        $data['zip_code'] ?? null,
        $data['country'],
        $is_favorite
    );
    
    $stmt->execute();
    
    // Confirmar transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Dirección guardada correctamente'
    ]);
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $conn->rollback();
    error_log("Error al guardar dirección: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar la dirección'
    ]);
}
?> 