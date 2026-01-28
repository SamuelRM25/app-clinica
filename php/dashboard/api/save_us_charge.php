<?php
session_start();
header('Content-Type: application/json');
require_once '../../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_paciente = $_POST['patient_id'] ?? null;
    $tipo_ultrasonido = $_POST['ultrasound_type'] ?? '';
    $cobro = $_POST['amount'] ?? 0;
    $tipo_pago = $_POST['tipo_pago'] ?? 'Efectivo';
    $usuario = $_SESSION['nombre'];

    if (!$id_paciente || !$cobro) {
        throw new Exception('Datos incompletos');
    }

    // Get Patient Name
    $stmtP = $conn->prepare("SELECT CONCAT(nombre, ' ', apellido) as nombre FROM pacientes WHERE id_paciente = ?");
    $stmtP->execute([$id_paciente]);
    $pat = $stmtP->fetch(PDO::FETCH_ASSOC);
    $nombre_paciente = $pat['nombre'] ?? '';

    $stmt = $conn->prepare("
        INSERT INTO ultrasonidos 
        (id_paciente, nombre_paciente, tipo_ultrasonido, cobro, usuario, tipo_pago) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([$id_paciente, $nombre_paciente, $tipo_ultrasonido, $cobro, $usuario, $tipo_pago]);
    $id = $conn->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'message' => 'Ultrasonido registrado',
        'id' => $id
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
