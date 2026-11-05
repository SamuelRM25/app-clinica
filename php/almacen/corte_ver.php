<?php
/**
 * almacen/corte_ver.php — Reporte imprimible de un corte de inventario.
 */
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login.php'); exit; }
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
verify_session();
date_default_timezone_set('America/Guatemala');

$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$money = fn($n) => ((float) $n < -0.004 ? '-Q' : 'Q') . number_format(abs((float) $n), 2);
$conn = (new Database())->getConnection();

$st = $conn->prepare("SELECT c.*, TRIM(CONCAT(COALESCE(u.nombre,''), ' ', COALESCE(u.apellido,''))) AS usuario
                      FROM area_cortes c LEFT JOIN usuarios u ON u.idUsuario = c.id_usuario
                      WHERE c.id_corte = ? AND c.id_hospital = ?");
$st->execute([(int) ($_GET['id'] ?? 0), $id_hospital]);
$c = $st->fetch(PDO::FETCH_ASSOC);
if (!$c || !in_array($c['area'], area_allowed_for_user(), true)) { header('Location: index.php'); exit; }

$st = $conn->prepare("SELECT * FROM area_corte_items WHERE id_corte = ? ORDER BY (diferencia = 0), nombre");
$st->execute([$c['id_corte']]);
$items = $st->fetchAll(PDO::FETCH_ASSOC);
$faltantes = array_filter($items, fn($i) => $i['diferencia'] < 0);
$sobrantes = array_filter($items, fn($i) => $i['diferencia'] > 0);
$v_falt = array_sum(array_map(fn($i) => $i['diferencia'] * $i['costo_unitario'], $faltantes));
$v_sobr = array_sum(array_map(fn($i) => $i['diferencia'] * $i['costo_unitario'], $sobrantes));
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Corte #<?php echo (int) $c['id_corte']; ?> · <?php echo $e(area_label($c['area'])); ?></title>
    <link rel="icon" type="image/png" href="../../assets/img/favicon-cmrm.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=optional" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        :root { --navy: #283251; --gold: #c9a24f; --ink: #1c2236; --muted: #6b7183; --line: #e7e5df; --ok: #198754; --bad: #c0392b; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, system-ui, sans-serif; color: var(--ink); background: #f3f2ee; }
        .sheet { max-width: 900px; margin: 1.5rem auto; background: #fff; padding: 2rem 2.25rem; border-radius: 14px; box-shadow: 0 10px 40px -20px rgba(20, 27, 48, .35); }
        .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding-bottom: 1rem; border-bottom: 3px solid var(--navy); }
        .top img { height: 54px; }
        .top h1 { margin: 0; font-size: 1.35rem; color: var(--navy); }
        .top p { margin: .2rem 0 0; color: var(--muted); font-size: .9rem; }
        .meta { display: grid; grid-template-columns: repeat(4, 1fr); gap: .75rem; margin: 1.25rem 0; }
        .meta div { padding: .7rem .85rem; border: 1px solid var(--line); border-radius: 10px; }
        .meta span { display: block; font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); }
        .meta b { font-size: 1.1rem; font-variant-numeric: tabular-nums; }
        table { width: 100%; border-collapse: collapse; font-size: .86rem; }
        th { text-align: left; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); border-bottom: 2px solid var(--line); padding: .5rem; }
        td { padding: .5rem; border-bottom: 1px solid var(--line); }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .ok { color: var(--ok); } .bad { color: var(--bad); } .muted { color: var(--muted); }
        .notes { margin-top: 1rem; padding: .75rem 1rem; background: #faf8f2; border-left: 3px solid var(--gold); font-size: .9rem; }
        .firmas { display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; margin-top: 3.5rem; }
        .firmas div { border-top: 1px solid var(--ink); padding-top: .4rem; text-align: center; font-size: .85rem; color: var(--muted); }
        .bar { max-width: 900px; margin: 1rem auto 0; display: flex; justify-content: space-between; gap: .5rem; }
        .bar a, .bar button { display: inline-flex; align-items: center; gap: .4rem; padding: .55rem 1rem; border-radius: 10px; border: 1px solid var(--line); background: #fff; color: var(--ink); font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
        .bar button { background: var(--navy); color: #fff; border-color: var(--navy); }
        @media (max-width: 640px) { .sheet { padding: 1.25rem; margin: .75rem; } .meta { grid-template-columns: 1fr 1fr; } }
        @media print { body { background: #fff; } .bar { display: none; } .sheet { box-shadow: none; margin: 0; max-width: none; padding: 0; } }
    </style>
</head>
<body>
    <div class="bar">
        <a href="index.php?area=<?php echo $e($c['area']); ?>#cortes"><i class="bi bi-arrow-left"></i>Volver al almacén</a>
        <button type="button" onclick="window.print()"><i class="bi bi-printer"></i>Imprimir</button>
    </div>
    <article class="sheet">
        <div class="top">
            <div>
                <h1>Corte de inventario #<?php echo (int) $c['id_corte']; ?></h1>
                <p>Almacén de <?php echo $e(area_label($c['area'])); ?> · <?php echo date('d/m/Y H:i', strtotime($c['fecha'])); ?> · <?php echo $e($c['usuario'] ?: '—'); ?></p>
            </div>
            <img src="../../assets/img/logo_cmrm.svg" alt="Centro Médico Dr. Roberto Morales">
        </div>

        <div class="meta">
            <div><span>Contados</span><b><?php echo (int) $c['items_contados']; ?></b></div>
            <div><span>Con diferencia</span><b><?php echo (int) $c['items_diferencia']; ?></b></div>
            <div><span>Faltante</span><b class="bad"><?php echo $money($v_falt); ?></b></div>
            <div><span>Sobrante</span><b class="ok"><?php echo $money($v_sobr); ?></b></div>
        </div>

        <table>
            <thead><tr><th>Artículo</th><th class="num">Sistema</th><th class="num">Físico</th><th class="num">Diferencia</th><th class="num">Costo</th><th class="num">Valor</th></tr></thead>
            <tbody>
            <?php foreach ($items as $i): $d = (float) $i['diferencia']; ?>
                <tr>
                    <td><?php echo $e($i['nombre']); ?> <span class="muted"><?php echo $e($i['unidad']); ?></span></td>
                    <td class="num"><?php echo area_qty($i['stock_sistema']); ?></td>
                    <td class="num"><?php echo area_qty($i['stock_fisico']); ?></td>
                    <td class="num <?php echo $d < 0 ? 'bad' : ($d > 0 ? 'ok' : 'muted'); ?>"><?php echo $d == 0 ? 'Igual' : ($d > 0 ? '+' : '') . area_qty($d); ?></td>
                    <td class="num muted"><?php echo $money($i['costo_unitario']); ?></td>
                    <td class="num <?php echo $d < 0 ? 'bad' : ($d > 0 ? 'ok' : 'muted'); ?>"><?php echo $d == 0 ? '—' : $money($d * $i['costo_unitario']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><td colspan="5" class="num"><b>Diferencia neta</b></td><td class="num"><b class="<?php echo $c['valor_diferencia'] < 0 ? 'bad' : 'ok'; ?>"><?php echo $money($c['valor_diferencia']); ?></b></td></tr></tfoot>
        </table>

        <?php if ($c['notas']): ?><div class="notes"><b>Notas:</b> <?php echo $e($c['notas']); ?></div><?php endif; ?>

        <div class="firmas"><div>Contó</div><div>Revisó</div></div>
    </article>
</body>
</html>
