<?php
/**
 * almacen/api.php — Acciones del almacén de Laboratorio y Quirófano (JSON).
 * GET  ?action=buscar|kardex|compra|receta|cirugia_insumos|plantilla
 * POST action=install|save_item|toggle_item|uso|ajuste|save_compra|pagar_compra|anular_compra
 *             |save_corte|save_receta|cirugia_insumo_add|cirugia_insumo_devolver|save_plantilla|toggle_plantilla
 */
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

function out($data) { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function fail(string $msg, int $code = 200) { http_response_code($code); out(['success' => false, 'message' => $msg]); }

if (!isset($_SESSION['user_id'])) fail('Su sesión terminó. Vuelva a iniciar sesión.', 401);

$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);
$user_id = (int) $_SESSION['user_id'];
$rol = $_SESSION['tipoUsuario'] ?? '';
$areas_ok = area_allowed_for_user();
$is_admin = $rol === 'admin';

$method = $_SERVER['REQUEST_METHOD'];
$in = $method === 'POST'
    ? (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false ? (json_decode(file_get_contents('php://input'), true) ?: []) : $_POST)
    : $_GET;
$action = $in['action'] ?? '';

if ($method === 'POST') {
    $tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($in['csrf_token'] ?? '');
    if (empty($tok) || !hash_equals($_SESSION['csrf_token'] ?? '', $tok)) fail('La página quedó desactualizada. Recárguela e intente de nuevo.');
}

function need_area(string $area) {
    global $areas_ok;
    if (!isset(AREAS_ALMACEN[$area]) || !in_array($area, $areas_ok, true)) fail('No tiene acceso al almacén de esta área.', 403);
    return $area;
}
function num($v): float { return round((float) str_replace(',', '.', (string) $v), 3); }
function item_area($conn, int $id_hospital, int $id_item): array {
    $st = $conn->prepare("SELECT * FROM area_items WHERE id_item = ? AND id_hospital = ?");
    $st->execute([$id_item, $id_hospital]);
    $it = $st->fetch(PDO::FETCH_ASSOC);
    if (!$it) fail('No se encontró el artículo.');
    need_area($it['area']);
    return $it;
}

