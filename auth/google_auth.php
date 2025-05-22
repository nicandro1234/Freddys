<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurar la cookie de sesión para que persista
ini_set('session.cookie_lifetime', 86400); // 24 horas
ini_set('session.gc_maxlifetime', 86400); // 24 horas
session_start();

// Guardar la URL de referencia antes de redirigir a Google
if (!isset($_GET['code'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['HTTP_REFERER'] ?? '/index.html'; // Asegurar que redirige a .html si es necesario
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../db.php'; // Ahora db.php usa $pdo

// Ya no se necesita Arrilot DotEnv si las variables de entorno se manejan de otra forma o directamente en config.php
// Comentando la carga de DotEnv ya que config.php ahora debería tener las constantes o $config array
// use Arrilot\DotEnv\DotEnv;
// DotEnv::load(__DIR__ . '/.env.php'); 
// Si GOOGLE_CLIENT_ID, etc., vienen de config.php (que incluye .env.php), úsalas directamente.
// Asumiré que están disponibles globalmente o a través del array $config de db.php si se requiere.

// Reemplazar DotEnv::get con el acceso a través del array $config cargado en db.php o constantes globales
// Esto asume que db.php hace que $config esté disponible o que las has definido como constantes globales.
// Si config.php define constantes (ej. GOOGLE_CLIENT_ID), úsalas directamente.
// Si no, y db.php carga $config, asegúrate que $config esté en el scope o pásala.
// Por simplicidad, asumiré que config.php define estas como constantes o que $config está disponible.
// Si db.php carga $config = require __DIR__ . '/.env.php'; y no la hace global, necesitaríamos incluir config.php directamente aquí también.
// O mejor, que config.php defina constantes.

// Incluir config.php directamente para asegurar que las constantes de Google estén definidas
require_once __DIR__ . '/../config.php';

$clientID = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : null;
$clientSecret = defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : null;
$redirectUri = defined('REDIRECT_URI') ? REDIRECT_URI : null;

if (!$clientID || !$clientSecret || !$redirectUri) {
    die('Error: Credenciales de Google API no configuradas correctamente. Verifica GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, y REDIRECT_URI.');
}

$client = new Google_Client();
$client->setClientId($clientID);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope('email');
$client->addScope('profile');

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        if (!isset($token['error'])) {
            $client->setAccessToken($token['access_token']);
            $oauth2 = new Google_Service_Oauth2($client);
            $userInfo = $oauth2->userinfo->get();
            
            // Verifica si el usuario existe y agrégalo si no, usando PDO
            // Asumimos que google_id es UNIQUE o PRIMARY KEY en la tabla users
            $sql = "INSERT INTO users (google_id, email, name) 
                    VALUES (:google_id, :email, :name) 
                    ON DUPLICATE KEY UPDATE email = VALUES(email), name = VALUES(name)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':google_id' => $userInfo->id,
                ':email' => $userInfo->email,
                ':name' => $userInfo->name
            ]);
            // $stmt->closeCursor(); // Opcional, buena práctica si $stmt se reutiliza.
            
            // Guardar información del usuario en la sesión
            $_SESSION['user'] = [
                'id' => $userInfo->id, // google_id
                'name' => $userInfo->name,
                'email' => $userInfo->email,
                'picture' => isset($userInfo->picture) ? $userInfo->picture : null
            ];
            // Estas son redundantes si ya tienes $_SESSION['user']
            // $_SESSION['user_id'] = $userInfo->id; 
            // $_SESSION['user_name'] = $userInfo->name; 
            // $_SESSION['user_email'] = $userInfo->email; 
            // $_SESSION['user_photo'] = isset($userInfo->picture) ? $userInfo->picture : null;
            
            // Redirigir a la página anterior o a index.html por defecto
            $redirect_url = $_SESSION['redirect_after_login'] ?? '/index.html';
            unset($_SESSION['redirect_after_login']);
            
            header('Location: ' . $redirect_url);
            exit();
        } else {
            throw new Exception('Error en la autenticación de Google: ' . (isset($token['error_description']) ? $token['error_description'] : $token['error']));
        }
    } catch (Exception $e) {
        error_log('Error en google_auth.php: ' . $e->getMessage());
        // Para depuración, podrías mostrar $e->getMessage(), pero en producción un error genérico es mejor.
        header('Location: /index.html?error=auth_failed'); // Redirigir a una página con mensaje de error
        exit();
    }
} else {
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
    exit(); // Asegurar que el script termina después de la redirección
}
?> 