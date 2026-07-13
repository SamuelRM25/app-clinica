<?php
ob_start();
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

verify_session();

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
if ($id_hospital === 0) {
    echo json_encode(['success' => false, 'message' => 'Hospital no identificado']);
    exit;
}

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!hash_equals($_SESSION['csrf_token'] ?? '', $data['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

if (!$data || !isset($data['action'])) {
    echo json_encode(['success' => false, 'message' => 'Acción no especificada']);
    exit;
}

$action = $data['action'];

try {
    $database = new Database();
    $conn = $database->getConnection();
    $conn->beginTransaction();

    if ($action === 'delete') {
        $id_tarifa = (int)($data['id_tarifa'] ?? 0);
        if ($id_tarifa === 0) {
            echo json_encode(['success' => false, 'message' => 'ID de tarifa inválido']);
            exit;
        }

        $fetchStmt = $conn->prepare("SELECT tipo_servicio, id_medico, nombre_servicio, precio_normal, precio_inhabil, costo_normal, costo_inhabil, costo_digital_normal, costo_digital_inhabil, costo_impreso_normal, costo_impreso_inhabil FROM tarifas_servicios WHERE id_tarifa = ? AND id_hospital = ?");
        $fetchStmt->execute([$id_tarifa, $id_hospital]);
        $oldData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("DELETE FROM tarifas_servicios WHERE id_tarifa = ? AND id_hospital = ?");
        $stmt->execute([$id_tarifa, $id_hospital]);
        $conn->commit();

        audit_log('delete', 'tarifas', "Tarifa eliminada: tipo={$data['tipo_servicio']}", [
            'table_name' => 'tarifas_servicios',
            'record_id' => $id_tarifa,
            'old_data' => $oldData
        ]);

        echo json_encode(['success' => true, 'message' => 'Tarifa eliminada']);
        exit;
    }

    if ($action === 'create') {
        $tipo = $data['tipo_servicio'] ?? '';
        $id_medico = isset($data['id_medico']) && $data['id_medico'] > 0 ? (int)$data['id_medico'] : null;
        $nombre = $data['nombre_servicio'] ?? null;
        $normal = (float)($data['precio_normal'] ?? 0);
        $inhabil = (float)($data['precio_inhabil'] ?? 0);
        $radio = isset($data['precio_radio']) ? (float)$data['precio_radio'] : null;
        $region = isset($data['region_count']) && $data['region_count'] > 0 ? (int)$data['region_count'] : null;
        $costo_normal  = isset($data['costo_normal'])  && $data['costo_normal']  !== '' && $data['costo_normal']  !== null ? (float)$data['costo_normal']  : null;
        $costo_inhabil = isset($data['costo_inhabil']) && $data['costo_inhabil'] !== '' && $data['costo_inhabil'] !== null ? (float)$data['costo_inhabil'] : null;
        $costo_digital_normal  = isset($data['costo_digital_normal'])  && $data['costo_digital_normal']  !== '' && $data['costo_digital_normal']  !== null ? (float)$data['costo_digital_normal']  : null;
        $costo_digital_inhabil = isset($data['costo_digital_inhabil']) && $data['costo_digital_inhabil'] !== '' && $data['costo_digital_inhabil'] !== null ? (float)$data['costo_digital_inhabil'] : null;
        $costo_impreso_normal  = isset($data['costo_impreso_normal'])  && $data['costo_impreso_normal']  !== '' && $data['costo_impreso_normal']  !== null ? (float)$data['costo_impreso_normal']  : null;
        $costo_impreso_inhabil = isset($data['costo_impreso_inhabil']) && $data['costo_impreso_inhabil'] !== '' && $data['costo_impreso_inhabil'] !== null ? (float)$data['costo_impreso_inhabil'] : null;

        $stmt = $conn->prepare("
            INSERT INTO tarifas_servicios (id_hospital, tipo_servicio, id_medico, nombre_servicio,
                precio_normal, precio_inhabil, precio_radio, region_count,
                costo_normal, costo_inhabil,
                costo_digital_normal, costo_digital_inhabil,
                costo_impreso_normal, costo_impreso_inhabil)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$id_hospital, $tipo, $id_medico, $nombre,
            $normal, $inhabil, $radio, $region,
            $costo_normal, $costo_inhabil,
            $costo_digital_normal, $costo_digital_inhabil,
            $costo_impreso_normal, $costo_impreso_inhabil]);
        $newId = $conn->lastInsertId();
        $conn->commit();

        audit_log('create', 'tarifas', "Nueva tarifa creada: tipo=$tipo, precio=$normal, costo=$costo_normal", [
            'table_name' => 'tarifas_servicios',
            'record_id' => (int)$newId,
            'new_data' => [
                'tipo_servicio' => $tipo,
                'id_medico' => $id_medico,
                'nombre_servicio' => $nombre,
                'precio_normal' => $normal,
                'precio_inhabil' => $inhabil,
                'precio_radio' => $radio,
                'region_count' => $region,
                'costo_normal' => $costo_normal,
                'costo_inhabil' => $costo_inhabil,
                'costo_digital_normal'  => $costo_digital_normal,
                'costo_digital_inhabil' => $costo_digital_inhabil,
                'costo_impreso_normal'  => $costo_impreso_normal,
                'costo_impreso_inhabil' => $costo_impreso_inhabil
            ]
        ]);

        echo json_encode(['success' => true, 'message' => 'Tarifa creada', 'id_tarifa' => (int)$newId]);
        exit;
    }

    if ($action === 'update') {
        $id_tarifa = (int)($data['id_tarifa'] ?? 0);
        $normal = (float)($data['precio_normal'] ?? 0);
        $inhabil = (float)($data['precio_inhabil'] ?? 0);
        $radio = isset($data['precio_radio']) ? (float)$data['precio_radio'] : null;
        $costo_normal  = isset($data['costo_normal'])  && $data['costo_normal']  !== '' && $data['costo_normal']  !== null ? (float)$data['costo_normal']  : null;
        $costo_inhabil = isset($data['costo_inhabil']) && $data['costo_inhabil'] !== '' && $data['costo_inhabil'] !== null ? (float)$data['costo_inhabil'] : null;
        $costo_digital_normal  = isset($data['costo_digital_normal'])  && $data['costo_digital_normal']  !== '' && $data['costo_digital_normal']  !== null ? (float)$data['costo_digital_normal']  : null;
        $costo_digital_inhabil = isset($data['costo_digital_inhabil']) && $data['costo_digital_inhabil'] !== '' && $data['costo_digital_inhabil'] !== null ? (float)$data['costo_digital_inhabil'] : null;
        $costo_impreso_normal  = isset($data['costo_impreso_normal'])  && $data['costo_impreso_normal']  !== '' && $data['costo_impreso_normal']  !== null ? (float)$data['costo_impreso_normal']  : null;
        $costo_impreso_inhabil = isset($data['costo_impreso_inhabil']) && $data['costo_impreso_inhabil'] !== '' && $data['costo_impreso_inhabil'] !== null ? (float)$data['costo_impreso_inhabil'] : null;

        $fetchStmt = $conn->prepare("SELECT tipo_servicio, id_medico, nombre_servicio, precio_normal, precio_inhabil, precio_radio, costo_normal, costo_inhabil, costo_digital_normal, costo_digital_inhabil, costo_impreso_normal, costo_impreso_inhabil FROM tarifas_servicios WHERE id_tarifa = ? AND id_hospital = ?");
        $fetchStmt->execute([$id_tarifa, $id_hospital]);
        $oldData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("
            UPDATE tarifas_servicios
            SET precio_normal = ?, precio_inhabil = ?, precio_radio = ?,
                costo_normal = ?, costo_inhabil = ?,
                costo_digital_normal = ?, costo_digital_inhabil = ?,
                costo_impreso_normal = ?, costo_impreso_inhabil = ?
            WHERE id_tarifa = ? AND id_hospital = ?
        ");
        $stmt->execute([$normal, $inhabil, $radio,
            $costo_normal, $costo_inhabil,
            $costo_digital_normal, $costo_digital_inhabil,
            $costo_impreso_normal, $costo_impreso_inhabil,
            $id_tarifa, $id_hospital]);
        $conn->commit();

        audit_log('update', 'tarifas', "Tarifa actualizada: ID=$id_tarifa", [
            'table_name' => 'tarifas_servicios',
            'record_id' => $id_tarifa,
            'old_data' => $oldData,
            'new_data' => [
                'precio_normal' => $normal,
                'precio_inhabil' => $inhabil,
                'precio_radio' => $radio,
                'costo_normal' => $costo_normal,
                'costo_inhabil' => $costo_inhabil,
                'costo_digital_normal'  => $costo_digital_normal,
                'costo_digital_inhabil' => $costo_digital_inhabil,
                'costo_impreso_normal'  => $costo_impreso_normal,
                'costo_impreso_inhabil' => $costo_impreso_inhabil
            ]
        ]);

        echo json_encode(['success' => true, 'message' => 'Tarifa actualizada']);
        exit;
    }

    if ($action === 'batch_save') {
        $items = $data['tarifas'] ?? [];

        $stmtInsert = $conn->prepare("
            INSERT INTO tarifas_servicios (id_hospital, tipo_servicio, id_medico, nombre_servicio,
                precio_normal, precio_inhabil, precio_radio, region_count,
                costo_normal, costo_inhabil,
                costo_digital_normal, costo_digital_inhabil,
                costo_impreso_normal, costo_impreso_inhabil)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmtUpdate = $conn->prepare("
            UPDATE tarifas_servicios
            SET precio_normal = ?, precio_inhabil = ?, precio_radio = ?,
                costo_normal = ?, costo_inhabil = ?,
                costo_digital_normal = ?, costo_digital_inhabil = ?,
                costo_impreso_normal = ?, costo_impreso_inhabil = ?
            WHERE id_tarifa = ? AND id_hospital = ?
        ");

        $updated = 0;
        $inserted = 0;
        foreach ($items as $item) {
            $tipo = $item['tipo_servicio'] ?? '';
            $id_medico = isset($item['id_medico']) && $item['id_medico'] > 0 ? (int)$item['id_medico'] : null;
            $nombre = $item['nombre_servicio'] ?? null;
            $normal = (float)($item['precio_normal'] ?? 0);
            $inhabil = (float)($item['precio_inhabil'] ?? 0);
            $radio = isset($item['precio_radio']) ? (float)$item['precio_radio'] : null;
            $region = isset($item['region_count']) && $item['region_count'] > 0 ? (int)$item['region_count'] : null;
            $costo_normal  = isset($item['costo_normal'])  && $item['costo_normal']  !== '' && $item['costo_normal']  !== null ? (float)$item['costo_normal']  : null;
            $costo_inhabil = isset($item['costo_inhabil']) && $item['costo_inhabil'] !== '' && $item['costo_inhabil'] !== null ? (float)$item['costo_inhabil'] : null;
            $costo_digital_normal  = isset($item['costo_digital_normal'])  && $item['costo_digital_normal']  !== '' && $item['costo_digital_normal']  !== null ? (float)$item['costo_digital_normal']  : null;
            $costo_digital_inhabil = isset($item['costo_digital_inhabil']) && $item['costo_digital_inhabil'] !== '' && $item['costo_digital_inhabil'] !== null ? (float)$item['costo_digital_inhabil'] : null;
            $costo_impreso_normal  = isset($item['costo_impreso_normal'])  && $item['costo_impreso_normal']  !== '' && $item['costo_impreso_normal']  !== null ? (float)$item['costo_impreso_normal']  : null;
            $costo_impreso_inhabil = isset($item['costo_impreso_inhabil']) && $item['costo_impreso_inhabil'] !== '' && $item['costo_impreso_inhabil'] !== null ? (float)$item['costo_impreso_inhabil'] : null;

            $id_tarifa = isset($item['id_tarifa']) && (int)$item['id_tarifa'] > 0 ? (int)$item['id_tarifa'] : null;

            if ($id_tarifa) {
                $stmtUpdate->execute([$normal, $inhabil, $radio,
                    $costo_normal, $costo_inhabil,
                    $costo_digital_normal, $costo_digital_inhabil,
                    $costo_impreso_normal, $costo_impreso_inhabil,
                    $id_tarifa, $id_hospital]);
                $updated += $stmtUpdate->rowCount();
            } else {
                $stmtInsert->execute([$id_hospital, $tipo, $id_medico, $nombre,
                    $normal, $inhabil, $radio, $region,
                    $costo_normal, $costo_inhabil,
                    $costo_digital_normal, $costo_digital_inhabil,
                    $costo_impreso_normal, $costo_impreso_inhabil]);
                $inserted++;
            }
        }

        $conn->commit();

        audit_log('create', 'tarifas', "Tarifas batch_save: $updated actualizadas, $inserted nuevas", [
            'table_name' => 'tarifas_servicios',
            'new_data' => [
                'updated' => $updated,
                'inserted' => $inserted,
            ]
        ]);

        ob_clean();
        echo json_encode(['success' => true, 'message' => 'Tarifas guardadas', 'updated' => $updated, 'inserted' => $inserted]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Acción desconocida']);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error en save_tarifas.php: " . $e->getMessage());
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'debug'   => ($_SESSION['tipoUsuario'] ?? '') === 'admin' ? $e->getMessage() : null,
    ]);
}
