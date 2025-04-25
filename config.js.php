<?php
// Configurar encabezados para evitar problemas de caché y CORS
header('Content-Type: application/javascript');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Permitir CORS para desarrollo local
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
} else {
    header('Access-Control-Allow-Origin: *');
}

// Incluir archivo de configuración
require_once __DIR__ . '/config.php';

// Convertir constantes PHP a objeto JS
$constants = array(
    'MP_PUBLIC_KEY',
    'SITE_URL',
    'PAYPAL_CLIENT_ID',
    'GOOGLE_MAPS_API_KEY',
    'GOOGLE_CLIENT_ID',
    'REDIRECT_URI'
);

$config = array();
foreach ($constants as $const) {
    if (defined($const)) {
        $config[$const] = constant($const);
    } else {
        // Valores predeterminados para evitar errores
        $config[$const] = '';
        error_log("Constante $const no definida en config.js.php");
    }
}

// Imprimir objeto JavaScript
echo "// Configuración generada por config.js.php: " . date('Y-m-d H:i:s') . "\n";
echo "window.config = " . json_encode($config, JSON_PRETTY_PRINT) . ";\n\n";

// Evento para notificar que la configuración está cargada
echo <<<'EOT'
// Notificar que la configuración está lista con event listener
(function() {
    console.log("[Config] Configuración cargada correctamente");
    
    try {
        // Verificar si estamos en un navegador
        if (typeof document !== 'undefined' && document.dispatchEvent) {
            // Crear evento personalizado con compatibilidad para IE
            var configEvent;
            if (typeof(Event) === 'function') {
                configEvent = new Event('configLoaded');
            } else {
                configEvent = document.createEvent('Event');
                configEvent.initEvent('configLoaded', true, true);
            }
            
            // Disparar evento asincrónicamente para asegurar que los listeners estén registrados
            setTimeout(function() {
                document.dispatchEvent(configEvent);
                console.log("[Config] Evento configLoaded disparado");
            }, 0);
        }
    } catch (e) {
        console.error("[Config] Error al disparar evento configLoaded:", e);
    }
})();

// Exponer función para verificar si la configuración está disponible
window.isConfigReady = function() {
    return window.config && Object.keys(window.config).length > 0;
};

// Exponer función para obtener valores de configuración con valores predeterminados
window.getConfigValue = function(key, defaultValue) {
    if (!window.config || !window.config[key]) {
        console.warn("[Config] Valor de configuración no encontrado para: " + key);
        return defaultValue;
    }
    return window.config[key];
};
EOT;
?> 