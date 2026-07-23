<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

require_once '../../config/hospital.php'; // Identidad de la carpeta

// Iniciar sesión con configuración consistente
start_app_session();


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

    // Rate limiting: max 3 attempts per 60 seconds
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_attempt_time'] = 0;
    }
    if ($_SESSION['login_attempts'] >= 3 && time() - $_SESSION['login_attempt_time'] < 60) {
        header("Location: ../../index.php?error=2");
        exit;
    }

    $database = new Database();
    $conn = $database->getConnection();

    $usuario = sanitize_input($_POST['usuario']);
    $password = sanitize_input($_POST['password']);

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
            $_SESSION['login_attempts'][$usuario] = ($_SESSION['login_attempts'][$usuario] ?? 0) + 1;
            header("Location: login.php?error=usuario_inactivo");
            exit;
        }

        $passwordValid = false;

        // Intentar verificación con password_hash primero
        if (password_verify($password, $user['password'])) {
            $passwordValid = true;
            // Re-hash si el algoritmo necesita actualización
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE idUsuario = ?");
                $updateStmt->execute([$newHash, $user['idUsuario']]);
            }
        } elseif ($password === $user['password']) {
            // Migración: contraseña en texto plano -> hash
            $passwordValid = true;
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE usuarios SET password = ? WHERE idUsuario = ?");
            $updateStmt->execute([$newHash, $user['idUsuario']]);
            error_log("LOGIN DEBUG: Migrated plaintext password to hash for user ID " . $user['idUsuario']);
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
        $_SESSION['login_attempts'] = 0;

        audit_log_auth('login_exitoso', 'Usuario: ' . $user['usuario'] . ' - Hospital ID: ' . $user['id_hospital']);

        error_log("LOGIN DEBUG: Redirecting to dashboard/index.php");
        header("Location: ../dashboard/index.php");
        exit();
    }
}

    $_SESSION['login_attempts']++;
    $_SESSION['login_attempt_time'] = time();
    audit_log_auth('login_fallido', 'Usuario intentado: ' . ($_POST['usuario'] ?? 'unknown'), 'error');
    error_log("LOGIN DEBUG: Login failed. Redirecting back to index.php?error=1");
    header("Location: ../../index.php?error=1");
    exit();
}
?>