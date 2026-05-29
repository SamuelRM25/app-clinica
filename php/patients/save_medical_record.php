<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');


verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Validate required fields
        $required_fields = ['id_paciente', 'motivo_consulta', 'sintomas', 'diagnostico', 'tratamiento', 'medico_responsable'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo $field es obligatorio");
            }
        }

        // Handle Physical Exam and Pain Points
        $examen_fisico = $_POST['examen_fisico'] ?? '';
        if (!empty($_POST['puntos_dolor'])) {
            $examen_fisico .= "\n\n[MAPA DE DOLOR]: " . $_POST['puntos_dolor'];
        }

        // Handle Lab Tests (Select2 Array)
        $examenes_ids = $_POST['examenes_realizados'] ?? [];
        $examenes_texto = '';
        if (!empty($examenes_ids)) {
            // Fetch names of selected tests for the text field
            $placeholders = implode(',', array_fill(0, count($examenes_ids), '?'));
            $stmtNames = $conn->prepare("SELECT nombre_prueba FROM catalogo_pruebas WHERE id_prueba IN ($placeholders) AND id_hospital = ?");
            $stmtNames->execute([...$examenes_ids, $id_hospital]);
            $test_names = $stmtNames->fetchAll(PDO::FETCH_COLUMN);
            $examenes_texto = implode(', ', $test_names);
        }


        // Prepare SQL statement
        $sql = "INSERT INTO historial_clinico (
                    id_paciente, motivo_consulta, sintomas, examen_fisico, diagnostico, tratamiento, 
                    receta_medica, antecedentes_personales, antecedentes_familiares, 
                    examenes_realizados, resultados_examenes, observaciones, 
                    proxima_cita, hora_proxima_cita, medico_responsable, especialidad_medico,
                    id_hospital
                ) VALUES (
                    :id_paciente, :motivo_consulta, :sintomas, :examen_fisico, :diagnostico, :tratamiento, 
                    :receta_medica, :antecedentes_personales, :antecedentes_familiares, 
                    :examenes_realizados, :resultados_examenes, :observaciones, 
                    :proxima_cita, :hora_proxima_cita, :medico_responsable, :especialidad_medico,
                    :id_hospital
                )";

        $stmt = $conn->prepare($sql);

        // Bind parameters
        $stmt->bindParam(':id_paciente', $_POST['id_paciente']);
        $stmt->bindParam(':motivo_consulta', $_POST['motivo_consulta']);
        $stmt->bindParam(':sintomas', $_POST['sintomas']);
        $stmt->bindParam(':examen_fisico', $examen_fisico);
        $stmt->bindParam(':diagnostico', $_POST['diagnostico']);
        $stmt->bindParam(':tratamiento', $_POST['tratamiento']);
        $stmt->bindParam(':receta_medica', $_POST['receta_medica']);
        $stmt->bindParam(':antecedentes_personales', $_POST['antecedentes_personales']);
        $stmt->bindParam(':antecedentes_familiares', $_POST['antecedentes_familiares']);
        $stmt->bindParam(':examenes_realizados', $examenes_texto);
        $stmt->bindParam(':resultados_examenes', $_POST['resultados_examenes']);
        $stmt->bindParam(':observaciones', $_POST['observaciones']);

        // Handle date field
        $proxima_cita = !empty($_POST['proxima_cita']) ? $_POST['proxima_cita'] : null;
        $stmt->bindParam(':proxima_cita', $proxima_cita);

        // Handle time field
        $hora_proxima_cita = !empty($_POST['hora_proxima_cita']) ? $_POST['hora_proxima_cita'] : null;
        $stmt->bindParam(':hora_proxima_cita', $hora_proxima_cita);

        $stmt->bindParam(':medico_responsable', $_POST['medico_responsable']);
        $stmt->bindParam(':especialidad_medico', $_POST['especialidad_medico']);
        $stmt->bindParam(':id_hospital', $id_hospital, PDO::PARAM_INT);

        // Execute the statement
        if ($stmt->execute()) {
            // Get the ID of the newly inserted medical record
            $historial_id = $conn->lastInsertId();

            $_SESSION['message'] = "Registro médico guardado correctamente";
            $_SESSION['message_type'] = "success";

            // If a next appointment date is set, create an appointment record
            if (!empty($proxima_cita)) {
                // Get the patient information for the appointment
                $patientStmt = $conn->prepare("SELECT nombre, apellido, telefono FROM pacientes WHERE id_paciente = :id_paciente AND id_hospital = :id_hospital");
                $patientStmt->bindParam(':id_paciente', $_POST['id_paciente']);
                $patientStmt->bindParam(':id_hospital', $id_hospital, PDO::PARAM_INT);
                $patientStmt->execute();
                $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);

                // Get the next appointment number
                $numCitaStmt = $conn->prepare("SELECT MAX(num_cita) as max_num FROM citas WHERE id_hospital = ?");
                $numCitaStmt->execute([$id_hospital]);
                $numCitaResult = $numCitaStmt->fetch(PDO::FETCH_ASSOC);
                $numCita = ($numCitaResult['max_num'] ?? 0) + 1;

                // Create the appointment
                $appointmentSql = "INSERT INTO citas (
                    nombre_pac, apellido_pac, num_cita, fecha_cita, hora_cita, telefono, historial_id, id_hospital
                ) VALUES (
                    :nombre_pac, :apellido_pac, :num_cita, :fecha_cita, :hora_cita, :telefono, :historial_id, :id_hospital
                )";

                $appointmentStmt = $conn->prepare($appointmentSql);

                // Use patient's first and last name separately
                $appointmentStmt->bindParam(':nombre_pac', $patient['nombre']);
                $appointmentStmt->bindParam(':apellido_pac', $patient['apellido']);
                $appointmentStmt->bindParam(':num_cita', $numCita);
                $appointmentStmt->bindParam(':fecha_cita', $proxima_cita);

                // Set the time or "Pendiente" if not specified
                $horaCita = !empty($hora_proxima_cita) ? $hora_proxima_cita : "Pendiente";
                $appointmentStmt->bindParam(':hora_cita', $horaCita);

                // Add patient's phone number
                $telefono = $patient['telefono'] ?? '';
                $appointmentStmt->bindParam(':telefono', $telefono);

                // Link to the medical record
                $appointmentStmt->bindParam(':historial_id', $historial_id);
                $appointmentStmt->bindParam(':id_hospital', $id_hospital, PDO::PARAM_INT);

                if ($appointmentStmt->execute()) {
                    $_SESSION['message'] .= " y se ha programado la próxima cita para el " . date('d/m/Y', strtotime($proxima_cita));
                    if (!empty($hora_proxima_cita)) {
                        $_SESSION['message'] .= " a las " . $hora_proxima_cita;
                    }
                } else {
                    $_SESSION['message'] .= " pero hubo un error al programar la próxima cita";
                }
            }

            // --- AUTOMATIC LAB ORDER LOGIC ---
            if (!empty($examenes_ids)) {
                // 1. Map Doctor Name to ID
                $stmtDoc = $conn->prepare("SELECT idUsuario FROM usuarios WHERE CONCAT(nombre, ' ', apellido) = ? AND id_hospital = ? LIMIT 1");
                $stmtDoc->execute([$_POST['medico_responsable'], $id_hospital]);
                $id_doctor = $stmtDoc->fetch(PDO::FETCH_ASSOC)['idUsuario'] ?? $_SESSION['user_id'];

                // 2. Generate unique order number
                $today_order = date('Ymd');
                $stmtCount = $conn->prepare("SELECT COUNT(*) as total FROM ordenes_laboratorio WHERE DATE(fecha_orden) = CURDATE() AND id_hospital = ?");
                $stmtCount->execute([$id_hospital]);
                $count = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'] + 1;
                $numero_orden = "LAB-" . $today_order . "-" . str_pad($count, 3, '0', STR_PAD_LEFT);

                // 3. Check if patient is hospitalized
                $stmtHosp = $conn->prepare("SELECT id_encamamiento FROM encamamientos WHERE id_paciente = ? AND estado = 'Activo' AND id_hospital = ? LIMIT 1");
                $stmtHosp->execute([$_POST['id_paciente'], $id_hospital]);
                $hosp_data = $stmtHosp->fetch(PDO::FETCH_ASSOC);
                $id_encamamiento = $hosp_data ? $hosp_data['id_encamamiento'] : null;

                // 4. Create Order
                $stmtOrder = $conn->prepare("
                    INSERT INTO ordenes_laboratorio (
                        numero_orden, id_paciente, id_doctor, id_encamamiento, 
                        prioridad, observaciones, estado, fecha_orden, id_hospital
                    ) VALUES (?, ?, ?, ?, 'Rutina', 'Orden generada desde consulta médica', 'Pendiente', NOW(), ?)
                ");
                $stmtOrder->execute([$numero_orden, $_POST['id_paciente'], $id_doctor, $id_encamamiento, $id_hospital]);
                $id_orden = $conn->lastInsertId();

                // 5. Insert Details and Billing
                $stmtDetail = $conn->prepare("INSERT INTO orden_pruebas (id_orden, id_prueba, estado) VALUES (?, ?, 'Pendiente')");
                $stmtPrice = $conn->prepare("SELECT nombre_prueba, precio FROM catalogo_pruebas WHERE id_prueba = ? AND id_hospital = ?");
                $items_billing = [];

                foreach ($examenes_ids as $id_prueba) {
                    $stmtDetail->execute([$id_orden, $id_prueba]);
                    $stmtPrice->execute([$id_prueba, $id_hospital]);
                    $test_inf = $stmtPrice->fetch(PDO::FETCH_ASSOC);
                    if ($test_inf) {
                        $items_billing[] = ['nombre' => $test_inf['nombre_prueba'], 'precio' => $test_inf['precio']];
                    }
                }

                // 6. Handling Billing/Cargos
                if ($id_encamamiento) {
                    $stmtCargo = $conn->prepare("
                        INSERT INTO cargos_hospitalarios (id_cuenta, tipo_cargo, descripcion, precio_unitario, fecha_cargo, registrado_por, id_hospital)
                        VALUES (
                            (SELECT id_cuenta FROM cuenta_hospitalaria WHERE id_encamamiento = ? AND estado_pago = 'Pendiente' LIMIT 1),
                            'Laboratorio', ?, ?, NOW(), ?, ?
                        )
                    ");
                    foreach ($items_billing as $item) {
                        $stmtCargo->execute([$id_encamamiento, "Laboratorio: " . $item['nombre'] . " (Orden #" . $numero_orden . ")", $item['precio'], $_SESSION['user_id'], $id_hospital]);
                    }
                } else {
                    // Regular payment record (using same logic as save_order.php)
                    $total_bill = array_sum(array_column($items_billing, 'precio'));
                    $desc_bill = "Servicios Laboratorio Order #" . $numero_orden . ": " . implode(", ", array_column($items_billing, 'nombre'));
                    
                    $patientDataStmt = $conn->prepare("SELECT CONCAT(nombre, ' ', apellido) as nombre_full FROM pacientes WHERE id_paciente = ? AND id_hospital = ?");
                    $patientDataStmt->execute([$_POST['id_paciente'], $id_hospital]);
                    $pac_full = $patientDataStmt->fetch(PDO::FETCH_ASSOC)['nombre_full'] ?? 'Paciente';

                    $stmtBill = $conn->prepare("
                        INSERT INTO examenes_realizados (id_paciente, id_orden, nombre_paciente, tipo_examen, cobro, tipo_pago, fecha_examen, id_hospital)
                        VALUES (?, ?, ?, ?, ?, 'Efectivo', NOW(), ?)
                    ");
                    $stmtBill->execute([$_POST['id_paciente'], $id_orden, $pac_full, $desc_bill, $total_bill, $id_hospital]);
                }
                
                $_SESSION['message'] .= " y se ha generado la Orden de Laboratorio #$numero_orden";
            }

        } else {
            throw new Exception("Error al guardar el registro médico");
        }

    } catch (Exception $e) {
        error_log('Error en patients/save_medical_record.php: ' . $e->getMessage());
        $_SESSION['message'] = "Error: " . 'Error del servidor.';
        $_SESSION['message_type'] = "danger";
    }

    // Redirect back to the medical history page
    header("Location: medical_history.php?id=" . $_POST['id_paciente']);
    exit;
}