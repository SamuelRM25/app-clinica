<?php
// laboratory/api/validate_order.php - Finalize and validate clinical laboratory order
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

verify_session();

// CSRF validation
$csrf_token = $_GET['csrf_token'] ?? '';
if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    die("Token CSRF inválido");
}

$id_orden = $_GET['id'] ?? null;

if (!$id_orden) {
    die("ID de orden no proporcionado");
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $conn->beginTransaction();

    // 1. Mark all tests in this order as Validada
    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $stmt = $conn->prepare("
        UPDATE orden_pruebas op
        JOIN ordenes_laboratorio ol ON op.id_orden = ol.id_orden
        SET op.estado = 'Validada', op.fecha_validada = NOW(), op.validado_por = ?
        WHERE op.id_orden = ? AND ol.id_hospital = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $id_orden, $id_hospital]);

    $stmt = $conn->prepare("
        UPDATE resultados_laboratorio rl
        JOIN orden_pruebas op ON rl.id_orden_prueba = op.id_orden_prueba
        JOIN ordenes_laboratorio ol ON op.id_orden = ol.id_orden
        SET rl.validado = 1, rl.validado_por = ?, rl.fecha_validacion = NOW()
        WHERE ol.id_orden = ? AND ol.id_hospital = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $id_orden, $id_hospital]);

    $stmt = $conn->prepare("UPDATE ordenes_laboratorio SET estado = 'Completada' WHERE id_orden = ? AND id_hospital = ?");
    $stmt->execute([$id_orden, $id_hospital]);

    $conn->commit();

    // Redirect to index with success
    header("Location: ../index.php?success=validated&id=" . $id_orden);

} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    error_log('Error en laboratory/api/validate_order.php: ' . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}
