<?php
require_once 'adminconfig.php';

// Si ya está autenticado, redirigir al panel
if (isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Usuario y contraseña son requeridos.';
    } else {
        try {
            // Verificar credenciales en la base de datos con PDO
            $query = "SELECT * FROM admins WHERE username = :username";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin) {
                if (password_verify($password, $admin['password'])) {
                    $_SESSION['admin_authenticated'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    
                    // Registrar el inicio de sesión
                    if (function_exists('logActivity')) {
                        logActivity('login', "Inicio de sesión exitoso para el usuario: " . $admin['username']);
                    }
                    
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Credenciales inválidas.';
                }
            } else {
                $error = 'Credenciales inválidas.';
            }
        } catch (PDOException $e) {
            error_log("Error de PDO en login.php: " . $e->getMessage());
            $error = 'Error de base de datos. Intente más tarde.';
        } catch (Exception $e) {
            error_log("Error general en login.php: " . $e->getMessage());
            $error = 'Ocurrió un error inesperado.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panel de Administración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 20px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: #333;
            font-size: 24px;
        }
        .form-control {
            margin-bottom: 15px;
        }
        .btn-login {
            width: 100%;
            padding: 10px;
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-login:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <h1>Panel de Administración</h1>
                <?php if (defined('STORE_NAME')): ?>
                    <p><?php echo htmlspecialchars(STORE_NAME); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">Usuario</label>
                    <input type="text" class="form-control" id="username" name="username" required autocomplete="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                </div>
                
                <button type="submit" class="btn btn-danger btn-login">Iniciar Sesión</button>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 