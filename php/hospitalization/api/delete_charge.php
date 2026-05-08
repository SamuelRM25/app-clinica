<?php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$id_cargo = isset($_POST['id_cargo']) ? intval($_POST['id_cargo']) : 0;
$id_encamamiento = isset($_POST['id_encamamiento']) ? intval($_POST['id_encamamiento']) : 0;

if ($id_cargo <= 0 || $id_encamamiento <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Datos inválidos']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Iniciar transacción
    $conn->beginTransaction();

    // 1. Obtener la cuenta hospitalaria de este encamamiento
    $stmt_cuenta = $conn->prepare("SELECT id_cuenta FROM cuenta_hospitalaria WHERE id_encamamiento = ?");
    $stmt_cuenta->execute([$id_encamamiento]);
    $cuenta = $stmt_cuenta->fetch(PDO::FETCH_ASSOC);

    if (!$cuenta) {
        throw new Exception("Cuenta hospitalaria no encontrada.");
    }

    $id_cuenta = $cuenta['id_cuenta'];

    // 2. Marcar el cargo como cancelado
    $stmt_delete = $conn->prepare("UPDATE cargos_hospitalarios SET cancelado = 1 WHERE id_cargo = ? AND id_cuenta = ?");
    $stmt_delete->execute([$id_cargo, $id_cuenta]);

    if ($stmt_delete->rowCount() === 0) {
        throw new Exception("Cargo no encontrado o ya eliminado.");
    }

    // 3. Recalcular la cuenta hospitalaria para mantener la integridad de los datos (Igual a detalle_encamamiento)
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
    echo json_encode(['status' => 'success', 'message' => 'Cargo eliminado correctamente.']);

} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>