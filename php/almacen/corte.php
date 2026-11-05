<?php
/**
 * almacen/corte.php — Corte de inventario (conteo físico) del almacén de un área.
 * La existencia del sistema se toma al abrir la hoja; al guardar se ajusta solo la
 * diferencia contada, así lo que entre o salga mientras se cuenta no se pierde.
 */
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login.php'); exit; }
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
verify_session();
date_default_timezone_set('America/Guatemala');

$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);
$areas = area_allowed_for_user();
if (!$areas) { header('Location: ../dashboard/index.php?acceso_denegado=1&modulo=almacen'); exit; }
$area = in_array($_GET['area'] ?? '', $areas, true) ? $_GET['area'] : $areas[0];
$A = AREAS_ALMACEN[$area];
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$conn = (new Database())->getConnection();
if (!area_tables_ready($conn)) { header('Location: index.php?area=' . urlencode($area)); exit; }

$st = $conn->prepare("SELECT id_item, codigo, nombre, categoria, unidad, presentacion, ubicacion, stock, costo_unitario
                      FROM area_items WHERE id_hospital = ? AND area = ? AND activo = 1
                      ORDER BY COALESCE(categoria, 'zzz'), nombre");
$st->execute([$id_hospital, $area]);
$items = $st->fetchAll(PDO::FETCH_ASSOC);
$grupos = [];
foreach ($items as $it) $grupos[$it['categoria'] ?: 'Sin categoría'][] = $it;
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $e(csrf_token()); ?>">
    <title>Corte de inventario · <?php echo $e($A['label']); ?></title>
    <link rel="icon" type="image/png" href="../../assets/img/favicon-cmrm.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=optional" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <?php include '../../includes/theme_head.php'; ?>
    <link rel="stylesheet" href="../../assets/css/processing-overlay.css">
    <link rel="stylesheet" href="../../assets/css/almacen.css?v=1">
    <style>
        .print-only { display: none; }
        @media print {
            .print-only { display: block; }
            .al-count { border: 0; border-bottom: 1px solid #999; border-radius: 0; background: none !important; }
            .al-count::placeholder { color: transparent; }
            .no-print-col { display: none; }
            h1.print-only { font-size: 18px; margin: 0 0 4px; }
        }
    </style>
</head>
<body>
<div class="marble-effect"></div>
<div class="dashboard-container">
    <header class="dashboard-header">
        <div class="header-content">
            <div class="brand-container"><img src="../../assets/img/logo_cmrm.svg" alt="Centro Médico Dr. Roberto Morales" class="brand-logo"></div>
            <div class="header-controls">
                <a href="index.php?area=<?php echo $e($area); ?>#cortes" class="back-btn"><i class="bi bi-arrow-left"></i><span>Almacén</span></a>
            </div>
        </div>
    </header>

    <main class="main-content al-page">
        <h1 class="print-only">Hoja de conteo · Almacén de <?php echo $e($A['label']); ?></h1>
        <p class="print-only">Fecha: ____________ &nbsp; Contó: ______________________ &nbsp; Revisó: ______________________</p>

        <section class="al-head">
            <div>
                <span class="al-eyebrow"><i class="bi bi-clipboard-check"></i> Corte de inventario</span>
                <h1><?php echo $e($A['label']); ?></h1>
                <p>Escriba lo que cuenta físicamente. Lo que deje en blanco no se toca.</p>
            </div>
            <div class="al-actions">
                <button type="button" class="al-btn al-btn--line" onclick="window.print()"><i class="bi bi-printer"></i>Imprimir hoja de conteo</button>
            </div>
        </section>

        <?php if (!$items): ?>
            <div class="al-panel al-empty"><i class="bi bi-box-seam"></i><b>No hay artículos activos para contar</b>
                <p><a href="index.php?area=<?php echo $e($area); ?>">Volver al almacén</a></p></div>
        <?php else: ?>
            <div class="al-alert" id="draftNote" hidden style="background:rgba(var(--color-accent-rgb),.1);border-color:rgba(var(--color-accent-rgb),.4)">
                <i class="bi bi-arrow-counterclockwise" style="color:var(--color-accent)"></i>
                <div>Se recuperó un conteo que no se había guardado. <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="btnDiscard">Empezar de cero</button></div>
            </div>

            <div class="al-toolbar">
                <label class="al-find"><i class="bi bi-search"></i><input type="search" id="cSearch" placeholder="Buscar artículo" aria-label="Buscar artículo"></label>
                <div class="al-chips" role="group" aria-label="Mostrar">
                    <button type="button" class="is-on" data-show="">Todos</button>
                    <button type="button" data-show="pend">Sin contar</button>
                    <button type="button" data-show="dif">Con diferencia</button>
                </div>
                <button type="button" class="al-btn al-btn--line al-btn--sm ms-auto" id="btnFill" title="Para lo que revisó y está igual que el sistema"><i class="bi bi-check2-all"></i>Lo que falta está igual que el sistema</button>
            </div>

            <div class="al-table-wrap">
                <table class="al-table al-table--dense" id="cTable">
                    <thead><tr>
                        <th>Artículo</th><th>Ubicación</th><th class="num">Sistema</th><th class="num">Conteo físico</th>
                        <th class="num no-print-col">Diferencia</th><th class="num no-print-col">Valor dif.</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($grupos as $cat => $lista): ?>
                        <tr class="al-cat-row"><td colspan="6"><?php echo $e($cat); ?></td></tr>
                        <?php foreach ($lista as $it): ?>
                            <tr data-id="<?php echo (int) $it['id_item']; ?>" data-sis="<?php echo (float) $it['stock']; ?>" data-costo="<?php echo (float) $it['costo_unitario']; ?>"
                                data-find="<?php echo $e(mb_strtolower($it['nombre'] . ' ' . $it['codigo'])); ?>">
                                <td><div class="al-name"><?php echo $e($it['nombre']); ?></div><div class="al-sub"><?php echo $e(implode(' · ', array_filter([$it['codigo'], $it['presentacion']]))); ?></div></td>
                                <td class="al-muted"><?php echo $e($it['ubicacion'] ?: '—'); ?></td>
                                <td class="num"><span data-sis-txt><?php echo area_qty($it['stock']); ?></span> <span class="al-unit"><?php echo $e($it['unidad']); ?></span></td>
                                <td class="num"><input type="number" class="form-control form-control-sm al-count d-inline-block" min="0" step="any" inputmode="decimal" placeholder="—" aria-label="Conteo de <?php echo $e($it['nombre']); ?>"></td>
                                <td class="num no-print-col" data-dif>—</td>
                                <td class="num no-print-col" data-val>—</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="al-corte-bar">
                <div class="d-flex flex-wrap align-items-center gap-4">
                    <div><div class="al-muted small">Contados</div><b id="sCount">0 de <?php echo count($items); ?></b><div class="al-progress mt-1"><span id="sBar" style="width:0"></span></div></div>
                    <div><div class="al-muted small">Con diferencia</div><b id="sDif">0</b></div>
                    <div><div class="al-muted small">Valor de la diferencia</div><b id="sVal">Q0.00</b></div>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <input class="form-control" id="cNotas" maxlength="500" placeholder="Notas del corte (opcional)" style="min-width:220px">
                    <button type="button" class="al-btn al-btn--gold" id="btnSave"><i class="bi bi-check2-circle"></i>Guardar corte</button>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../../assets/js/processing-overlay.js"></script>
<script src="../../assets/js/almacen.js?v=1"></script>
<script>
(function () {
    'use strict';
    var table = document.getElementById('cTable');
    if (!table) return;
    var AREA = <?php echo json_encode($area); ?>;
    var KEY = 'corte-draft-<?php echo $id_hospital; ?>-' + AREA;
    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-id]'));
    var show = '';
    var money = AL.money, qty = AL.qty;

    function load() { try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { return null; } }
    function save() {
        var d = { ts: Date.now(), items: {} };
        rows.forEach(function (tr) { var v = tr.querySelector('input').value; if (v !== '') d.items[tr.dataset.id] = { f: v, s: tr.dataset.sis }; });
        try { Object.keys(d.items).length ? localStorage.setItem(KEY, JSON.stringify(d)) : localStorage.removeItem(KEY); } catch (e) {}
    }

    function calc(tr) {
        var v = tr.querySelector('input').value, dif = tr.querySelector('[data-dif]'), val = tr.querySelector('[data-val]'), inp = tr.querySelector('input');
        if (v === '') { dif.textContent = '—'; val.textContent = '—'; dif.className = val.className = 'num no-print-col'; inp.classList.remove('is-diff'); tr.dataset.st = 'pend'; return; }
        var d = Math.round((parseFloat(v) - parseFloat(tr.dataset.sis)) * 1000) / 1000;
        var cls = d < 0 ? ' al-bad' : (d > 0 ? ' al-ok' : '');
        dif.textContent = d === 0 ? 'Igual' : (d > 0 ? '+' : '') + qty(d);
        val.textContent = d === 0 ? '—' : money(d * parseFloat(tr.dataset.costo));
        dif.className = val.className = 'num no-print-col' + cls;
        inp.classList.toggle('is-diff', d !== 0);
        tr.dataset.st = d === 0 ? 'ok' : 'dif';
    }
    function resumen() {
        var n = 0, nd = 0, vd = 0;
        rows.forEach(function (tr) {
            if (tr.dataset.st && tr.dataset.st !== 'pend') n++;
            if (tr.dataset.st === 'dif') { nd++; vd += (parseFloat(tr.querySelector('input').value) - parseFloat(tr.dataset.sis)) * parseFloat(tr.dataset.costo); }
        });
        document.getElementById('sCount').textContent = n + ' de ' + rows.length;
        document.getElementById('sBar').style.width = (rows.length ? n / rows.length * 100 : 0) + '%';
        document.getElementById('sDif').textContent = nd;
        var sv = document.getElementById('sVal');
        sv.textContent = money(vd); sv.className = vd < 0 ? 'al-bad' : (vd > 0 ? 'al-ok' : '');
    }
    function filtrar() {
        var q = document.getElementById('cSearch').value.trim().toLowerCase();
        rows.forEach(function (tr) { tr.hidden = !((!q || tr.dataset.find.indexOf(q) >= 0) && (!show || tr.dataset.st === show)); });
        Array.prototype.forEach.call(table.querySelectorAll('.al-cat-row'), function (cr) {
            var n = cr.nextElementSibling, any = false;
            while (n && !n.classList.contains('al-cat-row')) { if (!n.hidden) any = true; n = n.nextElementSibling; }
            cr.hidden = !any;
        });
    }

    // Recuperar borrador
    var draft = load();
    if (draft && draft.items && Object.keys(draft.items).length) {
        rows.forEach(function (tr) {
            var d = draft.items[tr.dataset.id];
            if (d) { tr.querySelector('input').value = d.f; tr.dataset.sis = d.s; tr.querySelector('[data-sis-txt]').textContent = qty(d.s); }
        });
        document.getElementById('draftNote').hidden = false;
    }
    rows.forEach(calc); resumen();

    table.addEventListener('input', function (e) { var tr = e.target.closest('tr[data-id]'); if (!tr) return; calc(tr); resumen(); save(); });
    table.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !e.target.classList.contains('al-count')) return;
        e.preventDefault();
        var vis = rows.filter(function (r) { return !r.hidden; }), i = vis.indexOf(e.target.closest('tr'));
        if (vis[i + 1]) { var n = vis[i + 1].querySelector('input'); n.focus(); n.select(); }
    });
    document.getElementById('cSearch').addEventListener('input', filtrar);
    Array.prototype.forEach.call(document.querySelectorAll('[data-show]'), function (b) {
        b.addEventListener('click', function () {
            show = b.getAttribute('data-show');
            Array.prototype.forEach.call(document.querySelectorAll('[data-show]'), function (x) { x.classList.toggle('is-on', x === b); });
            filtrar();
        });
    });
    document.getElementById('btnDiscard').addEventListener('click', function () { try { localStorage.removeItem(KEY); } catch (e) {} location.reload(); });
    document.getElementById('btnFill').addEventListener('click', async function () {
        var pend = rows.filter(function (tr) { return tr.querySelector('input').value === ''; });
        if (!pend.length) return;
        var c = await Swal.fire({ icon: 'question', title: 'Marcar ' + pend.length + ' artículos como iguales al sistema?',
            text: 'Úselo solo si ya los revisó y la cantidad es la misma.', showCancelButton: true, confirmButtonText: 'Sí, están iguales', cancelButtonText: 'Cancelar', confirmButtonColor: '#283251' });
        if (!c.isConfirmed) return;
        pend.forEach(function (tr) { tr.querySelector('input').value = qty(tr.dataset.sis < 0 ? 0 : tr.dataset.sis); calc(tr); });
        resumen(); save(); filtrar();
    });

    document.getElementById('btnSave').addEventListener('click', async function () {
        var conteos = rows.filter(function (tr) { return tr.querySelector('input').value !== ''; })
            .map(function (tr) { return { id_item: tr.dataset.id, fisico: tr.querySelector('input').value, sistema: tr.dataset.sis }; });
        if (!conteos.length) return Swal.fire({ icon: 'info', title: 'Todavía no hay conteos', text: 'Escriba al menos un conteo físico.', confirmButtonColor: '#283251' });
        var nd = rows.filter(function (tr) { return tr.dataset.st === 'dif'; }).length;
        var pend = rows.length - conteos.length;
        var c = await Swal.fire({ icon: 'question', title: 'Guardar corte',
            html: 'Se contaron <b>' + conteos.length + '</b> artículos y <b>' + nd + '</b> tienen diferencia.<br>Valor de la diferencia: <b>' + document.getElementById('sVal').textContent + '</b>' +
                (pend ? '<br><br><span class="al-muted">' + pend + ' sin contar se quedan como están.</span>' : ''),
            showCancelButton: true, confirmButtonText: 'Guardar y ajustar', cancelButtonText: 'Seguir contando', confirmButtonColor: '#283251' });
        if (!c.isConfirmed) return;
        var r = await AL.api('save_corte', { area: AREA, conteos: conteos, notas: document.getElementById('cNotas').value });
        if (!r.success) return Swal.fire({ icon: 'error', title: 'No se pudo', text: r.message, confirmButtonColor: '#283251' });
        try { localStorage.removeItem(KEY); } catch (e) {}
        if (window.showProcessing) window.showProcessing('Corte guardado', 'Abriendo el reporte…');
        location.href = 'corte_ver.php?id=' + r.id_corte;
    });
})();
</script>
</body>
</html>
