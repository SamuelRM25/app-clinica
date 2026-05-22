<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$id_inventario = $input['id_inventario'] ?? null;
$session_id = session_id();

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($id_inventario) {
        $stmt = $conn->prepare("DELETE FROM reservas_inventario WHERE id_inventario = ? AND session_id = ? AND id_hospital = ?");
        $stmt->execute([$id_inventario, $session_id, $id_hospital]);
    } else {
        // Clear all for this session
        $stmt = $conn->prepare("DELETE FROM reservas_inventario WHERE session_id = ? AND id_hospital = ?");
        $stmt->execute([$session_id, $id_hospital]);
    }

    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
