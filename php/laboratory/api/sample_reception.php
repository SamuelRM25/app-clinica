<?php
// laboratory/api/sample_reception.php - Mark a sample as received
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

verify_session();

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
        UPDATE orden_pruebas 
        SET estado = 'Muestra_Recibida', fecha_muestra_recibida = NOW() 
        WHERE id_orden_prueba = ?
    ");
    $stmt->execute([$id_orden_prueba]);
    
    // 2. Check if the whole order should move to Muestra_Recibida or En_Proceso
    // If at least one sample is received, the order is at least in that state
    $stmt = $conn->prepare("SELECT estado FROM ordenes_laboratorio WHERE id_orden = ?");
    $stmt->execute([$id_orden]);
    $current_status = $stmt->fetch(PDO::FETCH_ASSOC)['estado'];
    
    if ($current_status === 'Pendiente') {
        $stmt = $conn->prepare("UPDATE ordenes_laboratorio SET estado = 'Muestra_Recibida' WHERE id_orden = ?");
        $stmt->execute([$id_orden]);
    }
    
    // Redirect back to the processing page
    header("Location: ../procesar_orden.php?id=" . $id_orden);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
