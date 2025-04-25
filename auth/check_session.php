<?php
// Configurar la cookie de sesión para que persista
ini_set('session.cookie_lifetime', 86400); // 24 horas
ini_set('session.gc_maxlifetime', 86400); // 24 horas
session_start();

header('Content-Type: application/json');

if (isset($_SESSION['user'])) {
    echo json_encode([
        'authenticated' => true,
        'user' => [
            'name' => $_SESSION['user']['name'],
            'email' => $_SESSION['user']['email']
        ]
    ]);
} else {
    echo json_encode([
        'authenticated' => false
    ]);
}
?> 