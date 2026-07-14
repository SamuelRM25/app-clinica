<?php
// surgery/api/delete_sala.php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['tipoUsuario'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
    $id_sala = (int)($_POST['id_sala'] ?? 0);

    if (!$id_sala) {
        echo json_encode(['success' => false, 'message' => 'ID de sala inválido']);
        exit;
    }

    $check = $conn->prepare("SELECT COUNT(*) FROM cirugias WHERE id_sala = ? AND id_hospital = ? AND estado NOT IN ('Finalizada', 'Cancelada')");
    $check->execute([$id_sala, $id_hospital]);
    if ($check->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar: la sala tiene cirugías activas.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM salas_quirurgicas WHERE id_sala = ? AND id_hospital = ?");
    $stmt->execute([$id_sala, $id_hospital]);

    audit_log('delete', 'surgery', "Sala quirúrgica eliminada ID: $id_sala", [
        'table_name' => 'salas_quirurgicas',
        'record_id' => $id_sala,
    ]);

    echo json_encode(['success' => true, 'message' => 'Sala eliminada correctamente']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar', 'debug' => $e->getMessage()]);
}