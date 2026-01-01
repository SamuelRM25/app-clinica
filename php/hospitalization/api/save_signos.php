<?php
/**
 * API: Save vital signs
 */
session_start();
header('Content-Type: application/json');
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

date_default_timezone_set('America/Guatemala');

try {
    $required = ['id_encamamiento', 'fecha_registro'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Campo requerido: $field");
        }
    }
    
    $id_encamamiento = intval($_POST['id_encamamiento']);
    $fecha_registro = $_POST['fecha_registro'];
    $temperatura = isset($_POST['temperatura']) && $_POST['temperatura'] !== '' ? floatval($_POST['temperatura']) : null;
    $presion_sistolica = isset($_POST['presion_sistolica']) && $_POST['presion_sistolica'] !== '' ? intval($_POST['presion_sistolica']) : null;
    $presion_diastolica = isset($_POST['presion_diastolica']) && $_POST['presion_diastolica'] !== '' ? intval($_POST['presion_diastolica']) : null;
    $pulso = isset($_POST['pulso']) && $_POST['pulso'] !== '' ? intval($_POST['pulso']) : null;
    $frecuencia_respiratoria = isset($_POST['frecuencia_respiratoria']) && $_POST['frecuencia_respiratoria'] !== '' ? intval($_POST['frecuencia_respiratoria']) : null;
    $saturacion_oxigeno = isset($_POST['saturacion_oxigeno']) && $_POST['saturacion_oxigeno'] !== '' ? floatval($_POST['saturacion_oxigeno']) : null;
    $glucometria = isset($_POST['glucometria']) && $_POST['glucometria'] !== '' ? floatval($_POST['glucometria']) : null;
    $notas = isset($_POST['notas']) ? trim($_POST['notas']) : null;
    $registrado_por = $_SESSION['user_id'];
    
    $database = new Database();
    $conn = $database->getConnection();
    
    $stmt = $conn->prepare("
        INSERT INTO signos_vitales 
        (id_encamamiento, fecha_registro, temperatura, presion_sistolica, presion_diastolica,
         pulso, frecuencia_respiratoria, saturacion_oxigeno, glucometria, notas, registrado_por)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $id_encamamiento,
        $fecha_registro,
        $temperatura,
        $presion_sistolica,
        $presion_diastolica,
        $pulso,
        $frecuencia_respiratoria,
        $saturacion_oxigeno,
        $glucometria,
        $notas,
        $registrado_por
    ]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Signos vitales guardados correctamente',
        'id_signo' => $conn->lastInsertId()
    ]);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
