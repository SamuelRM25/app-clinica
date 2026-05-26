<?php
/**
 * multitenant.php - Gestión de Multi-hospital y Suscripciones
 */
require_once __DIR__ . '/../config/hospital.php'; // Identidad de la carpeta

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Seguridad: Verificar que la sesión pertenezca a esta carpeta ──
if (isset($_SESSION['id_hospital'])) {
    // Si ya hay sesión, verificamos que el ID coincida con el código de esta carpeta
    // (Esto evita que alguien con sesión en Hospital A entre a la carpeta del Hospital B)
    // Solo lo hacemos si la conexión a la BD está disponible o al menos una vez.
}

// Cache en memoria por request para evitar múltiples queries por página
$_HOSPITAL_CONFIG_CACHE = null;

/**
 * Obtiene la configuración del hospital actual (siempre desde la BD, no sesión)
 */
function get_hospital_config($conn, $hospital_id = null) {
    global $_HOSPITAL_CONFIG_CACHE;

    if ($_HOSPITAL_CONFIG_CACHE !== null) {
        return $_HOSPITAL_CONFIG_CACHE;
    }

    if ($hospital_id !== null) {
        // Buscar por ID numérico
        $stmt = $conn->prepare("SELECT * FROM hospitales WHERE id_hospital = ?");
        $stmt->execute([$hospital_id]);
    } else {
        // Buscar por CÓDIGO de la carpeta
        $stmt = $conn->prepare("SELECT * FROM hospitales WHERE codigo_hospital = ?");
        $stmt->execute([CURRENT_HOSPITAL_CODE]);
    }

    $hospital = $stmt->fetch();

    if ($hospital) {
        // SEGURIDAD: Verificar que si hay una sesión, coincida con este hospital
        if (isset($_SESSION['id_hospital']) && $_SESSION['id_hospital'] != $hospital['id_hospital']) {
            error_log("SECURITY DESTROY: session id_hospital (" . $_SESSION['id_hospital'] . ") != hospital id (" . $hospital['id_hospital'] . ")");
            session_destroy();
            header("Location: /index.php?err=security");
            exit;
        }

        $hospital['modulos_activos'] = json_decode($hospital['modulos_activos'], true) ?: ['core'];
        // Actualizar sesión para mantener en sincronía
        $_SESSION['hospital_modulos'] = $hospital['modulos_activos'];
        $_SESSION['hospital_status'] = $hospital['estado_suscripcion'];
        $_SESSION['hospital_type']   = $hospital['tipo_suscripcion'];
        $_SESSION['hospital_expiry'] = $hospital['fecha_vencimiento'];
        $_SESSION['hospital_nombre'] = $hospital['nombre'];
        $_SESSION['hospital_tema']   = $hospital['tema'] ?? 'classic';
    }

    $_HOSPITAL_CONFIG_CACHE = $hospital;
    return $hospital;
}

/**
 * Verifica si un módulo está activo. Siempre lee desde la BD a través del cache.
 * Requiere que get_hospital_config() haya sido llamado antes en la misma request.
 */
function is_module_active($module_name) {
    // 'core' siempre está activo
    if ($module_name === 'core') return true;

    // Lee del cache en sesión (ya sincronizado con BD en get_hospital_config)
    if (!isset($_SESSION['hospital_modulos'])) {
        return false;
    }
    return in_array($module_name, $_SESSION['hospital_modulos']);
}

/**
 * Verifica la suscripción del hospital
 */
function check_subscription_status() {
    if (!isset($_SESSION['hospital_status'])) return false;

    if ($_SESSION['hospital_status'] === 'Inactivo' || $_SESSION['hospital_status'] === 'Vencido') {
        return false;
    }

    // Si es de por vida, nunca vence
    if (isset($_SESSION['hospital_type']) && $_SESSION['hospital_type'] === 'De por vida') {
        return true;
    }

    // Verificar fecha de vencimiento
    if (isset($_SESSION['hospital_expiry']) && $_SESSION['hospital_expiry'] !== null && $_SESSION['hospital_expiry'] < date('Y-m-d')) {
        return false;
    }

    return true;
}

/**
 * Genera el filtro SQL para el hospital actual
 */
function get_hospital_filter($alias = '') {
    // Intentar usar el ID de la sesión primero
    $hospital_id = $_SESSION['id_hospital'] ?? null;
    
    // Si no hay sesión (ej: scripts de fondo o antes del login completo), 
    // podrías querer resolver el código, pero lo normal es que siempre haya sesión.
    // Como medida de seguridad, si no hay ID, usamos un valor que no devuelva nada o el ID 1.
    if (!$hospital_id) $hospital_id = 1; 

    $prefix = $alias ? "$alias." : "";
    return " {$prefix}id_hospital = " . (int)$hospital_id . " ";
}

/**
 * Inyecta el CSS del tema seleccionado por el hospital
 */
function apply_hospital_theme() {
    $tema = $_SESSION['hospital_tema'] ?? 'classic';
    $path_to_css = (strpos($_SERVER['SCRIPT_NAME'], '/php/') !== false) ? '../../assets/css/themes.css' : 'assets/css/themes.css';
    if (strpos($_SERVER['SCRIPT_NAME'], '/php/settings/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/php/dashboard/') !== false) {
        // Ajuste para carpetas profundas
        $depth = count(explode('/', trim($_SERVER['SCRIPT_NAME'], '/'))) - 1;
        $prefix = str_repeat('../', $depth - 1);
        $path_to_css = $prefix . 'assets/css/themes.css';
    }
    
    // Si estamos en la raíz (ej: admin.php o index.php)
    if (basename($_SERVER['SCRIPT_NAME']) == 'admin.php' || basename($_SERVER['SCRIPT_NAME']) == 'index.php') {
        $path_to_css = 'assets/css/themes.css';
    }

    echo "<!-- Theme Engine -->\n";
    echo "<link rel='stylesheet' href='{$path_to_css}'>\n";
    echo "<script>document.body.classList.add('theme-{$tema}');</script>\n";
    echo "<style>
        :root {
            --color-primary: var(--color-primary, #7c90db);
            --primary: var(--color-primary);
            --color-bg: var(--color-bg, #f8fafc);
            --color-surface: var(--color-surface, #ffffff);
            --color-text: var(--color-text, #1e293b);
            --color-border: var(--color-border, #e2e8f0);
        }
    </style>\n";
}
