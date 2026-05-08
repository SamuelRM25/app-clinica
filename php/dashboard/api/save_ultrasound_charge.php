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
    $tipo_pago = $_POST['tipo_pago'] ?? 'Efectivo';

    // Insert into ultrasonidos
    $stmt = $conn->prepare("INSERT INTO ultrasonidos (id_paciente, nombre_paciente, tipo_ultrasonido, cobro, tipo_pago, usuario, fecha_ultrasonido) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $patient_id,
        $patient_name,
        $ultrasound_type,
        $amount,
        $tipo_pago,
        $_SESSION['nombre'] ?? 'System'
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>