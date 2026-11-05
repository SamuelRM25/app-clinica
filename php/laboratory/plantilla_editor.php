<?php
/**
 * laboratory/plantilla_editor.php — Crear o editar una plantilla de resultados.
 */
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login.php'); exit; }
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';

check_module_access('laboratory');
verify_session();
if (!in_array($_SESSION['tipoUsuario'] ?? '', ['admin', 'lab'], true)) { header('Location: index.php'); exit; }
$id_hospital = hospital_id();
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$conn = (new Database())->getConnection();
if (!lab_tables_ready($conn)) { header('Location: plantillas.php'); exit; }

$id = (int) ($_GET['id'] ?? 0);
$tpl = ['id_plantilla' => 0, 'nombre' => '', 'diseno' => lab_tpl_normalize(['hoja' => '', 'secciones' => [['tipo' => 'tabla', 'filas' => []]]])];
if ($id) {
    $st = $conn->prepare("SELECT id_plantilla, nombre, diseno FROM lab_plantillas WHERE id_plantilla = ? AND id_hospital = ?");
    $st->execute([$id, $id_hospital]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) { header('Location: plantillas.php'); exit; }
    $tpl = ['id_plantilla' => (int) $row['id_plantilla'], 'nombre' => $row['nombre'], 'diseno' => lab_tpl_normalize($row['diseno'])];
}
$st = $conn->prepare("SELECT DISTINCT hoja FROM lab_plantillas WHERE id_hospital = ? AND hoja <> '' ORDER BY hoja");
$st->execute([$id_hospital]);
$hojas = $st->fetchAll(PDO::FETCH_COLUMN);
$st = $conn->prepare("SELECT cp.id_prueba, cp.nombre_prueba, cp.categoria, cp.id_plantilla, lp.nombre AS otra
                      FROM catalogo_pruebas cp LEFT JOIN lab_plantillas lp ON lp.id_plantilla = cp.id_plantilla
                      WHERE cp.id_hospital = ? ORDER BY (cp.id_plantilla = ?) DESC, cp.nombre_prueba");
$st->execute([$id_hospital, $id]);
$pruebas = $st->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $e(csrf_token()); ?>">
    <title><?php echo $id ? 'Editar ' . $e($tpl['nombre']) : 'Nueva plantilla'; ?> · CMRM</title>
    <link rel="icon" type="image/png" href="../../assets/img/favicon-cmrm.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=optional" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <?php include '../../includes/theme_head.php'; ?>
    <link rel="stylesheet" href="../../assets/css/processing-overlay.css">
    <link rel="stylesheet" href="../../assets/css/almacen.css?v=2">
    <link rel="stylesheet" href="../../assets/css/lab_resultados.css?v=1">
    <style>
        .pe-paper h4 { text-align: center; font-size: 14px; margin: 4px 0 10px; letter-spacing: .05em; color: #111; }
        .pe-paper .pv-head { text-align: center; font-weight: 700; font-size: 14px; border-bottom: 2px solid #283251; padding-bottom: 6px; margin-bottom: 8px; color: #111; }
        .pe-paper .pv-pat { font-size: 11px; color: #333; margin-bottom: 6px; }
        .pe-paper .pv-grid { display: grid; gap: 8px 10px; margin-bottom: 8px; }
        .pe-paper .pv-full { grid-column: 1 / -1; }
        .pe-paper .pv-t { width: 100%; border-collapse: collapse; }
        .pe-paper .pv-t th, .pe-paper .pv-t td { border: 1px solid #444; padding: 2px 5px; vertical-align: top; color: #111; }
        .pe-paper .pv-t th { background: #f1f1ef; font-size: 10px; text-transform: uppercase; }
        .pe-paper .pv-st { text-align: center; font-weight: 700; font-size: 11px; margin: 0 0 3px; color: #111; }
        .pe-paper .pv-opt { color: #888; font-style: italic; font-size: 10px; }
        .pe-paper .pv-note { font-size: 10px; color: #555; margin: 0; }
    </style>
</head>
<body>
<div class="marble-effect"></div>
<div class="dashboard-container">
    <header class="dashboard-header">
        <div class="header-content">
            <div class="brand-container"><img src="../../assets/img/logo_cmrm.svg" alt="Centro Médico Dr. Roberto Morales" class="brand-logo"></div>
            <div class="header-controls">
                <div class="theme-toggle"><button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema claro/oscuro"><i class="bi bi-sun theme-icon sun-icon"></i><i class="bi bi-moon theme-icon moon-icon"></i></button></div>
                <a href="plantillas.php" class="back-btn"><i class="bi bi-arrow-left"></i><span>Plantillas</span></a>
            </div>
        </div>
    </header>

    <main class="main-content al-page">
        <section class="al-head">
            <div>
                <span class="al-eyebrow"><i class="bi bi-file-earmark-text"></i> Plantilla de resultados</span>
                <h1><?php echo $id ? $e($tpl['nombre']) : 'Nueva plantilla'; ?></h1>
                <p>Los cambios aplican a resultados nuevos. Los que ya se registraron conservan su plantilla.</p>
            </div>
            <div class="al-actions">
                <button class="al-btn al-btn--gold" type="button" id="btnSave"><i class="bi bi-check2"></i>Guardar plantilla</button>
            </div>
        </section>

        <div class="pe-layout">
            <div>
                <section class="pe-panel">
                    <h3>Datos generales</h3>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="tNombre">Nombre *</label><input class="form-control" id="tNombre" maxlength="150" placeholder="Ej.: Perfil lipídico"></div>
                        <div class="col-md-6"><label class="form-label" for="tHoja">Hoja impresa</label><input class="form-control" id="tHoja" maxlength="120" list="hojaList" placeholder="Ej.: BIOQUÍMICA">
                            <datalist id="hojaList"><?php foreach ($hojas as $h): ?><option value="<?php echo $e($h); ?>"><?php endforeach; ?></datalist>
                            <small class="form-text">Las pruebas con la misma hoja se imprimen juntas.</small></div>
                        <div class="col-md-6"><label class="form-label" for="tCols">Secciones al imprimir</label>
                            <select class="form-select" id="tCols"><option value="1">Una debajo de otra</option><option value="2">En 2 columnas</option><option value="3">En 3 columnas</option></select></div>
                        <div class="col-md-6 d-flex align-items-end"><label class="d-flex gap-2 align-items-start"><input type="checkbox" id="tOcultar" class="mt-1">
                            <span>Al imprimir, ocultar exámenes sin resultado<br><small class="al-muted">Útil en hojas compartidas como Bioquímica.</small></span></label></div>
                    </div>
                </section>

                <section class="pe-panel">
                    <h3>Exámenes y secciones</h3>
                    <div id="secs"></div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="pe-add" data-add="tabla"><i class="bi bi-table"></i>Sección de exámenes</button>
                        <button type="button" class="pe-add" data-add="matriz"><i class="bi bi-grid-3x3"></i>Cuadrícula (ej. parásitos, títulos)</button>
                        <button type="button" class="pe-add" data-add="referencia"><i class="bi bi-list-columns"></i>Tabla de referencia</button>
                        <button type="button" class="pe-add" data-add="nota"><i class="bi bi-chat-left-text"></i>Nota</button>
                    </div>
                </section>

                <section class="pe-panel">
                    <h3>Pruebas del catálogo que usan esta plantilla</h3>
                    <input class="form-control mb-2" id="tBuscar" placeholder="Buscar prueba" aria-label="Buscar prueba">
                    <div class="pe-tests" id="tPruebas">
                        <?php foreach ($pruebas as $p): $mia = $id && (int) $p['id_plantilla'] === $id; ?>
                            <label data-n="<?php echo $e(mb_strtolower($p['nombre_prueba'])); ?>">
                                <input type="checkbox" value="<?php echo (int) $p['id_prueba']; ?>" <?php echo $mia ? 'checked' : ''; ?>>
                                <?php echo $e($p['nombre_prueba']); ?>
                                <small><?php echo !$mia && $p['otra'] ? 'Usa: ' . $e($p['otra']) : $e($p['categoria']); ?></small>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>

            <aside class="pe-preview" aria-label="Vista previa">
                <div class="al-muted small mb-2"><i class="bi bi-eye"></i> Vista previa de la hoja impresa</div>
                <div class="pe-paper" id="preview"></div>
            </aside>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../../assets/js/processing-overlay.js"></script>
<script>
(function () {
    'use strict';
    var ID = <?php echo $id; ?>;
    var T = <?php echo json_encode($tpl['diseno'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
    var NOMBRE = <?php echo json_encode($tpl['nombre'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    var TIPOS = { tabla: 'Exámenes', matriz: 'Cuadrícula', referencia: 'Tabla de referencia', nota: 'Nota' };
    var PRESETS = [['POSITIVO / NEGATIVO', ['NEGATIVO', 'POSITIVO']], ['REACTIVO', ['NO REACTIVO', 'REACTIVO']], ['Cruces', ['NEGATIVO (-)', 'ESCASO (+)', 'MODERADO (++)', 'ABUNDANTE (+++)']]];
    var $ = function (s, r) { return (r || document).querySelector(s); };
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    var uid = 0; function nid() { return 'n' + Date.now().toString(36) + (uid++); }
    var dirty = false;

    $('#tNombre').value = NOMBRE;
    $('#tHoja').value = T.hoja || '';
    $('#tCols').value = String(T.columnas || 1);
    $('#tOcultar').checked = !!T.ocultar_vacios;

    function nuevaSec(tipo) {
        if (tipo === 'tabla') return { tipo: 'tabla', titulo: '', completa: false, opciones: [], filas: [{ id: nid(), nombre: '', unidad: '', ref: '', opciones: [], min: null, max: null }] };
        if (tipo === 'matriz') return { tipo: 'matriz', titulo: '', completa: true, columnas: ['Columna 1', 'Columna 2'], opciones: [], filas: [{ id: nid(), nombre: '' }] };
        if (tipo === 'referencia') return { tipo: 'referencia', titulo: '', completa: false, columnas: ['Columna 1', 'Columna 2'], filas: [['', '']] };
        return { tipo: 'nota', titulo: '', completa: true, texto: '' };
    }

    function draw() {
        var box = $('#secs');
        box.innerHTML = T.secciones.map(function (s, i) {
            var head = '<div class="pe-sec__head"><span class="al-tag">' + TIPOS[s.tipo] + '</span>' +
                '<input class="form-control form-control-sm" data-s="' + i + '" data-k="titulo" value="' + esc(s.titulo) + '" placeholder="Título de la sección (opcional)">' +
                '<label class="small d-flex gap-1 align-items-center"><input type="checkbox" data-s="' + i + '" data-k="completa"' + (s.completa ? ' checked' : '') + '>Ancho completo</label>' +
                '<button type="button" class="pe-mini" data-move="' + i + '" data-d="-1" title="Subir" aria-label="Subir sección"><i class="bi bi-arrow-up"></i></button>' +
                '<button type="button" class="pe-mini" data-move="' + i + '" data-d="1" title="Bajar" aria-label="Bajar sección"><i class="bi bi-arrow-down"></i></button>' +
                '<button type="button" class="pe-mini pe-mini--bad" data-delsec="' + i + '" title="Quitar sección" aria-label="Quitar sección"><i class="bi bi-trash"></i></button></div>';
            var body = '';
            if (s.tipo === 'tabla') {
                body = '<table class="pe-rows"><thead><tr><th style="width:26%">Examen</th><th style="width:12%">Unidad</th><th style="width:22%">Valor normal</th><th style="width:9%">Mín</th><th style="width:9%">Máx</th><th>Opciones rápidas</th><th></th></tr></thead><tbody>' +
                    s.filas.map(function (f, j) {
                        return '<tr><td><input class="form-control" data-s="' + i + '" data-f="' + j + '" data-k="nombre" value="' + esc(f.nombre) + '" placeholder="Nombre del examen"></td>' +
                            '<td><input class="form-control" data-s="' + i + '" data-f="' + j + '" data-k="unidad" value="' + esc(f.unidad) + '" placeholder="mg/dl"></td>' +
                            '<td><textarea class="form-control" rows="1" data-s="' + i + '" data-f="' + j + '" data-k="ref" placeholder="65-110 mg/dl">' + esc(f.ref) + '</textarea></td>' +
                            '<td><input class="form-control" inputmode="decimal" data-s="' + i + '" data-f="' + j + '" data-k="min" value="' + (f.min == null ? '' : f.min) + '" title="Marca en rojo si es menor"></td>' +
                            '<td><input class="form-control" inputmode="decimal" data-s="' + i + '" data-f="' + j + '" data-k="max" value="' + (f.max == null ? '' : f.max) + '" title="Marca en rojo si es mayor"></td>' +
                            '<td><input class="form-control" data-s="' + i + '" data-f="' + j + '" data-k="opciones" value="' + esc((f.opciones || []).join(', ')) + '" placeholder="NEGATIVO, POSITIVO"></td>' +
                            '<td class="text-nowrap"><button type="button" class="pe-mini" data-fmove="' + i + ':' + j + '" data-d="-1" aria-label="Subir"><i class="bi bi-chevron-up"></i></button>' +
                            '<button type="button" class="pe-mini pe-mini--bad" data-fdel="' + i + ':' + j + '" aria-label="Quitar examen"><i class="bi bi-x-lg"></i></button></td></tr>';
                    }).join('') + '</tbody></table>' +
                    '<div class="d-flex flex-wrap gap-2 align-items-center"><button type="button" class="pe-add" data-addrow="' + i + '"><i class="bi bi-plus"></i>Agregar examen</button>' +
                    '<span class="al-muted small ms-2">Poner a todos:</span><div class="pe-chips">' + PRESETS.map(function (p, k) { return '<button type="button" data-preset="' + i + ':' + k + '">' + p[0] + '</button>'; }).join('') + '</div></div>';
            } else if (s.tipo === 'matriz') {
                body = '<div class="row g-2 mb-2"><div class="col-md-7"><label class="small fw-semibold">Columnas (separadas por coma)</label><input class="form-control form-control-sm" data-s="' + i + '" data-k="columnas" value="' + esc(s.columnas.join(', ')) + '"></div>' +
                    '<div class="col-md-5"><label class="small fw-semibold">Opciones rápidas</label><input class="form-control form-control-sm" data-s="' + i + '" data-k="opciones" value="' + esc((s.opciones || []).join(', ')) + '" placeholder="+, ++, +++"></div></div>' +
                    '<table class="pe-rows"><tbody>' + s.filas.map(function (f, j) {
                        return '<tr><td><input class="form-control" data-s="' + i + '" data-f="' + j + '" data-k="nombre" value="' + esc(f.nombre) + '" placeholder="Fila"></td>' +
                            '<td class="text-nowrap" style="width:70px"><button type="button" class="pe-mini" data-fmove="' + i + ':' + j + '" data-d="-1" aria-label="Subir"><i class="bi bi-chevron-up"></i></button>' +
                            '<button type="button" class="pe-mini pe-mini--bad" data-fdel="' + i + ':' + j + '" aria-label="Quitar fila"><i class="bi bi-x-lg"></i></button></td></tr>';
                    }).join('') + '</tbody></table><button type="button" class="pe-add" data-addrow="' + i + '"><i class="bi bi-plus"></i>Agregar fila</button>';
            } else if (s.tipo === 'referencia') {
                body = '<label class="small fw-semibold">Encabezados (separados por coma)</label><input class="form-control form-control-sm mb-2" data-s="' + i + '" data-k="columnas" value="' + esc(s.columnas.join(', ')) + '">' +
                    '<label class="small fw-semibold">Filas: una por línea, columnas separadas por |</label><textarea class="form-control form-control-sm" rows="5" data-s="' + i + '" data-k="filas">' + esc(s.filas.map(function (f) { return f.join(' | '); }).join('\n')) + '</textarea>';
            } else {
                body = '<textarea class="form-control" rows="3" data-s="' + i + '" data-k="texto" placeholder="Texto que aparece en la hoja">' + esc(s.texto) + '</textarea>';
            }
            return '<div class="pe-sec">' + head + '<div class="pe-sec__body">' + body + '</div></div>';
        }).join('') || '<p class="al-muted">Agregue una sección para empezar.</p>';
        preview();
    }

    function num(v) { v = String(v).replace(',', '.').trim(); return v === '' || isNaN(parseFloat(v)) ? null : parseFloat(v); }
    function list(v) { return String(v).split(',').map(function (x) { return x.trim(); }).filter(Boolean); }

    $('#secs').addEventListener('input', function (e) {
        var t = e.target, i = t.getAttribute('data-s'), k = t.getAttribute('data-k'), j = t.getAttribute('data-f');
        if (i == null || !k) return;
        var s = T.secciones[i];
        dirty = true;
        if (j != null) {
            var f = s.filas[j];
            if (k === 'opciones') f.opciones = list(t.value);
            else if (k === 'min' || k === 'max') f[k] = num(t.value);
            else f[k] = t.value;
        } else if (k === 'completa') s.completa = t.checked;
        else if (k === 'columnas' || k === 'opciones') s[k] = list(t.value);
        else if (k === 'filas') s.filas = t.value.split('\n').filter(function (l) { return l.trim(); }).map(function (l) { return l.split('|').map(function (c) { return c.trim(); }); });
        else s[k] = t.value;
        preview();
    });
    $('#secs').addEventListener('change', function (e) { if (e.target.type === 'checkbox') { var ev = new Event('input', { bubbles: true }); e.target.dispatchEvent(ev); } });

    document.addEventListener('click', function (e) {
        var b;
        if ((b = e.target.closest('[data-add]'))) { T.secciones.push(nuevaSec(b.getAttribute('data-add'))); dirty = true; draw(); return; }
        if ((b = e.target.closest('[data-addrow]'))) {
            var s = T.secciones[b.getAttribute('data-addrow')];
            s.filas.push(s.tipo === 'tabla' ? { id: nid(), nombre: '', unidad: '', ref: '', opciones: [], min: null, max: null } : { id: nid(), nombre: '' });
            dirty = true; draw();
            var ins = document.querySelectorAll('[data-s="' + b.getAttribute('data-addrow') + '"][data-k="nombre"]'); if (ins.length) ins[ins.length - 1].focus();
            return;
        }
        if ((b = e.target.closest('[data-delsec]'))) { T.secciones.splice(+b.getAttribute('data-delsec'), 1); dirty = true; draw(); return; }
        if ((b = e.target.closest('[data-move]'))) {
            var i = +b.getAttribute('data-move'), d = +b.getAttribute('data-d'), n = i + d;
            if (n < 0 || n >= T.secciones.length) return;
            var tmp = T.secciones[i]; T.secciones[i] = T.secciones[n]; T.secciones[n] = tmp; dirty = true; draw(); return;
        }
        if ((b = e.target.closest('[data-fdel]'))) { var p = b.getAttribute('data-fdel').split(':'); T.secciones[p[0]].filas.splice(+p[1], 1); dirty = true; draw(); return; }
        if ((b = e.target.closest('[data-fmove]'))) {
            var q = b.getAttribute('data-fmove').split(':'), fl = T.secciones[q[0]].filas, a = +q[1];
            if (a < 1) return; var t2 = fl[a]; fl[a] = fl[a - 1]; fl[a - 1] = t2; dirty = true; draw(); return;
        }
        if ((b = e.target.closest('[data-preset]'))) {
            var r = b.getAttribute('data-preset').split(':');
            T.secciones[r[0]].filas.forEach(function (f) { f.opciones = PRESETS[r[1]][1].slice(); }); dirty = true; draw(); return;
        }
    });
    ['tNombre', 'tHoja', 'tCols', 'tOcultar'].forEach(function (id) { $('#' + id).addEventListener('input', function () { dirty = true; preview(); }); $('#' + id).addEventListener('change', preview); });

    function preview() {
        var cols = +$('#tCols').value || 1;
        var h = '<div class="pv-head">' + esc(<?php echo json_encode(LAB_TITULO_IMPRESION, JSON_UNESCAPED_UNICODE); ?>) + '</div>' +
            '<div class="pv-pat"><b>PACIENTE:</b> NOMBRE DEL PACIENTE &nbsp; <b>MÉDICO(A):</b> DR(A). ——</div>' +
            '<h4>' + esc($('#tHoja').value || $('#tNombre').value || 'HOJA') + '</h4>' +
            '<div class="pv-grid" style="grid-template-columns:repeat(' + cols + ',minmax(0,1fr))">';
        T.secciones.forEach(function (s) {
            h += '<div class="' + (s.completa || s.tipo === 'matriz' ? 'pv-full' : '') + '">' + (s.titulo ? '<p class="pv-st">' + esc(s.titulo) + '</p>' : '');
            if (s.tipo === 'tabla') {
                var u = s.filas.some(function (f) { return f.unidad; }), rf = s.filas.some(function (f) { return f.ref; });
                h += '<table class="pv-t"><thead><tr><th>Examen</th><th>Resultado</th>' + (u ? '<th>Unidad</th>' : '') + (rf ? '<th>Valor normal</th>' : '') + '</tr></thead><tbody>' +
                    s.filas.map(function (f) {
                        return '<tr><td>' + esc(f.nombre || '…') + '</td><td>' + ((f.opciones || []).length ? '<span class="pv-opt">' + esc(f.opciones[0]) + '</span>' : '') + '</td>' +
                            (u ? '<td>' + esc(f.unidad) + '</td>' : '') + (rf ? '<td>' + esc(f.ref).replace(/\n/g, '<br>') + '</td>' : '') + '</tr>';
                    }).join('') + '</tbody></table>';
            } else if (s.tipo === 'matriz') {
                h += '<table class="pv-t"><thead><tr><th></th>' + s.columnas.map(function (c) { return '<th>' + esc(c) + '</th>'; }).join('') + '</tr></thead><tbody>' +
                    s.filas.map(function (f) { return '<tr><td>' + esc(f.nombre || '…') + '</td>' + s.columnas.map(function () { return '<td></td>'; }).join('') + '</tr>'; }).join('') + '</tbody></table>';
            } else if (s.tipo === 'referencia') {
                h += '<table class="pv-t"><thead><tr>' + s.columnas.map(function (c) { return '<th>' + esc(c) + '</th>'; }).join('') + '</tr></thead><tbody>' +
                    s.filas.map(function (f) { return '<tr>' + f.map(function (c) { return '<td style="text-align:center">' + esc(c) + '</td>'; }).join('') + '</tr>'; }).join('') + '</tbody></table>';
            } else {
                h += '<p class="pv-note">' + esc(s.texto).replace(/\n/g, '<br>') + '</p>';
            }
            h += '</div>';
        });
        $('#preview').innerHTML = h + '</div>';
    }

    $('#tBuscar').addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        document.querySelectorAll('#tPruebas label').forEach(function (l) { l.hidden = q && l.dataset.n.indexOf(q) < 0; });
    });
    document.getElementById('tPruebas').addEventListener('change', function () { dirty = true; });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

    $('#btnSave').addEventListener('click', async function () {
        var nombre = $('#tNombre').value.trim();
        if (!nombre) { $('#tNombre').focus(); return Swal.fire({ icon: 'info', title: 'Falta el nombre', text: 'Escriba el nombre de la plantilla.', confirmButtonColor: '#283251' }); }
        var data = { action: 'plantilla_save', id_plantilla: ID, nombre: nombre,
            diseno: { hoja: $('#tHoja').value.trim(), columnas: +$('#tCols').value, ocultar_vacios: $('#tOcultar').checked, secciones: T.secciones },
            pruebas: Array.prototype.map.call(document.querySelectorAll('#tPruebas input:checked'), function (c) { return c.value; }) };
        try {
            var r = await fetch('api/resultados.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(data) });
            var j = await r.json();
            if (!j.success) return Swal.fire({ icon: 'error', title: 'No se pudo', text: j.message, confirmButtonColor: '#283251' });
            dirty = false;
            if (window.showProcessing) window.showProcessing('Plantilla guardada', 'Volviendo a la lista…');
            location.href = 'plantillas.php';
        } catch (e) { Swal.fire({ icon: 'error', title: 'No se pudo', text: 'No hubo respuesta del servidor.', confirmButtonColor: '#283251' }); }
    });

    if (!T.secciones.length) T.secciones.push(nuevaSec('tabla'));
    T.secciones.forEach(function (s) {
        if (s.tipo === 'tabla' && !s.filas.length) s.filas.push({ id: nid(), nombre: '', unidad: '', ref: '', opciones: [], min: null, max: null });
        if (s.tipo === 'matriz' && !s.filas.length) s.filas.push({ id: nid(), nombre: '' });
    });
    draw();
})();
</script>
</body>
</html>
