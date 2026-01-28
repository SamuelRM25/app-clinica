<?php
// laboratory/api/save_parameters.php - Save parameters for a clinical test
header('Content-Type: application/json');
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

if ($_SESSION['tipoUsuario'] !== 'admin' && $_SESSION['user_id'] != 7) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id_prueba = $_POST['id_prueba'] ?? null;
$params = $_POST['params'] ?? [];

if (!$id_prueba) {
    echo json_encode(['success' => false, 'message' => 'ID de prueba no proporcionado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $conn->beginTransaction();

    // 1. Delete existing parameters for this test
    $stmt = $conn->prepare("DELETE FROM parametros_pruebas WHERE id_prueba = ?");
    $stmt->execute([$id_prueba]);

    // 2. Insert new parameters
    $stmt = $conn->prepare("
        INSERT INTO parametros_pruebas (
            id_prueba, nombre_parametro, unidad_medida, tipo_dato,
            valor_ref_hombre_min, valor_ref_hombre_max,
            valor_ref_mujer_min, valor_ref_mujer_max,
            valor_ref_pediatrico_min, valor_ref_pediatrico_max,
            orden_visualizacion
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($params as $idx => $param) {
        $stmt->execute([
            $id_prueba,
            $param['nombre'],
            $param['unidad'] ?: null,
            $param['tipo'] ?: 'Numérico',
            $param['h_min'] !== '' ? $param['h_min'] : null,
            $param['h_max'] !== '' ? $param['h_max'] : null,
            $param['m_min'] !== '' ? $param['m_min'] : null,
            $param['m_max'] !== '' ? $param['m_max'] : null,
            $param['p_min'] !== '' ? $param['p_min'] : null,
            $param['p_max'] !== '' ? $param['p_max'] : null,
            $idx
        ]);
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Configuración guardada correctamente']);

} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
