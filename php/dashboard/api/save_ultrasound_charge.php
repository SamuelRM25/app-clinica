<?php
// php/dashboard/api/save_ultrasound_charge.php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../../../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Validate required fields
    if (empty($_POST['patient_id']) || empty($_POST['amount']) || empty($_POST['ultrasound_type'])) {
        throw new Exception("Datos incompletos");
    }

    $patient_id = $_POST['patient_id'];
    $patient_name = $_POST['patient_name'] ?? 'Desconocido';
    $ultrasound_type = $_POST['ultrasound_type'];
    $amount = $_POST['amount'];
    $tipo_pago = $_POST['ultrasound_tipo_pago'] ?? 'Efectivo';

    // Insert into examenes_realizados (using same table as other quick charges)
    $stmt = $conn->prepare("INSERT INTO examenes_realizados (id_paciente, nombre_paciente, tipo_examen, cobro, tipo_pago, usuario) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $patient_id,
        $patient_name,
        "Ultrasonido: " . $ultrasound_type,
        $amount,
        $tipo_pago,
        $_SESSION['nombre'] ?? 'System'
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>