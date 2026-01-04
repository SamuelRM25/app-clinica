<?php
// laboratory/api/register_sample.php - API to register sample reception
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

verify_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $id_orden = $_POST['id_orden'] ?? null;
    $fecha_recepcion = $_POST['fecha_recepcion'] ?? date('Y-m-d H:i:s');
    $observaciones = $_POST['observaciones'] ?? '';
    
    if (!$id_orden) {
        throw new Exception('ID de orden no proporcionado');
    }
    
    // Update order status to Muestra_Recibida
    $stmt = $conn->prepare("
        UPDATE ordenes_laboratorio 
        SET estado = 'Muestra_Recibida',
            fecha_muestra_recibida = ?
        WHERE id_orden = ?
    ");
    $stmt->execute([$fecha_recepcion, $id_orden]);
    
    // Log the action if observations were provided
    if ($observaciones) {
        $stmt = $conn->prepare("
            INSERT INTO orden_logs (id_orden, accion, observaciones, id_usuario, fecha)
            VALUES (?, 'Muestra Recibida', ?, ?, NOW())
        ");
        $stmt->execute([$id_orden, $observaciones, $_SESSION['idUsuario']]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Muestra registrada exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
