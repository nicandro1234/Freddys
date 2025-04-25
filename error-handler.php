<?php
// Archivo para registrar y depurar errores

// Habilitar registro de errores
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-errors.log');

// Verificar PHP info
function test_php_processing() {
    // Crear archivo de prueba
    $test_file = __DIR__ . '/php-test-' . time() . '.txt';
    file_put_contents($test_file, 'PHP está procesando correctamente: ' . date('Y-m-d H:i:s'));
    
    // Verificar constantes
    $constants = array(
        'MP_PUBLIC_KEY',
        'MP_ACCESS_TOKEN',
        'SITE_URL',
        'PAYPAL_CLIENT_ID',
        'PAYPAL_CLIENT_SECRET',
        'GOOGLE_MAPS_API_KEY',
        'GOOGLE_CLIENT_ID',
        'GOOGLE_CLIENT_SECRET',
        'REDIRECT_URI'
    );
    
    $results = array();
    foreach ($constants as $const) {
        $results[$const] = defined($const) ? 'DEFINIDO' : 'NO DEFINIDO';
    }
    
    file_put_contents($test_file, "\n\nConstantes:\n" . print_r($results, true), FILE_APPEND);
    
    return "Test completado. Archivo creado: " . $test_file;
}

// Función para verificar el estado de los scripts
function check_scripts_status() {
    // Verificar cabeceras para permitir CORS en desarrollo local
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json; charset=UTF-8");
    
    // Verificar archivos críticos
    $critical_files = array(
        'config.php' => __DIR__ . '/config.php',
        'config.js.php' => __DIR__ . '/config.js.php',
        '.env.php' => __DIR__ . '/.env.php',
        'auth.js' => __DIR__ . '/assets/js/auth.js',
        'main.js' => __DIR__ . '/assets/js/main.js'
    );
    
    $status = array();
    foreach ($critical_files as $name => $path) {
        $status[$name] = array(
            'exists' => file_exists($path),
            'readable' => is_readable($path),
            'size' => file_exists($path) ? filesize($path) : 0,
            'modified' => file_exists($path) ? date("Y-m-d H:i:s", filemtime($path)) : null
        );
    }
    
    // Verificar extensiones PHP
    $required_extensions = array('json', 'curl', 'mbstring');
    $extensions = array();
    foreach ($required_extensions as $ext) {
        $extensions[$ext] = extension_loaded($ext);
    }
    
    // Verificar errores recientes
    $recent_errors = array();
    $error_log = __DIR__ . '/php-errors.log';
    if (file_exists($error_log) && is_readable($error_log)) {
        $log_content = file_get_contents($error_log);
        // Obtener las últimas 5 líneas del archivo de error
        $lines = explode("\n", $log_content);
        $lines = array_slice($lines, max(0, count($lines) - 5));
        $recent_errors = $lines;
    }
    
    // Recopilar información de versiones
    $versions = array(
        'php' => phpversion(),
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'
    );
    
    $result = array(
        'status' => 'ok',
        'timestamp' => date('Y-m-d H:i:s'),
        'files' => $status,
        'extensions' => $extensions,
        'versions' => $versions,
        'recent_errors' => $recent_errors
    );
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

// Si se llama directamente, ejecutar prueba
if (basename($_SERVER['SCRIPT_NAME']) == basename(__FILE__)) {
    // Si se solicita el estado de los scripts
    if (isset($_GET['check_scripts'])) {
        check_scripts_status();
    } else {
        header('Content-Type: text/plain');
        echo "Ejecutando prueba de procesamiento PHP...\n";
        echo test_php_processing();
    }
}
?> 