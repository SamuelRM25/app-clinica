<?php
/**
 * Registers an audit event in the log file.
 * In a production environment, consider migrating to an audit_log DB table.
 */
function audit_log($action, $details = '', $user_id = null) {
    if ($user_id === null && session_status() === PHP_SESSION_ACTIVE) {
        $user_id = $_SESSION['user_id'] ?? 'anonymous';
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] [user:$user_id] [ip:$ip] [$action] $details" . PHP_EOL;
    error_log($line, 3, __DIR__ . '/../audit.log');
}

/**
 * Checks if a payment type is in the whitelist of accepted values.
 */
function validar_tipo_pago($tipo) {
    $permitidos = ['Efectivo', 'Tarjeta', 'Transferencia', 'Traslado'];
    return in_array($tipo, $permitidos, true);
}

/**
 * Generates and stores a CSRF token in the session.
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates a CSRF token from the request.
 * Call at the start of every state-changing POST handler.
 */
function verify_csrf_token() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Token CSRF inválido. Recargue la página e intente de nuevo.']);
        } else {
            die('Token CSRF inválido. Recargue la página e intente de nuevo.');
        }
        exit;
    }
}

/**
 * Returns an HTML hidden input with the CSRF token.
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Sanitize user input for safe HTML output.
 * NOTE: This function is for escaping HTML output only.
 * Do NOT rely on it for SQL injection prevention — use prepared statements.
 */
function sanitize_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function verify_session()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../../index.php");
        exit();
    }

    // Verify id_hospital is set and valid
    if (empty($_SESSION['id_hospital'])) {
        session_unset();
        session_destroy();
        header("Location: ../../index.php?error=session_invalid");
        exit();
    }

    // Session idle timeout: 2 hours (7200 seconds)
    $idle_timeout = 7200;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $idle_timeout)) {
        session_unset();
        session_destroy();
        header("Location: ../../index.php?error=session_expired");
        exit();
    }
    $_SESSION['last_activity'] = time();

    // Absolute session lifetime: 8 hours (28800 seconds)
    $session_lifetime = 28800;
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $session_lifetime)) {
        session_unset();
        session_destroy();
        header("Location: ../../index.php?error=session_expired");
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
    $diff->d = $diff->d % 7;

    $string = array(
        'y' => 'año',
        'm' => 'mes',
        'd' => 'día',
        'h' => 'hora',
        'i' => 'minuto',
        's' => 'segundo',
    );

    $parts = [];
    if ($weeks > 0) {
        $parts[] = $weeks . ' semana' . ($weeks > 1 ? 's' : '');
    }
    foreach ($string as $k => $v) {
        if ($diff->$k) {
            $parts[] = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        }
    }

    if (!$full)
        $parts = array_slice($parts, 0, 1);
    return $parts ? 'hace ' . implode(', ', $parts) : 'justo ahora';
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
    // CSRF token global para fetch requests
    window.CSRF_TOKEN = '" . csrf_token() . "';

    // Intercepta fetch para agregar CSRF automáticamente
    (function() {
        const origFetch = window.fetch;
        window.fetch = function(input, init) {
            init = init || {};
            if (!init.method || init.method.toUpperCase() !== 'GET') {
                init.headers = init.headers || {};
                if (init.headers instanceof Headers) {
                    if (!init.headers.has('X-CSRF-Token')) {
                        init.headers.set('X-CSRF-Token', window.CSRF_TOKEN);
                    }
                } else {
                    init.headers['X-CSRF-Token'] = window.CSRF_TOKEN;
                }
            }
            return origFetch(input, init);
        };
    })();

    document.addEventListener('DOMContentLoaded', function() {
        // Keep-alive
        setInterval(function() {
            fetch('?keep_alive=1').catch(function() {});
        }, 300000);

        // Auto-disable submit buttons to prevent double-clicks
        document.addEventListener('submit', function(e) {
            const form = e.target;
            const btns = form.querySelectorAll('button[type=\"submit\"]');
            btns.forEach(function(btn) {
                btn.disabled = true;
                if (!btn.dataset.origText) {
                    btn.dataset.origText = btn.innerHTML;
                }
                btn.innerHTML = '<span class=\"spinner-border spinner-border-sm me-1\" role=\"status\"></span> Guardando...';
            });
        });

        // Confirm on data-confirm attribute
        document.addEventListener('click', function(e) {
            const el = e.target.closest('[data-confirm]');
            if (!el) return;
            if (!confirm(el.dataset.confirm)) {
                e.preventDefault();
                e.stopPropagation();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape closes modals
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal.show, .modal-backdrop');
                modals.forEach(function(m) { m.remove(); });
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
            // Ctrl+F focuses search inputs
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                const searchInput = document.querySelector('input[type=\"search\"], input.search, .search-input, [id*=\"search\"], [name*=\"search\"]');
                if (searchInput && !document.activeElement.closest('.modal')) {
                    e.preventDefault();
                    searchInput.focus();
                    searchInput.select();
                }
            }
        });
    });
    </script>";
}

/**
 * Compresses an image from source to destination with specified quality.
 * Supports JPG, JPEG, and PNG.
 */
function compressImage($sourcePath, $destinationPath, $quality = 60)
{
    $info = getimagesize($sourcePath);

    if ($info['mime'] == 'image/jpeg') {
        $image = imagecreatefromjpeg($sourcePath);
        imagejpeg($image, $destinationPath, $quality);
    } elseif ($info['mime'] == 'image/png') {
        $image = imagecreatefrompng($sourcePath);
        // Scale quality 0-100 to 0-9 for PNG
        $pngQuality = ($quality - 100) / 11.111111;
        $pngQuality = round(abs($pngQuality));
        imagepng($image, $destinationPath, $pngQuality);
    } else {
        // Fallback for other formats (like PDF), just move if possible
        // This function is mainly for images though
        return false;
    }
    
    imagedestroy($image);
    return true;
}

/**
 * Displays a stored session flash message as a SweetAlert2 toast
 * and clears it from the session.
 */
function flash_toast() {
    $pairs = [
        ['message', 'message_type'],
        ['patient_message', 'patient_status'],
        ['appointment_message', 'appointment_status'],
        ['purchase_message', 'purchase_status'],
        ['inventory_message', 'inventory_status'],
    ];
    foreach ($pairs as [$msgKey, $typeKey]) {
        if (!empty($_SESSION[$msgKey])) {
            $msg = addslashes($_SESSION[$msgKey]);
            $type = $_SESSION[$typeKey] ?? 'info';
            // map legacy types to Swal icons
            $icon = match ($type) {
                'success' => 'success',
                'danger', 'error' => 'error',
                'warning' => 'warning',
                default => 'info',
            };
            unset($_SESSION[$msgKey]);
            unset($_SESSION[$typeKey]);
            echo "<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({ toast: true, position: 'top-end', icon: '$icon', title: '$msg', showConfirmButton: false, timer: 4000 });
});
</script>";
            return; // only one per page
        }
    }
}

function validate_password_strength($password)
{
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = 'Mínimo 8 caracteres';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Al menos una mayúscula';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Al menos una minúscula';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Al menos un número';
    }
    return $errors;
}

?>