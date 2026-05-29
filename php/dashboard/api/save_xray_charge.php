<?php
// php/dashboard/api/save_xray_charge.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/multitenant.php';

try {
    verify_csrf_token();
    $database = new Database();
    $conn = $database->getConnection();

    if (empty($_POST['patient_id']) || empty($_POST['amount']) || empty($_POST['xray_type'])) {
        throw new Exception("Datos incompletos");
    }

    $patient_id = $_POST['patient_id'];
    $patient_name = $_POST['patient_name'] ?? 'Desconocido';
    $xray_type = $_POST['xray_type'];
    $amount = $_POST['amount'];
    $tipo_pago = $_POST['tipo_pago'] ?? 'Efectivo';
    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $stmt = $conn->prepare("INSERT INTO rayos_x (id_paciente, nombre_paciente, tipo_estudio, cobro, tipo_pago, usuario, fecha_estudio, id_hospital) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
    $stmt->execute([
        $patient_id,
        $patient_name,
        $xray_type,
        $amount,
        $tipo_pago,
        $_SESSION['nombre'] ?? 'System',
        $id_hospital
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('Error en php/dashboard/api/save_xray_charge.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error del servidor.']);
}
?>