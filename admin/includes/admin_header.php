<?php
// admin/includes/admin_header.php
require_once __DIR__ . '/../adminconfig.php'; // Anteriormente config.php

// Obtener el estado actual de la tienda para mostrar en el botón
$storeStatusInfo = getStoreStatus();
$store_is_open = $storeStatusInfo['is_open'] ?? true; // Asumir abierto si hay error

// Es una buena práctica iniciar la sesión en un archivo de configuración global o al principio de cada script.
// Si no lo haces ya, considera añadir session_start(); aquí o en un config.php que incluyas.
// session_start(); 

// Definir $page_title en la página principal antes de incluir este header.
// Ejemplo: <?php $page_title = "Pedidos"; include 'includes/admin_header.php'; ?>
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - Panel Admin' : 'Panel Admin - Freddy\'s Pizza'; ?></title>
    
    <!-- Favicon (ruta relativa desde la raíz del sitio) -->
    <link rel="shortcut icon" href="../../assets/img/favicon.png"> 

    <!-- Bootstrap CSS (ruta relativa desde admin/includes/admin_header.php a assets/css/) -->
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    
    <!-- Estilos principales (compilados desde SCSS) -->
    <link rel="stylesheet" href="../../assets/css/main.css"> 
    
    <!-- Font Awesome (si lo usas para iconos en el admin, asegúrate que main.css lo incluya o enlaza aquí) -->
    <!-- <link rel="stylesheet" href="../assets/css/all.min.css"> -->

    <!-- Google Fonts (Ejemplo con Roboto, ajusta o quita si ya está global) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- Si tienes una fuente específica para el logo y la quieres usar en texto: -->
    <!-- <link href="URL_DE_TU_FUENTE_LOGO" rel="stylesheet"> -->

    <!-- Bootstrap Icons CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css"> <!-- Ajusta la versión si usas otra -->

</head>
<body class="admin-body">

    <header class="admin-header">
        <div class="admin-header-logo-placeholder" style="width: 180px;"> <!-- Espacio reservado para mantener alineación, ajustar ancho si es necesario -->
            <!-- Logo eliminado del header -->
        </div>
        <div class="admin-header-page-title-container">
            <h1 class="admin-header-page-title"><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Panel'; ?></h1>
        </div>
        <div class="admin-store-status">
            <?php
            // Lógica para obtener el estado actual de la tienda (ej. de un archivo de config o DB)
            // Este es un placeholder, necesitarás implementar cómo se obtiene y actualiza $store_is_open
            
            // Asegurarse de que getStoreStatus() esté disponible o reemplazar con tu lógica real
            if (function_exists('getStoreStatus')) {
                $storeStatusInfo = getStoreStatus(); // Asumiendo que esto devuelve un array ['is_open' => true/false, ...]
                $store_is_open = $storeStatusInfo['is_open'] ?? true; // Valor por defecto si no se puede determinar
            } else {
                // Fallback si la función no existe (esto debería evitar errores fatales)
                $store_is_open = true; // O un valor por defecto más conservador como false
                error_log("Advertencia: La función getStoreStatus() no está definida en admin_header.php. Usando valor por defecto para estado de tienda.");
            }

            $button_text = $store_is_open ? "Tienda Abierta" : "Tienda Cerrada";
            $button_class = $store_is_open ? "status-open" : "status-closed"; 
            // Convertir el estado booleano a 'true' o 'false' string para JS
            // $js_store_is_open = $store_is_open ? 'true' : 'false'; // Ya no se necesita para JS
            ?>
            <!-- Restaurar botón para usar Modal -->
            <button type="button" class="status-toggle-btn <?php echo $button_class; ?>" 
                    data-bs-toggle="modal" data-bs-target="#storeStatusModal" 
                    title="Haz clic para cambiar el estado de la tienda">
                <i class="fas <?php echo $store_is_open ? 'fa-store' : 'fa-store-slash'; ?>"></i>
                <?php echo $button_text; ?>
            </button>
            <!-- Fin de botón restaurado -->
        </div>
    </header>
    
    <!-- El .admin-main-content y el sidebar vendrán después, típicamente en el archivo principal o el sidebar include -->

    <!-- Script para el toggle de estado de tienda ELIMINADO -->
