<?php
// Cargar directamente el archivo .env.php
$env_file = __DIR__ . '/.env.php';
$env_vars = [];

if (file_exists($env_file)) {
    $env_vars = require $env_file;
    
    // Definir constantes para las variables de entorno
    if (!empty($env_vars)) {
        foreach ($env_vars as $key => $value) {
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
} else {
    error_log('Archivo .env.php no encontrado en: ' . $env_file);
    die('Error: No se pudo cargar la configuración');
}

// Verificar que las claves importantes estén definidas
$required_keys = [
    'DB_HOST',
    'DB_USERNAME',
    'DB_PASSWORD',
    'DB_DBNAME',
    'MP_PUBLIC_KEY',
    'PAYPAL_CLIENT_ID',
    'GOOGLE_MAPS_API_KEY',
    'GOOGLE_CLIENT_ID',
    'REDIRECT_URI'
];

$missing_keys = [];
foreach ($required_keys as $key) {
    if (!defined($key) || empty(constant($key))) {
        $missing_keys[] = $key;
    }
}

if (!empty($missing_keys)) {
    error_log('Las siguientes claves no están definidas o están vacías: ' . implode(', ', $missing_keys));
    die('Error: Faltan claves de configuración requeridas');
}

// No agregar script aquí - se maneja en config.js.php
?> 