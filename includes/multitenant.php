<?php
/**
 * multitenant.php - Gestión de Multi-hospital y Suscripciones
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

    if ($hospital_id === null) {
        $hospital_id = $_SESSION['id_hospital'] ?? 1;
    }

    $stmt = $conn->prepare("SELECT * FROM hospitales WHERE id_hospital = ?");
    $stmt->execute([$hospital_id]);
    $hospital = $stmt->fetch();

    if ($hospital) {
        $hospital['modulos_activos'] = json_decode($hospital['modulos_activos'], true) ?: ['core'];
        // Actualizar sesión para mantener en sincronía
        $_SESSION['hospital_modulos'] = $hospital['modulos_activos'];
        $_SESSION['hospital_status'] = $hospital['estado_suscripcion'];
        $_SESSION['hospital_type']   = $hospital['tipo_suscripcion'];
        $_SESSION['hospital_expiry'] = $hospital['fecha_vencimiento'];
        $_SESSION['hospital_nombre'] = $hospital['nombre'];
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
    $hospital_id = $_SESSION['id_hospital'] ?? 1;
    $prefix = $alias ? "$alias." : "";
    return " {$prefix}id_hospital = " . (int)$hospital_id . " ";
}
