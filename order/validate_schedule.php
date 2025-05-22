<?php
header('Content-Type: application/json');

// Mínimo tiempo de anticipación en minutos para programar un pedido
const MIN_SCHEDULE_LEAD_TIME_MINUTES = 30;

require_once __DIR__ . '/../admin/adminconfig.php'; // Para $pdo y configuraciones
require_once __DIR__ . '/../admin/classes/StoreHours.php';

$response = ['valid' => false, 'message' => 'Error desconocido.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$scheduledDateTimeString = $input['scheduled_datetime'] ?? null;

if (empty($scheduledDateTimeString)) {
    $response['message'] = 'Por favor, selecciona una fecha y hora para programar.';
    echo json_encode($response);
    exit;
}

try {
    $scheduledTimestamp = strtotime($scheduledDateTimeString);
    if ($scheduledTimestamp === false) {
        $response['message'] = 'El formato de fecha y hora no es válido.';
        echo json_encode($response);
        exit;
    }

    $currentTimestamp = time();
    $minLeadTimestamp = $currentTimestamp + (MIN_SCHEDULE_LEAD_TIME_MINUTES * 60);

    if ($scheduledTimestamp < $currentTimestamp) {
        $response['message'] = 'No puedes programar un pedido para una fecha u hora pasada.';
        echo json_encode($response);
        exit;
    }

    if ($scheduledTimestamp < $minLeadTimestamp) {
        $response['message'] = 'Debes programar tu pedido con al menos ' . MIN_SCHEDULE_LEAD_TIME_MINUTES . ' minutos de anticipación.';
        echo json_encode($response);
        exit;
    }

    $storeHours = new StoreHours($pdo);
    $targetDayKey = strtolower(date('l', $scheduledTimestamp));
    $daySchedule = $storeHours->getHoursByDay($targetDayKey);

    if (!$daySchedule || $daySchedule['is_closed']) {
        $response['message'] = 'La tienda está cerrada el día seleccionado (' . ($storeHours->days_of_week_es[$targetDayKey] ?? ucfirst($targetDayKey)) . '). Por favor, elige otra fecha.';
        echo json_encode($response);
        exit;
    }

    if (!$storeHours->isWithinOperatingHours($scheduledDateTimeString)) {
        $openTime = $daySchedule['open_time'] ? date('g:i A', strtotime($daySchedule['open_time'])) : 'N/A';
        $closeTime = $daySchedule['close_time'] ? date('g:i A', strtotime($daySchedule['close_time'])) : 'N/A';
        $response['message'] = 'La hora seleccionada está fuera de nuestro horario de atención para ' . ($storeHours->days_of_week_es[$targetDayKey] ?? ucfirst($targetDayKey)) . ' (' . $openTime . ' - ' . $closeTime . ').';
        echo json_encode($response);
        exit;
    }

    $response['valid'] = true;
    $response['message'] = 'Fecha y hora programada es válida.';

} catch (Exception $e) {
    error_log("Error en validate_schedule.php: " . $e->getMessage());
    $response['message'] = 'Error al validar el horario. Inténtalo de nuevo.';
}

echo json_encode($response);
exit;
?> 