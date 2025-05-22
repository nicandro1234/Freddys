<?php
require_once '../adminconfig.php';
requireAuth();

header('Content-Type: application/json');

// Obtener datos del POST
$data = json_decode(file_get_contents('php://input'), true);
$phone = $data['phone'] ?? null;
$message = $data['message'] ?? null;

if (!$phone || !$message) {
    echo json_encode(['success' => false, 'message' => 'Faltan parámetros requeridos']);
    exit;
}

// Formatear número de teléfono
$phone = preg_replace('/[^0-9]/', '', $phone);
if (strlen($phone) === 10) {
    $phone = '52' . $phone;
}

// Preparar la solicitud a la API de WhatsApp
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.whatsapp.com/v1/messages');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'messaging_product' => 'whatsapp',
    'to' => $phone,
    'type' => 'text',
    'text' => [
        'body' => $message
    ]
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . WHATSAPP_API_KEY,
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    // Registrar la notificación en la base de datos usando PDO
    $query = "INSERT INTO notifications (type, message, recipient, status, sent_at) 
              VALUES (:type, :message, :recipient, 'sent', NOW())";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':type' => 'whatsapp',
        ':message' => $message,
        ':recipient' => $phone
    ]);
    
    echo json_encode(['success' => true]);
} else {
    // Registrar el error en la base de datos usando PDO
    $query = "INSERT INTO notifications (type, message, recipient, status, error_message) 
              VALUES (:type, :message, :recipient, 'failed', :error_message)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':type' => 'whatsapp',
        ':message' => $message,
        ':recipient' => $phone,
        ':error_message' => $response // $response es el cuerpo de la respuesta de la API de WhatsApp
    ]);
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al enviar el mensaje de WhatsApp',
        'response' => $response
    ]);
} 