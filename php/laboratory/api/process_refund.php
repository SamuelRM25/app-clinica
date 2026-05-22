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
    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $stmt_verify = $conn->prepare("SELECT id_orden FROM ordenes_laboratorio WHERE id_orden = ? AND id_hospital = ?");
    $stmt_verify->execute([$id_orden, $id_hospital]);
    if (!$stmt_verify->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Orden no encontrada o no pertenece a este hospital']);
        exit;
    }

    $placeholders = str_repeat('?,', count($pruebas_devueltas) - 1) . '?';
    $stmt_update_pruebas = $conn->prepare("UPDATE orden_pruebas op JOIN ordenes_laboratorio ol ON op.id_orden = ol.id_orden SET op.estado = 'Devuelto' WHERE ol.id_hospital = ? AND op.id_orden = ? AND op.id_orden_prueba IN ($placeholders)");
    $params = array_merge([$id_hospital, $id_orden], $pruebas_devueltas);
    $stmt_update_pruebas->execute($params);

    $stmt_devolucion = $conn->prepare("
        INSERT INTO examenes_realizados (id_paciente, id_doctor, examen, fecha, hora, cobro, idUsuario, id_hospital) 
        SELECT id_paciente, id_doctor, CONCAT('Devolución: ', ?), CURDATE(), CURTIME(), ?, ?, ?
        FROM ordenes_laboratorio WHERE id_orden = ? AND id_hospital = ?
    ");
    $monto_negativo = -$monto;
    $stmt_devolucion->execute([$motivo, $monto_negativo, $_SESSION['user_id'], $id_hospital, $id_orden, $id_hospital]);

    $conn->commit();
    echo json_encode(['status' => 'success', 'message' => 'Devolución procesada correctamente.']);

} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    echo json_encode(['status' => 'error', 'message' => 'Error al procesar: ' . $e->getMessage()]);
}
?>