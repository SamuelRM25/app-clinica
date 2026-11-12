<?php
/**
 * php/farmacia/_init.php — arranque común de las páginas propias de ClinicApp Farmacia.
 * Deja listos: $conn, $id_hospital, $rol, $user_name y los helpers fx_*.
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/multitenant.php';
require_once __DIR__ . '/../../includes/module_guard.php';

date_default_timezone_set('America/Guatemala');
verify_session();

$id_hospital = hospital_id();
$rol = $_SESSION['tipoUsuario'] ?? '';
$user_name = $_SESSION['nombre'] ?? 'Usuario';
$conn = (new Database())->getConnection();
get_hospital_config($conn, $id_hospital);

/** Ventas que cuentan como ingreso (sin traslados ni anuladas). Alias de ventas = v */
const FX_VENTA_VALIDA = "COALESCE(v.tipo_pago,'') <> 'Traslado' AND COALESCE(v.estado,'') NOT IN ('Cancelado','Anulado','Devuelto')";

function fx_e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function fx_q($n, int $dec = 2): string { return 'Q' . number_format((float) $n, $dec); }
function fx_n($n): string { return number_format((float) $n, 0); }

/** Ejecuta una consulta y devuelve todas las filas (arreglo vacío si falla). */
function fx_all(PDO $conn, string $sql, array $p = []): array {
    try { $st = $conn->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }
    catch (Throwable $e) { error_log('farmacia: ' . $e->getMessage()); return []; }
}
function fx_one(PDO $conn, string $sql, array $p = []): array { return fx_all($conn, $sql, $p)[0] ?? []; }
function fx_val(PDO $conn, string $sql, array $p = []) { $r = fx_one($conn, $sql, $p); return $r ? reset($r) : 0; }

/** Nombre corto de día/mes en español */
function fx_fecha_corta(string $fecha): string {
    $m = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $t = strtotime($fecha);
    return date('j', $t) . ' ' . $m[(int) date('n', $t) - 1];
}

/** Cabecera HTML común (el menú lo agrega farmacia_shell). */
function fx_head(string $titulo): void { ?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <title><?php echo fx_e($titulo . ' · ' . FARMA_NOMBRE); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <?php include __DIR__ . '/../../includes/theme_head.php'; ?>
</head>
<body>
<?php }

function fx_foot(): void { ?>
    <script>window.ES_CREADOR = <?php echo !empty($_SESSION['es_creador']) ? 'true' : 'false'; ?>;</script>
    <script defer src="../../assets/js/security.js"></script>
</body>
</html>
<?php }

/**
 * Gráfica de barras en SVG (sin librerías).
 * @param array $serie [['label' => '3 nov', 'valor' => 120.5, 'hint' => '...'], ...]
 */
function fx_barras(array $serie, string $id = 'fxChart'): string {
    $n = count($serie);
    if (!$n) return '<p class="fx-empty">Sin datos para mostrar.</p>';
    $max = max(array_map(fn($r) => (float) $r['valor'], $serie)) ?: 1;
    // Escala "bonita"
    $pot = pow(10, floor(log10($max)));
    $tope = ceil($max / $pot) * $pot;
    if ($tope / $pot > 5 && $tope / $pot < 10) $tope = 10 * $pot;
    $W = 720; $H = 240; $pl = 46; $pb = 26; $pt = 10; $pr = 6;
    $cw = ($W - $pl - $pr) / $n; $bw = max(6, min(34, $cw * .58));
    $svg = '<svg class="fx-bars" id="' . fx_e($id) . '" viewBox="0 0 ' . $W . ' ' . $H . '" role="img">';
    for ($i = 0; $i <= 4; $i++) {
        $y = $pt + ($H - $pt - $pb) * (1 - $i / 4);
        $v = $tope * $i / 4;
        $svg .= '<line x1="' . $pl . '" x2="' . ($W - $pr) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '" class="fx-bars__grid"/>';
        $svg .= '<text x="' . ($pl - 8) . '" y="' . round($y + 4, 1) . '" class="fx-bars__axis" text-anchor="end">' . ($v >= 1000 ? round($v / 1000, 1) . 'k' : round($v)) . '</text>';
    }
    $cada = $n > 16 ? (int) ceil($n / 10) : 1;
    foreach ($serie as $i => $r) {
        $v = (float) $r['valor'];
        $h = ($H - $pt - $pb) * ($v / $tope);
        $x = $pl + $cw * $i + ($cw - $bw) / 2;
        $y = $H - $pb - $h;
        $ultimo = $i === $n - 1;
        $svg .= '<g class="fx-bars__g' . ($ultimo ? ' is-last' : '') . '"><title>' . fx_e(($r['hint'] ?? $r['label']) . ': ' . fx_q($v)) . '</title>'
              . '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bw, 1) . '" height="' . round(max($h, $v > 0 ? 2 : 0), 1) . '" rx="5"/>';
        if ($i % $cada === 0 || $ultimo) {
            $svg .= '<text x="' . round($x + $bw / 2, 1) . '" y="' . ($H - 8) . '" class="fx-bars__axis" text-anchor="middle">' . fx_e($r['label']) . '</text>';
        }
        $svg .= '</g>';
    }
    return $svg . '</svg>';
}
