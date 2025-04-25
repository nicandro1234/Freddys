<?php
session_start();
header('Content-Type: application/json');

require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Usuario no autenticado']);
    exit;
}

try {
    $stmt = $conn->prepare("
        SELECT 
            id,
            phone_number,
            is_favorite
        FROM user_phones 
        WHERE user_id = ? 
        ORDER BY is_favorite DESC, created_at DESC
    ");
    
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $phones = [];
    while ($row = $result->fetch_assoc()) {
        $phones[] = [
            'id' => $row['id'],
            'phone_number' => $row['phone_number'],
            'is_default' => (bool)$row['is_favorite']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'phones' => $phones
    ]);
    
} catch (Exception $e) {
    error_log("Error al obtener teléfonos: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener los teléfonos'
    ]);
}
?> 