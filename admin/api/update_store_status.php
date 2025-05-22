<?php
require_once __DIR__ . '/../adminconfig.php'; // Incluir configuración y funciones

header('Content-Type: application/json');

// Verificar autenticación (opcional pero recomendado para APIs)
// requireAuth(); 

$response = ['success' => false, 'message' => 'Error desconocido.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Leer el cuerpo JSON de la petición
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['is_open'])) {
        $isOpen = filter_var($input['is_open'], FILTER_VALIDATE_BOOLEAN);
        
        // Usar la función de adminconfig.php para actualizar el estado del día actual
        if (updateStoreStatus($isOpen)) { // Esta función actualiza store_hours.is_closed para el día actual
            $response['success'] = true;
            $response['message'] = 'Estado de la tienda para hoy actualizado con éxito.';
             // No es necesario devolver el nuevo estado aquí, ya que la página se recarga
        } else {
            $response['message'] = 'Error al actualizar el estado en la base de datos.';
        }
    } else {
        $response['message'] = 'Parámetro \'is_open\' no encontrado en la petición.';
    }
} else {
    $response['message'] = 'Método no permitido.';
}

echo json_encode($response);
exit; 