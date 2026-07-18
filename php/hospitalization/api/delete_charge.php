<?php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/multitenant.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

// Check permissions
if (!in_array($_SESSION['tipoUsuario'] ?? '', ['admin', 'doc'])) {
    echo json_encode(['status' => 'error', 'message' => 'No tiene permisos para realizar esta acción']);
    exit;
}

$id_cargo = isset($_POST['id_cargo']) ? intval($_POST['id_cargo']) : 0;
$id_encamamiento = isset($_POST['id_encamamiento']) ? intval($_POST['id_encamamiento']) : 0;

if ($id_cargo <= 0 || $id_encamamiento <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
    exit;
}

try {
    // CSRF validation
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        throw new Exception('Token CSRF inválido');
    }

    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
    $user_id = (int)$_SESSION['user_id'];
    $db = new Database();
    $conn = $db->getConnection();

    $conn->beginTransaction();

    // 1. Verificar que el cargo pertenece a la cuenta hospitalaria
    $stmt_cuenta = $conn->prepare("SELECT ch.id_cuenta FROM cuenta_hospitalaria ch
                                     JOIN encamamientos e ON ch.id_encamamiento = e.id_encamamiento
                                     WHERE e.id_encamamiento = ? AND e.id_hospital = ?");
    $stmt_cuenta->execute([$id_encamamiento, $id_hospital]);
    $cuenta = $stmt_cuenta->fetch(PDO::FETCH_ASSOC);
    if (!$cuenta) {
        throw new Exception("Cuenta hospitalaria no encontrada.");
    }
    $id_cuenta = $cuenta['id_cuenta'];

    // 2. Capturar datos del cargo ANTES de cancelar (para auditoría)
    $stmt_old = $conn->prepare("SELECT id_cargo, id_cuenta, tipo_cargo, descripcion, cantidad, precio_unitario, subtotal, id_inventario, referencia_id, referencia_tabla FROM cargos_hospitalarios WHERE id_cargo = ? AND id_cuenta = ?");
    $stmt_old->execute([$id_cargo, $id_cuenta]);
    $old_charge = $stmt_old->fetch(PDO::FETCH_ASSOC);

    // 3. Intentar revertir stock si el cargo es de medicamento/inventario
    $reversion_info = revertirStockSiProcede($conn, $id_cargo, $id_hospital, $user_id, 'Eliminado manualmente desde hospitalización');

    // 3. Si revertirStockSiProcede devolvió reverted=false porque el cargo ya estaba cancelado,
    //    fallamos. Si lo canceló correctamente, revertirStockSiProcede ya marcó cancelado=1.
    if (!$reversion_info['reverted'] && (strpos($reversion_info['error'] ?? '', 'No se pudo') !== false)) {
        throw new Exception($reversion_info['error']);
    }

    if (!$reversion_info['reverted']) {
        // Cargo sin referencia a inventario: solo marcar cancelado
        $stmt_cancel = $conn->prepare("UPDATE cargos_hospitalarios SET cancelado = 1,
                                          motivo_cancelacion = 'Eliminado por el usuario'
                                       WHERE id_cargo = ? AND id_cuenta = ? AND id_hospital = ? AND cancelado = 0");
        $stmt_cancel->execute([$id_cargo, $id_cuenta, $id_hospital]);
        if ($stmt_cancel->rowCount() === 0) {
            throw new Exception("Cargo no encontrado o ya eliminado.");
        }
    }

    // 4. Recalcular la cuenta hospitalaria
    $stmt_sync = $conn->prepare("
        UPDATE cuenta_hospitalaria ch
        SET
            subtotal_habitacion = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Habitación' AND cancelado = FALSE),
            subtotal_medicamentos = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Medicamento' AND cancelado = FALSE),
            subtotal_procedimientos = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Procedimiento' AND cancelado = FALSE),
            subtotal_laboratorios = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Laboratorio' AND cancelado = FALSE),
            subtotal_honorarios = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Honorario' AND cancelado = FALSE),
            subtotal_otros = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo NOT IN ('Habitación','Medicamento','Procedimiento','Laboratorio','Honorario') AND cancelado = FALSE)
        WHERE ch.id_cuenta = ?
    ");
    $stmt_sync->execute([$id_cuenta]);

    $conn->commit();

    // Auditoría: registrar cancelación de cargo
    audit_log($old_charge ? 'delete' : 'cancel', 'hospitalization', "Cargo #$id_cargo cancelado: {$old_charge['descripcion']} (Subtotal: Q{$old_charge['subtotal']})" . ($reversion_info['reverted'] ? " + Stock revertido: {$reversion_info['cantidad']} unid de {$reversion_info['medicamento']}" : ''), [
        'tabla_afectada' => 'cargos_hospitalarios',
        'id_registro' => $id_cargo,
        'datos_anteriores' => $old_charge,
        'datos_nuevos' => [
            'cancelado' => 1,
            'motivo' => 'Eliminado por el usuario',
            'stock_revertido' => $reversion_info['reverted'] ?? false,
        ],
    ]);

    // Respuesta estructurada
    if ($reversion_info['reverted']) {
        echo json_encode([
            'status' => 'success',
            'message' => "✓ Se retornaron {$reversion_info['cantidad']} unidades de '{$reversion_info['medicamento']}' al inventario de {$reversion_info['origen_label']}. Stock actual: {$reversion_info['stock_nuevo']}",
            'reverted' => true,
            'medicamento' => $reversion_info['medicamento'],
            'cantidad' => $reversion_info['cantidad'],
            'origen_label' => $reversion_info['origen_label'],
            'stock_nuevo' => $reversion_info['stock_nuevo']
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'message' => 'Cargo eliminado correctamente.',
            'reverted' => false
        ]);
    }

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("hospitalization/api/delete_charge.php error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>