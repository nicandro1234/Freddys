<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// Incluir el archivo de configuración principal del administrador
require_once 'adminconfig.php'; // Anteriormente config.php

// Verificar si el usuario está autenticado, excluyendo la página de login
if (basename($_SERVER['PHP_SELF']) !== 'login.php') {
    requireAuth(); // Esta función está en adminconfig.php
}

// session_start(); // admin/config.php ya debería manejar session_start()
// require_once '../db.php'; // Redundante, db.php se carga a través de admin/config.php -> ../auth/config.php -> ../db.php
// require_once '../config.php'; // Confuso, el config principal está en auth. admin/config.php es específico para el admin.
require_once __DIR__ . '/../auth/functions.php'; // Para time_ago y otras helpers

// // Asegurarnos de que FontAwesome está cargado (si no está en admin_header.php)
// echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">';


// Lógica para obtener pedidos
$pending_orders = [];
$preparing_orders = [];
$ready_orders = [];
$scheduled_orders = [];

if (!isset($pdo)) {
    // Esto no debería ocurrir si config.php está bien.
    error_log("FATAL ERROR en admin/index.php: \$pdo no está definido después de incluir admin/config.php");
    die("Error crítico de configuración de base de datos. Revise los logs.");
}

try {
    // Usar la función getPendingOrders() si centraliza la lógica y es adecuada aquí
    // o mantener la consulta específica si se necesitan más estados o una lógica diferente.
    // Por ahora, mantendré la consulta actual ya que filtra por múltiples estados para diferentes columnas.
    $stmt = $pdo->query("SELECT o.*, u.name AS user_customer_name, u.email AS user_customer_email FROM orders o LEFT JOIN users u ON o.user_id = u.google_id ORDER BY o.created_at DESC");
    $all_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_orders as &$order) {
        $decoded_details = json_decode($order['order_details'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Error decoding JSON for order ID " . $order['id'] . ": " . json_last_error_msg());
            $order['items'] = [];
        } else {
            if (isset($decoded_details['items']) && is_array($decoded_details['items'])) {
                $order['items'] = $decoded_details['items'];
            } elseif (is_array($decoded_details)) {
                $order['items'] = $decoded_details;
            } else {
                $order['items'] = [];
            }
        }

        if ($order['is_scheduled'] == 1 && ($order['status'] == 'pending' || $order['status'] == 'processing' || $order['status'] == 'scheduled')) {
            $scheduled_orders[] = $order;
        } elseif ($order['status'] == 'pending') {
            $pending_orders[] = $order;
        } elseif ($order['status'] == 'processing') {
            $preparing_orders[] = $order;
        } elseif ($order['status'] == 'shipped' || $order['status'] == 'ready_for_pickup') {
            $ready_orders[] = $order;
        }
    }
    unset($order);
} catch (PDOException $e) {
    error_log("Error fetching orders in admin/index.php: " . $e->getMessage());
}


$pageTitle = "Pedidos";
// Corregir la ruta del include
include __DIR__ . '/includes/admin_header.php';
include __DIR__ . '/includes/admin_sidebar.php'; // Incluir la barra lateral
?>

