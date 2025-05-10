<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Mexico_City'); // Ajusta esto a tu zona horaria local


verify_session();

try {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $_SESSION['message'] = "ID de paciente inválido";
        $_SESSION['message_type'] = "danger";
        header("Location: index.php");
        exit;
    }

    $patient_id = $_GET['id'];
    
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get patient information
    $stmt = $conn->prepare("SELECT * FROM pacientes WHERE id_paciente = ?");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        $_SESSION['message'] = "Paciente no encontrado";
        $_SESSION['message_type'] = "danger";
        header("Location: index.php");
        exit;
    }
    
    // Get patient's medical history
    $stmt = $conn->prepare("SELECT * FROM historial_clinico WHERE id_paciente = ? ORDER BY fecha_consulta DESC");
    $stmt->execute([$patient_id]);
    $medical_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $page_title = "Historial Clínico - " . $patient['nombre'] . " " . $patient['apellido'];
    include_once '../../includes/header.php';
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<div class="d-flex">
    <?php include_once '../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center">
                    <a href="index.php" class="btn btn-outline-secondary me-3">
                        <i class="bi bi-arrow-left"></i> Regresar
                    </a>
                    <h2>Historial Clínico: <?php echo htmlspecialchars($patient['nombre'] . ' ' . $patient['apellido']); ?></h2>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newMedicalRecordModal">
                    <i class="bi bi-plus-circle me-2"></i>Nueva Consulta
                </button>
            </div>

            <!-- Patient Info Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Fecha de Nacimiento:</strong> <?php echo htmlspecialchars($patient['fecha_nacimiento']); ?></p>
                            <p><strong>Género:</strong> <?php echo htmlspecialchars($patient['genero']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($patient['telefono']); ?></p>
                            <p><strong>Correo:</strong> <?php echo htmlspecialchars($patient['correo']); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Medical History Records -->
            <?php if (count($medical_records) > 0): ?>
                <div class="accordion" id="medicalHistoryAccordion">
                    <?php foreach ($medical_records as $index => $record): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                <button class="accordion-button <?php echo ($index > 0) ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="<?php echo ($index === 0) ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $index; ?>">
                                    <div class="d-flex justify-content-between w-100 me-3">
                                        <span><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($record['fecha_consulta'])); ?></span>
                                        <span><strong>Médico:</strong> <?php echo htmlspecialchars($record['medico_responsable']); ?></span>
                                    </div>
                                </button>
                            </h2>
                            <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo ($index === 0) ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#medicalHistoryAccordion">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5>Motivo de Consulta</h5>
                                            <p><?php echo nl2br(htmlspecialchars($record['motivo_consulta'])); ?></p>
                                            
                                            <h5>Historia de la enfermedad actual</h5>
                                            <p><?php echo nl2br(htmlspecialchars($record['sintomas'])); ?></p>
                                            
                                            <?php if (!empty($record['examen_fisico'])): ?>
                                            <div class="mb-3">
                                                <div class="card">
                                                    <div class="card-header bg-light" data-bs-toggle="collapse" data-bs-target="#collapseExamenFisico<?php echo $index; ?>" aria-expanded="false" style="cursor: pointer;">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <h5 class="mb-0">Examen Físico</h5>
                                                            <i class="bi bi-chevron-down"></i>
                                                        </div>
                                                    </div>
                                                    <div class="collapse" id="collapseExamenFisico<?php echo $index; ?>">
                                                        <div class="card-body">
                                                            <p><?php echo nl2br(htmlspecialchars($record['examen_fisico'])); ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <h5>Diagnóstico</h5>
                                            <p><?php echo nl2br(htmlspecialchars($record['diagnostico'])); ?></p>
                                            
                                            <h5>Tratamiento</h5>
                                            <p><?php echo nl2br(htmlspecialchars($record['tratamiento'])); ?></p>
                                            
                                            <?php if (!empty($record['receta_medica'])): ?>
                                                <h5>Receta Médica</h5>
                                                <p><?php echo nl2br(htmlspecialchars($record['receta_medica'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6">
                                            <?php if (!empty($record['antecedentes_personales'])): ?>
                                                <h5>Antecedentes Personales</h5>
                                                <p><?php echo nl2br(htmlspecialchars($record['antecedentes_personales'])); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($record['antecedentes_familiares'])): ?>
                                                <h5>Antecedentes Familiares</h5>
                                                <p><?php echo nl2br(htmlspecialchars($record['antecedentes_familiares'])); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($record['examenes_realizados'])): ?>
                                                <h5>Exámenes Realizados</h5>
                                                <p><?php echo nl2br(htmlspecialchars($record['examenes_realizados'])); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($record['resultados_examenes'])): ?>
                                                <h5>Resultados de Exámenes</h5>
                                                <p><?php echo nl2br(htmlspecialchars($record['resultados_examenes'])); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($record['observaciones'])): ?>
                                                <h5>Observaciones</h5>
                                                <p><?php echo nl2br(htmlspecialchars($record['observaciones'])); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($record['proxima_cita'])): ?>
                                                <h5>Próxima Cita</h5>
                                                <p><?php echo date('d/m/Y', strtotime($record['proxima_cita'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="mt-3 text-end">
                                        <button class="btn btn-sm btn-primary" onclick="editMedicalRecord(<?php echo $record['id_historial']; ?>)">
                                            <i class="bi bi-pencil"></i> Editar
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteMedicalRecord(<?php echo $record['id_historial']; ?>)">
                                            <i class="bi bi-trash"></i> Eliminar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    No hay registros de historial clínico para este paciente.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- New Medical Record Modal -->
<div class="modal fade" id="newMedicalRecordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nueva Consulta Médica</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="newMedicalRecordForm" action="save_medical_record.php" method="POST">
                <input type="hidden" name="id_paciente" value="<?php echo $patient_id; ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Motivo de Consulta</label>
                                <textarea class="form-control" name="motivo_consulta" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Historia de la Enfermedad Actual</label>
                                <textarea class="form-control" name="sintomas" rows="3" required></textarea>
                            </div>
                            
                            <!-- Examen Físico Retráctil -->
                            <div class="mb-3">
                                <div class="card">
                                    <div class="card-header bg-light" data-bs-toggle="collapse" data-bs-target="#collapseExamenFisico" aria-expanded="false" style="cursor: pointer;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <label class="form-label mb-0">Examen Físico</label>
                                            <i class="bi bi-chevron-down"></i>
                                        </div>
                                    </div>
                                    <div class="collapse" id="collapseExamenFisico">
                                        <div class="card-body">
                                            <div class="row">
                                            <div class="col-md-12 mb-2">
                                                    <label class="form-label small">Signos Vitales</label>
                                                    <div class="row g-2">
                                                        <div class="col-md-3">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text">PA</span>
                                                                <input type="text" class="form-control form-control-sm" name="examen_fisico_pa" placeholder="120/80">
                                                                <span class="input-group-text">mmHg</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text">FC</span>
                                                                <input type="text" class="form-control form-control-sm" name="examen_fisico_fc" placeholder="80">
                                                                <span class="input-group-text">lpm</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text">FR</span>
                                                                <input type="text" class="form-control form-control-sm" name="examen_fisico_fr" placeholder="16">
                                                                <span class="input-group-text">rpm</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text">T°</span>
                                                                <input type="text" class="form-control form-control-sm" name="examen_fisico_temp" placeholder="36.5">
                                                                <span class="input-group-text">°C</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label small">Inspección General</label>
                                                    <textarea class="form-control form-control-sm" name="examen_fisico_inspeccion" rows="2" placeholder="Estado general, facies, piel..."></textarea>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label small">Cabeza y Cuello</label>
                                                    <textarea class="form-control form-control-sm" name="examen_fisico_cabeza" rows="2"></textarea>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label small">Tórax y Pulmones</label>
                                                    <textarea class="form-control form-control-sm" name="examen_fisico_torax" rows="2"></textarea>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label small">Cardiovascular</label>
                                                    <textarea class="form-control form-control-sm" name="examen_fisico_cardio" rows="2"></textarea>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label small">Abdomen</label>
                                                    <textarea class="form-control form-control-sm" name="examen_fisico_abdomen" rows="2"></textarea>
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label small">Extremidades</label>
                                                    <textarea class="form-control form-control-sm" name="examen_fisico_extremidades" rows="2"></textarea>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label small">Neurológico</label>
                                                    <textarea class="form-control form-control-sm" name="examen_fisico_neuro" rows="2"></textarea>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <label class="form-label small">Otros Hallazgos</label>
                                                <textarea class="form-control form-control-sm" name="examen_fisico_otros" rows="2"></textarea>
                                            </div>
                                            <input type="hidden" name="examen_fisico" id="examen_fisico_completo">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Diagnóstico</label>
                                <textarea class="form-control" name="diagnostico" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tratamiento</label>
                                <textarea class="form-control" name="tratamiento" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Receta Médica</label>
                                <textarea class="form-control" name="receta_medica" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Antecedentes Personales</label>
                                <textarea class="form-control" name="antecedentes_personales" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Antecedentes Familiares</label>
                                <textarea class="form-control" name="antecedentes_familiares" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Exámenes Realizados</label>
                                <textarea class="form-control" name="examenes_realizados" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Resultados de Exámenes</label>
                                <textarea class="form-control" name="resultados_examenes" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Observaciones</label>
                                <textarea class="form-control" name="observaciones" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Próxima Cita</label>
                                <input type="date" class="form-control" name="proxima_cita">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Hora de Próxima Cita</label>
                                <input type="time" class="form-control" name="hora_proxima_cita">
                                <small class="text-muted">Opcional. Si no se especifica, quedará como "Pendiente"</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Médico Responsable</label>
                                <input type="text" class="form-control" name="medico_responsable" value="<?php echo htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Especialidad</label>
                                <input type="text" class="form-control" name="especialidad_medico" value="<?php echo htmlspecialchars($_SESSION['especialidad']); ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Medical Record Modal -->
<div class="modal fade" id="editMedicalRecordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Consulta Médica</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editMedicalRecordForm" action="update_medical_record.php" method="POST">
                <input type="hidden" name="id_historial" id="edit_id_historial">
                <input type="hidden" name="id_paciente" value="<?php echo $patient_id; ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Motivo de Consulta</label>
                                <textarea class="form-control" name="motivo_consulta" id="edit_motivo_consulta" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Síntomas</label>
                                <textarea class="form-control" name="sintomas" id="edit_sintomas" rows="3" required></textarea>
                            </div>
                            
                            <!-- Examen Físico Retráctil (Edición) -->
                            <div class="mb-3">
                                <div class="card">
                                    <div class="card-header bg-light" data-bs-toggle="collapse" data-bs-target="#collapseExamenFisicoEdit" aria-expanded="false" style="cursor: pointer;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <label class="form-label mb-0">Examen Físico</label>
                                            <i class="bi bi-chevron-down"></i>
                                        </div>
                                    </div>
                                    <div class="collapse" id="collapseExamenFisicoEdit">
                                        <div class="card-body">
                                            <textarea class="form-control" name="examen_fisico" id="edit_examen_fisico" rows="5" placeholder="Registre los hallazgos del examen físico completo..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Diagnóstico</label>
                                <textarea class="form-control" name="diagnostico" id="edit_diagnostico" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tratamiento</label>
                                <textarea class="form-control" name="tratamiento" id="edit_tratamiento" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Receta Médica</label>
                                <textarea class="form-control" name="receta_medica" id="edit_receta_medica" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Antecedentes Personales</label>
                                <textarea class="form-control" name="antecedentes_personales" id="edit_antecedentes_personales" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Antecedentes Familiares</label>
                                <textarea class="form-control" name="antecedentes_familiares" id="edit_antecedentes_familiares" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Exámenes Realizados</label>
                                <textarea class="form-control" name="examenes_realizados" id="edit_examenes_realizados" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Resultados de Exámenes</label>
                                <textarea class="form-control" name="resultados_examenes" id="edit_resultados_examenes" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Observaciones</label>
                                <textarea class="form-control" name="observaciones" id="edit_observaciones" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Próxima Cita</label>
                                <input type="date" class="form-control" name="proxima_cita" id="edit_proxima_cita">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Hora de Próxima Cita</label>
                                <input type="time" class="form-control" name="hora_proxima_cita" id="edit_hora_proxima_cita">
                                <small class="text-muted">Opcional. Si no se especifica, quedará como "Pendiente"</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Médico Responsable</label>
                                <input type="text" class="form-control" name="medico_responsable" id="edit_medico_responsable" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Especialidad</label>
                                <input type="text" class="form-control" name="especialidad_medico" id="edit_especialidad_medico">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>

<script>
// Función para combinar los campos del examen físico antes de enviar el formulario
document.getElementById('newMedicalRecordForm').addEventListener('submit', function(e) {
    // Recopilar los signos vitales individuales
    const pa = document.querySelector('[name="examen_fisico_pa"]').value;
    const fc = document.querySelector('[name="examen_fisico_fc"]').value;
    const fr = document.querySelector('[name="examen_fisico_fr"]').value;
    const temp = document.querySelector('[name="examen_fisico_temp"]').value;
    
    // Construir el texto de signos vitales
    let signosVitales = '';
    if (pa) signosVitales += 'PA: ' + pa + ' mmHg, ';
    if (fc) signosVitales += 'FC: ' + fc + ' lpm, ';
    if (fr) signosVitales += 'FR: ' + fr + ' rpm, ';
    if (temp) signosVitales += 'T°: ' + temp + ' °C';
    
    // Eliminar la última coma si existe
    signosVitales = signosVitales.replace(/,\s*$/, '');
    
    // Recopilar todos los demás campos del examen físico
    const inspeccion = document.querySelector('[name="examen_fisico_inspeccion"]').value;
    const cabeza = document.querySelector('[name="examen_fisico_cabeza"]').value;
    const torax = document.querySelector('[name="examen_fisico_torax"]').value;
    const cardio = document.querySelector('[name="examen_fisico_cardio"]').value;
    const abdomen = document.querySelector('[name="examen_fisico_abdomen"]').value;
    const extremidades = document.querySelector('[name="examen_fisico_extremidades"]').value;
    const neuro = document.querySelector('[name="examen_fisico_neuro"]').value;
    const otros = document.querySelector('[name="examen_fisico_otros"]').value;
    
    // Construir el texto completo del examen físico
    let examenCompleto = '';
    
    if (signosVitales.trim()) examenCompleto += 'SIGNOS VITALES:\n' + signosVitales + '\n\n';
    if (inspeccion.trim()) examenCompleto += 'INSPECCIÓN GENERAL:\n' + inspeccion + '\n\n';
    if (cabeza.trim()) examenCompleto += 'CABEZA Y CUELLO:\n' + cabeza + '\n\n';
    if (torax.trim()) examenCompleto += 'TÓRAX Y PULMONES:\n' + torax + '\n\n';
    if (cardio.trim()) examenCompleto += 'CARDIOVASCULAR:\n' + cardio + '\n\n';
    if (abdomen.trim()) examenCompleto += 'ABDOMEN:\n' + abdomen + '\n\n';
    if (extremidades.trim()) examenCompleto += 'EXTREMIDADES:\n' + extremidades + '\n\n';
    if (neuro.trim()) examenCompleto += 'NEUROLÓGICO:\n' + neuro + '\n\n';
    if (otros.trim()) examenCompleto += 'OTROS HALLAZGOS:\n' + otros;
    
    // Asignar al campo oculto
    document.getElementById('examen_fisico_completo').value = examenCompleto.trim();
});

// Función para cargar el examen físico en el formulario de edición
function editMedicalRecord(id) {
    // Fetch medical record data
    fetch('get_medical_record.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const record = data.record;
                
                // Fill the edit form
                document.getElementById('edit_id_historial').value = record.id_historial;
                document.getElementById('edit_motivo_consulta').value = record.motivo_consulta;
                document.getElementById('edit_sintomas').value = record.sintomas;
                document.getElementById('edit_examen_fisico').value = record.examen_fisico || '';
                document.getElementById('edit_diagnostico').value = record.diagnostico;
                document.getElementById('edit_tratamiento').value = record.tratamiento;
                document.getElementById('edit_receta_medica').value = record.receta_medica;
                document.getElementById('edit_antecedentes_personales').value = record.antecedentes_personales;
                document.getElementById('edit_antecedentes_familiares').value = record.antecedentes_familiares;
                document.getElementById('edit_examenes_realizados').value = record.examenes_realizados;
                document.getElementById('edit_resultados_examenes').value = record.resultados_examenes;
                document.getElementById('edit_observaciones').value = record.observaciones;
                
                // Format date for input field (YYYY-MM-DD)
                if (record.proxima_cita) {
                    const date = new Date(record.proxima_cita);
                    const formattedDate = date.toISOString().split('T')[0];
                    document.getElementById('edit_proxima_cita').value = formattedDate;
                } else {
                    document.getElementById('edit_proxima_cita').value = '';
                }
                
                // Set time for next appointment
                if (record.hora_proxima_cita) {
                    document.getElementById('edit_hora_proxima_cita').value = record.hora_proxima_cita;
                } else {
                    document.getElementById('edit_hora_proxima_cita').value = '';
                }
                
                document.getElementById('edit_medico_responsable').value = record.medico_responsable;
                document.getElementById('edit_especialidad_medico').value = record.especialidad_medico;
                
                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('editMedicalRecordModal'));
                modal.show();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los datos del historial médico');
        });
}

function deleteMedicalRecord(id) {
    if (confirm('¿Está seguro de que desea eliminar este registro médico? Esta acción no se puede deshacer.')) {
        fetch('delete_medical_record.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert('Registro eliminado correctamente');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al eliminar el registro médico');
        });
    }
}
</script>