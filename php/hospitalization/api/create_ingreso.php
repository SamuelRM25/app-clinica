<?php
/**
 * API: Create patient admission (encamamento)
 * Creates the admission record, updates bed status, and auto-creates hospital account
 */

session_start();
header('Content-Type: application/json');
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

// Verify session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

date_default_timezone_set('America/Guatemala');

try {
    // Validate input
    $required_fields = ['id_paciente', 'id_cama', 'id_doctor', 'fecha_ingreso', 'motivo_ingreso', 'diagnostico_ingreso', 'tipo_ingreso'];
    
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Campo requerido faltante: $field");
        }
    }
    
    $id_paciente = intval($_POST['id_paciente']);
    $id_cama = intval($_POST['id_cama']);
    $id_doctor = intval($_POST['id_doctor']);
    $fecha_ingreso = $_POST['fecha_ingreso'];
    $motivo_ingreso = trim($_POST['motivo_ingreso']);
    $diagnostico_ingreso = trim($_POST['diagnostico_ingreso']);
    $tipo_ingreso = $_POST['tipo_ingreso'];
    $notas_ingreso = isset($_POST['notas_ingreso']) ? trim($_POST['notas_ingreso']) : null;
    $created_by = $_SESSION['user_id'];
    
    // Connect to database
    $database = new Database();
    $conn = $database->getConnection();
    
    // Start transaction
    $conn->beginTransaction();
    
    // Verify bed is available
    $stmt_check_bed = $conn->prepare("SELECT estado FROM camas WHERE id_cama = ?");
    $stmt_check_bed->execute([$id_cama]);
    $bed = $stmt_check_bed->fetch(PDO::FETCH_ASSOC);
    
    if (!$bed || $bed['estado'] !== 'Disponible') {
        throw new Exception("La cama seleccionada no está disponible");
    }
    
    // Verify patient exists
    $stmt_check_patient = $conn->prepare("SELECT id_paciente FROM pacientes WHERE id_paciente = ?");
    $stmt_check_patient->execute([$id_paciente]);
    if (!$stmt_check_patient->fetch()) {
        throw new Exception("Paciente no encontrado");
    }
    
    // Check if patient already has an active admission
    $stmt_check_active = $conn->prepare("SELECT id_encamamiento FROM encamamientos WHERE id_paciente = ? AND estado = 'Activo'");
    $stmt_check_active->execute([$id_paciente]);
    if ($stmt_check_active->fetch()) {
        throw new Exception("El paciente ya tiene un encamamiento activo");
    }
    
    // Insert encamamiento
    $stmt_insert = $conn->prepare("
        INSERT INTO encamamientos 
        (id_paciente, id_cama, id_doctor, fecha_ingreso, motivo_ingreso, 
         diagnostico_ingreso, tipo_ingreso, notas_ingreso, estado, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Activo', ?)
    ");
    
    $stmt_insert->execute([
        $id_paciente,
        $id_cama,
        $id_doctor,
        $fecha_ingreso,
        $motivo_ingreso,
        $diagnostico_ingreso,
        $tipo_ingreso,
        $notas_ingreso,
        $created_by
    ]);
    
    $id_encamamiento = $conn->lastInsertId();
    
    // NOTE: Bed status update and cuenta creation are handled by triggers
    // But we verify them here for safety
    
    // Verify bed was updated by trigger
    $stmt_verify_bed = $conn->prepare("SELECT estado FROM camas WHERE id_cama = ?");
    $stmt_verify_bed->execute([$id_cama]);
    $updated_bed = $stmt_verify_bed->fetch(PDO::FETCH_ASSOC);
    
    if ($updated_bed['estado'] !== 'Ocupada') {
        // Trigger didn't fire, update manually
        $stmt_update_bed = $conn->prepare("UPDATE camas SET estado = 'Ocupada' WHERE id_cama = ?");
        $stmt_update_bed->execute([$id_cama]);
    }
    
    // Verify cuenta was created by trigger
    $stmt_verify_cuenta = $conn->prepare("SELECT id_cuenta FROM cuenta_hospitalaria WHERE id_encamamiento = ?");
    $stmt_verify_cuenta->execute([$id_encamamiento]);
    if (!$stmt_verify_cuenta->fetch()) {
        // Trigger didn't fire, create manually
        $stmt_create_cuenta = $conn->prepare("INSERT INTO cuenta_hospitalaria (id_encamamiento) VALUES (?)");
        $stmt_create_cuenta->execute([$id_encamamiento]);
    }
    
    // Register first room charge (admission day)
    $stmt_room_info = $conn->prepare("
        SELECT h.tarifa_por_noche, h.numero_habitacion, c.numero_cama
        FROM camas c
        INNER JOIN habitaciones h ON c.id_habitacion = h.id_habitacion
        WHERE c.id_cama = ?
    ");
    $stmt_room_info->execute([$id_cama]);
    $room_info = $stmt_room_info->fetch(PDO::FETCH_ASSOC);
    
    if ($room_info) {
        $stmt_cuenta_id = $conn->prepare("SELECT id_cuenta FROM cuenta_hospitalaria WHERE id_encamamiento = ?");
        $stmt_cuenta_id->execute([$id_encamamiento]);
        $cuenta = $stmt_cuenta_id->fetch(PDO::FETCH_ASSOC);
        
        if ($cuenta) {
            $fecha_cargo = date('Y-m-d', strtotime($fecha_ingreso));
            $descripcion_cargo = "Habitación " . $room_info['numero_habitacion'] . " - Cama " . $room_info['numero_cama'] . " (Día de ingreso)";
            
            $stmt_cargo = $conn->prepare("
                INSERT INTO cargos_hospitalarios 
                (id_cuenta, tipo_cargo, descripcion, cantidad, precio_unitario, fecha_cargo, fecha_aplicacion, registrado_por)
                VALUES (?, 'Habitación', ?, 1, ?, ?, ?, ?)
            ");
            
            $stmt_cargo->execute([
                $cuenta['id_cuenta'],
                $descripcion_cargo,
                $room_info['tarifa_por_noche'],
                $fecha_ingreso,
                $fecha_cargo,
                $created_by
            ]);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Return success
    echo json_encode([
        'status' => 'success',
        'message' => 'Paciente ingresado correctamente',
        'id_encamamiento' => $id_encamamiento,
        'data' => [
            'id_paciente' => $id_paciente,
            'id_cama' => $id_cama,
            'fecha_ingreso' => $fecha_ingreso
        ]
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
