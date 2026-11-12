<?php
/**
 * includes/edition.php — Edición "ClinicApp Farmacia" (rama farmacia).
 *
 * Esta rama es una versión del sistema SOLO para farmacias:
 *   Punto de venta, Ventas, Inventario, Compras, Reportes y Sucursales (próximamente).
 * Todo lo clínico (pacientes, citas, laboratorio, quirófano, encamamiento, etc.)
 * queda bloqueado aunque el archivo exista.
 *
 * Para cambiar la marca basta con editar las constantes FARMA_*.
 */

if (!defined('APP_EDITION')) {

    define('APP_EDITION', 'farmacia');

    // ── Marca ───────────────────────────────────────────────────────────
    define('FARMA_MARCA', 'ClinicApp');          // primera parte del logotipo
    define('FARMA_PRODUCTO', 'Farmacia');        // segunda parte del logotipo
    define('FARMA_NOMBRE', FARMA_MARCA . ' ' . FARMA_PRODUCTO);
    define('FARMA_LEMA', 'Su farmacia, en orden.');
    define('FARMA_EMPRESA', 'RS Software Solutions');

    // Datos que salen en los comprobantes impresos (déjelos vacíos para ocultarlos)
    define('FARMA_DIRECCION', '');
    define('FARMA_TELEFONO', '');

    // Módulos (nombres de hospital_modulos) que existen en esta edición
    define('FARMA_MODULOS', ['core', 'pharmacy', 'sales', 'inventory', 'purchases', 'reports']);

    // Carpetas de php/ que se pueden abrir. El resto responde 403 / vuelve al inicio.
    define('FARMA_CARPETAS', ['auth', 'dashboard', 'dispensary', 'sales', 'inventory', 'purchases', 'reports', 'farmacia']);

    // Archivos clínicos dentro de carpetas permitidas (insumos de hospital, etc.)
    define('FARMA_ARCHIVOS_BLOQUEADOS', [
        'inventory/insumos.php', 'inventory/save_insumos.php', 'inventory/report_insumos.php',
        'inventory/report_insumos_mensual.php', 'inventory/hospital_medications.php', 'inventory/export_inventory_by_service.php',
    ]);

    function is_farmacia(): bool { return APP_EDITION === 'farmacia'; }

    /** Nombre de la farmacia del usuario (para comprobantes y reportes impresos). */
    function farmacia_local(): string {
        $n = trim((string) ($_SESSION['hospital_nombre'] ?? ''));
        return $n !== '' ? $n : FARMA_NOMBRE;
    }

    /**
     * Menú lateral. 'mod' = módulo requerido (null = siempre visible).
     */
    function farmacia_menu(): array {
        return [
            'inicio'     => ['Inicio',         'php/farmacia/inicio.php',     'bi-grid-1x2',        null],
            'pos'        => ['Punto de venta', 'php/dispensary/index.php',    'bi-upc-scan',        'pharmacy'],
            'ventas'     => ['Ventas',         'php/sales/index.php',         'bi-receipt',         'sales'],
            'inventario' => ['Inventario',     'php/inventory/index.php',     'bi-box-seam',        'inventory'],
            'compras'    => ['Compras',        'php/purchases/index.php',     'bi-bag-check',       'purchases'],
            'reportes'   => ['Reportes',       'php/farmacia/reportes.php',   'bi-bar-chart-line',  'reports'],
            'sucursales' => ['Sucursales',     'php/farmacia/sucursales.php', 'bi-shop-window',     null],
        ];
    }

    /** ¿El usuario actual puede ver este módulo? (suscripción + rol) */
    function farmacia_puede(?string $mod): bool {
        if ($mod === null) return true;
        $rol = $_SESSION['tipoUsuario'] ?? '';
        if (function_exists('role_can_access') && !role_can_access($rol, $mod)) return false;
        return in_array($mod, $_SESSION['hospital_modulos'] ?? ['core'], true);
    }

    /**
     * Bloquea las carpetas clínicas. Se ejecuta en cada request web
     * (functions.php lo llama al cargarse).
     */
    function farmacia_bloquear_rutas(): void {
        if (PHP_SAPI === 'cli') return;
        $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        if (!preg_match('#/php/([^/]+)/#', $script, $m)) return;   // raíz (login, admin) → libre
        $bloqueado_archivo = preg_match('#/php/([^/]+/[^/]+\.php)$#', $script, $f) && in_array($f[1], FARMA_ARCHIVOS_BLOQUEADOS, true);
        if (in_array($m[1], FARMA_CARPETAS, true) && !$bloqueado_archivo) return;

        $quiere_json = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
            || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
            || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET';
        http_response_code(403);
        if ($quiere_json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Este módulo no está disponible en ' . FARMA_NOMBRE . '.']);
        } else {
            header('Location: ' . app_base_path() . 'php/farmacia/inicio.php?no_disponible=' . urlencode($m[1]));
        }
        exit;
    }

    /**
     * Envuelve la página actual con el diseño de farmacia (barra lateral + barra superior).
     * Llamar ANTES de imprimir HTML. Inserta el CSS antes de </head> y el menú después de <body>.
     *
     * @param string $activo  clave de farmacia_menu()
     * @param array  $opts    ['titulo' => ..., 'sub' => ..., 'accion' => ['texto','url','icono']]
     */
    function farmacia_shell(string $activo, array $opts = []): void {
        $GLOBALS['__fx_shell'] = ['activo' => $activo, 'opts' => $opts];
        ob_start('farmacia_shell_inyectar');
    }

    function farmacia_shell_inyectar(string $html): string {
        $cfg = $GLOBALS['__fx_shell'] ?? null;
        if (!$cfg || stripos($html, '<body') === false) return $html;
        $base = app_base_path();
        $v = '20261111';
        $head = '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
              . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">'
              . '<link rel="stylesheet" href="' . $base . 'assets/css/farmacia.css?v=' . $v . '">'
              . '<link rel="icon" type="image/svg+xml" href="' . $base . 'assets/img/farmacia/mark.svg">'
              . '<meta name="theme-color" content="#0b2b26">';
        // Quitar favicons de la clínica para que no compitan con el de farmacia
        $html = preg_replace('#<link[^>]+rel="(icon|apple-touch-icon)"[^>]*>#i', '', $html);
        $html = preg_replace('#</head>#i', $head . '</head>', $html, 1);
        $html = preg_replace_callback('#<body([^>]*)>#i', function ($m) use ($cfg) {
            $attrs = $m[1];
            if (preg_match('/class="([^"]*)"/i', $attrs)) {
                $attrs = preg_replace('/class="([^"]*)"/i', 'class="$1 fx fx-page-' . $cfg['activo'] . '"', $attrs, 1);
            } else {
                $attrs .= ' class="fx fx-page-' . $cfg['activo'] . '"';
            }
            return '<body' . $attrs . '>' . farmacia_shell_html($cfg['activo'], $cfg['opts']);
        }, $html, 1);
        $html = preg_replace('#</body>#i', '<script src="' . $base . 'assets/js/farmacia.js?v=' . $v . '"></script></body>', $html, 1);
        return $html;
    }

    /** HTML de la barra lateral y la barra superior. */
    function farmacia_shell_html(string $activo, array $opts = []): string {
        $base = app_base_path();
        $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $menu = farmacia_menu();
        $nombre = trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido'] ?? ''));
        $rol = function_exists('role_label') ? role_label($_SESSION['tipoUsuario'] ?? '') : '';
        $local = $_SESSION['hospital_nombre'] ?? FARMA_NOMBRE;
        $titulo = $opts['titulo'] ?? ($menu[$activo][0] ?? FARMA_NOMBRE);
        $sub = $opts['sub'] ?? '';

        $items = '';
        foreach ($menu as $k => [$txt, $url, $ico, $mod]) {
            if (!farmacia_puede($mod)) continue;
            $on = $k === $activo;
            $pronto = $k === 'sucursales' ? '<span class="fx-pill">Pronto</span>' : '';
            $items .= '<a class="fx-nav__item' . ($on ? ' is-active' : '') . '" href="' . $base . $url . '"' . ($on ? ' aria-current="page"' : '') . '>'
                    . '<i class="bi ' . $ico . '"></i><span>' . $e($txt) . '</span>' . $pronto . '</a>';
        }

        $accion = '';
        if (array_key_exists('accion', $opts) && $opts['accion'] === false) {
            $accion = '';
        } elseif (!empty($opts['accion'])) {
            [$at, $au, $ai] = $opts['accion'] + [null, null, 'bi-plus-lg'];
            $accion = '<a class="fx-btn fx-btn--primary" href="' . $e($au) . '"><i class="bi ' . $ai . '"></i><span>' . $e($at) . '</span></a>';
        } elseif ($activo !== 'pos' && farmacia_puede('pharmacy')) {
            $accion = '<a class="fx-btn fx-btn--primary" href="' . $base . 'php/dispensary/index.php"><i class="bi bi-upc-scan"></i><span>Nueva venta</span></a>';
        }

        $ini = mb_strtoupper(mb_substr($nombre ?: 'U', 0, 1));
        $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $dias = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
        $hoy = $dias[(int) date('w')] . ' ' . date('j') . ' de ' . $meses[(int) date('n') - 1];

        return '
<div class="fx-scrim" data-fx-close></div>
<aside class="fx-side" id="fxSide" aria-label="Menú principal">
  <a class="fx-brand" href="' . $base . 'php/farmacia/inicio.php">
    <img src="' . $base . 'assets/img/farmacia/mark.svg" alt="" width="36" height="36">
    <span class="fx-brand__txt"><b>' . $e(FARMA_MARCA) . '</b><em>' . $e(FARMA_PRODUCTO) . '</em></span>
  </a>
  <div class="fx-local" title="' . $e($local) . '"><i class="bi bi-geo-alt"></i><span>' . $e($local) . '</span></div>
  <nav class="fx-nav">' . $items . '</nav>
  <div class="fx-side__foot">
    <button type="button" class="fx-theme" data-fx-theme aria-label="Cambiar modo claro u oscuro">
      <i class="bi bi-moon-stars"></i><i class="bi bi-sun"></i><span class="fx-theme__t">Modo oscuro</span>
    </button>
    <div class="fx-user">
      <span class="fx-avatar">' . $e($ini) . '</span>
      <span class="fx-user__txt"><b>' . $e($nombre ?: 'Usuario') . '</b><small>' . $e($rol) . '</small></span>
      <a class="fx-logout" href="' . $base . 'php/auth/logout.php" title="Cerrar sesión" aria-label="Cerrar sesión"><i class="bi bi-box-arrow-right"></i></a>
    </div>
  </div>
</aside>
<header class="fx-top">
  <button type="button" class="fx-burger" data-fx-open aria-label="Abrir menú" aria-controls="fxSide"><i class="bi bi-list"></i></button>
  <div class="fx-top__title">
    <h1>' . $e($titulo) . '</h1>
    <p>' . ($sub !== '' ? $e($sub) . ' · ' : '') . '<span class="fx-cap">' . $e($hoy) . '</span></p>
  </div>
  <div class="fx-top__actions">' . $accion . '</div>
</header>';
    }
}
