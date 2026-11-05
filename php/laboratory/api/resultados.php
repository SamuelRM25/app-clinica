<?php
/**
 * laboratory/api/resultados.php — Plantillas de resultados y captura de resultados (JSON).
 * POST action=install|seed|plantilla_save|plantilla_toggle|plantilla_duplicar|recibir_muestra|guardar|reabrir
 * GET  action=seed_plan|plantilla
 */
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/lab_seed.php';

header('Content-Type: application/json; charset=utf-8');

function out($d) { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function fail(string $m, int $code = 200) { http_response_code($code); out(['success' => false, 'message' => $m]); }

if (!isset($_SESSION['user_id'])) fail('Su sesión terminó. Vuelva a iniciar sesión.', 401);
$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);
$user_id = (int) $_SESSION['user_id'];
$rol = $_SESSION['tipoUsuario'] ?? '';
$puede = in_array($rol, ['admin', 'lab', 'user'], true);
if (!$puede) fail('Solo Laboratorio o un administrador puede hacer esto.', 403);

$method = $_SERVER['REQUEST_METHOD'];
$in = $method === 'POST'
    ? (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false ? (json_decode(file_get_contents('php://input'), true) ?: []) : $_POST)
    : $_GET;
$action = $in['action'] ?? ($_GET['action'] ?? '');
if (preg_match('/^(plantilla|seed|install)/', $action) && !in_array($rol, ['admin', 'lab'], true)) fail('Solo Laboratorio o un administrador puede cambiar plantillas.', 403);
if ($method === 'POST') {
    $tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($in['csrf_token'] ?? '');
    if (empty($tok) || !hash_equals($_SESSION['csrf_token'] ?? '', $tok)) fail('La página quedó desactualizada. Recárguela e intente de nuevo.');
}

