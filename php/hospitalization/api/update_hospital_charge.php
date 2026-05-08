<?php
// hospitalization/api/update_hospital_charge.php
session_start();
header('Content-Type: application/json');

require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Check session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

// Check permissions
if (!isset($_SESSION['usuario']) || !in_array($_SESSION['usuario'], ['admin', 'epineda', 'ysantos'])) {
    echo json_encode(['status' => 'error', 'message' => 'No tiene permisos para realizar esta acción']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Auto-populate session username if missing
    if (!isset($_SESSION['usuario'])) {
        $stmt_u = $conn->prepare("SELECT usuario FROM usuarios WHERE idUsuario = ?");
        $stmt_u->execute([$_SESSION['user_id']]);
        $u_row = $stmt_u->fetch(PDO::FETCH_ASSOC);
        if ($u_row) {
            $_SESSION['usuario'] = $u_row['usuario'];
        }
    }

    $id_cargo = isset($_POST['id_cargo']) ? intval($_POST['id_cargo']) : 0;
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
    $cantidad = isset($_POST['cantidad']) ? floatval($_POST['cantidad']) : 0;
    $precio_unitario = isset($_POST['precio_unitario']) ? floatval($_POST['precio_unitario']) : 0;

    if ($id_cargo <= 0 || empty($descripcion) || $cantidad <= 0 || $precio_unitario < 0) {
        throw new Exception('Datos inválidos o incompletos');
    }

    // Start transaction
    $conn->beginTransaction();

    // 1. Get account ID before update
    $stmt_get_acc = $conn->prepare("SELECT id_cuenta FROM cargos_hospitalarios WHERE id_cargo = ?");
    $stmt_get_acc->execute([$id_cargo]);
    $cargo_info = $stmt_get_acc->fetch(PDO::FETCH_ASSOC);

    if (!$cargo_info) {
        throw new Exception('Cargo no encontrado');
    }

    $id_cuenta = $cargo_info['id_cuenta'];

    // 2. Update the charge (manually recalculate subtotal)
    $stmt_update = $conn->prepare("
        UPDATE cargos_hospitalarios 
        SET descripcion = ?, cantidad = ?, precio_unitario = ?, subtotal = (? * ?)
        WHERE id_cargo = ?
    ");
    $stmt_update->execute([$descripcion, $cantidad, $precio_unitario, $cantidad, $precio_unitario, $id_cargo]);

    // 3. Recalculate account totals (copied logic from detalle_encamamiento.php)
    $stmt_sync = $conn->prepare("
        UPDATE cuenta_hospitalaria ch
        SET 
            subtotal_habitacion = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Habitación' AND cancelado = FALSE),
            subtotal_medicamentos = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Medicamento' AND cancelado = FALSE),
            subtotal_procedimientos = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Procedimiento' AND cancelado = FALSE),
            subtotal_laboratorios = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Laboratorio' AND cancelado = FALSE),
            subtotal_honorarios = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo = 'Honorario' AND cancelado = FALSE),
            subtotal_otros = (SELECT COALESCE(SUM(subtotal), 0) FROM cargos_hospitalarios WHERE id_cuenta = ch.id_cuenta AND tipo_cargo NOT IN ('Habitación', 'Medicamento', 'Procedimiento', 'Laboratorio', 'Honorario') AND cancelado = FALSE),
            total_pagado = (SELECT COALESCE(SUM(monto), 0) FROM abonos_hospitalarios WHERE id_cuenta = ch.id_cuenta),
            monto_pagado = (SELECT COALESCE(SUM(monto), 0) FROM abonos_hospitalarios WHERE id_cuenta = ch.id_cuenta)
        WHERE ch.id_cuenta = ?
    ");
    $stmt_sync->execute([$id_cuenta]);

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Cargo actualizado correctamente'
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
