<?php
// laboratory/api/sample_reception.php - Mark a sample as received
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/multitenant.php';

verify_session();

$id_hospital = hospital_id();

$id_orden_prueba = $_GET['id'] ?? null;
$id_orden = $_GET['id_orden'] ?? null;

if (!$id_orden_prueba || !$id_orden) {
    die("Datos incompletos");
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // 1. Update test status to Muestra_Recibida
    $stmt = $conn->prepare("
        UPDATE orden_pruebas op
        JOIN ordenes_laboratorio ol ON op.id_orden = ol.id_orden
        SET op.estado = 'Muestra_Recibida', op.fecha_muestra_recibida = NOW() 
        WHERE op.id_orden_prueba = ? AND ol.id_hospital = ?
    ");
    $stmt->execute([$id_orden_prueba, $id_hospital]);

    $stmt = $conn->prepare("SELECT estado FROM ordenes_laboratorio WHERE id_orden = ? AND id_hospital = ?");
    $stmt->execute([$id_orden, $id_hospital]);
    $current_status = $stmt->fetch(PDO::FETCH_ASSOC)['estado'];
    
    if ($current_status === 'Pendiente') {
        $stmt = $conn->prepare("UPDATE ordenes_laboratorio SET estado = 'Muestra_Recibida' WHERE id_orden = ? AND id_hospital = ?");
        $stmt->execute([$id_orden, $id_hospital]);
    }
    
    // Redirect back to the processing page
    header("Location: ../procesar_orden.php?id=" . $id_orden);
    
} catch (Exception $e) {
    error_log('Error en laboratory/api/sample_reception.php: ' . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}