try {
    $conn = (new Database())->getConnection();

    if ($action === 'install') {
        foreach (lab_install_sql() as $sql) $conn->exec($sql);
        audit_log('create', 'laboratory', 'Instalación de plantillas de resultados');
        out(['success' => true, 'message' => 'Listo.']);
    }
    if (!lab_tables_ready($conn)) fail('Las plantillas de resultados todavía no están instaladas.');

    switch ($action) {

    case 'seed_plan':
        out(['success' => true, 'plan' => lab_seed_plan($conn, $id_hospital, false, true)]);

    case 'seed': {
        $plan = lab_seed_plan($conn, $id_hospital, true, !empty($in['actualizar_precios']), $user_id);
        $nuevas = count(array_filter($plan['pruebas'], fn($p) => $p[2] === 'nueva'));
        $tpls = count(array_filter($plan['plantillas'], fn($p) => !$p[1]));
        audit_log('create', 'laboratory', "Carga inicial de laboratorio: $tpls plantillas, $nuevas pruebas nuevas");
        out(['success' => true, 'message' => "Se agregaron $tpls plantillas y $nuevas pruebas."]);
    }

    case 'plantilla': {
        $st = $conn->prepare("SELECT * FROM lab_plantillas WHERE id_plantilla = ? AND id_hospital = ?");
        $st->execute([(int) ($in['id'] ?? 0), $id_hospital]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) fail('No se encontró la plantilla.');
        $p['diseno'] = lab_tpl_normalize($p['diseno']);
        $st = $conn->prepare("SELECT nombre_prueba FROM catalogo_pruebas WHERE id_plantilla = ? AND id_hospital = ? ORDER BY nombre_prueba");
        $st->execute([$p['id_plantilla'], $id_hospital]);
        out(['success' => true, 'plantilla' => $p, 'pruebas' => $st->fetchAll(PDO::FETCH_COLUMN)]);
    }

    case 'plantilla_save': {
        $id = (int) ($in['id_plantilla'] ?? 0);
        $nombre = mb_substr(trim($in['nombre'] ?? ''), 0, 150);
        if ($nombre === '') fail('Escriba el nombre de la plantilla.');
        $d = lab_tpl_normalize($in['diseno'] ?? []);
        $filas = 0;
        foreach ($d['secciones'] as $s) $filas += in_array($s['tipo'], ['tabla', 'matriz'], true) ? count($s['filas']) : 0;
        if (!$filas) fail('Agregue al menos un examen a la plantilla.');
        $dup = $conn->prepare("SELECT COUNT(*) FROM lab_plantillas WHERE id_hospital = ? AND nombre = ? AND id_plantilla <> ?");
        $dup->execute([$id_hospital, $nombre, $id]);
        if ($dup->fetchColumn()) fail('Ya existe otra plantilla llamada "' . $nombre . '".');
        $json = json_encode($d, JSON_UNESCAPED_UNICODE);
        if ($id) {
            $st = $conn->prepare("UPDATE lab_plantillas SET nombre = ?, hoja = ?, diseno = ?, updated_by = ? WHERE id_plantilla = ? AND id_hospital = ?");
            $st->execute([$nombre, $d['hoja'], $json, $user_id, $id, $id_hospital]);
        } else {
            $conn->prepare("INSERT INTO lab_plantillas (id_hospital, nombre, hoja, diseno, updated_by) VALUES (?, ?, ?, ?, ?)")->execute([$id_hospital, $nombre, $d['hoja'], $json, $user_id]);
            $id = (int) $conn->lastInsertId();
        }
        // Pruebas que usan la plantilla
        if (isset($in['pruebas']) && is_array($in['pruebas'])) {
            $ids = array_values(array_filter(array_map('intval', $in['pruebas'])));
            $conn->prepare("UPDATE catalogo_pruebas SET id_plantilla = NULL WHERE id_plantilla = ? AND id_hospital = ?")->execute([$id, $id_hospital]);
            if ($ids) {
                $ph = implode(',', array_fill(0, count($ids), '?'));
                $conn->prepare("UPDATE catalogo_pruebas SET id_plantilla = ? WHERE id_hospital = ? AND id_prueba IN ($ph)")->execute(array_merge([$id, $id_hospital], $ids));
            }
        }
        audit_log('update', 'laboratory', "Plantilla de resultados guardada: $nombre", ['table_name' => 'lab_plantillas', 'record_id' => $id]);
        out(['success' => true, 'message' => 'Plantilla guardada.', 'id' => $id]);
    }

    case 'plantilla_toggle': {
        $conn->prepare("UPDATE lab_plantillas SET activo = 1 - activo WHERE id_plantilla = ? AND id_hospital = ?")->execute([(int) ($in['id'] ?? 0), $id_hospital]);
        out(['success' => true, 'message' => 'Plantilla actualizada.']);
    }

    case 'plantilla_duplicar': {
        $st = $conn->prepare("SELECT * FROM lab_plantillas WHERE id_plantilla = ? AND id_hospital = ?");
        $st->execute([(int) ($in['id'] ?? 0), $id_hospital]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) fail('No se encontró la plantilla.');
        $nombre = $p['nombre'] . ' (copia)';
        for ($i = 2; ; $i++) {
            $c = $conn->prepare("SELECT COUNT(*) FROM lab_plantillas WHERE id_hospital = ? AND nombre = ?");
            $c->execute([$id_hospital, $nombre]);
            if (!$c->fetchColumn()) break;
            $nombre = $p['nombre'] . " (copia $i)";
        }
        $conn->prepare("INSERT INTO lab_plantillas (id_hospital, nombre, hoja, diseno, updated_by) VALUES (?, ?, ?, ?, ?)")->execute([$id_hospital, $nombre, $p['hoja'], $p['diseno'], $user_id]);
        out(['success' => true, 'message' => 'Copia creada.', 'id' => (int) $conn->lastInsertId()]);
    }

    case 'recibir_muestra': {
        $id_orden = (int) ($in['id_orden'] ?? 0);
        $st = $conn->prepare("SELECT estado, numero_orden FROM ordenes_laboratorio WHERE id_orden = ? AND id_hospital = ?");
        $st->execute([$id_orden, $id_hospital]);
        $o = $st->fetch(PDO::FETCH_ASSOC);
        if (!$o) fail('No se encontró la orden.');
        if ($o['estado'] !== 'Pendiente') fail('La muestra de esta orden ya estaba recibida.');
        $conn->beginTransaction();
        $conn->prepare("UPDATE ordenes_laboratorio SET estado = 'Muestra_Recibida' WHERE id_orden = ? AND id_hospital = ?")->execute([$id_orden, $id_hospital]);
        try { $conn->prepare("UPDATE ordenes_laboratorio SET fecha_muestra_recibida = NOW() WHERE id_orden = ? AND id_hospital = ?")->execute([$id_orden, $id_hospital]); } catch (Throwable $e) {}
        $conn->prepare("UPDATE orden_pruebas SET estado = 'Muestra_Recibida' WHERE id_orden = ? AND estado = 'Pendiente'")->execute([$id_orden]);
        try { $conn->prepare("UPDATE orden_pruebas SET fecha_muestra_recibida = NOW() WHERE id_orden = ? AND fecha_muestra_recibida IS NULL")->execute([$id_orden]); } catch (Throwable $e) {}
        $conn->commit();
        audit_log('update', 'laboratory', "Muestra recibida: orden {$o['numero_orden']}", ['table_name' => 'ordenes_laboratorio', 'record_id' => $id_orden]);
        out(['success' => true, 'message' => 'Muestra recibida.']);
    }

    case 'guardar': {
        $id_orden = (int) ($in['id_orden'] ?? 0);
        $finalizar = !empty($in['finalizar']);
        $res = $in['r'] ?? [];
        $obs = $in['obs'] ?? [];
        $st = $conn->prepare("SELECT estado, numero_orden FROM ordenes_laboratorio WHERE id_orden = ? AND id_hospital = ?");
        $st->execute([$id_orden, $id_hospital]);
        $o = $st->fetch(PDO::FETCH_ASSOC);
        if (!$o) fail('No se encontró la orden.');
        if ($o['estado'] === 'Completada' || $o['estado'] === 'Validada') fail('Los resultados ya están finalizados. Use "Corregir" para editarlos.');

        $st = $conn->prepare("SELECT op.id_orden_prueba, op.estado, cp.nombre_prueba, cp.id_plantilla, lp.diseno, lp.activo
                              FROM orden_pruebas op JOIN catalogo_pruebas cp ON cp.id_prueba = op.id_prueba
                              LEFT JOIN lab_plantillas lp ON lp.id_plantilla = cp.id_plantilla AND lp.id_hospital = ?
                              WHERE op.id_orden = ? AND op.estado <> 'Devuelto'");
        $st->execute([$id_hospital, $id_orden]);
        $pruebas = $st->fetchAll(PDO::FETCH_ASSOC);
        $prev = $conn->prepare("SELECT plantilla, valores FROM lab_resultados WHERE id_orden_prueba = ?");

        $conn->beginTransaction();
        $up = $conn->prepare("INSERT INTO lab_resultados (id_hospital, id_orden, id_orden_prueba, id_plantilla, plantilla, valores, observaciones, guardado_por, fecha_guardado)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                              ON DUPLICATE KEY UPDATE valores = VALUES(valores), observaciones = VALUES(observaciones), guardado_por = VALUES(guardado_por), fecha_guardado = NOW()");
        $faltan = [];
        foreach ($pruebas as $p) {
            $idop = (int) $p['id_orden_prueba'];
            $prev->execute([$idop]);
            $ya = $prev->fetch(PDO::FETCH_ASSOC);
            $tpl = $ya ? lab_tpl_normalize($ya['plantilla']) : ($p['diseno'] ? lab_tpl_normalize($p['diseno']) : null);
            if (!$tpl) { $faltan[] = $p['nombre_prueba'] . ' (sin plantilla)'; continue; }
            // Solo se guardan llaves de la plantilla
            $validas = [];
            foreach ($tpl['secciones'] as $s) {
                if ($s['tipo'] === 'tabla') foreach ($s['filas'] as $f) $validas[$f['id']] = true;
                if ($s['tipo'] === 'matriz') foreach ($s['filas'] as $f) foreach ($s['columnas'] as $ci => $c) $validas[$f['id'] . '|' . $ci] = true;
            }
            $vals = [];
            foreach ((array) ($res[$idop] ?? []) as $k => $v) {
                if (isset($validas[$k])) $vals[$k] = mb_substr(trim((string) $v), 0, 200);
            }
            $o_txt = mb_substr(trim((string) ($obs[$idop] ?? '')), 0, 2000);
            $up->execute([$id_hospital, $id_orden, $idop, $p['id_plantilla'], json_encode($tpl, JSON_UNESCAPED_UNICODE), json_encode($vals, JSON_UNESCAPED_UNICODE), $o_txt ?: null, $user_id]);
            if (!lab_tiene_valores($vals)) $faltan[] = $p['nombre_prueba'];
            elseif (in_array($p['estado'], ['Pendiente', 'Muestra_Recibida'], true)) {
                $conn->prepare("UPDATE orden_pruebas SET estado = 'En_Proceso' WHERE id_orden_prueba = ?")->execute([$idop]);
            }
        }
        if ($finalizar && $faltan) {
            $conn->rollBack();
            fail('No se puede finalizar: falta resultado en ' . implode(', ', $faltan) . '.');
        }
        if ($finalizar) {
            $conn->prepare("UPDATE lab_resultados SET finalizado = 1, finalizado_por = ?, fecha_finalizado = NOW() WHERE id_orden = ? AND id_hospital = ?")->execute([$user_id, $id_orden, $id_hospital]);
            $conn->prepare("UPDATE orden_pruebas SET estado = 'Validada' WHERE id_orden = ? AND estado <> 'Devuelto'")->execute([$id_orden]);
            try { $conn->prepare("UPDATE orden_pruebas SET fecha_validada = NOW(), validado_por = ? WHERE id_orden = ? AND estado = 'Validada'")->execute([$user_id, $id_orden]); } catch (Throwable $e) {}
            $conn->prepare("UPDATE ordenes_laboratorio SET estado = 'Completada' WHERE id_orden = ? AND id_hospital = ?")->execute([$id_orden, $id_hospital]);
        } else {
            $conn->prepare("UPDATE ordenes_laboratorio SET estado = 'En_Proceso' WHERE id_orden = ? AND id_hospital = ? AND estado IN ('Pendiente', 'Muestra_Recibida')")->execute([$id_orden, $id_hospital]);
        }
        $conn->commit();
        audit_log('update', 'laboratory', ($finalizar ? 'Resultados finalizados' : 'Resultados guardados') . ": orden {$o['numero_orden']}", ['table_name' => 'lab_resultados', 'record_id' => $id_orden]);
        out(['success' => true, 'message' => $finalizar ? 'Resultados finalizados. Ya aparecen en la historia clínica.' : 'Avance guardado.', 'faltan' => $faltan]);
    }

    case 'reabrir': {
        $id_orden = (int) ($in['id_orden'] ?? 0);
        $st = $conn->prepare("SELECT numero_orden FROM ordenes_laboratorio WHERE id_orden = ? AND id_hospital = ? AND estado IN ('Completada', 'Validada')");
        $st->execute([$id_orden, $id_hospital]);
        $num = $st->fetchColumn();
        if (!$num) fail('La orden no está finalizada.');
        $motivo = trim($in['motivo'] ?? '');
        if ($motivo === '') fail('Escriba el motivo de la corrección.');
        $conn->beginTransaction();
        $conn->prepare("UPDATE lab_resultados SET finalizado = 0 WHERE id_orden = ? AND id_hospital = ?")->execute([$id_orden, $id_hospital]);
        $conn->prepare("UPDATE orden_pruebas SET estado = 'En_Proceso' WHERE id_orden = ? AND estado = 'Validada'")->execute([$id_orden]);
        $conn->prepare("UPDATE ordenes_laboratorio SET estado = 'En_Proceso' WHERE id_orden = ? AND id_hospital = ?")->execute([$id_orden, $id_hospital]);
        $conn->commit();
        audit_log('update', 'laboratory', "Resultados reabiertos para corregir: orden $num. Motivo: $motivo", ['table_name' => 'lab_resultados', 'record_id' => $id_orden]);
        out(['success' => true, 'message' => 'Puede corregir los resultados.']);
    }

    default:
        fail('Acción no reconocida.');
    }
} catch (Throwable $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log('laboratory/api/resultados.php [' . $action . ']: ' . $e->getMessage());
    fail('No se pudo completar la acción. Intente de nuevo.');
}
