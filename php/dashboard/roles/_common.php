<?php
/**
 * dashboard/roles/_common.php
 * Utilidades compartidas por los dashboards de Laboratorio, Quirófano y Farmacia.
 * Se incluye desde php/dashboard/index.php, así que $conn, $user_name, etc. ya existen.
 */

if (!function_exists('rd_query')) {

    /**
     * Ejecuta una consulta sin romper el dashboard si falla (se registra en el log).
     * $mode: 'all' (filas), 'row' (una fila), 'value' (primer valor).
     */
    function rd_query($conn, string $sql, array $params = [], string $mode = 'all') {
        try {
            $st = $conn->prepare($sql);
            $st->execute($params);
            if ($mode === 'value') { $v = $st->fetchColumn(); return $v === false ? 0 : $v; }
            if ($mode === 'row')   { return $st->fetch(PDO::FETCH_ASSOC) ?: []; }
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('Dashboard por rol: ' . $e->getMessage());
            return $mode === 'all' ? [] : ($mode === 'row' ? [] : 0);
        }
    }

    function rd_e($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

    function rd_money($v): string { return 'Q' . number_format((float) $v, 2); }

    function rd_initials(string $name): string {
        $parts = preg_split('/\s+/', trim($name));
        $ini = mb_substr($parts[0] ?? '', 0, 1) . mb_substr($parts[1] ?? '', 0, 1);
        return mb_strtoupper($ini ?: '?');
    }

    /** "hace 2 horas" (usa time_ago del sistema si existe). */
    function rd_ago(?string $dt): string {
        if (!$dt) return '';
        return function_exists('time_ago') ? time_ago($dt) : date('d/m H:i', strtotime($dt));
    }

    /** Duración desde $dt hasta ahora: "1 h 25 min". */
    function rd_elapsed(?string $dt): string {
        if (!$dt) return '';
        $min = max(0, (int) floor((time() - strtotime($dt)) / 60));
        return $min >= 60 ? floor($min / 60) . ' h ' . ($min % 60) . ' min' : $min . ' min';
    }

    /** Mes abreviado en español: "nov". */
    function rd_mes_corto(string $dt): string {
        $m = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
        return $m[(int) date('n', strtotime($dt)) - 1];
    }

    function rd_fecha_larga(): string {
        $dias  = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        return ucfirst($dias[(int) date('w')]) . ' ' . date('j') . ' de ' . $meses[(int) date('n') - 1];
    }

    /**
     * Encabezado de bienvenida del rol.
     * $actions: [['href' => ..., 'label' => ..., 'icon' => 'bi-...', 'style' => 'primary|ghost']]
     */
    function rd_hero(string $user_name, string $area, string $icon, string $summary, array $actions): void {
        $denied = isset($_GET['acceso_denegado']);
        ?>
        <?php if ($denied): ?>
            <div class="rd-alert" role="alert">
                <i class="bi bi-shield-lock"></i>
                <span>Ese módulo no está disponible para su usuario. Si lo necesita, pídale acceso al administrador.</span>
            </div>
        <?php endif; ?>
        <section class="rd-hero" aria-label="Bienvenida">
            <div class="rd-hero__text">
                <span class="rd-area"><i class="bi <?php echo rd_e($icon); ?>"></i> <?php echo rd_e($area); ?></span>
                <h2 id="greeting"><span id="greeting-text">Buenos días</span>, <?php echo rd_e($user_name); ?></h2>
                <p class="rd-summary"><?php echo $summary; ?></p>
                <p class="rd-date"><?php echo rd_e(rd_fecha_larga()); ?>, <span id="current-time"><?php echo date('H:i'); ?></span></p>
            </div>
            <div class="rd-hero__actions">
                <?php foreach ($actions as $a): ?>
                    <a href="<?php echo rd_e($a['href']); ?>" class="rd-btn rd-btn--<?php echo rd_e($a['style'] ?? 'ghost'); ?>">
                        <i class="bi <?php echo rd_e($a['icon']); ?>"></i>
                        <span><?php echo rd_e($a['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }

    /** Tarjeta de indicador. $tone: navy | gold | green | red | blue */
    function rd_kpi(string $label, $value, string $icon, string $tone = 'navy', string $note = '', string $href = ''): void {
        $tag = $href ? 'a' : 'div';
        ?>
        <<?php echo $tag; ?> class="rd-kpi rd-kpi--<?php echo rd_e($tone); ?>"<?php echo $href ? ' href="' . rd_e($href) . '"' : ''; ?>>
            <span class="rd-kpi__icon"><i class="bi <?php echo rd_e($icon); ?>"></i></span>
            <span class="rd-kpi__value"><?php echo rd_e($value); ?></span>
            <span class="rd-kpi__label"><?php echo rd_e($label); ?></span>
            <?php if ($note): ?><span class="rd-kpi__note"><?php echo rd_e($note); ?></span><?php endif; ?>
        </<?php echo $tag; ?>>
        <?php
    }

    function rd_empty(string $icon, string $title, string $text): void {
        ?>
        <div class="rd-empty">
            <i class="bi <?php echo rd_e($icon); ?>"></i>
            <strong><?php echo rd_e($title); ?></strong>
            <span><?php echo rd_e($text); ?></span>
        </div>
        <?php
    }
}
