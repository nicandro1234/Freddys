<?php
// Configuración de manejo de errores de PHP
ini_set('log_errors', 1); // Habilitar el registro de errores
ini_set('error_log', '/home/freddysc/public_html/php_error.log'); // Ruta personalizada para el log de errores
ini_set('display_errors', 1); // Mostrar errores durante el desarrollo
error_reporting(E_ALL); // Reportar todos los tipos de errores PHP

// Cargar configuración principal (constantes desde .env.php)
require_once __DIR__ . '/config.php'; // Este carga .env.php y define constantes como DB_HOST, etc.

// Verificar que las credenciales requeridas por db.php estén definidas (ya deberían estarlo por config.php)
if (!defined('DB_HOST') || !defined('DB_USERNAME') || !defined('DB_PASSWORD') || !defined('DB_DBNAME')) {
    error_log('Error CRÍTICO en db.php: Faltan constantes de base de datos (DB_HOST, etc.) después de incluir config.php de la raíz. Verifica que config.php las defina correctamente desde .env.php.');
    die('Error: Faltan configuraciones de base de datos cruciales.');
}

// Las constantes DB_HOST, DB_USERNAME, DB_PASSWORD, DB_DBNAME ya están definidas por config.php (raíz)
// No es necesario re-definirlas aquí.
// define('DB_HOST', $config['DB_HOST']); // Redundante
// define('DB_USERNAME', $config['DB_USERNAME']); // Redundante
// define('DB_PASSWORD', $config['DB_PASSWORD']); // Redundante
// define('DB_DBNAME', $config['DB_DBNAME']); // Redundante
if (!defined('DB_CHARSET')) { // Definir solo si no está ya en config.php (raíz)
    define('DB_CHARSET', 'utf8mb4'); 
}

// Crear conexión a la base de datos usando PDO
$pdo = null; // Inicializar a null
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_DBNAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Lanza excepciones en errores
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Modo de fetch por defecto: array asociativo
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Usar sentencias preparadas nativas del driver
    ];
    $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);
} catch (PDOException $e) {
    error_log("FALLO CRÍTICO en db.php - Conexión a BD con PDO: " . $e->getMessage());
    // En un entorno de producción, no muestres detalles del error al usuario.
    // Considera un mensaje genérico y registra el error detallado.
    die('Error de conexión a la base de datos. Por favor, inténtelo más tarde o contacte al administrador.');
}

// La variable $pdo ahora está disponible globalmente para los scripts que incluyan db.php
// No se necesita $conn->set_charset("utf8mb4"); ya que el charset se establece en el DSN.

// Crear tabla de horarios de la tienda
$sql = "CREATE TABLE IF NOT EXISTS store_hours (
    id INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    open_time TIME NOT NULL,
    close_time TIME NOT NULL,
    is_closed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_day (day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

try {
    $pdo->exec($sql);
    echo "Tabla store_hours creada exitosamente<br>";
} catch(PDOException $e) {
    echo "Error al crear la tabla store_hours: " . $e->getMessage() . "<br>";
}

// Insertar horarios por defecto
$default_hours = [
    ['monday', '14:00:00', '22:00:00'],
    ['tuesday', '14:00:00', '22:00:00'],
    ['wednesday', '14:00:00', '22:00:00'],
    ['thursday', '14:00:00', '22:00:00'],
    ['friday', '14:00:00', '23:00:00'],
    ['saturday', '14:00:00', '23:00:00'],
    ['sunday', '14:00:00', '22:00:00']
];

$insert_sql = "INSERT IGNORE INTO store_hours (day_of_week, open_time, close_time) VALUES (?, ?, ?)";
$stmt = $pdo->prepare($insert_sql);

foreach ($default_hours as $hours) {
    try {
        $stmt->execute($hours);
    } catch(PDOException $e) {
        echo "Error al insertar horario para {$hours[0]}: " . $e->getMessage() . "<br>";
    }
}
?> 