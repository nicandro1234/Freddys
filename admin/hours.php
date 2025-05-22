<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'adminconfig.php'; // Carga la configuración del admin (que incluye db.php)
requireAuth(); // Asegura que el usuario esté autenticado

require_once __DIR__ . '/classes/StoreHours.php';

// Verificar autenticación
if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

// Obtener el estado actual de la tienda
$storeStatus = [];
if (function_exists('getStoreStatus')) {
    $storeStatus = getStoreStatus();
}

if (!isset($pdo)) {
    error_log("FATAL ERROR en admin/hours.php: \$pdo no está definido.");
    die("Error crítico de configuración de base de datos. Revise los logs.");
}

$storeHours = new StoreHours($pdo);

// Procesar actualización de horarios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar si la acción es para actualizar horarios (y no, por ejemplo, para toggle_store_state del header)
    if (isset($_POST['day']) && isset($_POST['open_time']) && isset($_POST['close_time'])) {
        $day = $_POST['day'];
        $openTime = $_POST['open_time'];
        $closeTime = $_POST['close_time'];
        $isClosed = isset($_POST['is_closed']) ? 1 : 0;
        
        if ($storeHours->updateHours($day, $openTime, $closeTime, $isClosed)) {
            $_SESSION['hours_success_message'] = "Horarios actualizados correctamente para \"" . ucfirst($day) . "\"";
        } else {
            $_SESSION['hours_error_message'] = "Error al actualizar los horarios para \"" . ucfirst($day) . "\"";
        }
        header('Location: hours.php'); // Redirigir solo después de actualizar horarios
        exit;
    }
    // Si no es una actualización de horarios, el script continúa y permite que admin_header.php maneje otros POSTs
}

// Obtener horarios actuales
$hours = $storeHours->getHours();

$page_title = "Gestión de Horarios";
include __DIR__ . '/includes/admin_header.php';
include __DIR__ . '/includes/admin_sidebar.php';
?>

<main class="admin-main-content">
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card admin-card">
                    <div class="card-header admin-card-header">
                        <h4 class="mb-0">Gestión de Horarios</h4>
                    </div>
                    <div class="card-body admin-card-body">
                        <?php if (isset($_SESSION['hours_success_message'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($_SESSION['hours_success_message']); unset($_SESSION['hours_success_message']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['hours_error_message'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($_SESSION['hours_error_message']); unset($_SESSION['hours_error_message']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="hours.php" class="mb-4 admin-card p-3" style="background-color: #2a2e37;">
                            <div class="row g-3 align-items-center">
                                <div class="col-lg-2 col-md-4 col-sm-6 col-12 admin-form-group">
                                    <label for="day" class="form-label">Día</label>
                                    <select class="form-select" id="day" name="day" required>
                                        <option value="">Seleccionar día</option>
                                        <option value="monday">Lunes</option>
                                        <option value="tuesday">Martes</option>
                                        <option value="wednesday">Miércoles</option>
                                        <option value="thursday">Jueves</option>
                                        <option value="friday">Viernes</option>
                                        <option value="saturday">Sábado</option>
                                        <option value="sunday">Domingo</option>
                                    </select>
                                </div>
                                
                                <div class="col-lg-2 col-md-4 col-sm-6 col-12 admin-form-group">
                                    <label for="open_time" class="form-label">Apertura</label>
                                    <input type="time" class="form-control" id="open_time" name="open_time">
                                </div>
                                
                                <div class="col-lg-2 col-md-4 col-sm-6 col-12 admin-form-group">
                                    <label for="close_time" class="form-label">Cierre</label>
                                    <input type="time" class="form-control" id="close_time" name="close_time">
                                </div>
                                
                                <div class="col-lg-3 col-md-6 col-sm-6 col-12 admin-form-group d-flex align-items-center justify-content-start justify-content-md-center mt-3 mt-sm-0">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="is_closed" name="is_closed" style="transform: scale(1.4); margin-right: .5rem;">
                                        <label class="form-check-label" for="is_closed">
                                            Cerrado
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-3 col-md-6 col-sm-12 col-12 admin-form-group d-flex align-items-end mt-3 mt-md-0">
                                    <button type="submit" class="btn admin-btn btn-primary w-100">Actualizar Horario</button>
                                </div>
                            </div>
                        </form>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered hours-table admin-table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Día</th>
                                        <th>Hora de Apertura</th>
                                        <th>Hora de Cierre</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hours as $hour): ?>
                                        <tr>
                                            <td><?php echo ucfirst($hour['day_of_week']); ?></td>
                                            <td><?php echo $hour['open_time'] ? date('h:i A', strtotime($hour['open_time'])) : 'N/A'; ?></td>
                                            <td><?php echo $hour['close_time'] ? date('h:i A', strtotime($hour['close_time'])) : 'N/A'; ?></td>
                                            <td>
                                                <?php if ($hour['is_closed']): ?>
                                                    <span class="badge bg-danger">Cerrado</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Abierto</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/admin_footer.php'; ?> 