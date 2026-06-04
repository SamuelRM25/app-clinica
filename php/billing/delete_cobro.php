<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

header('Content-Type: application/json');

verify_session();

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$fuente = $data['fuente'] ?? '';
$id = isset($data['id']) ? (int)$data['id'] : 0;
$csrf_token = $data['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

if ($id <= 0 || empty($fuente)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$table_map = [
    'cobro' => ['table' => 'cobros', 'column' => 'in_cobro'],
    'venta' => ['table' => 'ventas', 'column' => 'id_venta'],
    'examen' => ['table' => 'examenes_realizados', 'column' => 'id_examen_realizado'],
    'procedimiento' => ['table' => 'procedimientos_menores', 'column' => 'id_procedimiento'],
    'ultrasonido' => ['table' => 'ultrasonidos', 'column' => 'id_ultrasonido'],
    'rayos_x' => ['table' => 'rayos_x', 'column' => 'id_rayos_x'],
    'electro' => ['table' => 'electrocardiogramas', 'column' => 'id_electro'],
];

if (!isset($table_map[$fuente])) {
    echo json_encode(['success' => false, 'message' => 'Fuente inválida']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $table_info = $table_map[$fuente];
    $table = $table_info['table'];
    $column = $table_info['column'];

    $conn->beginTransaction();

    $stmt = $conn->prepare("DELETE FROM `$table` WHERE `$column` = ? AND id_hospital = ?");
    $result = $stmt->execute([$id, $id_hospital]);

    if ($stmt->rowCount() === 0) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Registro no encontrado']);
        exit;
    }

    $conn->commit();

    audit_log('delete', 'billing', "Registro de cobro eliminado - Fuente: $fuente, ID: $id", [
        'table_name' => $table,
        'record_id' => $id,
    ]);

    echo json_encode(['success' => true, 'message' => 'Registro eliminado correctamente']);

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error en delete_cobro.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el registro']);
}
