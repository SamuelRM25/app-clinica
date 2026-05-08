<?php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$id_orden = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id_orden <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID de orden inválido']);
    exit;
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Obtener detalles de la orden
    $stmt = $conn->prepare("
        SELECT op.id_orden_prueba, cp.nombre_prueba, cp.precio, op.estado 
        FROM orden_pruebas op
        JOIN catalogo_pruebas cp ON op.id_prueba = cp.id_prueba
        WHERE op.id_orden = ?
    ");
    $stmt->execute([$id_orden]);
    $pruebas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'pruebas' => $pruebas
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error de base de datos: ' . $e->getMessage()
    ]);
}
?>