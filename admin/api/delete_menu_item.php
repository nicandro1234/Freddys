<?php
require_once '../adminconfig.php';
requireAuth();

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
    exit;
}

// Eliminar el artículo usando PDO
$query = "DELETE FROM menu_items WHERE id = :id";
$stmt = $pdo->prepare($query);

if ($stmt->execute([':id' => $id])) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el artículo']);
} 