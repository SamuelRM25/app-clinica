<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

csrf_token();
verify_session();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
    echo json_encode(['status' => 'error', 'message' => 'Token CSRF inválido']);
    exit;
}

if (!isset($data['id'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID de cita no proporcionado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

    // Fetch old data before deleting for audit trail
    $stmt_old = $conn->prepare("SELECT nombre_pac, apellido_pac, fecha_cita, hora_cita, id_doctor FROM cita WHERE id_cita = ? AND id_hospital = ?");
    $stmt_old->execute([$data['id'], $id_hospital]);
    $oldData = $stmt_old->fetch(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("DELETE FROM cita WHERE id_cita = ? AND id_hospital = ?");
    $result = $stmt->execute([$data['id'], $id_hospital]);

    if ($result && $stmt->rowCount() > 0) {
        if ($oldData) {
            audit_log('delete', 'appointments', "Cita eliminada: {$oldData['nombre_pac']} {$oldData['apellido_pac']} (ID: {$data['id']})", [
                'table_name' => 'cita',
                'record_id' => (int)$data['id'],
                'old_data' => $oldData
            ]);
        }
        echo json_encode(['status' => 'success', 'message' => 'Cita eliminada correctamente']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar la cita']);
    }
} catch (Exception $e) {
    error_log('Error en appointments/delete_appointment.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error del servidor']);
}