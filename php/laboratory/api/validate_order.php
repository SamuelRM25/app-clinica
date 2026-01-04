<?php
// laboratory/api/validate_order.php - Finalize and validate clinical laboratory order
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

verify_session();

$id_orden = $_GET['id'] ?? null;

if (!$id_orden) {
    die("ID de orden no proporcionado");
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $conn->beginTransaction();
    
    // 1. Mark all tests in this order as Validada
    $stmt = $conn->prepare("
        UPDATE orden_pruebas 
        SET estado = 'Validada', fecha_resultado_validado = NOW() 
        WHERE id_orden = ?
    ");
    $stmt->execute([$id_orden]);
    
    // 2. Mark all results for these tests as valid
    $stmt = $conn->prepare("
        UPDATE resultados_laboratorio rl
        JOIN orden_pruebas op ON rl.id_orden_prueba = op.id_orden_prueba
        SET rl.validado = 1, rl.validado_por = ?, rl.fecha_validacion = NOW()
        WHERE op.id_orden = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $id_orden]);
    
    // 3. Update overall order status to Completada (or Validada based on schema)
    $stmt = $conn->prepare("UPDATE ordenes_laboratorio SET estado = 'Completada' WHERE id_orden = ?");
    $stmt->execute([$id_orden]);
    
    $conn->commit();
    
    // Redirect to index with success
    header("Location: ../index.php?success=validated&id=" . $id_orden);
    
} catch (Exception $e) {
    if (isset($conn)) $conn->rollBack();
    die("Error: " . $e->getMessage());
}
