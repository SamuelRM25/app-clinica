<?php
/**
 * almacen/index.php — Almacén propio de Laboratorio y Quirófano.
 * Inventario, compras, movimientos (kardex) y cortes de inventario.
 * Laboratorio y Quirófano ven solo su área; el administrador elige el área.
 */
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../auth/login.php'); exit; }

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
verify_session();
date_default_timezone_set('America/Guatemala');

$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);
$is_admin = ($_SESSION['tipoUsuario'] ?? '') === 'admin';
$areas = area_allowed_for_user();
if (!$areas) { header('Location: ../dashboard/index.php?acceso_denegado=1&modulo=almacen'); exit; }

$area = in_array($_GET['area'] ?? '', $areas, true) ? $_GET['area'] : $areas[0];
$A = AREAS_ALMACEN[$area];
$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$q3 = fn($n) => area_qty($n);
$money = fn($n) => ((float) $n < -0.004 ? '-Q' : 'Q') . number_format(abs((float) $n), 2);

$conn = (new Database())->getConnection();
$ready = area_tables_ready($conn) && purchases_have_area($conn);

$items = $compras = $movs = $cortes = [];
$k = ['items' => 0, 'valor' => 0, 'bajo' => 0, 'agotado' => 0, 'vence' => 0, 'negativo' => 0];
$cats = [];
$f_desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$f_hasta = $_GET['hasta'] ?? date('Y-m-d');
$f_tipo = $_GET['tipo'] ?? '';
$f_q = trim($_GET['q'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_desde)) $f_desde = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_hasta)) $f_hasta = date('Y-m-d');