<main class="admin-main-content admin-dashboard-content">
    <div class="container-fluid">
        <div class="row">
            <!-- Columna Pendientes / Nuevos -->
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="admin-card status-card status-pending-card h-100">
                    <div class="admin-card-header">
                        <h3 class="admin-card-title"><i class="bi bi-clock-history me-2"></i>Nuevos Pedidos</h3>
                    </div>
                    <div class="admin-card-body order-list-body" id="pending-orders-list">
                        <?php if (empty($pending_orders)): ?>
                            <p class="text-center text-muted mt-3">No hay pedidos nuevos.</p>
                        <?php endif; ?>
                        <?php foreach ($pending_orders as $order): ?>
                            <div class="admin-order-card mb-3" id="order-card-<?php echo $order['id']; ?>">
                                <div class="admin-order-card-header">
                                    <h6>Pedido #<?php echo htmlspecialchars($order['id']); ?></h6>
                                    <small class="order-time-ago"><?php echo htmlspecialchars(time_ago($order['created_at'])); ?></small>
                                </div>
                                <div class="admin-order-card-body">
                                    <?php
                                    $customerNameDisplay = "Cliente Desconocido"; // Default
                                    // Intenta obtener el nombre del cliente desde order_details si shipping_address no lo tiene o es ambiguo
                                    $orderDetailsData = json_decode($order['order_details'], true);
                                    if (isset($orderDetailsData['customer_name']) && !empty($orderDetailsData['customer_name'])) {
                                        $customerNameDisplay = htmlspecialchars($orderDetailsData['customer_name']);
                                    } elseif (!empty($order['user_customer_name'])) { // Fallback al nombre de usuario de la tabla users
                                        $customerNameDisplay = htmlspecialchars($order['user_customer_name']);
                                    }
                                    // El campo shipping_address podría contener un JSON con 'name', si es así, podría usarse también
                                    // pero la información de customer_name en order_details (enviada desde el form) es más directa.
                                    ?>
                                    <p><strong>Cliente:</strong> <?php echo $customerNameDisplay; ?></p>
                                    <p><strong>Total:</strong> $<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>
                                    
                                    <!-- NUEVO: Mostrar información de Programación/Entrega -->
                                    <p class="mb-1">
                                        <strong>Entrega:</strong> 
                                        <?php if ($order['is_scheduled'] == 1 && !empty($order['estimated_delivery_time'])): ?>
                                            <span style="color: #007bff;"><i class="bi bi-calendar-event"></i> Programado para: <?php echo htmlspecialchars(date('d/m/Y h:i A', strtotime($order['estimated_delivery_time']))); ?></span>
                                        <?php else: ?>
                                            <span>Lo antes posible</span>
                                        <?php endif; ?>
                                    </p>
                                    <!-- FIN NUEVO -->

                                    <?php if (!empty($order['items']) && is_array($order['items'])): ?>
                                        <p class="mb-1"><strong>Artículos:</strong></p>
                                        <ul class="list-unstyled small ms-2 mb-2">
                                            <?php 
                                            $item_count = 0;
                                            foreach ($order['items'] as $item): 
                                                if ($item_count < 2): ?>
                                                <li><?php echo htmlspecialchars(isset($item['quantity']) ? $item['quantity'] : 'N/A'); ?>x <?php echo htmlspecialchars(isset($item['name']) ? $item['name'] : 'N/A'); ?></li>
                                            <?php 
                                                endif;
                                                $item_count++;
                                            endforeach; 
                                            if (count($order['items']) > 2): ?>
                                                <li>... y <?php echo (count($order['items']) - 2); ?> más.</li>
                                            <?php endif; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="small text-muted">No hay detalles de artículos.</p>
                                    <?php endif; ?>

                                    <?php if (!empty($order['shipping_address'])): ?>
                                        <?php
                                        $address_data = json_decode($order['shipping_address'], true);
                                        $address_display = 'No especificada';
                                        if (is_array($address_data) && isset($address_data['address'])) {
                                            $address_display = htmlspecialchars($address_data['address']);
                                            if (isset($address_data['reference'])) $address_display .= ', ' . htmlspecialchars($address_data['reference']);
                                        }
                                        ?>
                                        <p class="small text-muted"><i class="bi bi-geo-alt-fill me-1"></i><?php echo $address_display; ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="admin-order-card-footer">
                                    <button class="btn btn-sm admin-btn-action btn-view-details me-2" onclick='openOrderDetailsModal(<?php echo htmlspecialchars(json_encode($order, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>)'><i class="bi bi-eye-fill"></i> Detalles</button>
                                    <button class="btn btn-sm admin-btn-action btn-start-prep" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'processing', '<?php echo htmlspecialchars(function_exists('get_csrf_token') ? get_csrf_token() : '', ENT_QUOTES, 'UTF-8'); ?>')"><i class="bi bi-play-circle-fill me-1"></i> Preparar</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Columna En Preparación -->
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="admin-card status-card status-preparing-card h-100">
                    <div class="admin-card-header">
                        <h3 class="admin-card-title"><i class="bi bi-gear-wide-connected me-2"></i>En Preparación</h3>
                    </div>
                    <div class="admin-card-body order-list-body" id="preparing-orders-list">
                        <?php if (empty($preparing_orders)): ?>
                            <p class="text-center text-muted mt-3">Ningún pedido en preparación.</p>
                        <?php endif; ?>
                        <?php foreach ($preparing_orders as $order): ?>
                            <div class="admin-order-card mb-3" id="order-card-<?php echo $order['id']; ?>">
                                <div class="admin-order-card-header">
                                    <h6>Pedido #<?php echo htmlspecialchars($order['id']); ?></h6>
                                    <small class="order-time-ago"><?php echo htmlspecialchars(time_ago($order['created_at'])); ?></small>
                                </div>
                                <div class="admin-order-card-body">
                                    <?php
                                    $customerNameDisplay = "Cliente Desconocido";
                                    $orderDetailsData = json_decode($order['order_details'], true);
                                    if (isset($orderDetailsData['customer_name']) && !empty($orderDetailsData['customer_name'])) {
                                        $customerNameDisplay = htmlspecialchars($orderDetailsData['customer_name']);
                                    } elseif (!empty($order['user_customer_name'])) {
                                        $customerNameDisplay = htmlspecialchars($order['user_customer_name']);
                                    }
                                    ?>
                                    <p><strong>Cliente:</strong> <?php echo $customerNameDisplay; ?></p>
                                    <p><strong>Total:</strong> $<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>
                                    <p class="mb-1">
                                        <strong>Entrega:</strong> 
                                        <?php if ($order['is_scheduled'] == 1 && !empty($order['estimated_delivery_time'])): ?>
                                            <span style="color: #007bff;"><i class="bi bi-calendar-event"></i> Programado para: <?php echo htmlspecialchars(date('d/m/Y h:i A', strtotime($order['estimated_delivery_time']))); ?></span>
                                        <?php else: ?>
                                            <span>Lo antes posible</span>
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!empty($order['items']) && is_array($order['items'])): ?>
                                        <p class="mb-1"><strong>Artículos:</strong></p>
                                        <ul class="list-unstyled small ms-2 mb-2">
                                            <?php 
                                            $item_count = 0;
                                            foreach ($order['items'] as $item): 
                                                if ($item_count < 2): ?>
                                                <li><?php echo htmlspecialchars(isset($item['quantity']) ? $item['quantity'] : 'N/A'); ?>x <?php echo htmlspecialchars(isset($item['name']) ? $item['name'] : 'N/A'); ?></li>
                                            <?php 
                                                endif;
                                                $item_count++;
                                            endforeach; 
                                            if (count($order['items']) > 2): ?>
                                                <li>... y <?php echo (count($order['items']) - 2); ?> más.</li>
                                            <?php endif; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <?php if (!empty($order['shipping_address'])): ?>
                                        <?php
                                        $address_data = json_decode($order['shipping_address'], true);
                                        $address_display = 'No especificada';
                                        if (is_array($address_data) && isset($address_data['address'])) {
                                            $address_display = htmlspecialchars($address_data['address']);
                                        }
                                        ?>
                                        <p class="small text-muted"><i class="bi bi-geo-alt-fill me-1"></i><?php echo $address_display; ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="admin-order-card-footer">
                                    <button class="btn btn-sm admin-btn-action btn-view-details me-2" onclick='openOrderDetailsModal(<?php echo htmlspecialchars(json_encode($order, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>)'><i class="bi bi-eye-fill"></i> Detalles</button>
                                    <button class="btn btn-sm admin-btn-action btn-ready" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'shipped', '<?php echo htmlspecialchars(function_exists('get_csrf_token') ? get_csrf_token() : '', ENT_QUOTES, 'UTF-8'); ?>')"><i class="bi bi-check-circle-fill me-1"></i> Listo</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Columna Listos para Entregar/Recoger -->
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="admin-card status-card status-ready-card h-100">
                    <div class="admin-card-header">
                        <h3 class="admin-card-title"><i class="bi bi-box-seam me-2"></i>Listos</i></h3>
                    </div>
                    <div class="admin-card-body order-list-body" id="ready-orders-list">
                        <?php if (empty($ready_orders)): ?>
                            <p class="text-center text-muted mt-3">Ningún pedido listo.</p>
                        <?php endif; ?>
                        <?php foreach ($ready_orders as $order): ?>
                            <div class="admin-order-card mb-3" id="order-card-<?php echo $order['id']; ?>">
                                <div class="admin-order-card-header">
                                    <h6>Pedido #<?php echo htmlspecialchars($order['id']); ?></h6>
                                    <small class="order-time-ago"><?php echo htmlspecialchars(time_ago($order['created_at'])); ?></small>
                                </div>
                                <div class="admin-order-card-body">
                                    <?php
                                    $customerNameDisplay = "Cliente Desconocido";
                                    $orderDetailsData = json_decode($order['order_details'], true);
                                    if (isset($orderDetailsData['customer_name']) && !empty($orderDetailsData['customer_name'])) {
                                        $customerNameDisplay = htmlspecialchars($orderDetailsData['customer_name']);
                                    } elseif (!empty($order['user_customer_name'])) {
                                        $customerNameDisplay = htmlspecialchars($order['user_customer_name']);
                                    }
                                    ?>
                                    <p><strong>Cliente:</strong> <?php echo $customerNameDisplay; ?></p>
                                    <p><strong>Total:</strong> $<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>
                                    <p class="mb-1">
                                        <strong>Entrega:</strong> 
                                        <?php if ($order['is_scheduled'] == 1 && !empty($order['estimated_delivery_time'])): ?>
                                            <span style="color: #007bff;"><i class="bi bi-calendar-event"></i> Programado para: <?php echo htmlspecialchars(date('d/m/Y h:i A', strtotime($order['estimated_delivery_time']))); ?></span>
                                        <?php else: ?>
                                            <span>Lo antes posible</span>
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!empty($order['items']) && is_array($order['items'])): ?>
                                        <p class="mb-1"><strong>Artículos:</strong></p>
                                        <ul class="list-unstyled small ms-2 mb-2">
                                            <?php 
                                            $item_count = 0;
                                            foreach ($order['items'] as $item): 
                                                if ($item_count < 2): ?>
                                                <li><?php echo htmlspecialchars(isset($item['quantity']) ? $item['quantity'] : 'N/A'); ?>x <?php echo htmlspecialchars(isset($item['name']) ? $item['name'] : 'N/A'); ?></li>
                                            <?php 
                                                endif;
                                                $item_count++;
                                            endforeach; 
                                            if (count($order['items']) > 2): ?>
                                                <li>... y <?php echo (count($order['items']) - 2); ?> más.</li>
                                            <?php endif; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                                <div class="admin-order-card-footer">
                                    <button class="btn btn-sm admin-btn-action btn-view-details me-2" onclick='openOrderDetailsModal(<?php echo htmlspecialchars(json_encode($order, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>)'><i class="bi bi-eye-fill"></i> Detalles</button>
                                    <button class="btn btn-sm admin-btn-action btn-delivered" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'delivered', '<?php echo htmlspecialchars(function_exists('get_csrf_token') ? get_csrf_token() : '', ENT_QUOTES, 'UTF-8'); ?>')"><i class="bi bi-truck me-1"></i> Entregado</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Columna Programados -->
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="admin-card status-card status-scheduled-card h-100">
                    <div class="admin-card-header">
                        <h3 class="admin-card-title"><i class="bi bi-calendar-event me-2"></i>Programados</h3>
                    </div>
                    <div class="admin-card-body order-list-body" id="scheduled-orders-list">
                        <?php if (empty($scheduled_orders)): ?>
                            <p class="text-center text-muted mt-3">No hay pedidos programados.</p>
                        <?php endif; ?>
                        <?php foreach ($scheduled_orders as $order): ?>
                            <div class="admin-order-card mb-3 scheduled-order-highlight" id="order-card-<?php echo $order['id']; ?>">
                                <div class="admin-order-card-header">
                                    <h6>Pedido #<?php echo htmlspecialchars($order['id']); ?></h6>
                                    <small class="order-time-ago text-muted">Creado: <?php echo htmlspecialchars(time_ago($order['created_at'])); ?></small>
                                </div>
                                <div class="admin-order-card-body">
                                    <?php if (!empty($order['estimated_delivery_time']) && $order['estimated_delivery_time'] !== '0000-00-00 00:00:00'): ?>
                                        <div class="scheduled-time-prominent text-center my-2 p-2 rounded">
                                            <h5 class="mb-0"><i class="bi bi-alarm-fill me-2"></i>Programado para:</h5>
                                            <p class="lead fw-bold mb-0"><?php echo htmlspecialchars(date("d M Y, H:i A", strtotime($order['estimated_delivery_time']))); ?></p>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-center text-warning my-2"><i>Hora de entrega no especificada.</i></p>
                                    <?php endif; ?>

                                    <?php
                                    $customerNameDisplay = "Cliente Desconocido";
                                    $orderDetailsData = json_decode($order['order_details'], true);
                                    if (isset($orderDetailsData['customer_name']) && !empty($orderDetailsData['customer_name'])) {
                                        $customerNameDisplay = htmlspecialchars($orderDetailsData['customer_name']);
                                    } elseif (!empty($order['user_customer_name'])) {
                                        $customerNameDisplay = htmlspecialchars($order['user_customer_name']);
                                    }
                                    ?>
                                    <p><strong>Cliente:</strong> <?php echo $customerNameDisplay; ?></p>
                                    <p><strong>Total:</strong> $<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></p>
                                    <p class="mb-1" style="font-weight: bold; color: #007bff;">
                                        <i class="bi bi-calendar-event"></i> Programado para: <?php echo htmlspecialchars(date('d/m/Y h:i A', strtotime($order['estimated_delivery_time']))); ?>
                                    </p>
                                    <?php if (!empty($order['items']) && is_array($order['items'])): ?>
                                        <p class="mb-1"><strong>Artículos:</strong></p>
                                        <ul class="list-unstyled small ms-2 mb-2">
                                            <?php 
                                            $item_count = 0;
                                            foreach ($order['items'] as $item): 
                                                if ($item_count < 2): ?>
                                                <li><?php echo htmlspecialchars(isset($item['quantity']) ? $item['quantity'] : 'N/A'); ?>x <?php echo htmlspecialchars(isset($item['name']) ? $item['name'] : 'N/A'); ?></li>
                                            <?php 
                                                endif;
                                                $item_count++;
                                            endforeach; 
                                            if (count($order['items']) > 2): ?>
                                                <li>... y <?php echo (count($order['items']) - 2); ?> más.</li>
                                            <?php endif; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <p class="small text-muted">Estado: <?php echo ucfirst(htmlspecialchars($order['status'])); ?></p>
                                </div>
                                <div class="admin-order-card-footer">
                                    <button class="btn btn-sm admin-btn-action btn-view-details me-2" onclick='openOrderDetailsModal(<?php echo htmlspecialchars(json_encode($order, JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>)'><i class="bi bi-eye-fill"></i> Detalles</button>
                                    <?php if ($order['status'] == 'pending' || $order['status'] == 'scheduled'): // Permitir preparar si está pendiente o programado ?>
                                        <button class="btn btn-sm admin-btn-action btn-start-prep" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'processing', '<?php echo htmlspecialchars(function_exists('get_csrf_token') ? get_csrf_token() : '', ENT_QUOTES, 'UTF-8'); ?>')"><i class="bi bi-play-circle-fill me-1"></i> Preparar</button>
                                    <?php elseif ($order['status'] == 'processing'): ?>
                                        <button class="btn btn-sm admin-btn-action btn-ready" onclick="updateOrderStatus(<?php echo $order['id']; ?>, 'shipped', '<?php echo htmlspecialchars(function_exists('get_csrf_token') ? get_csrf_token() : '', ENT_QUOTES, 'UTF-8'); ?>')"><i class="bi bi-check-circle-fill me-1"></i> Listo</button>
                                    <?php else: ?>
                                        <span class="badge bg-info status-badge-info">Estado: <?php echo htmlspecialchars(ucfirst($order['status'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

<!-- Modal de Detalles del Pedido -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="orderDetailsModalLabel">Detalles del Pedido #<span id="modalOrderId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modalOrderCustomerInfo" class="mb-3">
                    <!-- La información del cliente se poblará aquí -->
                </div>
                
                <!-- NUEVO: Elemento para información de entrega/programación -->
                <div id="modalOrderDeliveryInfo" class="mb-3">
                    <!-- La información de entrega/programación se poblará aquí -->
                </div>
                <!-- FIN NUEVO -->

                <div id="modalOrderItems" class="mb-3">
                    <h6>Artículos:</h6>
                    <ul class="list-group" id="modalOrderItemsList">
                        <!-- Los artículos se poblarán aquí -->
                    </ul>
                </div>
                <div id="modalOrderTotals" class="mb-3">
                    <!-- Los totales se poblarán aquí -->
                </div>
                 <div id="modalOrderShippingAddress" class="mb-3">
                    <!-- Dirección de envío -->
                </div>
                <div id="modalOrderStatusInfo" class="mb-3">
                    <!-- Estado e historial -->
                </div>
                <div id="modalOrderPaymentInfo" class="mb-3">
                    <!-- Información de pago -->
                </div>

                <div class="mb-3">
                    <h6>Notas del Pedido:</h6>
                    <textarea id="modalOrderNotes" class="form-control" rows="3" placeholder="Añadir notas para este pedido..."></textarea>
                    <button class="btn btn-sm btn-outline-secondary mt-2" id="saveOrderNotesBtn">Guardar Notas</button>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <!-- Se pueden añadir más botones de acción aquí si es necesario -->
            </div>
        </div>
    </div>
</div>


    <script>
function updateOrderStatus(orderId, newStatus, csrfToken) {
    if (!confirm(`¿Estás seguro de que quieres cambiar el estado de este pedido a "${newStatus === 'processing' ? 'En Preparación' : (newStatus === 'shipped' ? 'Listo' : (newStatus === 'delivered' ? 'Entregado' : newStatus))}"?`)) {
        return;
    }

            fetch('api/update_order_status.php', {
                method: 'POST',
                headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
                },
        body: `order_id=${orderId}&status=${newStatus}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
            const orderCard = document.getElementById(`order-card-${orderId}`);
            if (orderCard) {
                let targetListId;
                let originalList = orderCard.parentElement;

                if (newStatus === 'pending') targetListId = 'pending-orders-list';
                else if (newStatus === 'processing') targetListId = 'preparing-orders-list';
                else if (newStatus === 'shipped' || newStatus === 'ready_for_pickup') targetListId = 'ready-orders-list';
                
                if (targetListId) {
                    const targetList = document.getElementById(targetListId);
                    if (targetList) {
                        const detailsButtonClone = orderCard.querySelector('.btn-view-details').cloneNode(true);
                        
                        targetList.insertBefore(orderCard, targetList.firstChild);
                        updateActionButtons(orderCard, newStatus, csrfToken, detailsButtonClone);

                        checkIfListEmpty(originalList); 
                        checkIfListEmpty(targetList);

                    }
                } else {
                    orderCard.remove();
                    checkIfListEmpty(originalList);
                }
            }
        } else {
            alert('Error al actualizar el estado del pedido: ' + (data.message || 'Error desconocido.'));
                }
            })
            .catch(error => {
        console.error('Error en la petición:', error);
        alert('Error de conexión al actualizar el estado.');
    });
}

function updateActionButtons(orderCard, newStatus, csrfToken, detailsButton) {
    const footer = orderCard.querySelector('.admin-order-card-footer');
    if (!footer) return;

    footer.innerHTML = '';
    if (detailsButton) {
         footer.appendChild(detailsButton);
    }
    
    let newButtonHTML = '';
    const orderId = orderCard.id.split('-')[2];

    if (newStatus === 'pending') {
        newButtonHTML = `<button class="btn btn-sm admin-btn-action btn-start-prep ms-2" onclick="updateOrderStatus(${orderId}, 'processing', '${csrfToken}')\"><i class="bi bi-play-circle-fill me-1"></i> Preparar</button>`;
    } else if (newStatus === 'processing') {
        newButtonHTML = `<button class="btn btn-sm admin-btn-action btn-ready ms-2" onclick="updateOrderStatus(${orderId}, 'shipped', '${csrfToken}')\"><i class="bi bi-check-circle-fill me-1"></i> Listo</button>`;
    } else if (newStatus === 'shipped' || newStatus === 'ready_for_pickup') {
        newButtonHTML = `<button class="btn btn-sm admin-btn-action btn-delivered ms-2" onclick="updateOrderStatus(${orderId}, 'delivered', '${csrfToken}')\"><i class="bi bi-truck me-1"></i> Entregado</button>`;
    }
    
    if (newButtonHTML) {
      footer.insertAdjacentHTML('beforeend', newButtonHTML);
    }
}

function checkIfListEmpty(listElement) {
    if (!listElement) return;
    const orderCards = listElement.querySelectorAll('.admin-order-card');
    let noOrdersMessage = listElement.querySelector('.text-center.text-muted');

    if (orderCards.length === 0) {
        if (!noOrdersMessage) { 
            const p = document.createElement('p');
            p.className = 'text-center text-muted mt-3';
            if(listElement.id === 'pending-orders-list') p.textContent = 'No hay pedidos nuevos.';
            else if(listElement.id === 'preparing-orders-list') p.textContent = 'Ningún pedido en preparación.';
            else if(listElement.id === 'ready-orders-list') p.textContent = 'Ningún pedido listo.';
            else if(listElement.id === 'scheduled-orders-list') p.textContent = 'No hay pedidos programados.';
            else p.textContent = 'No hay pedidos en esta categoría.';
            listElement.appendChild(p);
        } else {
            noOrdersMessage.style.display = 'block';
        }
    } else {
        if (noOrdersMessage) {
            noOrdersMessage.style.display = 'none';
        }
    }
}

var orderDetailsModalInstance;
document.addEventListener('DOMContentLoaded', function () {
    var modalElement = document.getElementById('orderDetailsModal');
    if (modalElement) {
        orderDetailsModalInstance = new bootstrap.Modal(modalElement);
    }
    checkIfListEmpty(document.getElementById('pending-orders-list'));
    checkIfListEmpty(document.getElementById('preparing-orders-list'));
    checkIfListEmpty(document.getElementById('ready-orders-list'));
    checkIfListEmpty(document.getElementById('scheduled-orders-list'));
});

function formatDateTime(dateTimeString) {
    if (!dateTimeString || dateTimeString === '0000-00-00 00:00:00') {
        return 'No especificada';
    }
    try {
        const date = new Date(dateTimeString);
        if (isNaN(date)) {
            return 'Fecha inválida';
        }
        // Formato: DD/MM/YYYY hh:mm AM/PM
        let day = date.getDate().toString().padStart(2, '0');
        let month = (date.getMonth() + 1).toString().padStart(2, '0'); // Meses son 0-indexados
        let year = date.getFullYear();
        let hours = date.getHours();
        let minutes = date.getMinutes().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // La hora '0' debe ser '12'
        return `${day}/${month}/${year} ${hours}:${minutes} ${ampm}`;
    } catch (e) {
        console.error("Error formatting date:", e);
        return dateTimeString; // Devuelve original si hay error
    }
}

function openOrderDetailsModal(order) {
    console.log("Abriendo modal para pedido:", order);
    if (!order || typeof order !== 'object') {
        console.error("Error: Datos del pedido no válidos para el modal.");
        showToast("Error al cargar detalles del pedido.", "error");
        return;
    }

    document.getElementById('modalOrderId').textContent = order.id || 'N/A';

    // Información del cliente
    let customerInfoHtml = '<h6>Información del Cliente:</h6>';
    let customerName = "Cliente Desconocido"; // Default

    // Intenta obtener el nombre del cliente desde order_details si shipping_address no lo tiene o es ambiguo
    let orderDetailsData = null;
    if (order.order_details) {
        try {
            orderDetailsData = JSON.parse(order.order_details);
        } catch (e) {
            console.warn("No se pudo parsear order_details:", e);
        }
    }

    if (orderDetailsData && orderDetailsData.customer_name) {
        customerName = orderDetailsData.customer_name;
    } else if (order.user_customer_name) { // Fallback al nombre de usuario de la tabla users
        customerName = order.user_customer_name;
    }
    // Si aún no hay nombre, se podría intentar desde shipping_address si existe ese campo y es JSON
    // pero la prioridad es order_details.customer_name.

    customerInfoHtml += `<p><strong>Nombre:</strong> ${sanitizeHTML(customerName)}</p>`;
    customerInfoHtml += `<p><strong>Email:</strong> ${sanitizeHTML(order.user_customer_email || (orderDetailsData && orderDetailsData.customer_email ? orderDetailsData.customer_email : 'No disponible'))}</p>`;
    customerInfoHtml += `<p><strong>Teléfono:</strong> ${sanitizeHTML(order.customer_phone || (orderDetailsData && orderDetailsData.customer_phone ? orderDetailsData.customer_phone : 'No disponible'))}</p>`;
    document.getElementById('modalOrderCustomerInfo').innerHTML = customerInfoHtml;

    // NUEVO: Información de Entrega/Programación
    let deliveryInfoHtml = '<h6>Información de Entrega:</h6>';
    if (order.is_scheduled == 1 && order.estimated_delivery_time && order.estimated_delivery_time !== '0000-00-00 00:00:00') {
        deliveryInfoHtml += `<p style="color: #007bff; font-weight: bold;"><i class="bi bi-calendar-event"></i> <strong>Programado para:</strong> ${formatDateTime(order.estimated_delivery_time)}</p>`;
    } else {
        deliveryInfoHtml += '<p><strong>Entrega:</strong> Lo antes posible</p>';
    }
    document.getElementById('modalOrderDeliveryInfo').innerHTML = deliveryInfoHtml;
    // FIN NUEVO


    // Items
    const itemsList = document.getElementById('modalOrderItemsList');
    itemsList.innerHTML = ''; // Limpiar lista anterior
    if (order.items && Array.isArray(order.items) && order.items.length > 0) {
        order.items.forEach(item => {
            const li = document.createElement('li');
            li.className = 'list-group-item d-flex justify-content-between align-items-center';
            
            let itemName = sanitizeHTML(item.name || 'Artículo Desconocido');
            let itemQuantity = sanitizeHTML(item.quantity || 'N/A');
            let itemPrice = parseFloat(item.price || 0).toFixed(2);
            let itemTotal = (parseFloat(item.quantity || 0) * parseFloat(item.price || 0)).toFixed(2);

            let itemDetailsHtml = `${itemQuantity}x ${itemName} - $${itemPrice} c/u`;
            
            // Opciones si existen
            if (item.options && typeof item.options === 'object' && Object.keys(item.options).length > 0) {
                itemDetailsHtml += '<small class="d-block text-muted">';
                for (const groupName in item.options) {
                    if (Array.isArray(item.options[groupName])) {
                         item.options[groupName].forEach(op => {
                            itemDetailsHtml += `&nbsp;&nbsp;&nbsp;↳ ${sanitizeHTML(op.name || groupName)} (+${parseFloat(op.price || 0).toFixed(2)})<br>`;
                         });
                    } else if (typeof item.options[groupName] === 'string') { // Para opciones simples como string
                        itemDetailsHtml += `&nbsp;&nbsp;&nbsp;↳ ${sanitizeHTML(groupName)}: ${sanitizeHTML(item.options[groupName])}<br>`;
                    }
                }
                itemDetailsHtml += '</small>';
            }


            li.innerHTML = `<span>${itemDetailsHtml}</span> <span class="badge bg-primary rounded-pill">$${itemTotal}</span>`;
            itemsList.appendChild(li);
        });
    } else {
        itemsList.innerHTML = '<li class="list-group-item">No hay artículos detallados para este pedido.</li>';
    }

    // Totales
    let totalsHtml = '<h6>Totales:</h6>';
    totalsHtml += `<p><strong>Subtotal:</strong> $${parseFloat(order.subtotal_amount || 0).toFixed(2)}</p>`;
    if (order.discount_amount && parseFloat(order.discount_amount) > 0) {
        totalsHtml += `<p><strong>Descuento:</strong> -$${parseFloat(order.discount_amount).toFixed(2)}</p>`;
    }
    if (order.delivery_fee && parseFloat(order.delivery_fee) > 0) {
        totalsHtml += `<p><strong>Costo de Envío:</strong> $${parseFloat(order.delivery_fee).toFixed(2)}</p>`;
    }
    totalsHtml += `<p><strong>Total General:</strong> $${parseFloat(order.total_amount || 0).toFixed(2)}</p>`;
    document.getElementById('modalOrderTotals').innerHTML = totalsHtml;

    // Dirección de Envío
    let shippingAddressHtml = '<h6>Dirección de Envío:</h6>';
    let addressDisplay = "No especificada o es para recoger en tienda.";
    if (order.shipping_address) {
        try {
            const addressData = JSON.parse(order.shipping_address);
            if (addressData && typeof addressData === 'object') {
                addressDisplay = '';
                if(addressData.address) addressDisplay += `${sanitizeHTML(addressData.address)}, `;
                if(addressData.city) addressDisplay += `${sanitizeHTML(addressData.city)}, `;
                if(addressData.postal_code) addressDisplay += `CP ${sanitizeHTML(addressData.postal_code)}. `;
                if(addressData.reference) addressDisplay += `Referencia: ${sanitizeHTML(addressData.reference)}. `;
                if(addressData.delivery_instructions) addressDisplay += `Instrucciones: ${sanitizeHTML(addressData.delivery_instructions)}. `;
                if(addressData.zone_name) addressDisplay += `Zona: ${sanitizeHTML(addressData.zone_name)}. `;
                if(order.distance_km) addressDisplay += `Distancia: ${parseFloat(order.distance_km).toFixed(2)} km. `;

                // Trim trailing comma and space if any
                addressDisplay = addressDisplay.replace(/, $/, '').trim();
                if (!addressDisplay) addressDisplay = "Detalles de dirección no proporcionados.";
            }
        } catch (e) {
            console.warn("No se pudo parsear shipping_address:", e);
            addressDisplay = sanitizeHTML(order.shipping_address); // Mostrar como texto plano si no es JSON válido
        }
    }
    shippingAddressHtml += `<p>${addressDisplay}</p>`;
    document.getElementById('modalOrderShippingAddress').innerHTML = shippingAddressHtml;

    // Estado e Historial (simplificado por ahora, podría expandirse)
    let statusInfoHtml = '<h6>Estado del Pedido:</h6>';
    statusInfoHtml += `<p><strong>Estado Actual:</strong> <span class="badge bg-info text-dark">${sanitizeHTML(order.status ? order.status.toUpperCase() : 'N/A')}</span></p>`;
    statusInfoHtml += `<p><strong>Creado:</strong> ${formatDateTime(order.created_at)}</p>`;
    if(order.updated_at && order.updated_at !== order.created_at) {
        statusInfoHtml += `<p><strong>Última Actualización:</strong> ${formatDateTime(order.updated_at)}</p>`;
    }
    // Aquí se podría añadir un historial de cambios de estado si se registra
    document.getElementById('modalOrderStatusInfo').innerHTML = statusInfoHtml;


    // Información de Pago
    let paymentInfoHtml = '<h6>Información de Pago:</h6>';
    paymentInfoHtml += `<p><strong>Método de Pago:</strong> ${sanitizeHTML(order.payment_method || 'No especificado')}</p>`;
    paymentInfoHtml += `<p><strong>ID de Transacción:</strong> ${sanitizeHTML(order.transaction_id || 'N/A')}</p>`;
    paymentInfoHtml += `<p><strong>Estado del Pago:</strong> ${sanitizeHTML(order.payment_status || 'No especificado')}</p>`;
    document.getElementById('modalOrderPaymentInfo').innerHTML = paymentInfoHtml;


    // Notas del pedido
    const notesTextarea = document.getElementById('modalOrderNotes');
    notesTextarea.value = order.notes || '';
    // Configurar el botón de guardar notas para el pedido actual
    const saveNotesBtn = document.getElementById('saveOrderNotesBtn');
    saveNotesBtn.onclick = function() {
        saveOrderNotes(order.id, notesTextarea.value);
    };


    var myModal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
    myModal.show();
}

function sanitizeHTML(str) {
    if (str === null || typeof str === 'undefined') {
        return '';
    }
    const temp = document.createElement('div');
    temp.textContent = str;
    return temp.innerHTML;
}

function saveOrderNotes(orderId, notes) {
    // (Implementación existente de saveOrderNotes) ...
    // Suponiendo que tienes una función para guardar notas mediante AJAX:
    console.log(`Guardando notas para el pedido ${orderId}: ${notes}`);
    fetch('update_order_notes.php', { // Asegúrate de que este endpoint exista y maneje la actualización
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            // Incluir CSRF token si es necesario, como en updateOrderStatus
            'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : ''
        },
        body: JSON.stringify({ order_id: orderId, notes: notes, csrf_token: (typeof csrfToken !== 'undefined' ? csrfToken : '') })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Notas guardadas exitosamente.', 'success');
            // Opcional: actualizar el objeto 'order' en la UI si es necesario, o recargar.
        } else {
            showToast('Error al guardar las notas: ' + (data.message || 'Error desconocido'), 'error');
        }
    })
    .catch(error => {
        console.error('Error en saveOrderNotes:', error);
        showToast('Error de red al guardar las notas.', 'error');
    });
}


// ... (resto del script, incluyendo admin_footer_scripts.php si existe o su contenido) ...
</script>

<?php include __DIR__ . '/includes/admin_footer.php'; ?> 