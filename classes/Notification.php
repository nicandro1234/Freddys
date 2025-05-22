<?php
require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Notification {
    private $conn;
    private $whatsappApiKey;
    private $whatsappPhoneNumber;
    private $emailFrom;
    private $emailFromName;
    
    public function __construct($conn) {
        $this->conn = $conn;
        $this->whatsappApiKey = WHATSAPP_API_KEY;
        $this->whatsappPhoneNumber = WHATSAPP_PHONE_NUMBER;
        $this->emailFrom = SITE_EMAIL;
        $this->emailFromName = SITE_NAME;
    }
    
    public function sendWhatsApp($to, $message) {
        // Aquí implementaremos la integración con la API de WhatsApp
        // Por ahora, solo registramos la notificación en la base de datos
        $query = "INSERT INTO notifications (order_id, type, message, recipient, status) 
                 VALUES (?, 'whatsapp', ?, ?, 'pending')";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iss", $orderId, $message, $to);
        
        if ($stmt->execute()) {
            // TODO: Implementar la llamada real a la API de WhatsApp
            return true;
        }
        
        return false;
    }
    
    public function sendEmail($to, $subject, $body) {
        $mail = new PHPMailer(true);
        
        try {
            // Configuración del servidor
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            
            // Remitente y destinatario
            $mail->setFrom($this->emailFrom, $this->emailFromName);
            $mail->addAddress($to);
            
            // Contenido
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            $mail->send();
            
            // Registrar la notificación en la base de datos
            $query = "INSERT INTO notifications (order_id, type, message, recipient, status, sent_at) 
                     VALUES (?, 'email', ?, ?, 'sent', NOW())";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iss", $orderId, $body, $to);
            $stmt->execute();
            
            return true;
        } catch (Exception $e) {
            // Registrar el error en la base de datos
            $query = "INSERT INTO notifications (order_id, type, message, recipient, status) 
                     VALUES (?, 'email', ?, ?, 'failed')";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("iss", $orderId, $body, $to);
            $stmt->execute();
            
            error_log("Error al enviar correo: " . $mail->ErrorInfo);
            return false;
        }
    }
    
    public function sendOrderConfirmation($orderId) {
        // Obtener detalles del pedido
        $query = "SELECT o.*, u.email, u.name FROM orders o 
                 JOIN users u ON o.user_id = u.id 
                 WHERE o.id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($order = $result->fetch_assoc()) {
            // Preparar el mensaje de WhatsApp
            $whatsappMessage = "¡Gracias por tu pedido en Freddy's Pizza!\n\n";
            $whatsappMessage .= "Número de pedido: #" . $orderId . "\n";
            $whatsappMessage .= "Total: $" . $order['total_amount'] . "\n";
            $whatsappMessage .= "Estado: " . $order['status'] . "\n\n";
            $whatsappMessage .= "Te mantendremos informado sobre el estado de tu pedido.";
            
            // Enviar WhatsApp
            $this->sendWhatsApp($order['shipping_phone'], $whatsappMessage);
            
            // Preparar el correo electrónico
            $emailBody = $this->getOrderConfirmationEmailTemplate($order);
            
            // Enviar correo
            $this->sendEmail($order['email'], "Confirmación de Pedido #" . $orderId, $emailBody);
            
            return true;
        }
        
        return false;
    }
    
    private function getOrderConfirmationEmailTemplate($order) {
        // Aquí implementaremos una plantilla HTML profesional para el correo
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .order-details { margin-bottom: 20px; }
                .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>¡Gracias por tu pedido!</h1>
                </div>
                <div class='order-details'>
                    <h2>Detalles del Pedido #" . $order['id'] . "</h2>
                    <p>Hola " . $order['name'] . ",</p>
                    <p>Hemos recibido tu pedido y estamos preparándolo.</p>
                    <p>Total: $" . $order['total_amount'] . "</p>
                    <p>Estado: " . $order['status'] . "</p>
                </div>
                <div class='footer'>
                    <p>Freddy's Pizza - La mejor pizza de la ciudad</p>
                </div>
            </div>
        </body>
        </html>";
    }
} 