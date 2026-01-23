<?php
function sanitize_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function verify_session()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: /cmhs/index.php");
        exit();
    }
}

function time_ago($datetime, $full = false)
{
    date_default_timezone_set('America/Guatemala');
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $weeks = floor($diff->d / 7);
    $diff->d += $weeks * 7; // Add weeks back to days since DateInterval doesn't have a weeks property
    $diff->d -= $weeks * 7;

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

    if (!$full)
        $string = array_slice($string, 0, 1);
    return $string ? 'hace ' . implode(', ', $string) : 'justo ahora';
}

// ==========================================
// MANTENER SESIÓN ACTIVA (GLOBAL)
// ==========================================

// 1. Detectar solicitud de keep_alive en cualquier página que incluya este archivo
if (isset($_GET['keep_alive']) && $_GET['keep_alive'] == '1') {
    // Asegurar que la sesión esté iniciada
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Devolver respuesta JSON y terminar ejecución
    header('Content-Type: application/json');
    echo json_encode(['status' => 'alive', 'timestamp' => time()]);
    exit;
}

// 2. Función helper para inyectar el script JS automáticamente
function output_keep_alive_script()
{
    echo "
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        setInterval(function() {
            fetch('?keep_alive=1')
                .then(response => response.json())
                .then(data => console.log('Session refreshed:', data))
                .catch(e => console.error('Keep-alive error:', e));
        }, 300000); // 5 minutos
    });
    </script>";
}
?>