<?php
// surgery/api/delete_consumo_cirugia.php
// Elimina un consumo de cirugía y revierte el stock de Quirófano
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
$user_id = (int)$_SESSION['user_id'];
$id_consumo = (int)($_POST['id_consumo'] ?? 0);

if (!$id_consumo) {
    echo json_encode(['success' => false, 'message' => 'ID de consumo requerido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Llamar al helper que maneja toda la lógica de reversión
    $result = revertirStockConsumoCirugia($conn, $id_consumo, $id_hospital, $user_id, 'Eliminado manualmente desde detalle de cirugía');

    if (!$result['reverted']) {
        echo json_encode([
            'success' => false,
            'message' => $result['error'] ?? 'No se pudo revertir el stock'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => "✓ Se retornaron {$result['cantidad']} unidades de '{$result['medicamento']}' al inventario de {$result['origen_label']}. Stock actual: {$result['stock_nuevo']}",
        'medicamento' => $result['medicamento'],
        'cantidad' => $result['cantidad'],
        'stock_nuevo' => $result['stock_nuevo'],
        'origen_label' => $result['origen_label']
    ]);

} catch (Exception $e) {
    error_log('delete_consumo_cirugia: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}