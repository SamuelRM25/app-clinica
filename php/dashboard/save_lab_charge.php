<?php
// save_lab_charge.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';

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

    // Insert into examenes_realizados
    $stmt = $conn->prepare("INSERT INTO examenes_realizados (id_paciente, nombre_paciente, tipo_examen, cobro, tipo_pago, usuario) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $patient_id,
        $patient_name,
        $exam_type,
        $amount,
        $tipo_pago,
        $_SESSION['nombre'] ?? 'System'
    ]);

    // Optional: Could update order status here if needed (e.g., to 'Completada' or 'Pagada')
    // but not explicitly requested and might interfere with lab flow. Keeping it simple.

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>