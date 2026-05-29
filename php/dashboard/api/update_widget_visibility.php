<?php
// php/dashboard/api/update_widget_visibility.php
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/multitenant.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sesión no activa']);
    exit;
}

$hospital_id = $_SESSION['id_hospital'] ?? 1;

try {
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        echo json_encode(['success' => false, 'error' => 'Token CSRF inválido']);
        exit;
    }
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['config'])) {
        throw new Exception('Datos de configuración no recibidos');
    }

    $database = new Database();
    $conn = $database->getConnection();

    $conn->beginTransaction();

    $stmt = $conn->prepare("
        INSERT INTO widget_settings (id_hospital, widget_id, is_enabled)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE is_enabled = ?
    ");

    foreach ($data['config'] as $widget_id => $is_enabled) {
        $enabled_val = $is_enabled ? 1 : 0;
        $stmt->execute([$hospital_id, $widget_id, $enabled_val, $enabled_val]);
    }

    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
