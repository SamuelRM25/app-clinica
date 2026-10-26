<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

require_once '../../config/hospital.php'; // Identidad de la carpeta

// Iniciar sesión con configuración consistente
start_app_session();

// ── Rate limiting de login (por usuario, guardado en sesión) ──
const LOGIN_MAX_ATTEMPTS = 3;
const LOGIN_LOCK_SECONDS = 60;

/** Devuelve ['count'=>n, 'time'=>t] del usuario, reiniciado si ya pasó la ventana. */
function login_attempt_get(string $key): array {
    $a = $_SESSION['login_attempts_by_user'][$key] ?? null;
    if (!is_array($a)) {
        $a = ['count' => 0, 'time' => 0]; // formato anterior (entero) o sin intentos
    }
    if (time() - (int) $a['time'] >= LOGIN_LOCK_SECONDS) {
        $a = ['count' => 0, 'time' => 0];
    }
    return $a;
}

/** Registra un intento fallido para el usuario. */
function login_attempt_fail(string $key): void {
    $a = login_attempt_get($key);
    $a['count']++;
    $a['time'] = time();
    $_SESSION['login_attempts_by_user'][$key] = $a;
}


// Si alguien llega por GET, redirigir al formulario de login real
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF: si el token no coincide (sesión expirada, cookies viejas, etc.),
    // simplemente regeneramos el token y dejamos pasar para no bloquear el login
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!empty($_SESSION['csrf_token']) && !empty($csrf_token)
        && !hash_equals($_SESSION['csrf_token'], $csrf_token))
    {
        error_log("CSRF mismatch in login.php — regenerando token para evitar bloqueo");
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    // Usuario: se limpia para buscarlo. Contraseña: se usa TAL CUAL la escribió
    // el usuario (save_user.php la guarda sin modificar, así que debe compararse igual).
    $usuario  = sanitize_input($_POST['usuario'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    // Rate limiting por usuario: máx. 3 intentos fallidos en 60 segundos
    if (!isset($_SESSION['login_attempts_by_user']) || !is_array($_SESSION['login_attempts_by_user'])) {
        $_SESSION['login_attempts_by_user'] = [];
    }
    $attempt_key = strtolower($usuario);
    $attempt = login_attempt_get($attempt_key);
    if ($attempt['count'] >= LOGIN_MAX_ATTEMPTS) {
        header("Location: ../../index.php?error=2");
        exit;
    }

    $database = new Database();
    $conn = $database->getConnection();

    // Buscar el usuario uniendo con la tabla hospitales para validar el CÓDIGO
    $stmt = $conn->prepare("
        SELECT u.*, h.id_hospital as hospital_real_id
        FROM usuarios u
        JOIN hospitales h ON u.id_hospital = h.id_hospital
        WHERE u.usuario = ? AND h.codigo_hospital = ?
    ");
    $stmt->execute([$usuario, CURRENT_HOSPITAL_CODE]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        error_log("LOGIN DEBUG: User found in DB for user: " . $usuario);
    } else {
        error_log("LOGIN DEBUG: User NOT found in DB with this hospital code.");
    }

    if ($user) {
        // Bloquear login de usuarios desactivados (soft delete)
        if (isset($user['activo']) && (int)$user['activo'] === 0) {
            error_log("LOGIN DEBUG: User is inactive: " . $usuario);
            login_attempt_fail($attempt_key);
            header("Location: ../../index.php?error=usuario_inactivo");
            exit;
        }

        $passwordValid = false;
        $needsRehash   = false;
        $stored        = (string) $user['password'];
        // Versión "limpiada" como la procesaba el login anterior (trim + htmlspecialchars).
        // Algunos hashes se generaron así al migrar contraseñas en texto plano.
        $legacyPassword = sanitize_input($password);

        if (password_verify($password, $stored)) {
            $passwordValid = true;
            $needsRehash   = password_needs_rehash($stored, PASSWORD_DEFAULT);
        } elseif ($legacyPassword !== $password && password_verify($legacyPassword, $stored)) {
            // Hash antiguo creado con la contraseña modificada: aceptar y corregir
            $passwordValid = true;
            $needsRehash   = true;
            error_log("LOGIN: Rehash de contraseña legacy (sanitizada) para user ID " . $user['idUsuario']);
        } elseif ($stored !== '' && (hash_equals($stored, $password) || hash_equals($stored, $legacyPassword))) {
            // Migración: contraseña en texto plano -> hash
            $passwordValid = true;
            $needsRehash   = true;
            error_log("LOGIN: Migrada contraseña en texto plano a hash para user ID " . $user['idUsuario']);
        }

        if ($passwordValid && $needsRehash) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE idUsuario = ?");
            $updateStmt->execute([$newHash, $user['idUsuario']]);
        }

        if ($passwordValid) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['idUsuario'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['id_hospital'] = $user['hospital_real_id'];
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();

            // Cargar configuración del hospital
            require_once '../../includes/multitenant.php';
            $h_config = get_hospital_config($conn, $user['id_hospital']);

        if ($h_config) {
            error_log("LOGIN DEBUG: Hospital config loaded successfully.");
            $_SESSION['hospital_nombre'] = $h_config['nombre'];
            $_SESSION['hospital_modulos'] = $h_config['modulos_activos'];
            $_SESSION['hospital_status'] = $h_config['estado_suscripcion'];
            $_SESSION['hospital_type'] = $h_config['tipo_suscripcion'];
            $_SESSION['hospital_expiry'] = $h_config['fecha_vencimiento'];
        } else {
            error_log("LOGIN DEBUG: Hospital config NOT found.");
        }

        $_SESSION['apellido'] = $user['apellido'];
        $_SESSION['clinica'] = $user['clinica'];
        $_SESSION['especialidad'] = $user['especialidad'];
        $_SESSION['tipoUsuario'] = $user['tipoUsuario'];
        $_SESSION['usuario'] = $user['usuario'];
        $_SESSION['es_creador'] = !empty($user['es_creador']);
        unset($_SESSION['login_attempts_by_user'][$attempt_key]);

        audit_log_auth('login_exitoso', 'Usuario: ' . $user['usuario'] . ' - Hospital ID: ' . $user['id_hospital']);

        error_log("LOGIN DEBUG: Redirecting to dashboard/index.php");
        header("Location: ../dashboard/index.php");
        exit();
    }
}

    login_attempt_fail($attempt_key);
    audit_log_auth('login_fallido', 'Usuario intentado: ' . ($_POST['usuario'] ?? 'unknown'), 'error');
    error_log("LOGIN DEBUG: Login failed. Redirecting back to index.php?error=1");
    header("Location: ../../index.php?error=1");
    exit();
}
?>