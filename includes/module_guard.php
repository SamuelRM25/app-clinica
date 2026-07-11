<?php
/**
 * includes/module_guard.php
 *
 * Incluir al inicio de cada index.php de módulo (excepto dashboard).
 * Realiza DOS verificaciones:
 *   1. Que el usuario tenga sesión activa.
 *   2. Que el módulo esté activo en la suscripción del hospital.
 *
 * Uso:
 *   require_once '../../includes/module_guard.php';
 *   check_module_access('pharmacy');   // nombre del módulo
 *
 * También expone:
 *   hospital_id()  → devuelve el id_hospital de la sesión actual (int)
 */

if (!function_exists('check_module_access')) {

    /**
     * Verifica que el módulo esté activo y el usuario autenticado.
     * Si no cumple, redirige al dashboard con un mensaje de error.
     */
    function check_module_access(string $module_name): void
    {
        // 1. Sesión activa
        if (!isset($_SESSION['user_id'])) {
            $project_root_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                . '/GitHub/app-clinica/index.php';
            header('Location: ' . $project_root_url);
            exit;
        }

        // 2. Módulo activo (core siempre está permitido)
        if ($module_name === 'core') {
            return;
        }

        $modulos_activos = $_SESSION['hospital_modulos'] ?? ['core'];

        if (!in_array($module_name, $modulos_activos, true)) {
            // Redirigir al dashboard con parámetro de error
            header('Location: ../dashboard/index.php?acceso_denegado=1&modulo=' . urlencode($module_name));
            exit;
        }
    }

    /**
     * Devuelve el id_hospital del hospital de la sesión actual.
     * Usar en todas las consultas SQL para filtrar datos.
     */
    function hospital_id(): int
    {
        return (int) ($_SESSION['id_hospital'] ?? 0);
    }
}
?>
