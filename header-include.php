<?php
// Este archivo se debe incluir en el encabezado de las páginas HTML
// Verifica si hay errores en la inclusión de configuración
$config_error = false;

// Configurar encabezados para mejorar la seguridad y evitar problemas de CORS
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// Permitir CORS para desarrollo local
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");
}

// Intentar incluir la configuración
try {
    if(!@include_once __DIR__ . '/config.php') {
        $config_error = true;
    }
} catch (Exception $e) {
    $config_error = true;
}

// Genera dinámicamente etiquetas script para APIs externas
// **DESACTIVADO**: La carga ahora se maneja dinámicamente desde el JS del frontend (index.html, my-account.html)
/*
function generate_api_tags() {
    // API de Google Maps
    if (defined('GOOGLE_MAPS_API_KEY')) {
        // echo '<script src="https://maps.googleapis.com/maps/api/js?key=' . GOOGLE_MAPS_API_KEY . '&libraries=places&callback=initMap" async defer></script>';
    }
    
    // API de PayPal
    if (defined('PAYPAL_CLIENT_ID')) {
        // echo '<script src="https://www.paypal.com/sdk/js?client-id=' . PAYPAL_CLIENT_ID . '&currency=MXN" defer></script>';
    }
    
    // Mercado Pago API
    if (defined('MP_PUBLIC_KEY')) {
        // echo '<script src="https://sdk.mercadopago.com/js/v2"></script>';
    }
}
*/

// NO llamar a generate_api_tags() aquí si existía la llamada
// generate_api_tags(); 

?>
<!-- Configuración JavaScript -->
<script src="config.js.php"></script> 