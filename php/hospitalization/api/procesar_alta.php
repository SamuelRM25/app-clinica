<?php
/**
 * API: Process patient discharge
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

    $required = ['id_encamamiento', 'diagnostico_egreso'];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Campo requerido: $field");
        }
    }
    
    $id_encamamiento = intval($_POST['id_encamamiento']);
    $diagnostico_egreso = trim($_POST['diagnostico_egreso']);
    $notas_alta = isset($_POST['notas_alta']) ? trim($_POST['notas_alta']) : null;
    
    $database = new Database();
    $conn = $database->getConnection();

    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);
    
    // Start transaction
    $conn->beginTransaction();
    
    // Update encamamiento
    $stmt = $conn->prepare("
        UPDATE encamamientos SET 
            estado = 'Alta_Administrativa',
            fecha_alta = NOW(),
            diagnostico_egreso = ?,
            notas_alta = ?
        WHERE id_encamamiento = ? AND id_hospital = ?
    ");
    
    $stmt->execute([
        $diagnostico_egreso,
        $notas_alta,
        $id_encamamiento,
        $id_hospital
    ]);
    
    // Trigger will automatically set bed status to 'Disponible'
    // Verify it worked
    $stmt_verify = $conn->prepare("
        SELECT c.estado FROM camas c
        INNER JOIN encamamientos e ON c.id_cama = e.id_cama
        WHERE e.id_encamamiento = ? AND e.id_hospital = ?
    ");
    $stmt_verify->execute([$id_encamamiento, $id_hospital]);
    $bed = $stmt_verify->fetch(PDO::FETCH_ASSOC);
    
    if ($bed && $bed['estado'] !== 'Disponible') {
        // Trigger didn't fire, update manually
        $stmt_update_bed = $conn->prepare("
            UPDATE camas c
            INNER JOIN encamamientos e ON c.id_cama = e.id_cama
            SET c.estado = 'Disponible'
            WHERE e.id_encamamiento = ? AND e.id_hospital = ?
        ");
        $stmt_update_bed->execute([$id_encamamiento, $id_hospital]);
    }
    
    $conn->commit();

    audit_log('update', 'hospitalization', "Alta hospitalaria procesada - Encamamiento #$id_encamamiento", [
        'table_name' => 'encamamientos',
        'record_id' => (int)$id_encamamiento,
        'new_data' => [
            'estado' => 'Alta_Administrativa',
            'fecha_alta' => date('Y-m-d H:i:s'),
            'diagnostico_egreso' => $diagnostico_egreso,
            'notas_alta' => $notas_alta,
        ]
    ]);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Paciente dado de alta correctamente'
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
        error_log("hospitalization/api/procesar_alta.php error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error del servidor.']);
}
