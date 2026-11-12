<?php
/**
 * includes/roles.php
 * Tipos de usuario del sistema y qué puede ver cada uno.
 *
 *   admin  Administrador (acceso total)
 *   user   Usuario estándar (recepción, caja)
 *   doc    Médico
 *   lab    Laboratorio
 *   qx     Quirófano
 *   farm   Farmacia
 *
 * Los roles con módulos definidos en ROLE_MODULES solo pueden entrar a esos
 * módulos (lo aplica check_module_access) y tienen su propio dashboard.
 */

if (!defined('APP_ROLES')) {

    define('APP_ROLES', [
        'admin' => 'Administrador',
        'user'  => 'Usuario estándar',
        'doc'   => 'Médico',
        'lab'   => 'Laboratorio',
        'qx'    => 'Quirófano',
        'farm'  => 'Farmacia',
    ]);

    // Módulos permitidos para los roles con acceso limitado
    define('ROLE_MODULES', [
        'lab'  => ['laboratory'],
        'qx'   => ['surgery'],
        // En la edición farmacia el cajero también ve el historial de ventas
        'farm' => (defined('APP_EDITION') && APP_EDITION === 'farmacia') ? ['pharmacy', 'inventory', 'sales'] : ['pharmacy', 'inventory'],
    ]);

    /** Nombre legible del tipo de usuario. */
    function role_label(?string $role): string {
        return APP_ROLES[$role ?? ''] ?? ucfirst((string) $role);
    }

    /** ¿Es un tipo de usuario válido? */
    function role_is_valid(?string $role): bool {
        return isset(APP_ROLES[$role ?? '']);
    }

    /** ¿El rol tiene acceso limitado a ciertos módulos (y dashboard propio)? */
    function role_is_limited(?string $role): bool {
        return isset(ROLE_MODULES[$role ?? '']);
    }

    /** ¿Puede el rol entrar a este módulo? */
    function role_can_access(?string $role, string $module): bool {
        if (!role_is_limited($role)) {
            return true;
        }
        return in_array($module, ROLE_MODULES[$role], true);
    }
}