if ($ready) {
    $st = $conn->prepare("SELECT * FROM area_items WHERE id_hospital = ? AND area = ? ORDER BY activo DESC, nombre");
    $st->execute([$id_hospital, $area]);
    $items = $st->fetchAll(PDO::FETCH_ASSOC);
    $hoy = strtotime(date('Y-m-d'));
    foreach ($items as &$it) {
        $s = (float) $it['stock'];
        $it['_estado'] = 'ok';
        $it['_dias'] = $it['fecha_vencimiento'] ? (int) floor((strtotime($it['fecha_vencimiento']) - $hoy) / 86400) : null;
        if (!$it['activo']) { $it['_estado'] = 'inactivo'; continue; }
        $k['items']++;
        $k['valor'] += max(0, $s) * (float) $it['costo_unitario'];
        if ($s < 0) { $it['_estado'] = 'negativo'; $k['negativo']++; $k['agotado']++; }
        elseif ($s == 0) { $it['_estado'] = 'agotado'; $k['agotado']++; }
        elseif ($s <= (float) $it['stock_minimo']) { $it['_estado'] = 'bajo'; $k['bajo']++; }
        if ($it['_dias'] !== null && $it['_dias'] <= 30 && $s > 0) $k['vence']++;
        if ($it['categoria']) $cats[$it['categoria']] = true;
    }
    unset($it);
    $cats = array_keys($cats);
    sort($cats);

    $st = $conn->prepare("SELECT ph.id, ph.purchase_date, ph.provider_name, ph.document_type, ph.document_number, ph.tipo_pago,
                                 ph.total_amount, ph.paid_amount, ph.payment_status,
                                 (SELECT COUNT(*) FROM area_compra_items ci WHERE ci.purchase_header_id = ph.id) AS n_items
                          FROM purchase_headers ph WHERE ph.id_hospital = ? AND ph.area = ?
                          ORDER BY ph.purchase_date DESC, ph.id DESC LIMIT 150");
    $st->execute([$id_hospital, $area]);
    $compras = $st->fetchAll(PDO::FETCH_ASSOC);
    $saldo_compras = array_sum(array_map(fn($c) => max(0, $c['total_amount'] - $c['paid_amount']), $compras));
    $mes_compras = array_sum(array_map(fn($c) => substr($c['purchase_date'], 0, 7) === date('Y-m') ? (float) $c['total_amount'] : 0, $compras));

    $sql = "SELECT m.*, ai.nombre, ai.unidad, TRIM(CONCAT(COALESCE(u.nombre,''), ' ', COALESCE(u.apellido,''))) AS usuario
            FROM area_movimientos m
            JOIN area_items ai ON ai.id_item = m.id_item
            LEFT JOIN usuarios u ON u.idUsuario = m.id_usuario
            WHERE m.id_hospital = ? AND m.area = ? AND m.fecha >= ? AND m.fecha < DATE_ADD(?, INTERVAL 1 DAY)";
    $p = [$id_hospital, $area, $f_desde, $f_hasta];
    if (isset(AREA_MOV_TIPOS[$f_tipo])) { $sql .= " AND m.tipo = ?"; $p[] = $f_tipo; }
    if ($f_q !== '') { $sql .= " AND (ai.nombre LIKE ? OR m.documento LIKE ? OR m.nota LIKE ?)"; array_push($p, "%$f_q%", "%$f_q%", "%$f_q%"); }
    $sql .= " ORDER BY m.fecha DESC, m.id_mov DESC LIMIT 500";
    $st = $conn->prepare($sql);
    $st->execute($p);
    $movs = $st->fetchAll(PDO::FETCH_ASSOC);
    $mov_in = $mov_out = 0;
    foreach ($movs as $m) {
        $v = (float) $m['cantidad'] * (float) $m['costo_unitario'];
        if ($m['cantidad'] > 0) $mov_in += $v; else $mov_out -= $v;
    }

    $st = $conn->prepare("SELECT c.*, TRIM(CONCAT(COALESCE(u.nombre,''), ' ', COALESCE(u.apellido,''))) AS usuario
                          FROM area_cortes c LEFT JOIN usuarios u ON u.idUsuario = c.id_usuario
                          WHERE c.id_hospital = ? AND c.area = ? ORDER BY c.fecha DESC LIMIT 60");
    $st->execute([$id_hospital, $area]);
    $cortes = $st->fetchAll(PDO::FETCH_ASSOC);
}

$estado_txt = ['ok' => 'Disponible', 'bajo' => 'Bajo mínimo', 'agotado' => 'Sin existencia', 'negativo' => 'En negativo', 'inactivo' => 'Inactivo'];
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $e(csrf_token()); ?>">
    <title>Almacén de <?php echo $e($A['label']); ?> · CMRM</title>
    <link rel="icon" type="image/png" href="../../assets/img/favicon-cmrm.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=optional" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <?php include '../../includes/theme_head.php'; ?>
    <link rel="stylesheet" href="../../assets/css/processing-overlay.css">
    <link rel="stylesheet" href="../../assets/css/almacen.css?v=1">
</head>
<body>
<div class="marble-effect"></div>
<div class="dashboard-container">
    <header class="dashboard-header">
        <div class="header-content">
            <div class="brand-container">
                <img src="../../assets/img/logo_cmrm.svg" alt="Centro Médico Dr. Roberto Morales" class="brand-logo">
            </div>
            <div class="header-controls">
                <div class="theme-toggle">
                    <button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema claro/oscuro">
                        <i class="bi bi-sun theme-icon sun-icon"></i><i class="bi bi-moon theme-icon moon-icon"></i>
                    </button>
                </div>
                <a href="../dashboard/index.php" class="back-btn"><i class="bi bi-arrow-left"></i><span>Dashboard</span></a>
            </div>
        </div>
    </header>

    <main class="main-content al-page" data-area="<?php echo $e($area); ?>">

        <section class="al-head">
            <div>
                <span class="al-eyebrow"><i class="bi <?php echo $e($A['icon']); ?>"></i> Almacén</span>
                <h1><?php echo $e($A['label']); ?></h1>
                <p>Inventario propio, compras y cortes de <?php echo $e(mb_strtolower($A['label'])); ?>.</p>
            </div>
            <div class="al-head__side">
                <?php if (count($areas) > 1): ?>
                    <nav class="al-seg" aria-label="Área">
                        <?php foreach ($areas as $a): ?>
                            <a href="?area=<?php echo $e($a); ?>" class="<?php echo $a === $area ? 'is-on' : ''; ?>" <?php echo $a === $area ? 'aria-current="page"' : ''; ?>>
                                <i class="bi <?php echo $e(AREAS_ALMACEN[$a]['icon']); ?>"></i><?php echo $e(AREAS_ALMACEN[$a]['label']); ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
                <?php if ($ready): ?>
                    <div class="al-actions">
                        <button class="al-btn al-btn--gold" type="button" data-open="uso"><i class="bi bi-box-arrow-up-right"></i>Registrar uso</button>
                        <button class="al-btn al-btn--primary" type="button" data-open="compra"><i class="bi bi-cart-plus"></i>Nueva compra</button>
                        <a class="al-btn al-btn--line" href="corte.php?area=<?php echo $e($area); ?>"><i class="bi bi-clipboard-check"></i>Corte de inventario</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!$ready): ?>
            <section class="al-panel al-install">
                <i class="bi bi-database-add"></i>
                <h2>El almacén todavía no está instalado</h2>
                <?php if ($is_admin): ?>
                    <p>Se crearán las tablas del almacén de Laboratorio y Quirófano. No se modifica el inventario de farmacia.</p>
                    <button class="al-btn al-btn--primary" type="button" id="btnInstall"><i class="bi bi-magic"></i>Instalar almacén</button>
                <?php else: ?>
                    <p>Pídale a un administrador que entre a Almacén para instalarlo.</p>
                <?php endif; ?>
            </section>
        <?php else: ?>

        <section class="al-kpis" aria-label="Resumen">
            <div class="al-kpi"><span>Artículos activos</span><b><?php echo $k['items']; ?></b><small><?php echo count($cats); ?> categorías</small></div>
            <div class="al-kpi"><span>Valor del inventario</span><b><?php echo $money($k['valor']); ?></b><small>A costo promedio</small></div>
            <button class="al-kpi al-kpi--warn" type="button" data-filter-go="bajo"><span>Bajo mínimo</span><b><?php echo $k['bajo']; ?></b><small>Conviene comprar</small></button>
            <button class="al-kpi al-kpi--bad" type="button" data-filter-go="agotado"><span>Sin existencia</span><b><?php echo $k['agotado']; ?></b><small><?php echo $k['negativo'] ? $k['negativo'] . ' en negativo' : 'Ninguno en negativo'; ?></small></button>
            <button class="al-kpi al-kpi--gold" type="button" data-filter-go="vence"><span>Vencen en 30 días</span><b><?php echo $k['vence']; ?></b><small>Úselos primero</small></button>
        </section>

        <?php if ($k['negativo']): ?>
            <div class="al-alert" role="alert">
                <i class="bi bi-exclamation-octagon"></i>
                <div><strong><?php echo $k['negativo']; ?> <?php echo $k['negativo'] === 1 ? 'artículo quedó' : 'artículos quedaron'; ?> en negativo.</strong>
                    Las órdenes o cirugías descontaron más de lo que había en el sistema. Registre la compra que falta o haga un corte de inventario.</div>
            </div>
        <?php endif; ?>

        <nav class="al-tabs" role="tablist">
            <button type="button" role="tab" data-tab="inventario"><i class="bi bi-box-seam"></i>Inventario <span><?php echo $k['items']; ?></span></button>
            <button type="button" role="tab" data-tab="compras"><i class="bi bi-receipt"></i>Compras <span><?php echo count($compras); ?></span></button>
            <button type="button" role="tab" data-tab="movimientos"><i class="bi bi-arrow-left-right"></i>Movimientos</button>
            <button type="button" role="tab" data-tab="cortes"><i class="bi bi-clipboard-check"></i>Cortes <span><?php echo count($cortes); ?></span></button>
        </nav>

        <!-- ═══ INVENTARIO ═══ -->
        <section class="al-tab" id="tab-inventario" role="tabpanel">
            <div class="al-toolbar">
                <label class="al-find"><i class="bi bi-search"></i><input type="search" id="invSearch" placeholder="Buscar por nombre, código o lote" aria-label="Buscar artículo"></label>
                <?php if ($cats): ?>
                    <select class="form-select al-select" id="invCat" aria-label="Categoría">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($cats as $c): ?><option><?php echo $e($c); ?></option><?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <div class="al-chips" role="group" aria-label="Filtro">
                    <button type="button" class="is-on" data-filter="">Todos</button>
                    <button type="button" data-filter="bajo">Bajo mínimo</button>
                    <button type="button" data-filter="agotado">Sin existencia</button>
                    <button type="button" data-filter="vence">Por vencer</button>
                    <button type="button" data-filter="inactivo">Inactivos</button>
                </div>
                <button class="al-btn al-btn--primary al-btn--sm ms-auto" type="button" data-open="item"><i class="bi bi-plus-lg"></i>Nuevo artículo</button>
            </div>

            <?php if (!$items): ?>
                <div class="al-panel al-empty">
                    <i class="bi bi-box-seam"></i>
                    <b>Todavía no hay artículos en este almacén</b>
                    <p>Créelos uno por uno con "Nuevo artículo", o registre su primera compra: los artículos nuevos se crean desde ahí.</p>
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <button class="al-btn al-btn--primary" type="button" data-open="item"><i class="bi bi-plus-lg"></i>Nuevo artículo</button>
                        <button class="al-btn al-btn--line" type="button" data-open="compra"><i class="bi bi-cart-plus"></i>Registrar compra</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="al-table-wrap">
                    <table class="al-table" id="invTable">
                        <thead><tr>
                            <th>Artículo</th><th>Categoría</th><th class="num">Existencia</th><th class="num">Mínimo</th>
                            <th class="num">Costo prom.</th><th class="num">Valor</th><th>Vence</th><th class="act"><span class="visually-hidden">Acciones</span></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($items as $it):
                            $vence = $it['_dias'] !== null && $it['_dias'] <= 30 && (float) $it['stock'] > 0;
                            $tags = $it['_estado'] . ($vence ? ' vence' : '');
                            $json = $e(json_encode([
                                'id_item' => (int) $it['id_item'], 'codigo' => $it['codigo'], 'nombre' => $it['nombre'], 'categoria' => $it['categoria'],
                                'unidad' => $it['unidad'], 'presentacion' => $it['presentacion'], 'stock' => (float) $it['stock'], 'stock_minimo' => (float) $it['stock_minimo'],
                                'costo_unitario' => (float) $it['costo_unitario'], 'fecha_vencimiento' => $it['fecha_vencimiento'], 'lote' => $it['lote'],
                                'ubicacion' => $it['ubicacion'], 'activo' => (int) $it['activo'],
                            ], JSON_UNESCAPED_UNICODE));
                        ?>
                            <tr data-tags="<?php echo $e($tags); ?>" data-cat="<?php echo $e($it['categoria']); ?>"
                                data-find="<?php echo $e(mb_strtolower($it['nombre'] . ' ' . $it['codigo'] . ' ' . $it['lote'] . ' ' . $it['presentacion'])); ?>"
                                data-item="<?php echo $json; ?>" <?php echo $it['activo'] ? '' : 'hidden'; ?>>
                                <td>
                                    <div class="al-name"><?php echo $e($it['nombre']); ?></div>
                                    <?php $sub = implode(' · ', array_filter([$it['codigo'], $it['presentacion'], $it['lote'] ? 'Lote ' . $it['lote'] : null, $it['ubicacion']])); ?>
                                    <?php if ($sub !== ''): ?><div class="al-sub"><?php echo $e($sub); ?></div><?php endif; ?>
                                </td>
                                <td><?php echo $it['categoria'] ? '<span class="al-tag">' . $e($it['categoria']) . '</span>' : '<span class="al-muted">—</span>'; ?></td>
                                <td class="num">
                                    <span class="al-stock al-stock--<?php echo $e($it['_estado']); ?>"><?php echo $q3($it['stock']); ?></span>
                                    <span class="al-unit"><?php echo $e($it['unidad']); ?></span>
                                    <?php if ($it['_estado'] !== 'ok' && $it['_estado'] !== 'inactivo'): ?>
                                        <div class="al-state al-state--<?php echo $e($it['_estado']); ?>"><?php echo $e($estado_txt[$it['_estado']]); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="num al-muted"><?php echo $q3($it['stock_minimo']); ?></td>
                                <td class="num"><?php echo $money($it['costo_unitario']); ?></td>
                                <td class="num"><?php echo $money(max(0, $it['stock']) * $it['costo_unitario']); ?></td>
                                <td>
                                    <?php if ($it['fecha_vencimiento']): ?>
                                        <span class="<?php echo $it['_dias'] < 0 ? 'al-bad' : ($vence ? 'al-warn' : ''); ?>"><?php echo date('d/m/Y', strtotime($it['fecha_vencimiento'])); ?></span>
                                        <?php if ($vence || $it['_dias'] < 0): ?><div class="al-sub"><?php echo $it['_dias'] < 0 ? 'Vencido' : ($it['_dias'] === 0 ? 'Vence hoy' : 'En ' . $it['_dias'] . ' días'); ?></div><?php endif; ?>
                                    <?php else: ?><span class="al-muted">—</span><?php endif; ?>
                                </td>
                                <td class="act">
                                    <div class="al-row-actions">
                                        <?php if ($it['activo']): ?>
                                            <button type="button" class="al-icon" data-act="usar" title="Registrar uso" aria-label="Registrar uso de <?php echo $e($it['nombre']); ?>"><i class="bi bi-box-arrow-up-right"></i></button>
                                        <?php endif; ?>
                                        <button type="button" class="al-icon" data-act="kardex" title="Movimientos (kardex)" aria-label="Movimientos de <?php echo $e($it['nombre']); ?>"><i class="bi bi-clock-history"></i></button>
                                        <div class="dropdown">
                                            <button type="button" class="al-icon" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Más acciones"><i class="bi bi-three-dots"></i></button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><button class="dropdown-item" type="button" data-act="editar"><i class="bi bi-pencil me-2"></i>Editar</button></li>
                                                <?php if ($is_admin): ?><li><button class="dropdown-item" type="button" data-act="ajustar"><i class="bi bi-sliders me-2"></i>Ajustar existencia</button></li><?php endif; ?>
                                                <li><button class="dropdown-item" type="button" data-act="toggle"><i class="bi <?php echo $it['activo'] ? 'bi-eye-slash' : 'bi-eye'; ?> me-2"></i><?php echo $it['activo'] ? 'Desactivar' : 'Activar'; ?></button></li>
                                            </ul>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="al-panel al-empty" id="invNoMatch" hidden><i class="bi bi-search"></i><b>Ningún artículo coincide</b></div>
            <?php endif; ?>
        </section>

        <!-- ═══ COMPRAS ═══ -->
        <section class="al-tab" id="tab-compras" role="tabpanel">
            <div class="al-toolbar">
                <div class="al-mini">
                    <div><span>Comprado este mes</span><b><?php echo $money($mes_compras); ?></b></div>
                    <div><span>Saldo por pagar</span><b class="<?php echo $saldo_compras > 0 ? 'al-warn' : ''; ?>"><?php echo $money($saldo_compras); ?></b></div>
                </div>
                <button class="al-btn al-btn--primary al-btn--sm ms-auto" type="button" data-open="compra"><i class="bi bi-cart-plus"></i>Nueva compra</button>
            </div>
            <?php if (!$compras): ?>
                <div class="al-panel al-empty"><i class="bi bi-receipt"></i><b>No hay compras de <?php echo $e(mb_strtolower($A['label'])); ?></b><p>Al registrar una compra, las existencias suben de inmediato y el gasto aparece en Reportes.</p></div>
            <?php else: ?>
                <div class="al-table-wrap">
                    <table class="al-table">
                        <thead><tr><th>Fecha</th><th>Proveedor</th><th>Documento</th><th class="num">Artículos</th><th class="num">Total</th><th>Pago</th><th class="act"></th></tr></thead>
                        <tbody>
                        <?php foreach ($compras as $c):
                            $saldo = round($c['total_amount'] - $c['paid_amount'], 2);
                            $tone = $c['payment_status'] === 'Pagado' ? 'ok' : ($c['payment_status'] === 'Parcial' ? 'warn' : 'bad'); ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($c['purchase_date'])); ?></td>
                                <td><div class="al-name"><?php echo $e($c['provider_name']); ?></div></td>
                                <td><?php echo $e(trim($c['document_type'] . ' ' . $c['document_number'])); ?></td>
                                <td class="num"><?php echo (int) $c['n_items']; ?></td>
                                <td class="num"><b><?php echo $money($c['total_amount']); ?></b></td>
                                <td>
                                    <span class="al-pill al-pill--<?php echo $tone; ?>"><?php echo $e($c['payment_status']); ?></span>
                                    <?php if ($saldo > 0): ?><div class="al-sub">Saldo <?php echo $money($saldo); ?> · <?php echo $e($c['tipo_pago']); ?></div><?php endif; ?>
                                </td>
                                <td class="act">
                                    <div class="al-row-actions">
                                        <button type="button" class="al-icon" data-compra-ver="<?php echo (int) $c['id']; ?>" title="Ver detalle" aria-label="Ver compra"><i class="bi bi-eye"></i></button>
                                        <?php if ($saldo > 0): ?>
                                            <button type="button" class="al-icon al-icon--ok" data-compra-pagar="<?php echo (int) $c['id']; ?>" data-saldo="<?php echo $saldo; ?>" title="Registrar abono" aria-label="Registrar abono"><i class="bi bi-cash-coin"></i></button>
                                        <?php endif; ?>
                                        <?php if ($is_admin): ?>
                                            <button type="button" class="al-icon al-icon--bad" data-compra-anular="<?php echo (int) $c['id']; ?>" data-prov="<?php echo $e($c['provider_name']); ?>" title="Anular compra" aria-label="Anular compra"><i class="bi bi-x-circle"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- ═══ MOVIMIENTOS ═══ -->
        <section class="al-tab" id="tab-movimientos" role="tabpanel">
            <form class="al-toolbar" method="get" action="#movimientos">
                <input type="hidden" name="area" value="<?php echo $e($area); ?>">
                <label class="al-field"><span>Desde</span><input type="date" class="form-control" name="desde" value="<?php echo $e($f_desde); ?>"></label>
                <label class="al-field"><span>Hasta</span><input type="date" class="form-control" name="hasta" value="<?php echo $e($f_hasta); ?>"></label>
                <label class="al-field"><span>Tipo</span>
                    <select class="form-select" name="tipo">
                        <option value="">Todos</option>
                        <?php foreach (AREA_MOV_TIPOS as $t => $d): ?><option value="<?php echo $t; ?>" <?php echo $f_tipo === $t ? 'selected' : ''; ?>><?php echo $e($d[0]); ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="al-field al-field--grow"><span>Buscar</span><input type="search" class="form-control" name="q" value="<?php echo $e($f_q); ?>" placeholder="Artículo, documento o nota"></label>
                <button class="al-btn al-btn--primary al-btn--sm" type="submit"><i class="bi bi-funnel"></i>Filtrar</button>
                <button class="al-btn al-btn--line al-btn--sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i>Imprimir</button>
            </form>
            <div class="al-mini mb-3">
                <div><span>Movimientos</span><b><?php echo count($movs); ?></b></div>
                <div><span>Entradas (a costo)</span><b class="al-ok"><?php echo $money($mov_in); ?></b></div>
                <div><span>Salidas (a costo)</span><b class="al-bad"><?php echo $money($mov_out); ?></b></div>
            </div>
            <?php if (!$movs): ?>
                <div class="al-panel al-empty"><i class="bi bi-arrow-left-right"></i><b>Sin movimientos en este rango</b></div>
            <?php else: ?>
                <div class="al-table-wrap">
                    <table class="al-table al-table--dense">
                        <thead><tr><th>Fecha</th><th>Tipo</th><th>Artículo</th><th class="num">Cantidad</th><th class="num">Queda</th><th>Documento / nota</th><th>Usuario</th></tr></thead>
                        <tbody>
                        <?php foreach ($movs as $m): $t = AREA_MOV_TIPOS[$m['tipo']] ?? [$m['tipo'], 'adj']; ?>
                            <tr>
                                <td class="al-nowrap"><?php echo date('d/m/Y H:i', strtotime($m['fecha'])); ?></td>
                                <td><span class="al-mov al-mov--<?php echo $e($t[1]); ?>"><?php echo $e($t[0]); ?></span></td>
                                <td><?php echo $e($m['nombre']); ?></td>
                                <td class="num <?php echo $m['cantidad'] > 0 ? 'al-ok' : 'al-bad'; ?>"><?php echo ($m['cantidad'] > 0 ? '+' : '') . $q3($m['cantidad']); ?> <span class="al-unit"><?php echo $e($m['unidad']); ?></span></td>
                                <td class="num <?php echo $m['stock_despues'] < 0 ? 'al-bad' : ''; ?>"><?php echo $q3($m['stock_despues']); ?></td>
                                <td><?php echo $e($m['documento']); ?><?php if ($m['nota']): ?><div class="al-sub"><?php echo $e($m['nota']); ?></div><?php endif; ?></td>
                                <td class="al-muted"><?php echo $e($m['usuario'] ?: '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($movs) >= 500): ?><p class="al-muted mt-2">Se muestran los 500 más recientes. Acorte el rango de fechas para ver el resto.</p><?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- ═══ CORTES ═══ -->
        <section class="al-tab" id="tab-cortes" role="tabpanel">
            <div class="al-toolbar">
                <p class="al-muted m-0">Cuente lo que hay físicamente y el sistema ajusta las diferencias. Lo que se mueva mientras cuenta (órdenes, compras) se respeta.</p>
                <a class="al-btn al-btn--primary al-btn--sm ms-auto" href="corte.php?area=<?php echo $e($area); ?>"><i class="bi bi-clipboard-check"></i>Hacer corte</a>
            </div>
            <?php if (!$cortes): ?>
                <div class="al-panel al-empty"><i class="bi bi-clipboard-check"></i><b>Todavía no hay cortes</b><p>Se recomienda uno al mes, y siempre que cambie el encargado del área.</p></div>
            <?php else: ?>
                <div class="al-table-wrap">
                    <table class="al-table">
                        <thead><tr><th>Corte</th><th>Fecha</th><th>Hecho por</th><th class="num">Contados</th><th class="num">Con diferencia</th><th class="num">Diferencia</th><th class="act"></th></tr></thead>
                        <tbody>
                        <?php foreach ($cortes as $c): ?>
                            <tr>
                                <td><b>#<?php echo (int) $c['id_corte']; ?></b></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($c['fecha'])); ?></td>
                                <td><?php echo $e($c['usuario'] ?: '—'); ?></td>
                                <td class="num"><?php echo (int) $c['items_contados']; ?></td>
                                <td class="num"><?php echo (int) $c['items_diferencia']; ?></td>
                                <td class="num <?php echo $c['valor_diferencia'] < 0 ? 'al-bad' : ($c['valor_diferencia'] > 0 ? 'al-ok' : ''); ?>"><?php echo $money($c['valor_diferencia']); ?></td>
                                <td class="act"><a class="al-icon" href="corte_ver.php?id=<?php echo (int) $c['id_corte']; ?>" title="Ver e imprimir" aria-label="Ver corte <?php echo (int) $c['id_corte']; ?>"><i class="bi bi-printer"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </main>
</div>

<?php if ($ready): ?>
<!-- Modal: artículo -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-labelledby="itemTitle" aria-hidden="true">
    <div class="modal-dialog al-dialog modal-lg modal-dialog-centered"><div class="modal-content al-modal">
        <div class="modal-header"><h5 class="modal-title" id="itemTitle">Nuevo artículo</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <form id="itemForm" class="modal-body">
            <input type="hidden" name="id_item">
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label" for="f_nombre">Nombre *</label><input class="form-control" id="f_nombre" name="nombre" required maxlength="150" placeholder="<?php echo $area === 'laboratorio' ? 'Ej.: Reactivo de glucosa' : 'Ej.: Gasa estéril 10x10'; ?>"></div>
                <div class="col-md-4"><label class="form-label" for="f_codigo">Código</label><input class="form-control" id="f_codigo" name="codigo" maxlength="50"></div>
                <div class="col-md-4"><label class="form-label" for="f_categoria">Categoría</label><input class="form-control" id="f_categoria" name="categoria" list="catList" maxlength="80" placeholder="<?php echo $area === 'laboratorio' ? 'Reactivos' : 'Material de curación'; ?>">
                    <datalist id="catList"><?php foreach ($cats as $c): ?><option value="<?php echo $e($c); ?>"><?php endforeach; ?></datalist></div>
                <div class="col-md-4"><label class="form-label" for="f_unidad">Se mide en *</label><input class="form-control" id="f_unidad" name="unidad" list="unitList" required maxlength="30" value="unidad">
                    <datalist id="unitList"><?php foreach ($A['unidades'] as $u): ?><option value="<?php echo $e($u); ?>"><?php endforeach; ?></datalist>
                    <small class="form-text">Es la unidad con la que se descuenta (ej.: ml, prueba, par).</small></div>
                <div class="col-md-4"><label class="form-label" for="f_presentacion">Presentación</label><input class="form-control" id="f_presentacion" name="presentacion" maxlength="120" placeholder="Ej.: Frasco 500 ml"></div>
                <div class="col-md-3 only-new"><label class="form-label" for="f_stock_inicial">Existencia inicial</label><input type="number" class="form-control" id="f_stock_inicial" name="stock_inicial" min="0" step="any" value="0"></div>
                <div class="col-md-3"><label class="form-label" for="f_stock_minimo">Mínimo</label><input type="number" class="form-control" id="f_stock_minimo" name="stock_minimo" min="0" step="any" value="0"><small class="form-text">Avisa al llegar aquí.</small></div>
                <div class="col-md-3 cost-field"><label class="form-label" for="f_costo">Costo unitario (Q)</label><input type="number" class="form-control" id="f_costo" name="costo_unitario" min="0" step="0.0001" value="0"></div>
                <div class="col-md-3"><label class="form-label" for="f_venc">Vence</label><input type="date" class="form-control" id="f_venc" name="fecha_vencimiento"></div>
                <div class="col-md-4"><label class="form-label" for="f_lote">Lote</label><input class="form-control" id="f_lote" name="lote" maxlength="60"></div>
                <div class="col-md-4"><label class="form-label" for="f_ubic">Ubicación</label><input class="form-control" id="f_ubic" name="ubicacion" maxlength="80" placeholder="Ej.: Refrigerador 2"></div>
            </div>
        </form>
        <div class="modal-footer">
            <button type="button" class="al-btn al-btn--line" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" form="itemForm" class="al-btn al-btn--primary"><i class="bi bi-check2"></i>Guardar</button>
        </div>
    </div></div>
</div>

<!-- Modal: registrar uso -->
<div class="modal fade" id="usoModal" tabindex="-1" aria-labelledby="usoTitle" aria-hidden="true">
    <div class="modal-dialog al-dialog modal-lg modal-dialog-centered"><div class="modal-content al-modal">
        <div class="modal-header"><h5 class="modal-title" id="usoTitle"><i class="bi bi-box-arrow-up-right me-2"></i>Registrar uso</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
            <p class="al-muted">Para lo que se gasta fuera de una orden o cirugía: controles de calidad, calibraciones, material dañado o vencido.</p>
            <div id="usoRows"></div>
            <div class="row g-3 mt-1">
                <div class="col-md-8"><label class="form-label" for="usoMotivo">¿Para qué se usó? *</label><input class="form-control" id="usoMotivo" list="usoMotivos" maxlength="200" placeholder="Ej.: Control de calidad diario">
                    <datalist id="usoMotivos"><option value="Control de calidad"><option value="Calibración de equipo"><option value="Material dañado"><option value="Material vencido"><option value="Uso en paciente sin orden"></datalist></div>
                <div class="col-md-4"><label class="form-label" for="usoDoc">Referencia</label><input class="form-control" id="usoDoc" maxlength="80" placeholder="Opcional"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="al-btn al-btn--line" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="al-btn al-btn--gold" id="btnUso"><i class="bi bi-check2"></i>Registrar uso</button>
        </div>
    </div></div>
</div>

<!-- Modal: nueva compra -->
<div class="modal fade" id="compraModal" tabindex="-1" aria-labelledby="compraTitle" aria-hidden="true">
    <div class="modal-dialog al-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content al-modal">
        <div class="modal-header"><h5 class="modal-title" id="compraTitle"><i class="bi bi-cart-plus me-2"></i>Nueva compra · <?php echo $e($A['label']); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body">
            <div class="row g-3 mb-3">
                <div class="col-md-4"><label class="form-label" for="cProv">Proveedor *</label><input class="form-control" id="cProv" maxlength="150" required></div>
                <div class="col-md-2"><label class="form-label" for="cFecha">Fecha *</label><input type="date" class="form-control" id="cFecha" value="<?php echo date('Y-m-d'); ?>"></div>
                <div class="col-md-2"><label class="form-label" for="cDocTipo">Documento</label><select class="form-select" id="cDocTipo"><option>Factura</option><option>Nota de Envío</option><option>Consumidor Final</option><option>Recibo</option></select></div>
                <div class="col-md-2"><label class="form-label" for="cDocNum">No. documento</label><input class="form-control" id="cDocNum" maxlength="50"></div>
                <div class="col-md-2"><label class="form-label" for="cPago">Pago</label><select class="form-select" id="cPago"><option value="Contado">Al contado</option><option value="Credito 30">Crédito 30</option><option value="Credito 60">Crédito 60</option></select></div>
            </div>
            <div class="al-buy-search"><i class="bi bi-search"></i><input type="text" class="form-control" id="cBuscar" placeholder="Buscar artículo del almacén. Si no existe, escríbalo y presione Enter para crearlo."></div>
            <div class="al-table-wrap mt-2">
                <table class="al-table al-table--dense al-buy">
                    <thead><tr><th>Artículo</th><th class="num" style="width:110px">Cantidad</th><th class="num" style="width:130px">Costo unit. (Q)</th><th style="width:150px">Vence</th><th style="width:110px">Lote</th><th class="num" style="width:110px">Subtotal</th><th style="width:40px"></th></tr></thead>
                    <tbody id="cRows"><tr class="al-buy-empty"><td colspan="7">Busque los artículos que vienen en la factura.</td></tr></tbody>
                    <tfoot><tr><td colspan="5" class="num"><b>Total</b></td><td class="num"><b id="cTotal">Q0.00</b></td><td></td></tr></tfoot>
                </table>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="al-btn al-btn--line" data-bs-dismiss="modal">Cancelar</button>
            <button type="button" class="al-btn al-btn--primary" id="btnCompra"><i class="bi bi-check2"></i>Guardar compra</button>
        </div>
    </div></div>
</div>

<!-- Modal: kardex / ver compra -->
<div class="modal fade" id="infoModal" tabindex="-1" aria-labelledby="infoTitle" aria-hidden="true">
    <div class="modal-dialog al-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content al-modal">
        <div class="modal-header"><h5 class="modal-title" id="infoTitle"></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body" id="infoBody"></div>
    </div></div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../../assets/js/processing-overlay.js"></script>
<script src="../../assets/js/almacen.js?v=1"></script>
<script>
(function () {
    'use strict';
    var AREA = <?php echo json_encode($area); ?>;
    var IS_ADMIN = <?php echo $is_admin ? 'true' : 'false'; ?>;
    var TIPOS = <?php echo json_encode(AREA_MOV_TIPOS, JSON_UNESCAPED_UNICODE); ?>;
    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
    var esc = AL.esc, qty = AL.qty, money = AL.money;
    var brand = getComputedStyle(document.documentElement).getPropertyValue('--cmrm-navy-700').trim() || '#283251';

    function modal(id) { return bootstrap.Modal.getOrCreateInstance(document.getElementById(id)); }
    function ok(msg) {
        if (window.showProcessing) window.showProcessing(msg || 'Listo', 'Actualizando…');
        setTimeout(function () { location.reload(); }, 350);
    }
    function err(msg) { return Swal.fire({ icon: 'error', title: 'No se pudo', text: msg, confirmButtonColor: brand }); }

    <?php if (!$ready): ?>
    var bi = $('#btnInstall');
    if (bi) bi.addEventListener('click', async function () {
        var r = await AL.api('install', {});
        if (r.success) ok('Almacén instalado'); else err(r.message);
    });
    return;
    <?php endif; ?>

    /* ── Pestañas (se recuerdan en la URL) ── */
    function tab(name) {
        if (!$('#tab-' + name)) name = 'inventario';
        $$('.al-tab').forEach(function (t) { t.classList.toggle('is-on', t.id === 'tab-' + name); });
        $$('[data-tab]').forEach(function (b) { var on = b.getAttribute('data-tab') === name; b.classList.toggle('is-on', on); b.setAttribute('aria-selected', on); });
        if (location.hash !== '#' + name) history.replaceState(null, '', location.pathname + location.search + '#' + name);
    }
    $$('[data-tab]').forEach(function (b) { b.addEventListener('click', function () { tab(b.getAttribute('data-tab')); }); });
    tab((location.hash || '#inventario').slice(1));

    /* ── Filtros del inventario ── */
    var filtro = '';
    function filtrar() {
        var q = ($('#invSearch') ? $('#invSearch').value : '').trim().toLowerCase();
        var cat = $('#invCat') ? $('#invCat').value : '';
        var n = 0;
        $$('#invTable tbody tr').forEach(function (tr) {
            var tags = tr.dataset.tags.split(' ');
            var okF = filtro === 'inactivo' ? tags.indexOf('inactivo') >= 0
                : tags.indexOf('inactivo') < 0 && (!filtro || tags.indexOf(filtro) >= 0 || (filtro === 'agotado' && tags.indexOf('negativo') >= 0));
            var show = okF && (!q || tr.dataset.find.indexOf(q) >= 0) && (!cat || tr.dataset.cat === cat);
            tr.hidden = !show; if (show) n++;
        });
        if ($('#invNoMatch')) $('#invNoMatch').hidden = n > 0;
    }
    if ($('#invSearch')) $('#invSearch').addEventListener('input', filtrar);
    if ($('#invCat')) $('#invCat').addEventListener('change', filtrar);
    function setFiltro(f) {
        filtro = f;
        $$('[data-filter]').forEach(function (b) { b.classList.toggle('is-on', b.getAttribute('data-filter') === f); });
        filtrar();
    }
    $$('[data-filter]').forEach(function (b) { b.addEventListener('click', function () { setFiltro(b.getAttribute('data-filter')); }); });
    $$('[data-filter-go]').forEach(function (b) { b.addEventListener('click', function () { tab('inventario'); setFiltro(b.getAttribute('data-filter-go')); }); });
    filtrar();

    function rowItem(el) { var tr = el.closest('tr[data-item]'); return tr ? JSON.parse(tr.dataset.item) : null; }

    /* ── Artículo: nuevo / editar ── */
    function openItem(it) {
        var f = $('#itemForm');
        f.reset();
        f.id_item.value = it ? it.id_item : '';
        $('#itemTitle').textContent = it ? 'Editar ' + it.nombre : 'Nuevo artículo';
        $$('.only-new', f).forEach(function (x) { x.hidden = !!it; });
        $$('.cost-field', f).forEach(function (x) { x.hidden = !!it && !IS_ADMIN; });
        if (it) ['codigo', 'nombre', 'categoria', 'unidad', 'presentacion', 'stock_minimo', 'costo_unitario', 'fecha_vencimiento', 'lote', 'ubicacion']
            .forEach(function (k) { if (f[k]) f[k].value = it[k] == null ? '' : it[k]; });
        modal('itemModal').show();
        setTimeout(function () { f.nombre.focus(); }, 300);
    }
    $('#itemForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        var f = e.target, d = { area: AREA };
        new FormData(f).forEach(function (v, k) { d[k] = v; });
        if (d.id_item && !IS_ADMIN) delete d.costo_unitario;
        var r = await AL.api('save_item', d);
        if (r.success) { modal('itemModal').hide(); ok(r.message); } else err(r.message);
    });

    /* ── Registrar uso (varios artículos) ── */
    var uso = [];
    function drawUso() {
        var box = $('#usoRows');
        box.innerHTML = '<div class="al-buy-search"><i class="bi bi-search"></i><input type="text" class="form-control" id="usoBuscar" placeholder="Buscar artículo…"></div>' +
            (uso.length ? '<div class="al-uso">' + uso.map(function (u, i) {
                return '<div class="al-uso__row"><div><b>' + esc(u.nombre) + '</b><small>Hay ' + qty(u.stock) + ' ' + esc(u.unidad) + '</small></div>' +
                    '<div class="al-receta__qty"><input type="number" class="form-control" min="0" step="any" value="' + esc(u.cantidad) + '" data-u="' + i + '" aria-label="Cantidad"><span>' + esc(u.unidad) + '</span></div>' +
                    '<button type="button" class="btn btn-sm btn-outline-danger" data-ud="' + i + '" aria-label="Quitar"><i class="bi bi-x-lg"></i></button></div>';
            }).join('') + '</div>' : '<div class="al-receta__empty">Busque lo que se usó.</div>');
        AL.search($('#usoBuscar'), { area: AREA, onPick: function (it) {
            if (!uso.some(function (u) { return u.id_item == it.id; })) uso.push({ id_item: it.id, nombre: it.nombre, unidad: it.unidad, stock: it.stock, cantidad: 1 });
            drawUso(); var last = $('[data-u="' + (uso.length - 1) + '"]'); if (last) { last.focus(); last.select(); }
        } });
    }
    $('#usoRows').addEventListener('input', function (e) { var i = e.target.getAttribute('data-u'); if (i != null) uso[i].cantidad = e.target.value; });
    $('#usoRows').addEventListener('click', function (e) { var b = e.target.closest('[data-ud]'); if (b) { uso.splice(+b.getAttribute('data-ud'), 1); drawUso(); } });
    function openUso(it) {
        uso = it ? [{ id_item: it.id_item, nombre: it.nombre, unidad: it.unidad, stock: it.stock, cantidad: 1 }] : [];
        $('#usoMotivo').value = ''; $('#usoDoc').value = '';
        drawUso();
        modal('usoModal').show();
        setTimeout(function () { var f = it ? $('[data-u="0"]') : $('#usoBuscar'); if (f) { f.focus(); if (f.select) f.select(); } }, 300);
    }
    $('#btnUso').addEventListener('click', async function () {
        if (!uso.length) return err('Agregue al menos un artículo.');
        if (!$('#usoMotivo').value.trim()) { $('#usoMotivo').focus(); return err('Escriba para qué se usó.'); }
        var r = await AL.api('uso', { items: uso, motivo: $('#usoMotivo').value, documento: $('#usoDoc').value });
        if (r.success) { modal('usoModal').hide(); ok(r.message); } else err(r.message);
    });

    /* ── Compra ── */
    var compra = [];
    function drawCompra() {
        var tb = $('#cRows'), total = 0;
        if (!compra.length) { tb.innerHTML = '<tr class="al-buy-empty"><td colspan="7">Busque los artículos que vienen en la factura.</td></tr>'; $('#cTotal').textContent = 'Q0.00'; return; }
        tb.innerHTML = compra.map(function (l, i) {
            var sub = (parseFloat(l.cantidad) || 0) * (parseFloat(l.costo) || 0); total += sub;
            return '<tr><td><b>' + esc(l.nombre) + '</b>' + (l.id_item ? '<div class="al-sub">Hay ' + qty(l.stock) + ' ' + esc(l.unidad) + '</div>'
                    : '<div class="al-sub"><span class="al-tag al-tag--new">Nuevo</span> se mide en <input class="al-inline" data-c="' + i + '" data-k="unidad" value="' + esc(l.unidad) + '" list="unitList" aria-label="Unidad"></div>') + '</td>' +
                '<td><input type="number" class="form-control form-control-sm text-end" min="0" step="any" data-c="' + i + '" data-k="cantidad" value="' + esc(l.cantidad) + '" aria-label="Cantidad"></td>' +
                '<td><input type="number" class="form-control form-control-sm text-end" min="0" step="0.0001" data-c="' + i + '" data-k="costo" value="' + esc(l.costo) + '" aria-label="Costo"></td>' +
                '<td><input type="date" class="form-control form-control-sm" data-c="' + i + '" data-k="fecha_vencimiento" value="' + esc(l.fecha_vencimiento || '') + '" aria-label="Vence"></td>' +
                '<td><input class="form-control form-control-sm" data-c="' + i + '" data-k="lote" value="' + esc(l.lote || '') + '" aria-label="Lote"></td>' +
                '<td class="num" data-sub="' + i + '">' + money(sub) + '</td>' +
                '<td><button type="button" class="btn btn-sm btn-outline-danger" data-cd="' + i + '" aria-label="Quitar"><i class="bi bi-x-lg"></i></button></td></tr>';
        }).join('');
        $('#cTotal').textContent = money(total);
    }
    $('#cRows').addEventListener('input', function (e) {
        var i = e.target.getAttribute('data-c'); if (i == null) return;
        compra[i][e.target.getAttribute('data-k')] = e.target.value;
        var total = 0;
        compra.forEach(function (l, j) { var s = (parseFloat(l.cantidad) || 0) * (parseFloat(l.costo) || 0); total += s; var c = $('[data-sub="' + j + '"]'); if (c) c.textContent = money(s); });
        $('#cTotal').textContent = money(total);
    });
    $('#cRows').addEventListener('click', function (e) { var b = e.target.closest('[data-cd]'); if (b) { compra.splice(+b.getAttribute('data-cd'), 1); drawCompra(); } });
    function addLinea(l) { compra.push(l); drawCompra(); var f = $('[data-c="' + (compra.length - 1) + '"][data-k="cantidad"]'); if (f) { f.focus(); f.select(); } }
    AL.search($('#cBuscar'), {
        area: AREA,
        onPick: function (it) { addLinea({ id_item: it.id, nombre: it.nombre, unidad: it.unidad, stock: it.stock, cantidad: 1, costo: parseFloat(it.costo_unitario) || 0 }); },
        onCreate: function (nombre) { addLinea({ id_item: 0, nombre: nombre, unidad: 'unidad', stock: 0, cantidad: 1, costo: 0 }); }
    });
    function openCompra() { compra = []; drawCompra(); $('#cProv').value = ''; $('#cDocNum').value = ''; modal('compraModal').show(); setTimeout(function () { $('#cProv').focus(); }, 300); }
    $('#btnCompra').addEventListener('click', async function () {
        if (!$('#cProv').value.trim()) { $('#cProv').focus(); return err('Escriba el proveedor.'); }
        if (!compra.length) return err('Agregue los artículos de la compra.');
        var r = await AL.api('save_compra', { area: AREA, header: {
            provider_name: $('#cProv').value, purchase_date: $('#cFecha').value, document_type: $('#cDocTipo').value,
            document_number: $('#cDocNum').value, tipo_pago: $('#cPago').value }, items: compra });
        if (r.success) { modal('compraModal').hide(); ok(r.message); } else err(r.message);
    });

    /* ── Botones de apertura ── */
    $$('[data-open]').forEach(function (b) {
        b.addEventListener('click', function () {
            var w = b.getAttribute('data-open');
            if (w === 'item') openItem(null); else if (w === 'uso') openUso(null); else if (w === 'compra') openCompra();
        });
    });

    /* ── Acciones por fila ── */
    document.addEventListener('click', async function (e) {
        var b = e.target.closest('[data-act]');
        if (!b) return;
        var it = rowItem(b); if (!it) return;
        var act = b.getAttribute('data-act');
        if (act === 'editar') openItem(it);
        else if (act === 'usar') openUso(it);
        else if (act === 'kardex') kardex(it);
        else if (act === 'toggle') {
            var c = await Swal.fire({ icon: 'question', title: (it.activo ? 'Desactivar ' : 'Activar ') + esc(it.nombre) + '?',
                text: it.activo ? 'Ya no aparecerá en búsquedas, recetas ni cortes. Su historial se conserva.' : 'Volverá a aparecer en el almacén.',
                showCancelButton: true, confirmButtonText: it.activo ? 'Desactivar' : 'Activar', cancelButtonText: 'Cancelar', confirmButtonColor: brand });
            if (!c.isConfirmed) return;
            var r = await AL.api('toggle_item', { id_item: it.id_item });
            r.success ? ok(r.message) : err(r.message);
        } else if (act === 'ajustar') {
            var res = await Swal.fire({ title: 'Ajustar ' + esc(it.nombre),
                html: '<p class="al-muted mb-2">Existencia actual: <b>' + qty(it.stock) + ' ' + esc(it.unidad) + '</b></p>' +
                    '<input id="swNuevo" type="number" min="0" step="any" class="swal2-input" placeholder="Nueva existencia" value="' + Math.max(0, it.stock) + '">' +
                    '<input id="swMotivo" class="swal2-input" placeholder="Motivo del ajuste">',
                showCancelButton: true, confirmButtonText: 'Ajustar', cancelButtonText: 'Cancelar', confirmButtonColor: brand, focusConfirm: false,
                preConfirm: function () {
                    var n = document.getElementById('swNuevo').value, m = document.getElementById('swMotivo').value.trim();
                    if (n === '' || n < 0) { Swal.showValidationMessage('Escriba la nueva existencia'); return false; }
                    if (!m) { Swal.showValidationMessage('Escriba el motivo'); return false; }
                    return { stock_nuevo: n, motivo: m };
                } });
            if (!res.isConfirmed) return;
            var r2 = await AL.api('ajuste', Object.assign({ id_item: it.id_item }, res.value));
            r2.success ? ok(r2.message) : err(r2.message);
        }
    });

    async function kardex(it) {
        $('#infoTitle').innerHTML = '<i class="bi bi-clock-history me-2"></i>' + esc(it.nombre);
        $('#infoBody').innerHTML = '<div class="al-muted p-3">Cargando…</div>';
        modal('infoModal').show();
        var r = await AL.api('kardex', { id_item: it.id_item }, 'GET');
        if (!r.success) { $('#infoBody').innerHTML = '<div class="al-alert">' + esc(r.message) + '</div>'; return; }
        $('#infoBody').innerHTML = '<div class="al-mini mb-3"><div><span>Existencia</span><b>' + qty(r.item.stock) + ' ' + esc(r.item.unidad) + '</b></div>' +
            '<div><span>Costo promedio</span><b>' + money(r.item.costo_unitario) + '</b></div><div><span>Mínimo</span><b>' + qty(r.item.stock_minimo) + '</b></div></div>' +
            (r.movs.length ? '<div class="al-table-wrap"><table class="al-table al-table--dense"><thead><tr><th>Fecha</th><th>Tipo</th><th class="num">Cant.</th><th class="num">Queda</th><th>Detalle</th></tr></thead><tbody>' +
            r.movs.map(function (m) {
                var t = TIPOS[m.tipo] || [m.tipo, 'adj'], c = parseFloat(m.cantidad);
                return '<tr><td class="al-nowrap">' + esc(m.fecha.slice(0, 16).replace('T', ' ')) + '</td><td><span class="al-mov al-mov--' + t[1] + '">' + esc(t[0]) + '</span></td>' +
                    '<td class="num ' + (c > 0 ? 'al-ok' : 'al-bad') + '">' + (c > 0 ? '+' : '') + qty(c) + '</td><td class="num">' + qty(m.stock_despues) + '</td>' +
                    '<td>' + esc(m.documento || '') + (m.nota ? '<div class="al-sub">' + esc(m.nota) + '</div>' : '') + (m.usuario ? '<div class="al-sub">' + esc(m.usuario) + '</div>' : '') + '</td></tr>';
            }).join('') + '</tbody></table></div>' : '<div class="al-empty">Sin movimientos todavía.</div>');
    }

    /* ── Compras: ver, abonar, anular ── */
    document.addEventListener('click', async function (e) {
        var v = e.target.closest('[data-compra-ver]');
        if (v) {
            $('#infoTitle').innerHTML = '<i class="bi bi-receipt me-2"></i>Compra';
            $('#infoBody').innerHTML = '<div class="al-muted p-3">Cargando…</div>';
            modal('infoModal').show();
            var r = await AL.api('compra', { id: v.getAttribute('data-compra-ver') }, 'GET');
            if (!r.success) { $('#infoBody').innerHTML = '<div class="al-alert">' + esc(r.message) + '</div>'; return; }
            var h = r.header;
            $('#infoTitle').innerHTML = '<i class="bi bi-receipt me-2"></i>' + esc(h.provider_name);
            $('#infoBody').innerHTML = '<div class="al-mini mb-3"><div><span>Fecha</span><b>' + esc(h.purchase_date) + '</b></div><div><span>Documento</span><b>' + esc((h.document_type + ' ' + (h.document_number || '')).trim()) + '</b></div>' +
                '<div><span>Total</span><b>' + money(h.total_amount) + '</b></div><div><span>Pagado</span><b>' + money(h.paid_amount) + '</b></div></div>' +
                '<div class="al-table-wrap"><table class="al-table al-table--dense"><thead><tr><th>Artículo</th><th class="num">Cant.</th><th class="num">Costo</th><th class="num">Subtotal</th><th>Vence / lote</th></tr></thead><tbody>' +
                r.items.map(function (i) { return '<tr><td>' + esc(i.nombre) + '</td><td class="num">' + qty(i.cantidad) + ' ' + esc(i.unidad) + '</td><td class="num">' + money(i.costo_unitario) + '</td><td class="num">' + money(i.subtotal) + '</td><td>' + esc([i.fecha_vencimiento, i.lote].filter(Boolean).join(' · ') || '—') + '</td></tr>'; }).join('') +
                '</tbody></table></div>' +
                (r.pagos.length ? '<h6 class="mt-3">Pagos</h6><ul class="al-list">' + r.pagos.map(function (p) { return '<li><span>' + esc(p.payment_date) + ' · ' + esc(p.payment_method) + '</span><b>' + money(p.amount) + '</b></li>'; }).join('') + '</ul>' : '') +
                '<p class="al-muted mt-3 mb-0">Registrada por ' + esc(h.usuario || '—') + '.</p>';
            return;
        }
        var p = e.target.closest('[data-compra-pagar]');
        if (p) {
            var saldo = parseFloat(p.getAttribute('data-saldo'));
            var res = await Swal.fire({ title: 'Registrar abono',
                html: '<p class="al-muted mb-2">Saldo pendiente: <b>' + money(saldo) + '</b></p>' +
                    '<input id="swMonto" type="number" min="0" step="0.01" class="swal2-input" value="' + saldo.toFixed(2) + '" aria-label="Monto">' +
                    '<select id="swMetodo" class="swal2-select" aria-label="Forma de pago"><option>Efectivo</option><option>Transferencia</option><option>Cheque</option><option>Tarjeta</option></select>' +
                    '<input id="swFecha" type="date" class="swal2-input" value="' + new Date().toISOString().slice(0, 10) + '" aria-label="Fecha">',
                showCancelButton: true, confirmButtonText: 'Registrar', cancelButtonText: 'Cancelar', confirmButtonColor: brand, focusConfirm: false,
                preConfirm: function () { return { monto: document.getElementById('swMonto').value, metodo: document.getElementById('swMetodo').value, fecha: document.getElementById('swFecha').value }; } });
            if (!res.isConfirmed) return;
            var r3 = await AL.api('pagar_compra', Object.assign({ id: p.getAttribute('data-compra-pagar') }, res.value));
            r3.success ? ok(r3.message) : err(r3.message);
            return;
        }
        var a = e.target.closest('[data-compra-anular]');
        if (a) {
            var c = await Swal.fire({ icon: 'warning', title: 'Anular compra de ' + esc(a.getAttribute('data-prov')) + '?',
                text: 'Se restan las existencias que entraron y se borran sus pagos. Solo se puede si no se ha usado lo comprado.',
                showCancelButton: true, confirmButtonText: 'Anular', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc3545' });
            if (!c.isConfirmed) return;
            var r4 = await AL.api('anular_compra', { id: a.getAttribute('data-compra-anular') });
            r4.success ? ok(r4.message) : err(r4.message);
        }
    });
})();
</script>
</body>
</html>
