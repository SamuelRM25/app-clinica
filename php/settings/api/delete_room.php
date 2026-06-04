<?php
// settings/api/delete_room.php
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

    $id_habitacion = (int) ($_POST['id'] ?? 0);

    if (empty($id_habitacion)) {
        echo json_encode(['success' => false, 'message' => 'ID de habitación no válido']);
        exit;
    }

    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    // Fetch old data for audit
    $fetchStmt = $conn->prepare("SELECT numero_habitacion, tipo_habitacion FROM habitaciones WHERE id_habitacion = ? AND id_hospital = ?");
    $fetchStmt->execute([$id_habitacion, $id_hospital]);
    $oldData = $fetchStmt->fetch(PDO::FETCH_ASSOC);

    // Delete associated beds first (foreign key constraint)
    $stmt_delete_beds = $conn->prepare("DELETE FROM camas WHERE id_habitacion = ?");
    $stmt_delete_beds->execute([$id_habitacion]);

    // Delete the room
    $stmt = $conn->prepare("DELETE FROM habitaciones WHERE id_habitacion = ? AND id_hospital = ?");
    $stmt->execute([$id_habitacion, $id_hospital]);

    audit_log('delete', 'rooms', "Habitación eliminada: #{$oldData['numero_habitacion']} (ID: $id_habitacion)", [
        'table_name' => 'habitaciones',
        'record_id' => $id_habitacion,
        'old_data' => $oldData
    ]);

    echo json_encode(['success' => true, 'message' => 'Habitación eliminada correctamente']);

} catch (Exception $e) {
    error_log("delete_room error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar la habitación.']);
}
?>