try {
    $conn = (new Database())->getConnection();

    // ── Instalación (solo admin) ─────────────────────────────────────────────
    if ($action === 'install') {
        if (!$is_admin) fail('Solo un administrador puede instalar el almacén.', 403);
        foreach (area_install_sql() as $sql) $conn->exec($sql);
        audit_log('create', 'almacen', 'Instalación de tablas del almacén por área');
        out(['success' => true, 'message' => 'Almacén instalado.']);
    }

    if (!area_tables_ready($conn)) fail('El almacén todavía no está instalado. Un administrador debe instalarlo desde Almacén.');

    switch ($action) {

    // ── Búsqueda para recetas, uso y compras ────────────────────────────────
    case 'buscar': {
        $area = need_area($in['area'] ?? '');
        $q = trim($in['q'] ?? '');
        $like = '%' . $q . '%';
        $st = $conn->prepare("SELECT id_item AS id, 'area' AS fuente, nombre, unidad, presentacion, stock, costo_unitario, codigo
                              FROM area_items WHERE id_hospital = ? AND area = ? AND activo = 1
                              AND (? = '' OR nombre LIKE ? OR codigo LIKE ? OR categoria LIKE ?)
                              ORDER BY nombre LIMIT 25");
        $st->execute([$id_hospital, $area, $q, $like, $like, $like]);
        $res = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($in['farmacia']) && $area === 'quirofano' && strlen($q) >= 1) {
            $st = $conn->prepare("SELECT id_inventario AS id, 'farmacia' AS fuente, nom_medicamento AS nombre, 'unidad' AS unidad,
                                         presentacion_med AS presentacion, stock_quirofano AS stock, 0 AS costo_unitario, codigo_barras AS codigo
                                  FROM inventario WHERE id_hospital = ? AND (nom_medicamento LIKE ? OR codigo_barras LIKE ? OR mol_medicamento LIKE ?)
                                  ORDER BY stock_quirofano > 0 DESC, nom_medicamento LIMIT 15");
            $st->execute([$id_hospital, $like, $like, $like]);
            $res = array_merge($res, $st->fetchAll(PDO::FETCH_ASSOC));
        }
        out(['success' => true, 'items' => $res]);
    }

    case 'kardex': {
        $it = item_area($conn, $id_hospital, (int) ($in['id_item'] ?? 0));
        $st = $conn->prepare("SELECT m.fecha, m.tipo, m.cantidad, m.stock_despues, m.documento, m.nota,
                                     TRIM(CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, ''))) AS usuario
                              FROM area_movimientos m LEFT JOIN usuarios u ON u.idUsuario = m.id_usuario
                              WHERE m.id_item = ? AND m.id_hospital = ? ORDER BY m.fecha DESC, m.id_mov DESC LIMIT 150");
        $st->execute([$it['id_item'], $id_hospital]);
        out(['success' => true, 'item' => $it, 'movs' => $st->fetchAll(PDO::FETCH_ASSOC), 'tipos' => AREA_MOV_TIPOS]);
    }

    // ── Artículos ────────────────────────────────────────────────────────────
    case 'save_item': {
        $id = (int) ($in['id_item'] ?? 0);
        $nombre = trim($in['nombre'] ?? '');
        if ($nombre === '') fail('Escriba el nombre del artículo.');
        $unidad = trim($in['unidad'] ?? '') ?: 'unidad';
        $f = [
            trim($in['codigo'] ?? '') ?: null, $nombre, trim($in['categoria'] ?? '') ?: null, $unidad,
            trim($in['presentacion'] ?? '') ?: null, max(0, num($in['stock_minimo'] ?? 0)),
            ($in['fecha_vencimiento'] ?? '') ?: null, trim($in['lote'] ?? '') ?: null, trim($in['ubicacion'] ?? '') ?: null,
        ];
        $conn->beginTransaction();
        if ($id) {
            $it = item_area($conn, $id_hospital, $id);
            $conn->prepare("UPDATE area_items SET codigo=?, nombre=?, categoria=?, unidad=?, presentacion=?, stock_minimo=?, fecha_vencimiento=?, lote=?, ubicacion=? WHERE id_item=? AND id_hospital=?")
                ->execute(array_merge($f, [$id, $id_hospital]));
            if (isset($in['costo_unitario']) && $is_admin) {
                $conn->prepare("UPDATE area_items SET costo_unitario = ? WHERE id_item = ? AND id_hospital = ?")->execute([max(0, (float) $in['costo_unitario']), $id, $id_hospital]);
            }
            $msg = 'Artículo actualizado.';
        } else {
            $area = need_area($in['area'] ?? '');
            $dup = $conn->prepare("SELECT COUNT(*) FROM area_items WHERE id_hospital = ? AND area = ? AND activo = 1 AND nombre = ?");
            $dup->execute([$id_hospital, $area, $nombre]);
            if ($dup->fetchColumn() > 0) { $conn->rollBack(); fail('Ya existe "' . $nombre . '" en este almacén.'); }
            $conn->prepare("INSERT INTO area_items (codigo, nombre, categoria, unidad, presentacion, stock_minimo, fecha_vencimiento, lote, ubicacion, costo_unitario, id_hospital, area)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute(array_merge($f, [max(0, (float) ($in['costo_unitario'] ?? 0)), $id_hospital, $area]));
            $id = (int) $conn->lastInsertId();
            $inicial = max(0, num($in['stock_inicial'] ?? 0));
            if ($inicial > 0) {
                area_mover($conn, $id_hospital, $id, $inicial, 'inicial', ['nota' => 'Existencia al crear el artículo']);
            }
            $msg = 'Artículo creado.';
        }
        $conn->commit();
        out(['success' => true, 'message' => $msg, 'id_item' => $id]);
    }

    case 'toggle_item': {
        $it = item_area($conn, $id_hospital, (int) ($in['id_item'] ?? 0));
        $nuevo = $it['activo'] ? 0 : 1;
        if (!$nuevo && (float) $it['stock'] > 0 && !$is_admin) fail('Solo puede desactivar artículos sin existencia. Registre el uso o haga un corte primero.');
        $conn->prepare("UPDATE area_items SET activo = ? WHERE id_item = ? AND id_hospital = ?")->execute([$nuevo, $it['id_item'], $id_hospital]);
        out(['success' => true, 'message' => $nuevo ? 'Artículo activado.' : 'Artículo desactivado.']);
    }

    // ── Uso manual (varios artículos a la vez) ───────────────────────────────
    case 'uso': {
        $lineas = $in['items'] ?? [];
        $motivo = trim($in['motivo'] ?? '');
        if (!$lineas) fail('Agregue al menos un artículo.');
        if ($motivo === '') fail('Escriba para qué se usó (ej.: control de calidad, se derramó, paciente X).');
        $conn->beginTransaction();
        $n = 0;
        foreach ($lineas as $l) {
            $cant = num($l['cantidad'] ?? 0);
            if ($cant <= 0) continue;
            item_area($conn, $id_hospital, (int) $l['id_item']);
            area_mover($conn, $id_hospital, (int) $l['id_item'], -$cant, 'uso', ['nota' => $motivo, 'documento' => trim($in['documento'] ?? '') ?: null]);
            $n++;
        }
        if (!$n) { $conn->rollBack(); fail('Las cantidades deben ser mayores que cero.'); }
        $conn->commit();
        out(['success' => true, 'message' => $n === 1 ? 'Uso registrado.' : "Uso registrado ($n artículos)."]);
    }

    case 'ajuste': {
        if (!$is_admin) fail('Solo un administrador puede ajustar existencias. Use "Corte de inventario".', 403);
        $it = item_area($conn, $id_hospital, (int) ($in['id_item'] ?? 0));
        $nuevo = num($in['stock_nuevo'] ?? -1);
        if ($nuevo < 0) fail('La existencia no puede ser negativa.');
        $motivo = trim($in['motivo'] ?? '');
        if ($motivo === '') fail('Escriba el motivo del ajuste.');
        $conn->beginTransaction();
        $delta = $nuevo - (float) $it['stock'];
        if (abs($delta) > 0.0005) area_mover($conn, $id_hospital, (int) $it['id_item'], $delta, 'ajuste', ['nota' => $motivo, 'permitir_negativo' => true]);
        $conn->commit();
        out(['success' => true, 'message' => 'Existencia ajustada.']);
    }

    // ── Compras ──────────────────────────────────────────────────────────────
    case 'save_compra': {
        $area = need_area($in['area'] ?? '');
        if (!purchases_have_area($conn)) fail('Falta la columna "area" en compras. Un administrador debe instalar el almacén.');
        $h = $in['header'] ?? [];
        $items = $in['items'] ?? [];
        $prov = trim($h['provider_name'] ?? '');
        $fecha = $h['purchase_date'] ?? '';
        if ($prov === '') fail('Escriba el proveedor.');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) fail('Elija la fecha de compra.');
        $tipo_pago = in_array($h['tipo_pago'] ?? '', ['Contado', 'Credito 30', 'Credito 60'], true) ? $h['tipo_pago'] : 'Contado';
        $doc_tipo = in_array($h['document_type'] ?? '', ['Factura', 'Nota de Envío', 'Consumidor Final', 'Recibo'], true) ? $h['document_type'] : 'Factura';
        $lineas = [];
        $total = 0;
        foreach ($items as $l) {
            $cant = num($l['cantidad'] ?? 0);
            $costo = round((float) ($l['costo'] ?? 0), 4);
            if ($cant <= 0) continue;
            if ($costo < 0) fail('El costo no puede ser negativo.');
            $lineas[] = $l + ['_cant' => $cant, '_costo' => $costo, '_sub' => round($cant * $costo, 2)];
            $total += round($cant * $costo, 2);
        }
        if (!$lineas) fail('Agregue al menos un artículo con cantidad.');
        $contado = $tipo_pago === 'Contado';

        $conn->beginTransaction();
        $conn->prepare("INSERT INTO purchase_headers (document_type, document_number, tipo_pago, provider_name, purchase_date, total_amount, status, paid_amount, payment_status, created_by, id_hospital, area)
                        VALUES (?, ?, ?, ?, ?, ?, 'Completado', ?, ?, ?, ?, ?)")
            ->execute([$doc_tipo, trim($h['document_number'] ?? ''), $tipo_pago, $prov, $fecha, $total, $contado ? $total : 0, $contado ? 'Pagado' : 'Pendiente', $user_id, $id_hospital, $area]);
        $hid = (int) $conn->lastInsertId();
        if ($contado && $total > 0) {
            $conn->prepare("INSERT INTO purchase_payments (purchase_header_id, amount, payment_date, payment_method, notes, id_hospital) VALUES (?, ?, ?, 'Contado', ?, ?)")
                ->execute([$hid, $total, $fecha, 'Pago automático - Al Contado (' . area_label($area) . ')', $id_hospital]);
        }
        $ins = $conn->prepare("INSERT INTO area_compra_items (id_hospital, purchase_header_id, id_item, cantidad, costo_unitario, subtotal, fecha_vencimiento, lote) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $doc = trim(($doc_tipo . ' ' . ($h['document_number'] ?? '')));
        foreach ($lineas as $l) {
            $id_item = (int) ($l['id_item'] ?? 0);
            if (!$id_item) {
                // Artículo nuevo desde la compra
                $nombre = trim($l['nombre'] ?? '');
                if ($nombre === '') { $conn->rollBack(); fail('Hay una fila sin artículo.'); }
                $conn->prepare("INSERT INTO area_items (id_hospital, area, nombre, unidad, categoria) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$id_hospital, $area, $nombre, trim($l['unidad'] ?? '') ?: 'unidad', trim($l['categoria'] ?? '') ?: null]);
                $id_item = (int) $conn->lastInsertId();
            } else {
                $it = item_area($conn, $id_hospital, $id_item);
                if ($it['area'] !== $area) { $conn->rollBack(); fail('"' . $it['nombre'] . '" es de otra área.'); }
            }
            $venc = ($l['fecha_vencimiento'] ?? '') ?: null;
            $lote = trim($l['lote'] ?? '') ?: null;
            $ins->execute([$id_hospital, $hid, $id_item, $l['_cant'], $l['_costo'], $l['_sub'], $venc, $lote]);
            area_mover($conn, $id_hospital, $id_item, $l['_cant'], 'compra', [
                'costo' => $l['_costo'], 'ref_tipo' => 'compra', 'ref_id' => $hid, 'documento' => $doc, 'nota' => $prov,
            ]);
            if ($venc || $lote) {
                // Se guarda el vencimiento más próximo y el último lote
                $conn->prepare("UPDATE area_items SET fecha_vencimiento = CASE WHEN ? IS NULL THEN fecha_vencimiento
                                    WHEN fecha_vencimiento IS NULL OR stock - ? <= 0 OR ? < fecha_vencimiento THEN ? ELSE fecha_vencimiento END,
                                    lote = COALESCE(?, lote) WHERE id_item = ?")
                    ->execute([$venc, $l['_cant'], $venc, $venc, $lote, $id_item]);
            }
        }
        $conn->commit();
        audit_log('create', 'almacen', 'Compra de ' . area_label($area) . " #$hid - $prov - Q" . number_format($total, 2), ['table_name' => 'purchase_headers', 'record_id' => $hid]);
        out(['success' => true, 'message' => 'Compra registrada. Las existencias ya se actualizaron.', 'id' => $hid]);
    }

    case 'compra': {
        $id = (int) ($in['id'] ?? 0);
        $st = $conn->prepare("SELECT ph.*, TRIM(CONCAT(COALESCE(u.nombre,''), ' ', COALESCE(u.apellido,''))) AS usuario
                              FROM purchase_headers ph LEFT JOIN usuarios u ON u.idUsuario = ph.created_by
                              WHERE ph.id = ? AND ph.id_hospital = ?");
        $st->execute([$id, $id_hospital]);
        $h = $st->fetch(PDO::FETCH_ASSOC);
        if (!$h) fail('No se encontró la compra.');
        need_area($h['area'] ?? '');
        $st = $conn->prepare("SELECT ci.*, ai.nombre, ai.unidad FROM area_compra_items ci JOIN area_items ai ON ai.id_item = ci.id_item WHERE ci.purchase_header_id = ? AND ci.id_hospital = ?");
        $st->execute([$id, $id_hospital]);
        $items = $st->fetchAll(PDO::FETCH_ASSOC);
        $st = $conn->prepare("SELECT amount, payment_date, payment_method, notes FROM purchase_payments WHERE purchase_header_id = ? AND id_hospital = ? ORDER BY payment_date, id");
        $st->execute([$id, $id_hospital]);
        out(['success' => true, 'header' => $h, 'items' => $items, 'pagos' => $st->fetchAll(PDO::FETCH_ASSOC)]);
    }

    case 'pagar_compra': {
        $id = (int) ($in['id'] ?? 0);
        $monto = round((float) ($in['monto'] ?? 0), 2);
        $st = $conn->prepare("SELECT * FROM purchase_headers WHERE id = ? AND id_hospital = ? FOR UPDATE");
        $conn->beginTransaction();
        $st->execute([$id, $id_hospital]);
        $h = $st->fetch(PDO::FETCH_ASSOC);
        if (!$h) { $conn->rollBack(); fail('No se encontró la compra.'); }
        need_area($h['area'] ?? '');
        $saldo = round((float) $h['total_amount'] - (float) $h['paid_amount'], 2);
        if ($monto <= 0) { $conn->rollBack(); fail('Escriba el monto del abono.'); }
        if ($monto > $saldo + 0.001) { $conn->rollBack(); fail('El abono es mayor que el saldo (Q' . number_format($saldo, 2) . ').'); }
        $metodo = in_array($in['metodo'] ?? '', ['Efectivo', 'Transferencia', 'Cheque', 'Tarjeta'], true) ? $in['metodo'] : 'Efectivo';
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $in['fecha'] ?? '') ? $in['fecha'] : date('Y-m-d');
        $conn->prepare("INSERT INTO purchase_payments (purchase_header_id, amount, payment_date, payment_method, notes, id_hospital) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$id, $monto, $fecha, $metodo, trim($in['nota'] ?? '') ?: null, $id_hospital]);
        $pagado = (float) $h['paid_amount'] + $monto;
        $estado = $pagado >= (float) $h['total_amount'] - 0.001 ? 'Pagado' : 'Parcial';
        $conn->prepare("UPDATE purchase_headers SET paid_amount = ?, payment_status = ? WHERE id = ? AND id_hospital = ?")->execute([$pagado, $estado, $id, $id_hospital]);
        $conn->commit();
        audit_log('create', 'almacen', "Abono a compra #$id: Q$monto", ['table_name' => 'purchase_payments']);
        out(['success' => true, 'message' => $estado === 'Pagado' ? 'Compra pagada por completo.' : 'Abono registrado.']);
    }

    case 'anular_compra': {
        if (!$is_admin) fail('Solo un administrador puede anular compras.', 403);
        $id = (int) ($in['id'] ?? 0);
        $conn->beginTransaction();
        $st = $conn->prepare("SELECT * FROM purchase_headers WHERE id = ? AND id_hospital = ? FOR UPDATE");
        $st->execute([$id, $id_hospital]);
        $h = $st->fetch(PDO::FETCH_ASSOC);
        if (!$h) { $conn->rollBack(); fail('No se encontró la compra.'); }
        need_area($h['area'] ?? '');
        $st = $conn->prepare("SELECT id_item, SUM(cantidad) AS cant FROM area_compra_items WHERE purchase_header_id = ? AND id_hospital = ? GROUP BY id_item");
        $st->execute([$id, $id_hospital]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            try {
                area_mover($conn, $id_hospital, (int) $r['id_item'], -(float) $r['cant'], 'anulacion', [
                    'ref_tipo' => 'compra', 'ref_id' => $id, 'documento' => trim($h['document_type'] . ' ' . $h['document_number']), 'nota' => 'Compra anulada',
                ]);
            } catch (AreaStockException $e) {
                $conn->rollBack();
                fail('No se puede anular: ' . $e->getMessage() . ' Parte de esta compra ya se usó.');
            }
        }
        $conn->prepare("DELETE FROM purchase_payments WHERE purchase_header_id = ? AND id_hospital = ?")->execute([$id, $id_hospital]);
        $conn->prepare("DELETE FROM area_compra_items WHERE purchase_header_id = ? AND id_hospital = ?")->execute([$id, $id_hospital]);
        $conn->prepare("DELETE FROM purchase_headers WHERE id = ? AND id_hospital = ?")->execute([$id, $id_hospital]);
        $conn->commit();
        audit_log('delete', 'almacen', "Compra #$id anulada ({$h['provider_name']}, Q{$h['total_amount']})", ['table_name' => 'purchase_headers', 'old_data' => $h]);
        out(['success' => true, 'message' => 'Compra anulada y existencias regresadas.']);
    }

    // ── Corte de inventario ─────────────────────────────────────────────────
    case 'save_corte': {
        $area = need_area($in['area'] ?? '');
        $conteos = $in['conteos'] ?? [];
        if (!$conteos) fail('No hay conteos para guardar.');
        $conn->beginTransaction();
        $conn->prepare("INSERT INTO area_cortes (id_hospital, area, id_usuario, notas) VALUES (?, ?, ?, ?)")
            ->execute([$id_hospital, $area, $user_id, mb_substr(trim($in['notas'] ?? ''), 0, 500) ?: null]);
        $id_corte = (int) $conn->lastInsertId();
        $ins = $conn->prepare("INSERT INTO area_corte_items (id_corte, id_item, nombre, unidad, stock_sistema, stock_fisico, diferencia, costo_unitario) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $contados = 0; $con_dif = 0; $v_sis = 0; $v_fis = 0;
        foreach ($conteos as $c) {
            if (!isset($c['fisico']) || $c['fisico'] === '' || $c['fisico'] === null) continue;
            $it = item_area($conn, $id_hospital, (int) $c['id_item']);
            if ($it['area'] !== $area) continue;
            $fisico = num($c['fisico']);
            if ($fisico < 0) { $conn->rollBack(); fail('El conteo de "' . $it['nombre'] . '" no puede ser negativo.'); }
            // Se compara contra la existencia que había cuando se abrió la hoja de conteo;
            // lo que se movió después (órdenes, compras) se respeta.
            $sistema = isset($c['sistema']) ? num($c['sistema']) : (float) $it['stock'];
            $dif = round($fisico - $sistema, 3);
            $costo = (float) $it['costo_unitario'];
            $ins->execute([$id_corte, $it['id_item'], $it['nombre'], $it['unidad'], $sistema, $fisico, $dif, $costo]);
            $contados++;
            $v_sis += $sistema * $costo;
            $v_fis += $fisico * $costo;
            if (abs($dif) > 0.0005) {
                $con_dif++;
                area_mover($conn, $id_hospital, (int) $it['id_item'], $dif, 'corte', [
                    'ref_tipo' => 'corte', 'ref_id' => $id_corte, 'documento' => 'Corte #' . $id_corte,
                    'nota' => 'Conteo físico: ' . area_qty($fisico) . ' (sistema ' . area_qty($sistema) . ')', 'permitir_negativo' => true,
                ]);
            }
        }
        if (!$contados) { $conn->rollBack(); fail('Escriba al menos un conteo.'); }
        $conn->prepare("UPDATE area_cortes SET items_contados = ?, items_diferencia = ?, valor_sistema = ?, valor_fisico = ?, valor_diferencia = ? WHERE id_corte = ?")
            ->execute([$contados, $con_dif, round($v_sis, 2), round($v_fis, 2), round($v_fis - $v_sis, 2), $id_corte]);
        $conn->commit();
        audit_log('create', 'almacen', 'Corte de inventario #' . $id_corte . ' (' . area_label($area) . "): $contados contados, $con_dif con diferencia", ['table_name' => 'area_cortes', 'record_id' => $id_corte]);
        out(['success' => true, 'message' => 'Corte guardado.', 'id_corte' => $id_corte]);
    }

    // ── Recetas (insumos por prueba / plantilla) ────────────────────────────
    case 'receta': {
        $ref_tipo = $in['ref_tipo'] ?? '';
        $ref_id = (int) ($in['ref_id'] ?? 0);
        need_area($ref_tipo === 'prueba' ? 'laboratorio' : 'quirofano');
        out(['success' => true, 'items' => area_receta($conn, $id_hospital, $ref_tipo, $ref_id)]);
    }

    case 'save_receta': {
        $ref_tipo = $in['ref_tipo'] ?? '';
        $ref_id = (int) ($in['ref_id'] ?? 0);
        if ($ref_tipo !== 'prueba' || !$ref_id) fail('Datos incompletos.');
        need_area('laboratorio');
        $st = $conn->prepare("SELECT nombre_prueba FROM catalogo_pruebas WHERE id_prueba = ? AND id_hospital = ?");
        $st->execute([$ref_id, $id_hospital]);
        $prueba = $st->fetchColumn();
        if ($prueba === false) fail('No se encontró la prueba.');
        $conn->beginTransaction();
        guardar_receta($conn, $id_hospital, 'prueba', $ref_id, $in['items'] ?? [], 'laboratorio');
        $conn->commit();
        audit_log('update', 'almacen', "Insumos de la prueba $prueba actualizados", ['table_name' => 'area_recetas', 'record_id' => $ref_id]);
        out(['success' => true, 'message' => 'Insumos guardados.']);
    }

    // ── Plantillas de quirófano ─────────────────────────────────────────────
    case 'plantilla': {
        need_area('quirofano');
        $st = $conn->prepare("SELECT * FROM qx_plantillas WHERE id_plantilla = ? AND id_hospital = ?");
        $st->execute([(int) ($in['id'] ?? 0), $id_hospital]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) fail('No se encontró la plantilla.');
        out(['success' => true, 'plantilla' => $p, 'items' => area_receta($conn, $id_hospital, 'plantilla', (int) $p['id_plantilla'])]);
    }

    case 'save_plantilla': {
        need_area('quirofano');
        $id = (int) ($in['id_plantilla'] ?? 0);
        $nombre = trim($in['nombre'] ?? '');
        if ($nombre === '') fail('Escriba el nombre de la operación o procedimiento.');
        $tipo = ($in['tipo'] ?? '') === 'Procedimiento' ? 'Procedimiento' : 'Cirugía';
        $precio = max(0, round((float) ($in['precio'] ?? 0), 2));
        $desc = mb_substr(trim($in['descripcion'] ?? ''), 0, 500) ?: null;
        $conn->beginTransaction();
        if ($id) {
            $st = $conn->prepare("UPDATE qx_plantillas SET nombre = ?, tipo = ?, precio = ?, descripcion = ? WHERE id_plantilla = ? AND id_hospital = ?");
            $st->execute([$nombre, $tipo, $precio, $desc, $id, $id_hospital]);
            $chk = $conn->prepare("SELECT COUNT(*) FROM qx_plantillas WHERE id_plantilla = ? AND id_hospital = ?");
            $chk->execute([$id, $id_hospital]);
            if (!$chk->fetchColumn()) { $conn->rollBack(); fail('No se encontró la plantilla.'); }
        } else {
            $conn->prepare("INSERT INTO qx_plantillas (id_hospital, nombre, tipo, precio, descripcion) VALUES (?, ?, ?, ?, ?)")
                ->execute([$id_hospital, $nombre, $tipo, $precio, $desc]);
            $id = (int) $conn->lastInsertId();
        }
        guardar_receta($conn, $id_hospital, 'plantilla', $id, $in['items'] ?? [], 'quirofano');
        $conn->commit();
        audit_log('update', 'almacen', "Plantilla de quirófano: $nombre", ['table_name' => 'qx_plantillas', 'record_id' => $id]);
        out(['success' => true, 'message' => 'Plantilla guardada.', 'id' => $id]);
    }

    case 'toggle_plantilla': {
        need_area('quirofano');
        $conn->prepare("UPDATE qx_plantillas SET activo = 1 - activo WHERE id_plantilla = ? AND id_hospital = ?")->execute([(int) ($in['id'] ?? 0), $id_hospital]);
        out(['success' => true, 'message' => 'Plantilla actualizada.']);
    }

    // ── Insumos del almacén en una cirugía ──────────────────────────────────
    case 'cirugia_insumos': {
        need_area('quirofano');
        out(['success' => true, 'items' => area_insumos_cirugia($conn, $id_hospital, (int) ($in['id_cirugia'] ?? 0))]);
    }

    case 'cirugia_insumo_add':
    case 'cirugia_insumo_devolver': {
        need_area('quirofano');
        $id_cir = (int) ($in['id_cirugia'] ?? 0);
        $st = $conn->prepare("SELECT numero_cirugia, estado FROM cirugias WHERE id_cirugia = ? AND id_hospital = ?");
        $st->execute([$id_cir, $id_hospital]);
        $c = $st->fetch(PDO::FETCH_ASSOC);
        if (!$c) fail('No se encontró la cirugía.');
        if (!in_array($c['estado'], ['Programada', 'En_Curso'], true)) fail('La cirugía ya está ' . strtolower(str_replace('_', ' ', $c['estado'])) . '; no se pueden cambiar sus insumos.');
        $it = item_area($conn, $id_hospital, (int) ($in['id_item'] ?? 0));
        if ($it['area'] !== 'quirofano') fail('Ese artículo no es del almacén de Quirófano.');
        $cant = num($in['cantidad'] ?? 0);
        if ($cant <= 0) fail('La cantidad debe ser mayor que cero.');
        $conn->beginTransaction();
        if ($action === 'cirugia_insumo_add') {
            area_mover($conn, $id_hospital, (int) $it['id_item'], -$cant, 'cirugia', ['ref_tipo' => 'cirugia', 'ref_id' => $id_cir, 'documento' => $c['numero_cirugia'], 'nota' => 'Agregado en la cirugía']);
            $msg = 'Insumo agregado y descontado.';
        } else {
            $st = $conn->prepare("SELECT -COALESCE(SUM(cantidad), 0) FROM area_movimientos WHERE id_hospital = ? AND ref_tipo = 'cirugia' AND ref_id = ? AND id_item = ?");
            $st->execute([$id_hospital, $id_cir, (int) $it['id_item']]);
            $salio = (float) $st->fetchColumn();
            if ($salio <= 0) { $conn->rollBack(); fail('Ese insumo no tiene salida en esta cirugía.'); }
            if ($cant > $salio + 0.0005) { $conn->rollBack(); fail('Solo salieron ' . area_qty($salio) . ' ' . $it['unidad'] . ' para esta cirugía.'); }
            area_revertir_ref($conn, $id_hospital, 'cirugia', $id_cir, 'Devuelto al almacén', (int) $it['id_item'], $cant);
            $msg = 'Insumo devuelto al almacén.';
        }
        $conn->commit();
        out(['success' => true, 'message' => $msg]);
    }

    default:
        fail('Acción no reconocida.');
    }

} catch (AreaStockException $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    fail($e->getMessage());
} catch (Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log('almacen/api.php [' . $action . ']: ' . $e->getMessage());
    fail('No se pudo completar la acción. Intente de nuevo.');
}

/** Reemplaza los insumos de una receta. */
function guardar_receta($conn, int $id_hospital, string $ref_tipo, int $ref_id, array $items, string $area): void
{
    $conn->prepare("DELETE FROM area_recetas WHERE id_hospital = ? AND ref_tipo = ? AND ref_id = ?")->execute([$id_hospital, $ref_tipo, $ref_id]);
    $ins = $conn->prepare("INSERT INTO area_recetas (id_hospital, ref_tipo, ref_id, fuente, id_item, id_inventario, cantidad) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $chkA = $conn->prepare("SELECT COUNT(*) FROM area_items WHERE id_item = ? AND id_hospital = ? AND area = ?");
    $chkF = $conn->prepare("SELECT COUNT(*) FROM inventario WHERE id_inventario = ? AND id_hospital = ?");
    $vistos = [];
    foreach ($items as $l) {
        $cant = num($l['cantidad'] ?? 0);
        $id = (int) ($l['id'] ?? 0);
        $fuente = ($l['fuente'] ?? 'area') === 'farmacia' && $area === 'quirofano' ? 'farmacia' : 'area';
        if ($cant <= 0 || !$id || isset($vistos[$fuente . $id])) continue;
        $vistos[$fuente . $id] = true;
        if ($fuente === 'area') {
            $chkA->execute([$id, $id_hospital, $area]);
            if (!$chkA->fetchColumn()) continue;
            $ins->execute([$id_hospital, $ref_tipo, $ref_id, 'area', $id, null, $cant]);
        } else {
            $chkF->execute([$id, $id_hospital]);
            if (!$chkF->fetchColumn()) continue;
            $ins->execute([$id_hospital, $ref_tipo, $ref_id, 'farmacia', null, $id, $cant]);
        }
    }
}
