<?php
/**
 * API: Update a charge in surgery account
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
    $descripcion = trim($_POST['descripcion'] ?? '');
    $cantidad = floatval($_POST['cantidad'] ?? 1);
    $precio_unitario = floatval($_POST['precio_unitario'] ?? 0);
    $precio_costo = floatval($_POST['precio_costo'] ?? 0);

    if (!$id_cargo) throw new Exception('ID de cargo requerido');
    if ($descripcion === '') throw new Exception('Descripción requerida');
    if ($cantidad <= 0) throw new Exception('Cantidad debe ser > 0');
    if ($precio_unitario < 0) throw new Exception('Precio inválido');

    $conn->beginTransaction();

    // Verify the cargo belongs to this hospital and is in active surgery
    $stmtV = $conn->prepare("
        SELECT ch.id_cuenta, ch.referencia_id, ch.cantidad AS cant_actual, c.estado, c.id_cirugia
        FROM cargos_hospitalarios ch
        JOIN cirugias c ON ch.id_cirugia = c.id_cirugia
        WHERE ch.id_cargo = ? AND ch.id_hospital = ?
    ");
    $stmtV->execute([$id_cargo, $id_hospital]);
    $cargo = $stmtV->fetch(PDO::FETCH_ASSOC);

    if (!$cargo) throw new Exception('Cargo no encontrado');
    if (!in_array($cargo['estado'], ['Programada', 'En_Curso'], true)) {
        throw new Exception('Solo se pueden editar cargos de cirugías activas');
    }

    // If cantidad changed AND linked to inventory, adjust stock
    if ($cargo['referencia_id']) {
        $delta = $cantidad - (float)$cargo['cant_actual'];
        if ($delta != 0) {
            if ($delta > 0) {
                // Increase qty → deduct more stock
                $stmtS = $conn->prepare("
                    UPDATE inventario SET stock_quirofano = stock_quirofano - ?
                    WHERE id_inventario = ? AND stock_quirofano >= ? AND id_hospital = ?
                ");
                $stmtS->execute([$delta, $cargo['referencia_id'], $delta, $id_hospital]);
                if ($stmtS->rowCount() === 0) throw new Exception('Stock insuficiente para aumentar la cantidad');
            } else {
                // Decrease qty → return stock
                $delta_abs = abs($delta);
                $stmtS = $conn->prepare("
                    UPDATE inventario SET stock_quirofano = stock_quirofano + ?
                    WHERE id_inventario = ? AND id_hospital = ?
                ");
                $stmtS->execute([$delta_abs, $cargo['referencia_id'], $id_hospital]);
            }
        }
    }

    // Update cargo
    $stmtU = $conn->prepare("
        UPDATE cargos_hospitalarios
        SET descripcion = ?, cantidad = ?, precio_unitario = ?, precio_costo = ?
        WHERE id_cargo = ? AND id_hospital = ?
    ");
    $stmtU->execute([$descripcion, $cantidad, $precio_unitario, $precio_costo, $id_cargo, $id_hospital]);

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

    audit_log('update', 'surgery', "Cargo #$id_cargo actualizado", [
        'table_name' => 'cargos_hospitalarios',
        'record_id' => $id_cargo,
        'id_cirugia' => $cargo['id_cirugia'],
    ]);

    echo json_encode(['success' => true, 'message' => 'Cargo actualizado']);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) $conn->rollBack();
    error_log('update_cargo_cirugia: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}