<?php
// save_lab_charge.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
start_app_session();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}


try {
    $database = new Database();
    $conn = $database->getConnection();

    // Validate required fields
    if (empty($_POST['patient_id']) || empty($_POST['amount'])) {
        throw new Exception("Datos incompletos");
    }

    $patient_id = $_POST['patient_id'];
    $patient_name = $_POST['patient_name'] ?? 'Desconocido';
    $exam_type = $_POST['exam_type'] ?? 'Cobro de Laboratorio'; // Contains formatted description
    $amount = $_POST['amount'];
    $tipo_pago = $_POST['tipo_pago'] ?? 'Efectivo';

    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $stmt = $conn->prepare("INSERT INTO examenes_realizados (id_paciente, nombre_paciente, tipo_examen, cobro, tipo_pago, usuario, id_hospital) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $patient_id,
        $patient_name,
        $exam_type,
        $amount,
        $tipo_pago,
        $_SESSION['nombre'] ?? 'System',
        $id_hospital
    ]);

    // Optional: Could update order status here if needed (e.g., to 'Completada' or 'Pagada')
    // but not explicitly requested and might interfere with lab flow. Keeping it simple.

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('Error en dashboard/save_lab_charge.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error del servidor.']);
}
?>