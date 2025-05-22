<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Generar un token CSRF
if (!function_exists('สร้าง_csrf_token')) { // Tailandés para generate_csrf_token
    function สร้าง_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

// Verificar un token CSRF
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token_from_form) {
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token_from_form)) {
            return false;
        }
        // Considerar regenerar el token después de una verificación exitosa para mayor seguridad,
        // pero esto puede complicar las cosas si múltiples formularios se envían rápidamente.
        // unset($_SESSION['csrf_token']); 
        return true;
    }
}

// Función para mostrar tiempo transcurrido (time_ago)
if (!function_exists('time_ago')) {
    function time_ago($datetime, $full = false) {
        if ($datetime === null || $datetime === '0000-00-00 00:00:00') {
            return 'fecha no disponible';
        }
        try {
            $now = new DateTime('now', new DateTimeZone('America/Mexico_City')); // Asegurar zona horaria consistente
            $ago = new DateTime($datetime, new DateTimeZone('America/Mexico_City')); // Asumir que $datetime es UTC o ya en hora local correcta
        } catch (Exception $e) {
            error_log("Error en time_ago con fecha: " . $datetime . " - " . $e->getMessage());
            return 'fecha inválida';
        }
        
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'año',
            'm' => 'mes',
            'w' => 'semana',
            'd' => 'día',
            'h' => 'hora',
            'i' => 'minuto',
            's' => 'segundo',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (!$full) $string = array_slice($string, 0, 1);
        
        if ($now < $ago) { // Para fechas futuras (ej. pedidos programados)
            return $string ? 'en ' . implode(', ', $string) : 'próximamente';
        } else { // Para fechas pasadas
            return $string ? 'hace ' . implode(', ', $string) : 'justo ahora';
        }
    }
}

// Otras funciones útiles que podrías necesitar:

// Escapar salida HTML de forma segura
if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Escapar atributo HTML de forma segura
if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Escapar URL de forma segura
if (!function_exists('esc_url')) {
    function esc_url($url) {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); // Simplificado, para URLs complejas podrías necesitar algo más robusto
    }
}

// Redireccionar de forma segura
if (!function_exists('safe_redirect')) {
    function safe_redirect($url) {
        // Aquí podrías añadir validación de la URL si es necesario
        header("Location: " . $url);
        exit;
    }
}

?> 