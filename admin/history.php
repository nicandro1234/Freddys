<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'adminconfig.php'; // Carga la configuración del admin (que incluye db.php)
// require_once __DIR__ . '/../auth/sessions.php'; // Funciones de sesión - REDUNDANTE, adminconfig.php maneja sesiones

// requireAuth(); // adminconfig.php define requireAuth(), pero isLoggedIn() e isAdmin() se usan abajo.
if (!isLoggedIn() || !isAdmin()) { // Combinando la verificación de sesión y de admin
    header("Location: login.php");
    exit;
}

// Obtener el estado actual de la tienda (asegúrate que esta función esté disponible)
$storeStatus = []; // Valor por defecto
if (function_exists('getStoreStatus')) {
    $storeStatus = getStoreStatus(); // Llamada consistente con admin_header.php, asume que getStoreStatus accede a $pdo si lo necesita
}

// Obtener parámetros de filtro
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
// Validar formato de fechas para seguridad y consistencia
if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $startDate)) {
    $startDate = date('Y-m-d', strtotime('-30 days')); // Default si es inválido
}
if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $endDate)) {
    $endDate = date('Y-m-d'); // Default si es inválido
}

$status = $_GET['status'] ?? 'delivered';
$allowed_statuses = ['delivered', 'cancelled', 'pending', 'confirmed', 'shipped']; // Añadir todos los estados permitidos
if (!in_array($status, $allowed_statuses)) {
    $status = 'delivered'; // Default si no es un estado permitido
}

$orders = [];
try {
    // Construir la consulta base
    // Se asume que un usuario puede tener varios teléfonos, pero aquí queremos el más reciente o el por defecto.
    // Esta consulta tomará UN teléfono asociado al usuario. Si se necesita una lógica más compleja (ej. teléfono de envío del pedido específico si es diferente), se ajustará.
    $query = "SELECT o.*, u.name, u.email, 
                     (SELECT up.phone_number FROM user_phones up WHERE up.user_id = o.user_id AND up.is_favorite = 1 LIMIT 1) as phone_default,
                     (SELECT up.phone_number FROM user_phones up WHERE up.user_id = o.user_id ORDER BY up.created_at DESC LIMIT 1) as phone_latest
              FROM orders o 
              LEFT JOIN users u ON o.user_id = u.google_id 
              WHERE DATE(o.created_at) BETWEEN :start_date AND :end_date 
              AND o.status = :status 
              ORDER BY o.updated_at DESC 
              LIMIT 50";

    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':start_date', $startDate, PDO::PARAM_STR);
    $stmt->bindParam(':end_date', $endDate, PDO::PARAM_STR);
    $stmt->bindParam(':status', $status, PDO::PARAM_STR);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error al obtener historial de pedidos: " . $e->getMessage());
    // Considera mostrar un mensaje de error amigable al usuario o registrarlo
} catch (Exception $e) {
    error_log("Error general en historial de pedidos: " . $e->getMessage());
}

$page_title = "Historial de Pedidos";
include __DIR__ . '/includes/admin_header.php'; 
include __DIR__ . '/includes/admin_sidebar.php';
?>

<main class="admin-main-content">
    <!-- Contenido principal del historial de pedidos -->
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card admin-card">
                    <div class="card-header admin-card-header">
                        <h4 class="mb-0">Historial de Pedidos</h4>
                    </div>
                    <div class="card-body admin-card-body">
                        <!-- Filtros -->
                        <div class="filter-section admin-card mb-4" style="background-color: #2a2e37; padding: 15px; border-radius: 5px;">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-3 admin-form-group">
                                    <label for="start_date" class="form-label">Fecha Inicio</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                                </div>
                                <div class="col-md-3 admin-form-group">
                                    <label for="end_date" class="form-label">Fecha Fin</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                                </div>
                                <div class="col-md-3 admin-form-group">
                                    <label for="status" class="form-label">Estado</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendientes</option>
                                        <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>Confirmados</option>
                                        <option value="shipped" <?php echo $status === 'shipped' ? 'selected' : ''; ?>>Enviados</option>
                                        <option value="delivered" <?php echo $status === 'delivered' ? 'selected' : ''; ?>>Entregados</option>
                                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelados</option>
                                    </select>
                                </div>
                                <div class="col-md-3 admin-form-group">
                                    <label for="filter-btn" class="form-label" style="visibility: hidden;">Acción</label>
                                    <button type="submit" id="filter-btn" class="btn admin-btn btn-primary w-100">Filtrar</button>
                                </div>
                            </form>
                        </div>

                        <!-- Lista de pedidos -->
                        <?php if (empty($orders)): ?>
                            <div class="alert alert-info">
                                No se encontraron pedidos con los filtros seleccionados.
                            </div>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                                <div class="card order-card mb-3">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h5 class="card-title">Pedido #<?php echo htmlspecialchars($order['id']); ?></h5>
                                                <p class="mb-1"><strong>Cliente:</strong> <?php echo htmlspecialchars($order['name'] ?? 'Cliente no registrado'); ?></p>
                                                <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($order['email'] ?? 'N/A'); ?></p>
                                                <p class="mb-1"><strong>Total:</strong> $<?php echo number_format($order['total_amount'], 2); ?></p>
                                                <p class="mb-1"><strong>Fecha Pedido:</strong> <?php echo $order['created_at'] ? date('d/m/Y H:i', strtotime($order['created_at'])) : 'N/A'; ?></p>
                                                <p class="mb-1"><strong>Última Actualización:</strong> <?php echo $order['updated_at'] ? date('d/m/Y H:i', strtotime($order['updated_at'])) : 'N/A'; ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <?php 
                                                    $shippingAddressDecoded = null; // Inicializar por si $order['shipping_address'] es null
                                                    if ($order['shipping_address'] !== null) {
                                                        $shippingAddressDecoded = json_decode($order['shipping_address'], true);
                                                    }
                                                    $addressDisplay = 'No especificada';
                                                    if (is_array($shippingAddressDecoded) && !empty($shippingAddressDecoded['address_line1'])) {
                                                        $addressDisplay = htmlspecialchars($shippingAddressDecoded['address_line1']);
                                                        if (!empty($shippingAddressDecoded['city'])) $addressDisplay .= ", " . htmlspecialchars($shippingAddressDecoded['city']);
                                                        if (!empty($shippingAddressDecoded['zip_code'])) $addressDisplay .= " - " . htmlspecialchars($shippingAddressDecoded['zip_code']);
                                                    } elseif (is_string($order['shipping_address'])) {
                                                        $addressDisplay = htmlspecialchars($order['shipping_address']);
                                                    }
                                                ?>
                                                <p class="mb-1"><strong>Dirección de Envío:</strong> <?php echo $addressDisplay; ?></p>
                                                <p class="mb-1"><strong>Teléfono (Pedido):</strong> <?php echo htmlspecialchars($order['shipping_phone'] ?? 'No especificado'); ?></p>
                                                <p class="mb-1"><strong>Método de pago:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order['payment_method'] ?? 'No especificado'))); ?></p>
                                                <p class="mb-1"><strong>Estado:</strong> <span class="badge bg-info text-dark"><?php echo htmlspecialchars(ucfirst($order['status'])); ?></span></p>
                                            </div>
                                        </div>
                                        <!-- Aquí podrías añadir un botón o enlace para ver detalles del pedido si es necesario -->
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/admin_footer.php'; // Incluir el footer ?>

<?php
// Eliminar </body> y </html> redundantes de aquí, ya que están en admin_footer.php
//</body>
//</html> 
?> 