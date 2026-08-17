<?php
/**
 * API: Soft-delete (cancel) a charge in surgery account + return stock if applicable
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/multitenant.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

date_default_timezone_set('America/Guatemala');

try {
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        throw new Exception('Token CSRF inválido');
    }

    $database = new Database();
    $conn = $database->getConnection();

    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
    $id_cargo = (int)($_POST['id_cargo'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? '');

    if (!$id_cargo) throw new Exception('ID de cargo requerido');

    $conn->beginTransaction();

    // Verify cargo
    $stmtV = $conn->prepare("
        SELECT ch.id_cuenta, ch.tipo_cargo, ch.cantidad, ch.referencia_id, ch.referencia_tabla,
               c.estado, c.id_cirugia
        FROM cargos_hospitalarios ch
        JOIN cirugias c ON ch.id_cirugia = c.id_cirugia
        WHERE ch.id_cargo = ? AND ch.id_hospital = ?
    ");
    $stmtV->execute([$id_cargo, $id_hospital]);
    $cargo = $stmtV->fetch(PDO::FETCH_ASSOC);

    if (!$cargo) throw new Exception('Cargo no encontrado');
    if (!in_array($cargo['estado'], ['Programada', 'En_Curso'], true)) {
        throw new Exception('Solo se pueden eliminar cargos de cirugías activas');
    }

    // Soft-cancel
    $stmtC = $conn->prepare("
        UPDATE cargos_hospitalarios
        SET cancelado = 1, motivo_cancelacion = ?, fecha_cancelacion = NOW()
        WHERE id_cargo = ? AND id_hospital = ?
    ");
    $stmtC->execute([$motivo ?: 'Eliminado por el usuario', $id_cargo, $id_hospital]);

    // Return stock if linked to inventory
    if ($cargo['referencia_id'] && $cargo['referencia_tabla'] === 'inventario') {
        $stmtR = $conn->prepare("
            UPDATE inventario SET stock_quirofano = stock_quirofano + ?
            WHERE id_inventario = ? AND id_hospital = ?
        ");
        $stmtR->execute([(float)$cargo['cantidad'], (int)$cargo['referencia_id'], $id_hospital]);
    }

    // Sync cuenta
    $stmtSync = $conn->prepare("
        UPDATE cuenta_hospitalaria ch SET
            subtotal_habitacion = COALESCE((SELECT SUM(subtotal) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Habitación' AND cancelado = 0), 0),
            subtotal_medicamentos = COALESCE((SELECT SUM(subtotal) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Medicamento' AND cancelado = 0), 0),
            subtotal_procedimientos = COALESCE((SELECT SUM(subtotal) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Cirugía' AND cancelado = 0), 0) + COALESCE((SELECT SUM(subtotal) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Procedimiento' AND cancelado = 0), 0),
            subtotal_laboratorios = COALESCE((SELECT SUM(subtotal) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Laboratorio' AND cancelado = 0), 0),
            subtotal_honorarios = COALESCE((SELECT SUM(subtotal) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Honorario' AND cancelado = 0), 0),
            subtotal_otros = COALESCE((SELECT SUM(subtotal) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo NOT IN ('Habitación','Medicamento','Procedimiento','Cirugía','Laboratorio','Honorario') AND cancelado = 0), 0)
        WHERE ch.id_cuenta = ?
    ");
    $stmtSync->execute([$cargo['id_cuenta']]);

    $conn->commit();

    audit_log('delete', 'surgery', "Cargo #$id_cargo cancelado (Cirugía #{$cargo['id_cirugia']})", [
        'table_name' => 'cargos_hospitalarios',
        'record_id' => $id_cargo,
        'motivo' => $motivo,
    ]);

    echo json_encode(['success' => true, 'message' => 'Cargo eliminado correctamente']);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log('delete_cargo_cirugia: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}