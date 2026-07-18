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
// Cantidad a retornar (opcional). Si es null/0/ausente, retorna todo.
$cantidad_retorno_raw = $_POST['cantidad_retorno'] ?? null;
$cantidad_retorno = ($cantidad_retorno_raw === null || $cantidad_retorno_raw === '') ? null : (float)$cantidad_retorno_raw;

if (!$id_consumo) {
    echo json_encode(['success' => false, 'message' => 'ID de consumo requerido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Llamar al helper que maneja toda la lógica de reversión
    // Si $cantidad_retorno es null, retorna todo (comportamiento legacy)
    $result = revertirStockConsumoCirugia($conn, $id_consumo, $id_hospital, $user_id, 'Retorno manual desde detalle de cirugía', $cantidad_retorno);

    if (!$result['reverted']) {
        echo json_encode([
            'success' => false,
            'message' => $result['error'] ?? 'No se pudo revertir el stock'
        ]);
        exit;
    }

    if ($result['retorno_total'] ?? true) {
        $mensaje = "✓ Se retornaron {$result['cantidad']} unidades de '{$result['medicamento']}' al inventario de {$result['origen_label']}. Stock actual: {$result['stock_nuevo']}";
    } else {
        $restante = $result['cantidad_restante'] ?? 0;
        $mensaje = "✓ Se retornaron {$result['cantidad']} unidades de '{$result['medicamento']}'. Quedan {$restante} unidades en el consumo. Stock actual: {$result['stock_nuevo']}";
    }

    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'medicamento' => $result['medicamento'],
        'cantidad' => $result['cantidad'],
        'cantidad_restante' => $result['cantidad_restante'] ?? 0,
        'retorno_total' => $result['retorno_total'] ?? true,
        'stock_nuevo' => $result['stock_nuevo'],
        'origen_label' => $result['origen_label']
    ]);

} catch (Exception $e) {
    error_log('delete_consumo_cirugia: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}