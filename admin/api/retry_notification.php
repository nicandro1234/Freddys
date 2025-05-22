<?php
require_once __DIR__ . '/../adminconfig.php'; // Carga la config del admin (que incluye db.php y config raíz)
// require_once __DIR__ . '/../../auth/sessions.php'; // Esto debería estar cubierto por adminconfig.php si es necesario globalmente, o adminconfig.php define funciones de sesión directamente.

// Carga de PHPMailer (asumiendo que está en vendor o una ruta específica)
// Si usas Composer, esto sería require_once __DIR__ . '/../../vendor/autoload.php';
// Si no, ajusta la ruta a PHPMailer.
// Por ejemplo, si PHPMailer está en una carpeta 'PHPMailer' dentro de 'includes' o 'lib':
// require_once __DIR__ . '/../../includes/PHPMailer/src/Exception.php';
// require_once __DIR__ . '/../../includes/PHPMailer/src/PHPMailer.php';
// require_once __DIR__ . '/../../includes/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.']);
    exit;
}

// Obtener ID de la notificación del cuerpo de la solicitud JSON
$data = json_decode(file_get_contents('php://input'), true);
$notificationId = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

if (!$notificationId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de notificación inválido o no proporcionado.']);
    exit;
}

$pdo->beginTransaction();

try {
    // Obtener la notificación
    $stmt_get = $pdo->prepare("SELECT * FROM notifications WHERE id = :id");
    $stmt_get->bindParam(':id', $notificationId, PDO::PARAM_INT);
    $stmt_get->execute();
    $notification = $stmt_get->fetch(PDO::FETCH_ASSOC);

    if (!$notification) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Notificación no encontrada.']);
        $pdo->rollBack(); // No hay nada que hacer, pero buena práctica
        exit;
    }

    $sent_successfully = false;
    $error_detail = null;
    $current_attempts = (int)$notification['attempts'];

    if ($notification['type'] === 'whatsapp') {
        if (!defined('WHATSAPP_API_KEY') || !defined('WHATSAPP_SENDER_ID')) { // Asumiendo que también necesitas un ID de remitente
            throw new Exception('La configuración de la API de WhatsApp no está completa.');
        }
        $phone = preg_replace('/[^0-9]/', '', $notification['recipient']);
        if (strlen($phone) === 10 && substr($phone, 0, 1) !== '1') { // Ejemplo: Asumiendo que 10 dígitos es México sin código de país
             $phone = '52' . $phone; // Añadir código de país para México
        } elseif (strlen($phone) === 11 && substr($phone, 0, 2) === '52') { // Ya tiene código de país de México
            // No hacer nada, ya está formateado
        } else {
             // Considerar otros formatos o lanzar error si el formato no es esperado
        }

        // Reemplaza con la URL correcta y el payload de la API de WhatsApp que estés usando.
        // Este es un ejemplo generalizado.
        $whatsappApiUrl = defined('WHATSAPP_API_URL') ? WHATSAPP_API_URL : 'https://graph.facebook.com/v18.0/'.WHATSAPP_SENDER_ID.'/messages'; // URL puede variar
        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $notification['message']]
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $whatsappApiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . WHATSAPP_API_KEY,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Timeout de 30 segundos

        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            $error_detail = "cURL Error: " . $curl_error;
        } elseif ($http_code >= 200 && $http_code < 300) { // Éxito si es 2xx
            $sent_successfully = true;
        } else {
            $error_detail = "WhatsApp API Error (HTTP $http_code): " . $response_body;
        }

    } elseif ($notification['type'] === 'email') {
        if (!defined('SMTP_HOST') || !defined('SMTP_USER') || !defined('SMTP_PASS') || !defined('SMTP_PORT') || !defined('SMTP_FROM') || !defined('SITE_NAME')) {
            throw new Exception('La configuración SMTP para emails no está completa.');
        }
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : PHPMailer::ENCRYPTION_STARTTLS; // tls o ssl
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom(SMTP_FROM, SITE_NAME . ' Notificaciones');
            $mail->addAddress($notification['recipient']); 
            $mail->Subject = 'Notificación de ' . SITE_NAME . ' (#' . $notificationId . ')';
            $mail->isHTML(true); // Asumir que el mensaje puede ser HTML o texto plano formateado con saltos de línea.
            $mail->Body    = nl2br(htmlspecialchars($notification['message'])); // Convertir saltos de línea y escapar HTML
            // $mail->AltBody = strip_tags($notification['message']); // Versión en texto plano

            $mail->send();
            $sent_successfully = true;
        } catch (Exception $e) {
            $error_detail = "PHPMailer Error: " . $mail->ErrorInfo;
        }
    } else {
        $error_detail = "Tipo de notificación desconocido: " . htmlspecialchars($notification['type']);
    }

    // Actualizar estado de la notificación en la BD
    $new_status = $sent_successfully ? 'sent' : 'failed';
    $stmt_update = $pdo->prepare(
        "UPDATE notifications SET status = :status, last_attempt_at = NOW(), attempts = :attempts, error_message = :error_message WHERE id = :id"
    );
    $stmt_update->execute([
        ':status' => $new_status,
        ':attempts' => $current_attempts + 1,
        ':error_message' => $error_detail, // Guardar NULL si no hay error
        ':id' => $notificationId
    ]);

    if ($sent_successfully) {
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Notificación reenviada exitosamente.']);
    } else {
        // Ya se hizo rollback implícito o no se llegó al commit si hay excepción antes.
        // Si la lógica de envío falló pero no lanzó excepción, hacemos rollback aquí si es necesario.
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500); // Error del servidor porque el envío falló
        echo json_encode([
            'success' => false, 
            'message' => 'Error al reenviar la notificación.',
            'error_detail' => $error_detail
        ]);
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error de BD en retry_notification.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos al procesar la notificación.', 'error_detail' => $e->getMessage()]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error general en retry_notification.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error general al procesar la notificación.', 'error_detail' => $e->getMessage()]);
}
?> 