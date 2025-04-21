<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/../vendor/autoload.php';

use Arrilot\DotEnv\DotEnv;

// Carga las variables de entorno usando Arrilot Dotenv-PHP
DotEnv::load(__DIR__ . '/.env.php');

// Ahora accede a las variables usando DotEnv::get
$clientID = DotEnv::get('GOOGLE_CLIENT_ID');
$clientSecret = DotEnv::get('GOOGLE_CLIENT_SECRET');
$redirectUri = DotEnv::get('REDIRECT_URI');

echo 'Debug: Valor de REDIRECT_URI después de cargar con Arrilot: ' . var_export($redirectUri, true);  // Depuración agregada
if (!is_string($redirectUri) || empty($redirectUri)) {
    die('Error: REDIRECT_URI no está configurada o es inválida en .env.php. Asegúrate de que sea una cadena correcta.');
}

$client = new Google_Client();
$client->setClientId($clientID);
$client->setClientSecret($clientSecret);
$client->setRedirectUri($redirectUri);
$client->addScope('email');
$client->addScope('profile');

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (!isset($token['error'])) {
        $client->setAccessToken($token['access_token']);
        $oauth2 = new Google_Service_Oauth2($client);
        $userInfo = $oauth2->userinfo->get();
        
        // Conexión a la base de datos y guarda el usuario
        $servername = DotEnv::get('DB_HOST');  // Usar DotEnv::get en lugar de getenv
        $username = DotEnv::get('DB_USERNAME');  // Usar DotEnv::get en lugar de getenv
        $password = DotEnv::get('DB_PASSWORD');  // Usar DotEnv::get en lugar de getenv
        $dbname = DotEnv::get('DB_DBNAME');  // Usar DotEnv::get en lugar de getenv
        
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
        
        $_SESSION['user'] = $userInfo;  // Guarda en sesión
        header('Location: /my-account.php');  // Redirige a la página de cuenta (cambiada a PHP)
        exit();
    } else {
        echo 'Error en la autenticación.';
    }
} else {
    $authUrl = $client->createAuthUrl();
    header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
}
?> 