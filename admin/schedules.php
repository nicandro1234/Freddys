<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'adminconfig.php'; // Carga la configuración del admin (que incluye db.php)
requireAuth(); // Asegura que el usuario esté autenticado

// require_once __DIR__ . '/../auth/config.php'; // REDUNDANTE
// require_once __DIR__ . '/../auth/sessions.php'; // REDUNDANTE

if (!isLoggedIn() || !isAdmin()) {
    header("Location: login.php");
    exit;
}

$success_message = $_SESSION['success_message'] ?? null;
$error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Procesar actualización de estado si es un POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scheduleId = filter_input(INPUT_POST, 'schedule_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $allowed_statuses = ['confirmed', 'cancelled']; // Estados permitidos desde el frontend

    if ($scheduleId && $status && in_array($status, $allowed_statuses)) {
        try {
            $pdo->beginTransaction();
            
            $stmt_update = $pdo->prepare("UPDATE order_schedules SET status = :status, updated_at = NOW() WHERE id = :id AND status = 'pending'"); // Solo actualizar si está pendiente
            $stmt_update->bindParam(':status', $status, PDO::PARAM_STR);
            $stmt_update->bindParam(':id', $scheduleId, PDO::PARAM_INT);
            
            if ($stmt_update->execute()) {
                if ($stmt_update->rowCount() > 0) {
                    // Si el estado se actualiza a 'confirmed', podríamos querer actualizar también el estado del pedido principal en la tabla 'orders' a 'confirmed' o 'processing'
                    // Por ahora, solo actualizamos la tabla order_schedules.
                    // Ejemplo: Si se confirma, y el pedido estaba 'pending_payment' o 'scheduled', pasarlo a 'confirmed' o 'processing'
                    // if ($status === 'confirmed') {
                    //     $order_id_stmt = $pdo->prepare("SELECT order_id FROM order_schedules WHERE id = :schedule_id");
                    //     $order_id_stmt->execute([':schedule_id' => $scheduleId]);
                    //     $order_id_result = $order_id_stmt->fetchColumn();
                    //     if ($order_id_result) {
                    //         $update_order_stmt = $pdo->prepare("UPDATE orders SET status = 'confirmed', updated_at = NOW() WHERE id = :order_id AND status = 'scheduled'"); // O el estado que corresponda
                    //         $update_order_stmt->execute([':order_id' => $order_id_result]);
                    //     }
                    // }
                    $_SESSION['success_message'] = 'Estado del pedido programado actualizado correctamente.';
                     $pdo->commit();
                } else {
                    $_SESSION['error_message'] = 'No se pudo actualizar el estado (quizás ya no estaba pendiente o ID no existe).';
                    if ($pdo->inTransaction()) $pdo->rollBack();
                }
            } else {
                $_SESSION['error_message'] = 'Error al ejecutar la actualización del estado.';
                if ($pdo->inTransaction()) $pdo->rollBack();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['error_message'] = 'Error de base de datos al actualizar: ' . $e->getMessage();
            error_log("Error en admin/schedules.php (POST): " . $e->getMessage());
        }
    } else {
        $_SESSION['error_message'] = 'Datos inválidos para la actualización.';
    }
    // Redirigir para evitar reenvío de formulario (PRG pattern)
    header("Location: schedules.php");
    exit;
}

// Obtener pedidos programados pendientes
$scheduledOrders = [];
$db_error = null;
try {
    // Asumiendo que users.google_id es la PK de users y orders.user_id es FK a esta.
    // Y user_phones.user_id es FK a users.google_id y tiene un is_default.
    $query_select = "SELECT os.*, o.total_amount, o.status as order_original_status, 
                            u.name as customer_name, u.email as customer_email,
                            (SELECT up.phone_number FROM user_phones up WHERE up.user_id = o.user_id AND up.is_default = 1 LIMIT 1) as customer_phone
                     FROM order_schedules os 
                     JOIN orders o ON os.order_id = o.id 
                     LEFT JOIN users u ON o.user_id = u.google_id 
                     WHERE os.status = 'pending' 
                     ORDER BY os.scheduled_time ASC LIMIT 100"; // Limitar por si hay muchos
    $stmt_select = $pdo->query($query_select);
    $scheduledOrders = $stmt_select->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = "Error al cargar pedidos programados: " . $e->getMessage();
    error_log($db_error);
}

$page_title = "Pedidos Programados";
include __DIR__ . '/includes/admin_header.php';
include __DIR__ . '/includes/admin_sidebar.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo htmlspecialchars($page_title); ?></h1>
                <!-- Podríamos añadir un botón para crear una nueva programación manual si fuera necesario -->
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($db_error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($db_error); ?></div>
            <?php endif; ?>
            
            <div class="card admin-card">
                <div class="card-header admin-card-header">
                    <h5 class="mb-0">Pedidos Programados Pendientes</h5>
                </div>
                <div class="card-body admin-card-body">
                    <?php if (empty($scheduledOrders) && !$db_error): ?>
                        <div class="alert alert-info">No hay pedidos programados pendientes.</div>
                    <?php elseif (!empty($scheduledOrders)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID Prog.</th>
                                        <th>ID Pedido</th>
                                        <th>Cliente</th>
                                        <th>Teléfono</th>
                                        <th>Email</th>
                                        <th>Total Pedido</th>
                                        <th>Programado Para</th>
                                        <th>Estado Pedido Orig.</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($scheduledOrders as $order): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($order['id']); ?></td>
                                            <td><a href="order_details.php?id=<?php echo htmlspecialchars($order['order_id']); ?>">#<?php echo htmlspecialchars($order['order_id']); ?></a></td>
                                            <td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($order['customer_phone'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($order['customer_email'] ?? 'N/A'); ?></td>
                                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td><?php echo htmlspecialchars(date('d/m/Y H:i A', strtotime($order['scheduled_time']))); ?></td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars(ucfirst($order['order_original_status'])); ?></span></td>
                                            <td>
                                                <form method="POST" action="schedules.php" class="d-inline" onsubmit="return confirmAction('confirmar');">
                                                    <input type="hidden" name="schedule_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                                                    <input type="hidden" name="status" value="confirmed">
                                                    <button type="submit" class="btn btn-sm btn-success me-1" title="Confirmar Programación">
                                                        <i class="bi bi-check-circle"></i> Confirmar
                                                    </button>
                                                </form>
                                                <form method="POST" action="schedules.php" class="d-inline" onsubmit="return confirmAction('cancelar');">
                                                    <input type="hidden" name="schedule_id" value="<?php echo htmlspecialchars($order['id']); ?>">
                                                    <input type="hidden" name="status" value="cancelled">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Cancelar Programación">
                                                        <i class="bi bi-x-circle"></i> Cancelar
                                                    </button>
                                                </form>
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

<?php include __DIR__ . '/includes/admin_footer.php'; ?>

<script>
    function confirmAction(action) {
        return confirm('¿Estás seguro de que deseas ' + action + ' este pedido programado?');
    }
    // Auto-dismiss alerts after some time
    document.addEventListener('DOMContentLoaded', (event) => {
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                new bootstrap.Alert(alert).close();
            });
        }, 5000); // 5 segundos
    });
</script>

</body>
</html> 