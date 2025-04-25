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
            address_line,
            city,
            state,
            zip_code,
            country,
            is_favorite
        FROM addresses 
        WHERE user_id = ? 
        ORDER BY is_favorite DESC, created_at DESC
    ");
    
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $addresses = [];
    while ($row = $result->fetch_assoc()) {
        $addresses[] = [
            'id' => $row['id'],
            'street' => $row['address_line'],
            'city' => $row['city'],
            'state' => $row['state'],
            'zip_code' => $row['zip_code'],
            'country' => $row['country'],
            'is_default' => (bool)$row['is_favorite']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'addresses' => $addresses
    ]);
    
} catch (Exception $e) {
    error_log("Error al obtener direcciones: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener las direcciones'
    ]);
} 