<?php
/**
 * Enhanced audit logging function that writes to both file and database.
 *
 * @param string $action Action type: login, logout, create, update, delete, etc.
 * @param string $modulo Module name: patients, inventory, billing, auth, etc.
 * @param string $descripcion Human-readable description of the event
 * @param array $data Optional associative array with keys:
 *   - table_name: string (affected table)
 *   - record_id: int (affected record ID)
 *   - old_data: array (previous values for updates/deletes)
 *   - new_data: array (new values for creates/updates)
 *   - result: string (exito, error, revertido)
 *   - error_message: string (error message if result != exito)
 * @param int|null $user_id Override user ID (null = use session)
 */
function audit_log($action, $modulo = 'system', $descripcion = '', $data = [], $user_id = null) {
    try {
        $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

        if ($user_id === null && session_status() === PHP_SESSION_ACTIVE) {
            $user_id = $_SESSION['user_id'] ?? null;
        }

        $user_nombre = $_SESSION['nombre'] ?? null;
        $user_tipo = $_SESSION['tipoUsuario'] ?? null;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $user_agent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 512);
        $session_id = session_id();
        $timestamp = date('Y-m-d H:i:s');

        $tabla_afectada = $data['table_name'] ?? null;
        $id_registro = isset($data['record_id']) ? (int)$data['record_id'] : null;
        $datos_anteriores = isset($data['old_data']) ? json_encode($data['old_data'], JSON_UNESCAPED_UNICODE) : null;
        $datos_nuevos = isset($data['new_data']) ? json_encode($data['new_data'], JSON_UNESCAPED_UNICODE) : null;
        $resultado = $data['result'] ?? 'exito';
        $mensaje_error = $data['error_message'] ?? null;

        // 1. Always log to file (backup/debug)
        $file_line = "[$timestamp] [hospital:$id_hospital] [user:$user_id] [ip:$ip] [$modulo|$action] $descripcion" . PHP_EOL;
        $audit_log_path = sys_get_temp_dir() . '/clinicapp_audit.log';
        @error_log($file_line, 3, $audit_log_path);

        // 2. Log to database (audit_log table)
        $database = new Database();
        $conn = $database->getConnection();

        if ($conn) {
            $stmt = $conn->prepare("
                INSERT INTO audit_log (
                    id_hospital, fecha_audit, user_id, user_nombre, user_tipo,
                    ip_address, user_agent, session_id,
                    accion, modulo, descripcion,
                    tabla_afectada, id_registro,
                    datos_anteriores, datos_nuevos,
                    resultado, mensaje_error
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?,
                    ?, ?,
                    ?, ?
                )
            ");

            $stmt->execute([
                $id_hospital,
                $timestamp,
                $user_id,
                $user_nombre,
                $user_tipo,
                $ip,
                $user_agent,
                $session_id,
                $action,
                $modulo,
                $descripcion,
                $tabla_afectada,
                $id_registro,
                $datos_anteriores,
                $datos_nuevos,
                $resultado,
                $mensaje_error
            ]);
        }

    } catch (Exception $e) {
        // Never let audit logging failure break the application
        error_log("Audit log error: " . $e->getMessage());
    }
}

/**
 * Shortcut for authentication audit events (login, logout).
 */
function audit_log_auth($action, $descripcion, $result = 'exito', $error_message = null) {
    audit_log($action, 'auth', $descripcion, [
        'result' => $result,
        'error_message' => $error_message
    ]);
}

/**
 * Checks if a payment type is in the whitelist of accepted values.
 */
function validar_tipo_pago($tipo) {
    $permitidos = ['Efectivo', 'Tarjeta', 'Transferencia', 'Traslado'];
    return in_array($tipo, $permitidos, true);
}

/**
 * CSS class for charge-type badge by service category label.
 */
function charge_type_badge_class($tipo_cobro) {
    $map = [
        'Consulta' => 'charge-consulta',
        'Reconsulta' => 'charge-reconsulta',
        'Farmacia' => 'charge-farmacia',
        'Laboratorio' => 'charge-laboratorio',
        'Examen' => 'charge-examen',
        'Procedimiento' => 'charge-procedimiento',
        'Ultrasonido' => 'charge-ultrasonido',
        'Rayos X' => 'charge-rayos-x',
        'Electrocardiograma' => 'charge-electro',
    ];
    return $map[$tipo_cobro] ?? 'charge-otro';
}

/**
 * Bootstrap icon class for charge-type badge.
 */
function charge_type_icon($tipo_cobro) {
    $map = [
        'Consulta' => 'bi-stethoscope',
        'Reconsulta' => 'bi-arrow-repeat',
        'Farmacia' => 'bi-capsule',
        'Laboratorio' => 'bi-droplet-half',
        'Examen' => 'bi-clipboard2-pulse',
        'Procedimiento' => 'bi-bandaid',
        'Ultrasonido' => 'bi-soundwave',
        'Rayos X' => 'bi-radioactive',
        'Electrocardiograma' => 'bi-heart-pulse',
    ];
    return $map[$tipo_cobro] ?? 'bi-receipt';
}

/**
 * Print URL for a unified billing registry row.
 */
function billing_print_url($fuente, $id_registro) {
    $id = (int) $id_registro;
    switch ($fuente) {
        case 'cobro':
            return 'print_receipt.php?id=' . $id;
        case 'venta':
            return '../dispensary/print_receipt.php?id=' . $id;
        case 'examen':
            return '../laboratory/print_lab_receipt.php?id=' . $id;
        case 'procedimiento':
            return '../dashboard/print_procedure_receipt.php?id=' . $id;
        case 'ultrasonido':
            return '../ultrasonidos/print_us_receipt.php?id=' . $id;
        case 'rayos_x':
            return '../rayos_x/print_rx_receipt.php?id=' . $id;
        case 'electro':
            return 'print_electro.php?id=' . $id;
        default:
            return '#';
    }
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
    // Build an absolute URL back to the project root that works regardless of caller's depth
    $project_root_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . '/GitHub/app-clinica/index.php';

    if (!isset($_SESSION['user_id'])) {
        header("Location: " . $project_root_url);
        exit();
    }

    // Verify id_hospital is set and valid
    if (empty($_SESSION['id_hospital'])) {
        session_unset();
        session_destroy();
        header("Location: " . $project_root_url . "?error=session_invalid");
        exit();
    }

    // Session idle timeout: 2 hours (7200 seconds)
    $idle_timeout = 7200;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $idle_timeout)) {
        session_unset();
        session_destroy();
        header("Location: " . $project_root_url . "?error=session_expired");
        exit();
    }
    $_SESSION['last_activity'] = time();

    // Absolute session lifetime: 8 hours (28800 seconds)
    $session_lifetime = 28800;
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > $session_lifetime)) {
        session_unset();
        session_destroy();
        header("Location: " . $project_root_url . "?error=session_expired");
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
            const method = (init.method || 'GET').toUpperCase();
            if (method !== 'GET') {
                init.headers = init.headers || {};
                if (init.headers instanceof Headers) {
                    if (!init.headers.has('X-CSRF-Token')) {
                        init.headers.set('X-CSRF-Token', window.CSRF_TOKEN);
                    }
                } else if (!init.headers['X-CSRF-Token']) {
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
        }, 60000);

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