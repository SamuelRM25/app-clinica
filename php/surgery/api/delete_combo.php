<?php
// surgery/api/delete_combo.php
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
    $id_combo = (int)($_POST['id_combo'] ?? 0);

    if (!$id_combo) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    $check = $conn->prepare("SELECT COUNT(*) FROM cirugias WHERE id_combo = ? AND id_hospital = ?");
    $check->execute([$id_combo, $id_hospital]);
    if ($check->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'No se puede eliminar: hay cirugías usando este combo.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM cirugia_combos WHERE id_combo = ? AND id_hospital = ?");
    $stmt->execute([$id_combo, $id_hospital]);

    audit_log('delete', 'surgery', "Combo eliminado ID: $id_combo", ['table_name' => 'cirugia_combos', 'record_id' => $id_combo]);
    echo json_encode(['success' => true, 'message' => 'Combo eliminado correctamente']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar', 'debug' => $e->getMessage()]);
}