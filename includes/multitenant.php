<?php
/**
 * multitenant.php - Gestión de Multi-hospital y Suscripciones
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Obtiene la configuración del hospital actual
 */
function get_hospital_config($conn, $hospital_id) {
    $stmt = $conn->prepare("SELECT * FROM hospitales WHERE id_hospital = ?");
    $stmt->execute([$hospital_id]);
    $hospital = $stmt->fetch();
    
    if ($hospital) {
        $hospital['modulos_activos'] = json_decode($hospital['modulos_activos'], true) ?: ['core'];
    }
    
    return $hospital;
}

/**
 * Verifica si un módulo está activo para el hospital actual
 */
function is_module_active($module_name) {
    if (!isset($_SESSION['hospital_modulos'])) {
        return $module_name === 'core';
    }
    return in_array($module_name, $_SESSION['hospital_modulos']) || $module_name === 'core';
}

/**
 * Verifica la suscripción del hospital
 */
function check_subscription_status() {
    if (!isset($_SESSION['hospital_status'])) return false;
    
    if ($_SESSION['hospital_status'] === 'Inactivo' || $_SESSION['hospital_status'] === 'Vencido') {
        return false;
    }
    
    // Verificar fecha de vencimiento
    if (isset($_SESSION['hospital_expiry']) && $_SESSION['hospital_expiry'] < date('Y-m-d')) {
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
    return " {$prefix}id_hospital = $hospital_id ";
}
