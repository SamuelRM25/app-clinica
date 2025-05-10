<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Validate required fields
        $required_fields = ['id_historial', 'id_paciente', 'motivo_consulta', 'sintomas', 'diagnostico', 'tratamiento', 'medico_responsable'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo $field es obligatorio");
            }
        }
        
        // Prepare SQL statement
        $sql = "UPDATE historial_clinico SET 
                    motivo_consulta = :motivo_consulta,
                    sintomas = :sintomas,
                    examen_fisico = :examen_fisico,
                    diagnostico = :diagnostico,
                    tratamiento = :tratamiento,
                    receta_medica = :receta_medica,
                    antecedentes_personales = :antecedentes_personales,
                    antecedentes_familiares = :antecedentes_familiares,
                    examenes_realizados = :examenes_realizados,
                    resultados_examenes = :resultados_examenes,
                    observaciones = :observaciones,
                    proxima_cita = :proxima_cita,
                    hora_proxima_cita = :hora_proxima_cita,
                    medico_responsable = :medico_responsable,
                    especialidad_medico = :especialidad_medico
                WHERE id_historial = :id_historial";
                
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':id_historial', $_POST['id_historial']);
        $stmt->bindParam(':motivo_consulta', $_POST['motivo_consulta']);
        $stmt->bindParam(':sintomas', $_POST['sintomas']);
        $stmt->bindParam(':examen_fisico', $_POST['examen_fisico']);
        $stmt->bindParam(':diagnostico', $_POST['diagnostico']);
        $stmt->bindParam(':tratamiento', $_POST['tratamiento']);
        $stmt->bindParam(':receta_medica', $_POST['receta_medica']);
        $stmt->bindParam(':antecedentes_personales', $_POST['antecedentes_personales']);
        $stmt->bindParam(':antecedentes_familiares', $_POST['antecedentes_familiares']);
        $stmt->bindParam(':examenes_realizados', $_POST['examenes_realizados']);
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
        
        // Execute the statement
        if ($stmt->execute()) {
            $_SESSION['message'] = "Registro médico actualizado correctamente";
            $_SESSION['message_type'] = "success";
            
            // If a next appointment date is set, update or create an appointment record
            if (!empty($proxima_cita)) {
                // Get the patient information for the appointment
                $patientStmt = $conn->prepare("SELECT nombre, apellido, telefono FROM pacientes WHERE id_paciente = :id_paciente");
                $patientStmt->bindParam(':id_paciente', $_POST['id_paciente']);
                $patientStmt->execute();
                $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if an appointment already exists for this medical record
                $checkStmt = $conn->prepare("SELECT id_cita FROM citas WHERE historial_id = :historial_id");
                $checkStmt->bindParam(':historial_id', $_POST['id_historial']);
                $checkStmt->execute();
                $existingAppointment = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                // Combine patient's first and last name
                $pacienteCita = $patient['nombre'] . ' ' . $patient['apellido'];
                
                // Set the time or "Pendiente" if not specified
                $horaCita = !empty($hora_proxima_cita) ? $hora_proxima_cita : "Pendiente";
                
                if ($existingAppointment) {
                    // Update existing appointment
                    $appointmentSql = "UPDATE citas SET 
                        nombre_pac = :nombre_pac,
                        apellido_pac = :apellido_pac,
                        fecha_cita = :fecha_cita,
                        hora_cita = :hora_cita,
                        telefono = :telefono
                        WHERE id_cita = :id_cita";
                    
                    $appointmentStmt = $conn->prepare($appointmentSql);
                    $appointmentStmt->bindParam(':nombre_pac', $patient['nombre']);
                    $appointmentStmt->bindParam(':apellido_pac', $patient['apellido']);
                    $appointmentStmt->bindParam(':fecha_cita', $proxima_cita);
                    $appointmentStmt->bindParam(':hora_cita', $horaCita);
                    $appointmentStmt->bindParam(':telefono', $patient['telefono']);
                    $appointmentStmt->bindParam(':id_cita', $existingAppointment['id_cita']);
                    
                    if ($appointmentStmt->execute()) {
                        $_SESSION['message'] .= " y se ha actualizado la próxima cita para el " . date('d/m/Y', strtotime($proxima_cita));
                        if (!empty($hora_proxima_cita)) {
                            $_SESSION['message'] .= " a las " . $hora_proxima_cita;
                        }
                    } else {
                        $_SESSION['message'] .= " pero hubo un error al actualizar la próxima cita";
                    }
                } else {
                    // Get the next appointment number
                    $numCitaStmt = $conn->query("SELECT MAX(num_cita) as max_num FROM citas");
                    $numCitaResult = $numCitaStmt->fetch(PDO::FETCH_ASSOC);
                    $numCita = ($numCitaResult['max_num'] ?? 0) + 1;
                    
                    // Create new appointment
                    $appointmentSql = "INSERT INTO citas (
                        nombre_pac, apellido_pac, num_cita, fecha_cita, hora_cita, telefono, historial_id
                    ) VALUES (
                        :nombre_pac, :apellido_pac, :num_cita, :fecha_cita, :hora_cita, :telefono, :historial_id
                    )";
                    
                    $appointmentStmt = $conn->prepare($appointmentSql);
                    $appointmentStmt->bindParam(':nombre_pac', $patient['nombre']);
                    $appointmentStmt->bindParam(':apellido_pac', $patient['apellido']);
                    $appointmentStmt->bindParam(':num_cita', $numCita);
                    $appointmentStmt->bindParam(':fecha_cita', $proxima_cita);
                    $appointmentStmt->bindParam(':hora_cita', $horaCita);
                    $appointmentStmt->bindParam(':telefono', $patient['telefono']);
                    $appointmentStmt->bindParam(':historial_id', $_POST['id_historial']);
                    
                    if ($appointmentStmt->execute()) {
                        $_SESSION['message'] .= " y se ha programado la próxima cita para el " . date('d/m/Y', strtotime($proxima_cita));
                        if (!empty($hora_proxima_cita)) {
                            $_SESSION['message'] .= " a las " . $hora_proxima_cita;
                        }
                    } else {
                        $_SESSION['message'] .= " pero hubo un error al programar la próxima cita";
                    }
                }
            }
        } else {
            throw new Exception("Error al actualizar el registro médico");
        }
        
    } catch (Exception $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
    
    // Redirect back to the medical history page
    header("Location: medical_history.php?id=" . $_POST['id_paciente']);
    exit;
}