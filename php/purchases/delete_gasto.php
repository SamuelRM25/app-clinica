<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

csrf_token();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

    $data = json_decode(file_get_contents('php://input'), true);

    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        throw new Exception('Token CSRF inválido');
    }

    if (!$data || !isset($data['id'])) {
        throw new Exception('ID de gasto no proporcionado');
    }

    $id = (int)$data['id'];

    // Verify ownership
    $stmt = $conn->prepare("SELECT id, descripcion, total FROM gastos WHERE id = ? AND id_hospital = ?");
    $stmt->execute([$id, $id_hospital]);
    $gasto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gasto) {
        throw new Exception('Gasto no encontrado');
    }

    $stmt = $conn->prepare("DELETE FROM gastos WHERE id = ? AND id_hospital = ?");
    $stmt->execute([$id, $id_hospital]);

    audit_log('delete', 'gastos', "Gasto #{$gasto['id']} eliminado - {$gasto['descripcion']} - Q{$gasto['total']}", [
        'table_name' => 'gastos',
        'record_id' => $id,
        'old_data' => $gasto,
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('Error en purchases/delete_gasto.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
