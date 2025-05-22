<?php
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Verificar si el array $_SESSION['user'] existe y tiene los datos necesarios
// Se verifica que 'id' exista, ya que es el identificador principal del usuario de Google.
if (!isset($_SESSION['user']) || empty($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo json_encode([
        'authenticated' => false
    ]);
} else {
    // El usuario está autenticado.
    // Construimos el array de usuario para el frontend asegurando consistencia en las claves.
    // auth/google_auth.php guarda: 'id', 'name', 'email', 'picture'.
    // El frontend (auth.js) en handleAuthenticatedUser(user) usa estas claves.
    
    $user_data_for_js = [
        'id'      => $_SESSION['user']['id'], // google_id
        'name'    => $_SESSION['user']['name'] ?? 'Usuario', // Proporcionar un valor predeterminado
        'email'   => $_SESSION['user']['email'] ?? '',
        'picture' => $_SESSION['user']['picture'] ?? null // 'picture' es consistente con Google y google_auth.php
    ];

    echo json_encode([
        'authenticated' => true,
        'user'          => $user_data_for_js
    ]);
}
?> 