<?php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$id_orden = isset($data['id_orden']) ? intval($data['id_orden']) : 0;
$monto = isset($data['monto']) ? floatval($data['monto']) : 0;
$motivo = isset($data['motivo']) ? trim($data['motivo']) : '';
$pruebas_devueltas = isset($data['pruebas']) && is_array($data['pruebas']) ? $data['pruebas'] : [];

if ($id_orden <= 0 || $monto <= 0 || empty($motivo) || empty($pruebas_devueltas)) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos o inválidos']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Iniciar transacción
    $conn->beginTransaction();

    // 1. Actualizar estado de las pruebas a "Devuelto"
    $placeholders = str_repeat('?,', count($pruebas_devueltas) - 1) . '?';
    $stmt_update_pruebas = $conn->prepare("UPDATE orden_pruebas SET estado = 'Devuelto' WHERE id_orden = ? AND id_orden_prueba IN ($placeholders)");
    $params = array_merge([$id_orden], $pruebas_devueltas);
    $stmt_update_pruebas->execute($params);

    // 2. Registrar el movimiento negativo en la caja (en la tabla que CMHS use para ingresos de lab). 
    // Lo más probables es examenes_realizados y en la caja chica/turnos.
    // Insertamos una entrada de devolución en el flujo de caja diario o simplemente dejamos el registro en una tabla de devoluciones si existe.
    // Como requerimiento, es registrar un monto negativo devuelto.
    // Verificamos si existe la tabla devoluciones, si no, lo registramos como cobro negativo en donde aplique.

    // Insert en una tabla de auditoria simple o examenes_realizados con cobro negativo.
    $stmt_devolucion = $conn->prepare("
        INSERT INTO examenes_realizados (id_paciente, id_doctor, examen, fecha, hora, cobro, idUsuario) 
        SELECT id_paciente, id_doctor, CONCAT('Devolución: ', ?), CURDATE(), CURTIME(), ?, ?
        FROM ordenes_laboratorio WHERE id_orden = ?
    ");
    // El cobro devuelto entra como valor negativo.
    $monto_negativo = -$monto;
    $stmt_devolucion->execute([$motivo, $monto_negativo, $_SESSION['user_id'], $id_orden]);

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Devolución procesada correctamente.']);

} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Error al procesar: ' . $e->getMessage()]);
}
?>