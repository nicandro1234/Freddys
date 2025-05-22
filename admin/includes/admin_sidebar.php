<?php
// admin/includes/admin_sidebar.php

// Determinar la página activa para el estilo del menú.
// Esto es un ejemplo simple. Puedes hacerlo más robusto.
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<aside class="admin-sidebar">
    <div class="sidebar-top-content"> <!-- Nuevo contenedor para todo excepto el footer -->
        <div class="sidebar-header">
            <!-- Logo en lugar de texto -->
            <img src="../../assets/img/logo/LogoBIG.png" alt="Freddy's Pizza Logo" class="sidebar-logo-image">
        </div>

        <nav class="admin-nav">
            <ul>
                <li>
                    <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                        <i class="fas fa-receipt"></i> <!-- Icono cambiado para Pedidos -->
                        Pedidos
                    </a>
                </li>
                <li>
                    <a href="history.php" class="<?php echo ($current_page == 'history.php') ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i> 
                        Historial Pedidos
                    </a>
                </li>
                <li>
                    <a href="menu_manager.php" class="<?php echo ($current_page == 'menu_manager.php') ? 'active' : ''; ?>">
                        <i class="fas fa-utensils"></i>
                        Gestor de Menú
                    </a>
                </li>
                <li>
                    <a href="hours.php" class="<?php echo ($current_page == 'hours.php') ? 'active' : ''; ?>">
                        <i class="fas fa-clock"></i> 
                        Horarios
                    </a>
                </li>
                <!-- Enlaces de Notificaciones y Planificador Semanal eliminados -->
            </ul>
        </nav>
    </div> <!-- Fin de .sidebar-top-content -->

    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn-sidebar-professional">
            <i class="fas fa-sign-out-alt"></i> 
            Cerrar Sesión
        </a>
    </div>
</aside>

<!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> -->

