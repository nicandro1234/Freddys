<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'adminconfig.php'; // Carga la configuración del admin (que incluye db.php)
requireAuth(); // Asegura que el usuario esté autenticado

$notifications = [];
$error_message = null;

try {
    // Obtener notificaciones
    // Considerar paginación para grandes cantidades de notificaciones
    $query = "SELECT * FROM notifications ORDER BY created_at DESC LIMIT 100";
    $stmt = $pdo->query($query);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error al cargar notificaciones: " . $e->getMessage();
    error_log($error_message);
    // No es necesario hacer die() aquí, se puede mostrar un mensaje en la página
} catch (Exception $e) {
    $error_message = "Error inesperado: " . $e->getMessage();
    error_log($error_message);
}

$page_title = "Notificaciones"; // Para el header
// Incluir header y sidebar estandarizados
include __DIR__ . '/includes/admin_header.php'; 
include __DIR__ . '/includes/admin_sidebar.php';
?>

<!-- Main content -->
<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Notificaciones</h1>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <div class="card admin-card">
                <div class="card-header admin-card-header">
                    <h5 class="mb-0">Últimas 100 Notificaciones</h5>
                </div>
                <div class="card-body admin-card-body">
                    <?php if (empty($notifications) && !$error_message): ?>
                        <div class="alert alert-info">No hay notificaciones para mostrar.</div>
                    <?php elseif (!empty($notifications)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Destinatario</th>
                                        <th>Mensaje</th>
                                        <th>Estado</th>
                                        <th>Intentos</th>
                                        <th>Últ. Intento</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($notifications as $notification): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($notification['id']); ?></td>
                                        <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($notification['created_at']))); ?></td>
                                        <td>
                                            <?php if ($notification['type'] === 'whatsapp'): ?>
                                                <span title="WhatsApp"><i class="bi bi-whatsapp text-success"></i> <?php echo ucfirst(htmlspecialchars($notification['type'])); ?></span>
                                            <?php elseif ($notification['type'] === 'email'): ?>
                                                <span title="Email"><i class="bi bi-envelope text-primary"></i> <?php echo ucfirst(htmlspecialchars($notification['type'])); ?></span>
                                            <?php else: ?>
                                                <span title="Sistema"><i class="bi bi-info-circle"></i> <?php echo ucfirst(htmlspecialchars($notification['type'])); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($notification['recipient'] ?? 'N/A'); ?></td>
                                        <td><small><?php echo nl2br(htmlspecialchars($notification['message'])); ?></small></td>
                                        <td>
                                            <?php 
                                            $status_class = 'bg-secondary';
                                            if ($notification['status'] === 'sent') $status_class = 'bg-success';
                                            elseif ($notification['status'] === 'failed') $status_class = 'bg-danger';
                                            elseif ($notification['status'] === 'pending') $status_class = 'bg-warning text-dark';
                                            ?>
                                            <span class="badge <?php echo $status_class; ?>"><?php echo ucfirst(htmlspecialchars($notification['status'])); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($notification['attempts']); ?></td>
                                        <td><?php echo $notification['last_attempt_at'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($notification['last_attempt_at']))) : 'N/A'; ?></td>
                                        <td>
                                            <?php if ($notification['status'] === 'failed' || $notification['status'] === 'pending'): // Permitir reintentar pendientes también ?>
                                                <button class="btn btn-sm btn-primary retry-btn" 
                                                        data-id="<?php echo htmlspecialchars($notification['id']); ?>"
                                                        onclick="retryNotification(<?php echo htmlspecialchars($notification['id']); ?>, this)"> <!-- Pasar this (el botón) -->
                                                    <i class="bi bi-arrow-clockwise"></i> Reenviar
                                                </button>
                                            <?php endif; ?>
                                            <!-- Podría haber un botón para ver detalles si el mensaje es muy largo -->
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; // Incluir un footer estándar si existe ?>

<script>
// Asegurarse que el script se ejecute después de que el DOM esté cargado
document.addEventListener('DOMContentLoaded', function() {
    // No es necesario, ya que onclick está en el botón.
});

function retryNotification(id, buttonElement) { // Recibir el elemento botón
    if (!buttonElement) {
        console.error('Elemento botón no proporcionado a retryNotification');
        return;
    }
    
    buttonElement.disabled = true;
    buttonElement.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Enviando...';

    fetch('api/retry_notification.php', { // Asegúrate que esta ruta sea correcta
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            // Considerar añadir CSRF token headers si tu sistema los usa
        },
        body: JSON.stringify({ id: id })
    })
    .then(response => {
        if (!response.ok) {
            // Intentar obtener más detalles del error si es un JSON
            return response.json().then(err => { throw new Error(err.message || `Error del servidor: ${response.status}`); });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            buttonElement.innerHTML = '<i class="bi bi-check-lg"></i> Reenviado';
            buttonElement.classList.remove('btn-primary', 'btn-danger');
            buttonElement.classList.add('btn-success');
            // Opcional: Actualizar la fila de la tabla dinámicamente en lugar de recargar toda la página
            // Por ejemplo, actualizar el estado, intentos, etc.
            // Para simplificar, recargamos, pero para UX mejor, actualizar la fila sería ideal.
            setTimeout(() => {
                location.reload(); 
            }, 1200);
        } else {
            buttonElement.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Falló';
            buttonElement.classList.remove('btn-primary', 'btn-success');
            buttonElement.classList.add('btn-danger');
            buttonElement.disabled = false; // Habilitar para reintentar
            alert('Error al reenviar notificación: ' + (data.message || 'Respuesta no exitosa pero sin mensaje.'));
        }
    })
    .catch(error => {
        console.error('Fetch Error:', error);
        buttonElement.innerHTML = '<i class="bi bi-x-octagon"></i> Error Fatal';
        buttonElement.classList.remove('btn-primary', 'btn-success');
        buttonElement.classList.add('btn-danger');
        buttonElement.disabled = false; // Habilitar para reintentar
        alert('Error de conexión o script al reenviar notificación: ' + error.message);
    });
}
</script>

</body>
</html> 