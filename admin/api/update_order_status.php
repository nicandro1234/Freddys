<?php
require_once '../adminconfig.php';
requireAuth();

// Establecer el tipo de contenido como JSON
header('Content-Type: application/json');

// Obtener datos de POST ya que el frontend envía x-www-form-urlencoded
// $data = json_decode(file_get_contents('php://input'), true); // Comentado o eliminado

// Validar los datos recibidos de $_POST
if (!isset($_POST['order_id']) || !isset($_POST['status']) /* || !isset($_POST['csrf_token']) */ ) { // Añadir validación de CSRF si es necesario
    echo json_encode(['success' => false, 'message' => 'Datos incompletos desde POST']);
    exit;
}

// Validar CSRF token si se implementa
// if (!validate_csrf_token($_POST['csrf_token'])) {
//     echo json_encode(['success' => false, 'message' => 'Error de validación CSRF']);
//     exit;
// }

$orderId = (int)$_POST['order_id'];
$status = $_POST['status'];

// Validar el estado
$validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'ready_for_pickup']; // Añadido 'ready_for_pickup'
if (!in_array($status, $validStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Estado inválido']);
    exit;
}

// Actualizar el estado del pedido
// La función updateOrderStatus está definida en adminconfig.php
if (updateOrderStatus($orderId, $status)) {
    // Registrar la actividad (logActivity también está en adminconfig.php)
    // logActivity('update_order_status', "Pedido #$orderId actualizado a: $status"); // updateOrderStatus ya lo hace internamente
    
    echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado del pedido en la base de datos.']);
} 