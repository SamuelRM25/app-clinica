<?php
/**
 * API: Save medical evolution (SOAP note)
 */
session_start();
header('Content-Type: application/json');
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/multitenant.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

date_default_timezone_set('America/Guatemala');

try {
    // CSRF validation
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        throw new Exception('Token CSRF inválido');
    }

    $required = ['id_encamamiento', 'fecha_evolucion'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Campo requerido: $field");
        }
    }
    
    $id_encamamiento = intval($_POST['id_encamamiento']);
    $fecha_evolucion = $_POST['fecha_evolucion'];
    $id_doctor = $_SESSION['user_id'];
    $subjetivo = isset($_POST['subjetivo']) ? trim($_POST['subjetivo']) : null;
    $objetivo = isset($_POST['objetivo']) ? trim($_POST['objetivo']) : null;
    $evaluacion = isset($_POST['evaluacion']) ? trim($_POST['evaluacion']) : null;
    $plan_tratamiento = isset($_POST['plan_tratamiento']) ? trim($_POST['plan_tratamiento']) : null;
    $notas_adicionales = isset($_POST['notas_adicionales']) ? trim($_POST['notas_adicionales']) : null;
    
    $database = new Database();
    $conn = $database->getConnection();

    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
    
    $stmt = $conn->prepare("
        INSERT INTO evoluciones_medicas 
        (id_encamamiento, fecha_evolucion, id_doctor, subjetivo, objetivo,
         evaluacion, plan_tratamiento, notas_adicionales, id_hospital)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $id_encamamiento,
        $fecha_evolucion,
        $id_doctor,
        $subjetivo,
        $objetivo,
        $evaluacion,
        $plan_tratamiento,
        $notas_adicionales,
        $id_hospital
    ]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Evolución guardada correctamente',
        'id_evolucion' => $conn->lastInsertId()
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
