<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurar la cookie de sesión para que persista
ini_set('session.cookie_lifetime', 86400); // 24 horas
ini_set('session.gc_maxlifetime', 86400); // 24 horas
session_start();

// Guardar la URL de referencia antes de redirigir a Google
if (!isset($_GET['code'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['HTTP_REFERER'] ?? '/index';
}

require_once __DIR__ . '/../vendor/autoload.php';

use Arrilot\DotEnv\DotEnv;

// Carga las variables de entorno usando Arrilot Dotenv-PHP
DotEnv::load(__DIR__ . '/.env.php');

$clientID = DotEnv::get('GOOGLE_CLIENT_ID');
$clientSecret = DotEnv::get('GOOGLE_CLIENT_SECRET');
$redirectUri = DotEnv::get('REDIRECT_URI');

if (!is_string($redirectUri) || empty($redirectUri)) {
    die('Error: REDIRECT_URI no está configurada o es inválida en .env.php');
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
            
            // Conexión a la base de datos
            $servername = DotEnv::get('DB_HOST');
            $username = DotEnv::get('DB_USERNAME');
            $password = DotEnv::get('DB_PASSWORD');
            $dbname = DotEnv::get('DB_DBNAME');
            
            $conn = new mysqli($servername, $username, $password, $dbname);
            if ($conn->connect_error) {
                die("Conexión fallida: " . $conn->connect_error);
            }
            
            // Verifica si el usuario existe y agregalo si no
            $stmt = $conn->prepare("INSERT INTO users (google_id, email, name) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE email=email");
            $stmt->bind_param("sss", $userInfo->id, $userInfo->email, $userInfo->name);
            $stmt->execute();
            $stmt->close();
            $conn->close();
            
            // Guardar información del usuario en la sesión
            $_SESSION['user'] = [
                'id' => $userInfo->id,
                'name' => $userInfo->name,
                'email' => $userInfo->email
            ];
            
            // Redirigir a la página anterior o a index por defecto
            $redirect_url = $_SESSION['redirect_after_login'] ?? '/index';
            unset($_SESSION['redirect_after_login']);
            
            header('Location: ' . $redirect_url);
            exit();
        } else {
            throw new Exception('Error en la autenticación: ' . $token['error']);
        }
    } catch (Exception $e) {
        error_log('Error en google_auth.php: ' . $e->getMessage());
        header('Location: /index?error=auth_failed');
        exit();
    }
} else {
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
}
?> 