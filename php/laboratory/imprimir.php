<?php
/**
 * laboratory/imprimir.php — Hoja de resultados para imprimir (tamaño carta).
 * Una página por "hoja" de plantilla; las pruebas con la misma hoja salen juntas.
 * ?id=orden  [&op=id_orden_prueba para imprimir solo una prueba]
 */
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login.php'); exit; }
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
verify_session();
date_default_timezone_set('America/Guatemala');

$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);
$rol = $_SESSION['tipoUsuario'] ?? '';
$id_orden = (int) ($_GET['id'] ?? 0);
$solo_op = (int) ($_GET['op'] ?? 0);
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$conn = (new Database())->getConnection();
if (!lab_tables_ready($conn)) die('Las plantillas de resultados no están instaladas.');

$st = $conn->prepare("SELECT ol.*, p.nombre, p.apellido, p.genero, p.fecha_nacimiento,
                             TRIM(CONCAT(COALESCE(u.nombre,''), ' ', COALESCE(u.apellido,''))) AS doctor
                      FROM ordenes_laboratorio ol
                      JOIN pacientes p ON ol.id_paciente = p.id_paciente
                      LEFT JOIN usuarios u ON ol.id_doctor = u.idUsuario
                      WHERE ol.id_orden = ? AND ol.id_hospital = ?");
$st->execute([$id_orden, $id_hospital]);
$o = $st->fetch(PDO::FETCH_ASSOC);
if (!$o) die('No se encontró la orden.');

$final = in_array($o['estado'], ['Completada', 'Validada'], true);
$borrador = !$final;
if ($borrador && !in_array($rol, ['admin', 'lab', 'user'], true)) die('Los resultados de esta orden todavía no están listos.');

$sql = "SELECT op.id_orden_prueba, cp.nombre_prueba, lr.plantilla, lr.valores, lr.observaciones, lr.fecha_finalizado,
               lp.diseno, TRIM(CONCAT(COALESCE(uf.nombre,''), ' ', COALESCE(uf.apellido,''))) AS finalizado_por
        FROM orden_pruebas op
        JOIN catalogo_pruebas cp ON cp.id_prueba = op.id_prueba
        LEFT JOIN lab_resultados lr ON lr.id_orden_prueba = op.id_orden_prueba
        LEFT JOIN lab_plantillas lp ON lp.id_plantilla = cp.id_plantilla AND lp.id_hospital = ?
        LEFT JOIN usuarios uf ON uf.idUsuario = lr.finalizado_por
        WHERE op.id_orden = ? AND op.estado <> 'Devuelto'" . ($solo_op ? " AND op.id_orden_prueba = ?" : '') . " ORDER BY op.id_orden_prueba";
$st = $conn->prepare($sql);
$st->execute($solo_op ? [$id_hospital, $id_orden, $solo_op] : [$id_hospital, $id_orden]);

$hojas = [];
$sin = [];
$fecha_res = null;
$responsable = '';
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $src = $r['plantilla'] ?: $r['diseno'];
    if (!$src) { $sin[] = $r['nombre_prueba']; continue; }
    $tpl = lab_tpl_normalize($src);
    $hoja = $tpl['hoja'] !== '' ? $tpl['hoja'] : mb_strtoupper($r['nombre_prueba']);
    $hojas[$hoja][] = ['tpl' => $tpl, 'valores' => $r['valores'] ? (json_decode($r['valores'], true) ?: []) : [], 'nombre' => $r['nombre_prueba'], 'obs' => $r['observaciones']];
    if ($r['fecha_finalizado'] && (!$fecha_res || $r['fecha_finalizado'] > $fecha_res)) { $fecha_res = $r['fecha_finalizado']; $responsable = $r['finalizado_por']; }
}
$fecha = $fecha_res ?: $o['fecha_orden'];
$paciente = trim($o['nombre'] . ' ' . $o['apellido']);
$medico = trim($o['doctor']) ? 'DR(A). ' . mb_strtoupper(trim($o['doctor'])) : 'PARTICULAR';
$edad = lab_edad($o['fecha_nacimiento']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultados <?php echo $e($o['numero_orden'] . ' · ' . $paciente); ?></title>
    <link rel="icon" type="image/png" href="../../assets/img/favicon-cmrm.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        @page { size: letter; margin: 12mm 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e9e8e3; color: #111; font-family: Calibri, Carlito, 'Segoe UI', Arial, sans-serif; font-size: 12.5px; }
        .bar { position: sticky; top: 0; z-index: 5; display: flex; flex-wrap: wrap; gap: .5rem; justify-content: center; padding: .7rem; background: #283251; }
        .bar button, .bar a { display: inline-flex; align-items: center; gap: .4rem; padding: .5rem 1rem; border-radius: 9px; border: 1px solid rgba(255,255,255,.3); background: transparent; color: #fff; font: 600 14px system-ui, sans-serif; cursor: pointer; text-decoration: none; }
        .bar button.main { background: #c9a24f; border-color: #c9a24f; color: #241a06; }
        .page { position: relative; width: 216mm; min-height: 279mm; margin: 1rem auto; padding: 12mm 14mm; background: #fff; box-shadow: 0 10px 40px -20px rgba(0,0,0,.35); display: flex; flex-direction: column; }
        .head { display: flex; align-items: center; gap: 14px; padding-bottom: 8px; border-bottom: 2px solid #283251; }
        .head img { height: 52px; }
        .head h1 { flex: 1; margin: 0; text-align: center; font-size: 19px; letter-spacing: .04em; color: #111; }
        .head .spacer { width: 52px; }
        .pat { display: flex; justify-content: space-between; gap: 18px; margin: 14px 0 6px; }
        .pat dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 3px 8px; }
        .pat dt { font-weight: 700; }
        .pat dd { margin: 0; text-transform: uppercase; }
        .nobox { border-collapse: collapse; align-self: flex-start; }
        .nobox td { border: 1px solid #333; padding: 3px 9px; }
        .nobox td:first-child { font-weight: 700; }
        h2.hoja { text-align: center; margin: 12px 0 10px; font-size: 15px; letter-spacing: .05em; }
        .lp-grid { display: grid; gap: 10px 14px; grid-template-columns: minmax(0, 1fr); margin-bottom: 10px; align-items: start; }
        .lp-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .lp-cols-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .lp-sec--full { grid-column: 1 / -1; }
        .lp-sec__t { margin: 0 0 4px; font-size: 12px; text-align: center; letter-spacing: .04em; }
        .lp-t { width: 100%; border-collapse: collapse; }
        .lp-t th { font-size: 11px; text-transform: uppercase; text-align: left; padding: 4px 6px; border: 1px solid #333; background: #f1f1ef; }
        .lp-t td { padding: 4px 6px; border: 1px solid #333; vertical-align: top; }
        .lp-v { font-weight: 700; white-space: nowrap; }
        .lp-flag { text-decoration: underline; }
        .lp-u { white-space: nowrap; }
        .lp-r { font-size: 11.5px; }
        .lp-t--m th, .lp-t--m td.lp-c { text-align: center; }
        .lp-t--ref th, .lp-t--ref td { text-align: center; font-size: 11.5px; }
        .lp-note { font-size: 11px; color: #444; margin: 0; }
        .lp-obs { margin: 6px 0; font-size: 12px; }
        .foot { margin-top: auto; padding-top: 36px; display: flex; justify-content: space-between; align-items: flex-end; gap: 20px; font-size: 11px; color: #444; }
        .firma { width: 230px; border-top: 1px solid #111; padding-top: 4px; text-align: center; color: #111; }
        .marca { position: absolute; inset: 0; display: grid; place-items: center; pointer-events: none; font-size: 90px; font-weight: 800; color: rgba(200, 30, 30, .08); transform: rotate(-24deg); letter-spacing: .1em; }
        .aviso { max-width: 216mm; margin: 1rem auto 0; padding: .7rem 1rem; border-radius: 10px; background: #fff3cd; color: #6b4e00; font: 14px system-ui, sans-serif; }
        @media print {
            body { background: #fff; }
            .bar, .aviso { display: none; }
            .page { width: auto; min-height: calc(279mm - 24mm); margin: 0; padding: 0; box-shadow: none; page-break-after: always; break-after: page; }
            .page:last-child { page-break-after: auto; break-after: auto; }
        }
        @media screen and (max-width: 860px) { .page { width: auto; min-height: 0; margin: .5rem; padding: 1rem; } .lp-cols-2, .lp-cols-3 { grid-template-columns: minmax(0, 1fr); } }
    </style>
</head>
<body>
    <div class="bar">
        <button type="button" class="main" onclick="window.print()"><i class="bi bi-printer"></i>Imprimir</button>
        <button type="button" onclick="history.length > 1 ? history.back() : window.close()"><i class="bi bi-arrow-left"></i>Volver</button>
    </div>
    <?php if ($borrador): ?><div class="aviso"><b>Vista previa.</b> Estos resultados todavía no se han finalizado.</div><?php endif; ?>
    <?php if ($sin): ?><div class="aviso">Sin plantilla (no se imprimen): <?php echo $e(implode(', ', $sin)); ?>.</div><?php endif; ?>

    <?php if (!$hojas): ?>
        <div class="page"><p>No hay resultados para imprimir.</p></div>
    <?php endif; ?>

    <?php foreach ($hojas as $hoja => $items): ?>
        <article class="page">
            <?php if ($borrador): ?><div class="marca">BORRADOR</div><?php endif; ?>
            <header class="head">
                <img src="../../assets/img/logo_cmrm.svg" alt="">
                <h1><?php echo $e(LAB_TITULO_IMPRESION); ?></h1>
                <span class="spacer"></span>
            </header>
            <div class="pat">
                <dl>
                    <dt>PACIENTE:</dt><dd><?php echo $e($paciente); ?></dd>
                    <dt>MÉDICO(A):</dt><dd><?php echo $e($medico); ?></dd>
                    <?php if ($edad): ?><dt>EDAD:</dt><dd><?php echo $e($edad); ?></dd><?php endif; ?>
                    <?php if ($o['genero']): ?><dt>SEXO:</dt><dd><?php echo $e($o['genero']); ?></dd><?php endif; ?>
                </dl>
                <table class="nobox"><tr><td>NO.</td><td><?php echo $e($o['numero_orden']); ?></td></tr><tr><td>FECHA:</td><td><?php echo date('d/m/Y', strtotime($fecha)); ?></td></tr></table>
            </div>
            <h2 class="hoja"><?php echo $e($hoja); ?></h2>
            <?php echo lab_render_print($items); ?>
            <footer class="foot">
                <div><?php echo $fecha_res ? 'Resultado emitido el ' . date('d/m/Y H:i', strtotime($fecha_res)) : 'Orden del ' . date('d/m/Y H:i', strtotime($o['fecha_orden'])); ?><br>↑ ↓ Valor fuera del rango de referencia.</div>
                <div class="firma"><?php echo $e($responsable ?: ''); ?><br>Responsable de laboratorio</div>
            </footer>
        </article>
    <?php endforeach; ?>
</body>
</html>
