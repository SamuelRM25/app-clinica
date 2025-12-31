<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');


verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        
        // Prepare SQL statement
        $sql = "INSERT INTO historial_clinico (
                    id_paciente, motivo_consulta, sintomas, examen_fisico, diagnostico, tratamiento, 
                    receta_medica, antecedentes_personales, antecedentes_familiares, 
                    examenes_realizados, resultados_examenes, observaciones, 
                    proxima_cita, hora_proxima_cita, medico_responsable, especialidad_medico
                ) VALUES (
                    :id_paciente, :motivo_consulta, :sintomas, :examen_fisico, :diagnostico, :tratamiento, 
                    :receta_medica, :antecedentes_personales, :antecedentes_familiares, 
                    :examenes_realizados, :resultados_examenes, :observaciones, 
                    :proxima_cita, :hora_proxima_cita, :medico_responsable, :especialidad_medico
                )";
                
        $stmt = $conn->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':id_paciente', $_POST['id_paciente']);
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
            // Get the ID of the newly inserted medical record
            $historial_id = $conn->lastInsertId();
            
            $_SESSION['message'] = "Registro médico guardado correctamente";
            $_SESSION['message_type'] = "success";
            
            // If a next appointment date is set, create an appointment record
            if (!empty($proxima_cita)) {
                // Get the patient information for the appointment
                $patientStmt = $conn->prepare("SELECT nombre, apellido, telefono FROM pacientes WHERE id_paciente = :id_paciente");
                $patientStmt->bindParam(':id_paciente', $_POST['id_paciente']);
                $patientStmt->execute();
                $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
                
                // Get the next appointment number
                $numCitaStmt = $conn->query("SELECT MAX(num_cita) as max_num FROM citas");
                $numCitaResult = $numCitaStmt->fetch(PDO::FETCH_ASSOC);
                $numCita = ($numCitaResult['max_num'] ?? 0) + 1;
                
                // Create the appointment
                $appointmentSql = "INSERT INTO citas (
                    nombre_pac, apellido_pac, num_cita, fecha_cita, hora_cita, telefono, historial_id
                ) VALUES (
                    :nombre_pac, :apellido_pac, :num_cita, :fecha_cita, :hora_cita, :telefono, :historial_id
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
                
                if ($appointmentStmt->execute()) {
                    $_SESSION['message'] .= " y se ha programado la próxima cita para el " . date('d/m/Y', strtotime($proxima_cita));
                    if (!empty($hora_proxima_cita)) {
                        $_SESSION['message'] .= " a las " . $hora_proxima_cita;
                    }
                } else {
                    $_SESSION['message'] .= " pero hubo un error al programar la próxima cita";
                }
            }
        } else {
            throw new Exception("Error al guardar el registro médico");
        }
        
    } catch (Exception $e) {
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }
    
    // Redirect back to the medical history page
    header("Location: medical_history.php?id=" . $_POST['id_paciente']);
    exit;
}