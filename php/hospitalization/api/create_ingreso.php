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
    $is_retrasado = isset($_POST['is_retrasado']) && $_POST['is_retrasado'] == '1';
    $tipo_ingreso = $_POST['tipo_ingreso'] ?? '';

    // Adjust required fields based on tipo_ingreso
    if ($tipo_ingreso !== 'Referido') {
        if (!isset($_POST['id_paciente']) || empty($_POST['id_paciente'])) {
            throw new Exception("Campo requerido faltante: id_paciente");
        }
    } else {
        if (
            (!isset($_POST['id_paciente']) || empty($_POST['id_paciente'])) &&
            (!isset($_POST['referido_nombre']) || empty($_POST['referido_nombre']))
        ) {
            throw new Exception("Debe seleccionar un paciente o ingresar el nombre del paciente referido");
        }
    }

    $id_paciente = isset($_POST['id_paciente']) ? intval($_POST['id_paciente']) : 0;
    $id_cama = intval($_POST['id_cama']);
    $id_doctor = isset($_POST['id_doctor']) ? intval($_POST['id_doctor']) : 0;
    $referido_doctor = isset($_POST['referido_doctor']) ? trim($_POST['referido_doctor']) : '';

    $fecha_ingreso = $_POST['fecha_ingreso'];
    $motivo_ingreso = trim($_POST['motivo_ingreso']);
    $diagnostico_ingreso = trim($_POST['diagnostico_ingreso']);
    $tipo_ingreso = $_POST['tipo_ingreso'];
    $notas_ingreso = isset($_POST['notas_ingreso']) ? trim($_POST['notas_ingreso']) : '';

    if ($is_retrasado) {
        $notas_ingreso = '[RETRASADO] ' . $notas_ingreso;
    }

    if ($tipo_ingreso === 'Referido' && !empty($referido_doctor)) {
        $notas_ingreso = '[MÉDICO REFERENTE: ' . $referido_doctor . '] ' . $notas_ingreso;
        // If id_doctor is missing, use current user as placeholder
        if ($id_doctor === 0) {
            $id_doctor = $_SESSION['user_id'];
        }
    } else if ($id_doctor === 0) {
        throw new Exception("Campo requerido faltante: id_doctor");
    }

    $fecha_alta = $is_retrasado ? $_POST['fecha_alta'] : null;
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

    // For Referred patients, if id_paciente is not provided, create a minimal record
    if ($tipo_ingreso === 'Referido' && $id_paciente === 0) {
        $nombre = trim($_POST['referido_nombre']);
        $apellido = trim($_POST['referido_apellido'] ?? '');

        $stmt_new_pac = $conn->prepare("
            INSERT INTO pacientes (nombre, apellido, fecha_nacimiento, genero, direccion) 
            VALUES (?, ?, '1900-01-01', 'Masculino', 'Referente Externo')
        ");
        $stmt_new_pac->execute([$nombre, $apellido]);
        $id_paciente = $conn->lastInsertId();
    }

    // Verificar que el paciente existe
    $stmt_patient = $conn->prepare("SELECT id_paciente, nombre, apellido FROM pacientes WHERE id_paciente = ?");
    $stmt_patient->execute([$id_paciente]);
    $patient = $stmt_patient->fetch();

    if (!$patient) {
        throw new Exception("El paciente seleccionado no existe");
    }

    // Verificar si el paciente tiene un registro en historial_clinico
    // Si no existe, crear uno mínimo para satisfacer la restricción de clave foránea
    $stmt_check_historial = $conn->prepare("SELECT id_paciente FROM historial_clinico WHERE id_paciente = ? LIMIT 1");
    $stmt_check_historial->execute([$id_paciente]);

    if (!$stmt_check_historial->fetch()) {
        // Crear registro mínimo en historial_clinico
        $stmt_create_historial = $conn->prepare("
            INSERT INTO historial_clinico 
            (id_paciente, fecha_consulta, motivo_consulta, sintomas, diagnostico, tratamiento, medico_responsable) 
            VALUES (?, NOW(), ?, '', ?, '', ?)
        ");
        // For medico_responsable, we'll use the user ID as a placeholder if a name isn't available.
        // If 'medico_responsable' is expected to be a name, you might need to fetch the user's name from the 'users' table.
        // For now, using 'Sistema' as per the instruction's implied fallback.
        $stmt_create_historial->execute([
            $id_paciente,
            $motivo_ingreso,
            $diagnostico_ingreso ?? 'Ingreso hospitalario',
            'Sistema' // Using 'Sistema' as created_by_name is not defined and this is a minimal record.
        ]);
    }

    // Check if patient already has an active admission
    $stmt_check_active = $conn->prepare("SELECT id_encamamiento FROM encamamientos WHERE id_paciente = ? AND estado = 'Activo'");
    $stmt_check_active->execute([$id_paciente]);
    if ($stmt_check_active->fetch()) {
        throw new Exception("El paciente ya tiene un encamamiento activo");
    }

    // Insert encamamiento
    $estado_inicial = $is_retrasado ? 'Alta_Administrativa' : 'Activo';
    $stmt_insert = $conn->prepare("
        INSERT INTO encamamientos 
        (id_paciente, id_cama, id_doctor, fecha_ingreso, fecha_alta, motivo_ingreso, 
         diagnostico_ingreso, tipo_ingreso, notas_ingreso, estado, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt_insert->execute([
        $id_paciente,
        $id_cama,
        $id_doctor,
        $fecha_ingreso,
        $fecha_alta,
        $motivo_ingreso,
        $diagnostico_ingreso,
        $tipo_ingreso,
        $notas_ingreso,
        $estado_inicial,
        $created_by
    ]);

    $id_encamamiento = $conn->lastInsertId();

    // NOTE: Bed status update and cuenta creation are handled by triggers
    // But we verify them here for safety

    // Verify bed status
    $target_bed_status = $is_retrasado ? 'Disponible' : 'Ocupada';
    $stmt_verify_bed = $conn->prepare("SELECT estado FROM camas WHERE id_cama = ?");
    $stmt_verify_bed->execute([$id_cama]);
    $updated_bed = $stmt_verify_bed->fetch(PDO::FETCH_ASSOC);

    if ($updated_bed['estado'] !== $target_bed_status) {
        // Manual override if trigger didn't set expected status
        $stmt_update_bed = $conn->prepare("UPDATE camas SET estado = ? WHERE id_cama = ?");
        $stmt_update_bed->execute([$target_bed_status, $id_cama]);
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
