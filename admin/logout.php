<?php
// Incluir el gestor de sesiones que llama a session_start()
// Asumimos que sessions.php se encarga de iniciar la sesión.
require_once __DIR__ . '/../auth/sessions.php';

// 1. Limpiar todas las variables de sesión específicas del admin si las hubiera
// Por ejemplo, si guardamos datos del admin en $_SESSION['admin']
if (isset($_SESSION['admin'])) {
    unset($_SESSION['admin']);
}
// O de forma más general, si hay un identificador específico para el admin logueado
if (isset($_SESSION['admin_user_id'])) {
    unset($_SESSION['admin_user_id']);
}
if (isset($_SESSION['admin_username'])) {
    unset($_SESSION['admin_username']);
}
// etc., para cualquier otra variable de sesión específica del admin.

// 2. Destruir todas las variables de la sesión actual.
$_SESSION = array();

// 3. Eliminar la cookie de sesión.
// Nota: Esto destruirá la sesión, y no solo los datos de la sesión.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Finalmente, destruir la sesión.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// 5. Redirigir a la página de login del admin
// Asegurarse que no haya salida antes de este header.
header("Location: login.php");
exit; 