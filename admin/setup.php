<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'adminconfig.php'; // Carga la configuración del admin, que incluye db.php

// Verificar si el usuario está autenticado. Solo administradores pueden correr setup.
requireAuth();

// Asegúrate que este script SÓLO se ejecute una vez o tenga protecciones 
// si las tablas/datos ya existen para evitar errores o duplicados.

// Cambio principal: usar $pdo de config.php y sintaxis PDO.
// Es crucial que config.php NO aborte si las tablas no existen aún.
// Idealmente, config.php podría tener una función para obtener $pdo 
// o la conexión se establece aquí de forma más controlada para el setup.

// require_once __DIR__ . '/../../config.php'; // REDUNDANTE - adminconfig.php ya lo maneja indirectamente

// Habilitar reportes de errores de PDO en modo excepción para este script de setup
if (isset($pdo)) {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} else {
    die("Error crítico: No se pudo inicializar la conexión PDO desde config.php. Verifica la configuración.");
}

echo "<pre>"; // Para mejor formato de salida durante el setup

try {
    $pdo->beginTransaction();

    // 1. Crear tabla de administradores (admins) - MOVIDA ANTES DE ADMIN_LOGS
    echo "Creando tabla admins...\n";
    $query_admins = "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL, -- Guardar hashes de contraseñas seguras
        email VARCHAR(100) NOT NULL UNIQUE, 
        full_name VARCHAR(100),
        is_super_admin BOOLEAN DEFAULT FALSE,
        is_active BOOLEAN DEFAULT TRUE,
        last_login DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($query_admins);
    echo "Tabla admins verificada/creada.<br>\n";

    // Insertar usuario administrador por defecto si la tabla admins está vacía o el usuario no existe
    $admin_user_to_check = 'Freddys';
    $stmt_check_admin = $pdo->prepare("SELECT COUNT(*) as count FROM admins WHERE username = :username");
    $stmt_check_admin->execute([':username' => $admin_user_to_check]);
    $row_admin = $stmt_check_admin->fetch(PDO::FETCH_ASSOC);

    if ($row_admin && $row_admin['count'] == 0) {
        echo "Insertando usuario administrador por defecto ('$admin_user_to_check')...\n";
        $username = $admin_user_to_check;
        // ¡IMPORTANTE! Cambiar esta contraseña por defecto después del setup inicial.
        $password = password_hash('Familia123!', PASSWORD_DEFAULT);
        $email = 'freddyspizzaoficial@gmail.com'; // Usar un email real
        $full_name = 'Administrador Principal';
        $is_super_admin = TRUE;

        $insert_admin_sql = "INSERT INTO admins (username, password, email, full_name, is_super_admin, is_active) 
                             VALUES (:username, :password, :email, :full_name, :is_super_admin, TRUE)";
        $stmt_insert_admin = $pdo->prepare($insert_admin_sql);
        $stmt_insert_admin->execute([
            ':username' => $username,
            ':password' => $password,
            ':email' => $email,
            ':full_name' => $full_name,
            ':is_super_admin' => $is_super_admin ? 1 : 0
        ]);
        echo "Usuario administrador '$admin_user_to_check' creado.<br>\n";
    } else {
        echo "El usuario administrador '$admin_user_to_check' ya existe o no se pudo verificar.<br>\n";
    }

    // 2. Crear tabla de logs de administración (admin_logs) - AHORA DEPENDE DE ADMINS
    echo "Creando tabla admin_logs...\n";
    $query_admin_logs = "CREATE TABLE IF NOT EXISTS admin_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_user_id INT NULL, -- Quién realizó la acción, si aplica
        action VARCHAR(255) NOT NULL,
        details TEXT,
        ip_address VARCHAR(45),
        user_agent VARCHAR(255),
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_user_id) REFERENCES admins(id) ON DELETE SET NULL -- Enlace opcional a la tabla admins
    )";
    $pdo->exec($query_admin_logs);
    echo "Tabla admin_logs verificada/creada.<br>\n";

    // 3. Crear tabla de horarios de la tienda (store_hours)
    echo "Creando tabla store_hours...\n";
    $query_store_hours = "CREATE TABLE IF NOT EXISTS store_hours (
        id INT AUTO_INCREMENT PRIMARY KEY,
        day_of_week VARCHAR(10) NOT NULL,
        open_time TIME NULL,      -- Permitir NULL
        close_time TIME NULL,     -- Permitir NULL
        is_closed BOOLEAN DEFAULT FALSE,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_day (day_of_week)
    )";
    $pdo->exec($query_store_hours);
    echo "Tabla store_hours verificada/creada.<br>\n";

    // Insertar horarios por defecto si la tabla store_hours está vacía
    $stmt_check_hours = $pdo->query("SELECT COUNT(*) as count FROM store_hours");
    $row_hours = $stmt_check_hours->fetch(PDO::FETCH_ASSOC);
    
    if ($row_hours && $row_hours['count'] == 0) {
        echo "Insertando horarios por defecto en store_hours...\n";
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $insert_hour_sql = "INSERT INTO store_hours (day_of_week, open_time, close_time, is_closed) 
                             VALUES (:day_of_week, '10:00:00', '22:00:00', FALSE)";
        $stmt_insert_hour = $pdo->prepare($insert_hour_sql);
        foreach ($days as $day) {
            $stmt_insert_hour->execute([':day_of_week' => $day]);
        }
        echo "Horarios por defecto insertados.<br>\n";
    } else {
        echo "La tabla store_hours ya contiene datos o no se pudo verificar.<br>\n";
    }

    // 4. Crear tabla de notificaciones (notifications)
    echo "Creando tabla notifications...\n";
    $query_notifications = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(50) NOT NULL, -- ej. 'whatsapp', 'email', 'system'
        recipient VARCHAR(255) NULL, -- A quién se envió (número de teléfono, email)
        message TEXT NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'pending', -- ej. 'pending', 'sent', 'failed', 'read'
        attempts INT DEFAULT 0, -- Número de intentos de envío
        last_attempt_at DATETIME NULL,
        error_message TEXT NULL, -- Mensaje de error si falló
        related_entity_type VARCHAR(50) NULL, -- ej. 'order', 'user'
        related_entity_id INT NULL, -- ID de la entidad relacionada
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($query_notifications);
    echo "Tabla notifications verificada/creada.<br>\n";

    // 5. Crear tabla de pedidos programados (order_schedules)
    echo "Creando tabla order_schedules...\n";
    $query_order_schedules = "CREATE TABLE IF NOT EXISTS order_schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        scheduled_time DATETIME NOT NULL, -- Fecha y hora para la cual está programado el pedido
        status VARCHAR(50) NOT NULL DEFAULT 'pending', -- ej. 'pending', 'confirmed', 'cancelled', 'processing', 'completed'
        notes TEXT NULL, -- Notas adicionales sobre la programación
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE -- Si se borra el pedido, se borra la programación
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $pdo->exec($query_order_schedules);
    echo "Tabla order_schedules verificada/creada.<br>\n";
    
    $pdo->commit();
    echo "<br>------------------------------------\n";
    echo "Configuración del panel de administración completada exitosamente.\n";
    echo "Tablas: admins, admin_logs, store_hours, notifications, order_schedules verificadas/creadas y datos iniciales insertados (si aplicaba).\n";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error durante la configuración: " . $e->getMessage() . "<br>\n";
    error_log("Error en admin/setup.php: " . $e->getMessage());
    echo "<br>LA CONFIGURACIÓN FALLÓ. Revisa los errores e intenta de nuevo si es necesario.\n";
} catch (Exception $e) {
    // Para cualquier otra excepción no PDO
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error general durante la configuración: " . $e->getMessage() . "<br>\n";
    error_log("Error general en admin/setup.php: " . $e->getMessage());
    echo "<br>LA CONFIGURACIÓN FALLÓ. Revisa los errores e intenta de nuevo si es necesario.\n";
}

echo "</pre>";
echo "<br><a href='index.php'>Ir al panel de administración</a>";
?> 