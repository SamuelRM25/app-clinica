<?php
/**
 * API: Process patient discharge
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
    
    // Start transaction
    $conn->beginTransaction();
    
    // Update encamamiento
    $stmt = $conn->prepare("
        UPDATE encamamientos SET 
            estado = 'Alta_Administrativa',
            fecha_alta = NOW(),
            diagnostico_egreso = ?,
            notas_alta = ?
        WHERE id_encamamiento = ?
    ");
    
    $stmt->execute([
        $diagnostico_egreso,
        $notas_alta,
        $id_encamamiento
    ]);
    
    // Trigger will automatically set bed status to 'Disponible'
    // Verify it worked
    $stmt_verify = $conn->prepare("
        SELECT c.estado FROM camas c
        INNER JOIN encamamientos e ON c.id_cama = e.id_cama
        WHERE e.id_encamamiento = ?
    ");
    $stmt_verify->execute([$id_encamamiento]);
    $bed = $stmt_verify->fetch(PDO::FETCH_ASSOC);
    
    if ($bed && $bed['estado'] !== 'Disponible') {
        // Trigger didn't fire, update manually
        $stmt_update_bed = $conn->prepare("
            UPDATE camas c
            INNER JOIN encamamientos e ON c.id_cama = e.id_cama
            SET c.estado = 'Disponible'
            WHERE e.id_encamamiento = ?
        ");
        $stmt_update_bed->execute([$id_encamamiento]);
    }
    
    $conn->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Paciente dado de alta correctamente'
    ]);
    
} catch (Exception $e) {
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
