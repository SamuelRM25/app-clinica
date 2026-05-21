<?php
/**
 * includes/theme_head.php
 * Incluir este fragmento DENTRO del <head> de cada página,
 * DESPUÉS del link a global_dashboard.css.
 *
 * Inyecta el theme-loader.js como script bloqueante inline
 * para evitar el flash de estilos sin tema (FOUC).
 */
$theme_loader_path = dirname(__DIR__) . '/assets/js/theme-loader.js';
$theme_js = file_exists($theme_loader_path) ? file_get_contents($theme_loader_path) : '';
?>
<script><?php echo $theme_js; ?></script>
