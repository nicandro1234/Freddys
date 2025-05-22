<?php
// Ajustar la ruta para config.php y auth_functions.php si es necesario, asumiendo que están en el directorio raíz 'auth'
require_once __DIR__ . '/../adminconfig.php'; // Carga la config del admin (que incluye db.php y config raíz)
require_once __DIR__ . '/../classes/StoreHours.php'; // Clase StoreHours
// require_once __DIR__ . '/../../auth/sessions.php'; // Cubierto por adminconfig.php

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

if (!isLoggedIn()) { // Asumiendo que requireAuth() redirige o termina si no está autenticado.
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado. Se requiere iniciar sesión.']);
    exit;
}

if (!isAdmin()) { // O la función que verifica si es administrador
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado. Se requiere ser administrador.']);
    exit;
}


$day = $_GET['day'] ?? '';

if (empty($day)) {
    echo json_encode(['success' => false, 'message' => 'Falta el parámetro day']);
    exit;
}

try {
    $storeHours = new StoreHours($pdo); // Usar la conexión PDO
    $hours = $storeHours->getHoursByDay($day);

    if ($hours) {
        echo json_encode([
            'success' => true,
            'hours' => $hours
        ]);
    } else {
        // Podría ser que el día no tenga horas configuradas o el día no sea válido.
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró el horario para el día especificado o el día no es válido.'
        ]);
    }
} catch (PDOException $e) {
    error_log("Error en get_hours.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error en la base de datos.']);
} catch (Exception $e) {
    error_log("Error general en get_hours.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocurrió un error inesperado.']);
}
?> 