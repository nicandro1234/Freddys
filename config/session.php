<?php
// Configuración de seguridad para las sesiones
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', '3600'); // 1 hora

// Iniciar la sesión
session_start();

// Regenerar el ID de sesión periódicamente para prevenir fijación de sesión
if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} 