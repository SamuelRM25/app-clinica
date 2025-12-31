<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');


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


<!-- Inject Dashboard Custom Styles -->
<link rel="stylesheet" href="../../assets/css/dashboard-reengineered.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<style>
    .medical-timeline {
        position: relative;
        padding-left: 3rem;
    }
    .medical-timeline::before {
        content: '';
        position: absolute;
        left: 0.75rem;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #e2e8f0;
    }
    .timeline-item {
        position: relative;
        margin-bottom: 2rem;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -2.25rem;
        top: 0.5rem;
        width: 1rem;
        height: 1rem;
        border-radius: 50%;
        background: white;
        border: 3px solid #2563eb;
        z-index: 1;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
    }
    .record-card {
        border-radius: 1rem;
        overflow: hidden;
        border: 1px solid #e2e8f0 !important;
        background: white !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03) !important;
    }
    
    .record-header {
        background: #f8fafc !important;
        border-bottom: 1px solid #e2e8f0 !important;
        padding: 1.25rem 1.5rem;
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .record-header:hover {
        background: #f1f5f9 !important;
    }

    .section-title-clinical {
        font-size: 0.75rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #64748b;
        margin-bottom: 0.75rem;
    }

    .clinical-value {
        color: #1e293b;
        font-weight: 500;
        line-height: 1.6;
    }

    .treatment-box {
        background: #eff6ff;
        border-left: 4px solid #2563eb;
        padding: 1rem;
        border-radius: 0.5rem;
        color: #1e3a8a;
        font-weight: 500;
    }

    .diagnosis-box {
        background: #fff7ed;
        border-left: 4px solid #f97316;
        padding: 1rem;
        border-radius: 0.5rem;
        color: #7c2d12;
        font-weight: 700;
    }

    .receta-preview-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 1rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        margin-top: 2rem;
    }

    .receta-header {
        background: #f8fafc;
        padding: 1rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #e2e8f0;
        color: #1e293b;
        font-weight: 700;
        font-size: 0.85rem;
    }

    .receta-logo-text {
        color: #16a34a;
        font-weight: 800;
        letter-spacing: -0.5px;
    }
    
    .transition-transform { transition: transform 0.3s ease; }
    .rotate-180 { transform: rotate(180deg); }
    .collapse-icon { font-size: 1.2rem; color: #94a3b8; }

    /* Custom Tabs Styling */
    .custom-tabs .nav-link, .custom-tabs-success .nav-link {
        border: none;
        color: #64748b;
        font-weight: 600;
        font-size: 0.85rem;
        border-radius: 0.5rem 0.5rem 0 0;
        margin-right: 2px;
        transition: all 0.2s ease;
    }
    .custom-tabs .nav-link.active {
        background: white !important;
        color: #2563eb !important;
        border-bottom: 3px solid #2563eb !important;
    }
    .custom-tabs-success .nav-link.active {
        background: white !important;
        color: #16a34a !important;
        border-bottom: 3px solid #16a34a !important;
    }
    .nav-tabs .nav-item { margin-bottom: -1px; }
    
    .text-xxs { font-size: 0.65rem; }
</style>

<div class="dashboard-wrapper sidebar-collapsed">
    <!-- Mobile Overlay -->
    <div class="dashboard-mobile-overlay"></div>

    <!-- Desktop Sidebar Toggle -->
    <button class="desktop-nav-toggle" id="desktopSidebarToggle" title="Mostrar Menú">
        <i class="bi bi-list"></i>
    </button>

    <!-- Sidebar Reengineered -->
    <div class="sidebar-glass p-3 d-flex flex-column">
        <div class="brand-section">
            <div class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-decoration-none">
                <img src="../../assets/img/siloe.png" alt="Logo" style="height: 40px; margin-right: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">
            </div>
        </div>
        
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item"><a href="../dashboard/index.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="index.php" class="nav-link active"><i class="bi bi-people"></i> Pacientes</a></li>
            <li><a href="../appointments/index.php" class="nav-link"><i class="bi bi-calendar"></i> Citas</a></li>
        </ul>
        
        <div class="mt-auto">
            <div class="dropdown p-2">
                <div class="d-flex align-items-center text-dark">
                    <div class="avatar-circle me-2 bg-primary text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px;">
                        <?php echo strtoupper(substr($_SESSION['nombre'], 0, 1)); ?>
                    </div>
                    <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Reengineered -->
    <div class="main-content-glass">
        <div class="container-fluid">
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-4 animate__animated animate__fadeInDown">
                <div class="d-flex align-items-center">
                    <a href="index.php" class="btn btn-icon btn-glass me-3 text-secondary">
                        <i class="bi bi-arrow-left fs-4"></i>
                    </a>
                    <div>
                        <h2 class="fw-bold text-dark mb-0">Historial Clínico</h2>
                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 mt-1">
                            <?php echo htmlspecialchars($patient['nombre'] . ' ' . $patient['apellido']); ?>
                        </span>
                    </div>
                </div>
                <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#newMedicalRecordModal">
                    <i class="bi bi-plus-circle-fill me-2"></i>Nueva Consulta
                </button>
            </div>

            <div class="row">
                <!-- Patient Quick Info Sidebar -->
                <div class="col-lg-3 mb-4 animate__animated animate__fadeInLeft animate__delay-1s">
                    <div class="card-glass h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-4 text-primary">Información General</h5>
                            
                            <div class="mb-3">
                                <label class="text-xs text-muted d-block">Edad</label>
                                <div class="fw-bold">
                                    <?php 
                                        $nac = new DateTime($patient['fecha_nacimiento']);
                                        $hoy = new DateTime();
                                        echo $hoy->diff($nac)->y . " años";
                                    ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="text-xs text-muted d-block">Fecha Nacimiento</label>
                                <div class="fw-bold"><?php echo htmlspecialchars($patient['fecha_nacimiento']); ?></div>
                            </div>

                            <div class="mb-3">
                                <label class="text-xs text-muted d-block">Género</label>
                                <span class="badge bg-light text-dark fw-normal"><?php echo htmlspecialchars($patient['genero']); ?></span>
                            </div>

                            <div class="mb-3">
                                <label class="text-xs text-muted d-block">Teléfono</label>
                                <div class="fw-bold text-primary"><?php echo htmlspecialchars($patient['telefono'] ?? 'N/A'); ?></div>
                            </div>

                            <div class="mb-0">
                                <label class="text-xs text-muted d-block">Dirección</label>
                                <div class="text-sm"><?php echo htmlspecialchars($patient['direccion'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Timeline of Consultations -->
                <div class="col-lg-9 animate__animated animate__fadeInUp animate__delay-1s">
                    <?php if (count($medical_records) > 0): ?>
                        <div class="medical-timeline">
                            <?php foreach ($medical_records as $index => $record): ?>
                                <div class="timeline-item">
                                    <div class="card-header bg-white border-0 p-0 d-flex align-items-center">
                                        <!-- Main Clickable Area (Trigger) -->
                                        <div class="flex-grow-1 py-3 px-4 cursor-pointer collapse-trigger" 
                                             role="button"
                                             data-bs-toggle="collapse" 
                                             data-bs-target="#collapseRecord<?php echo $record['id_historial']; ?>" 
                                             aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                                            
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center">
                                                    <div class="me-3 p-2 rounded-3 bg-white border shadow-sm item-icon">
                                                        <span class="fw-bold text-primary d-block text-xs text-uppercase opacity-75">Consulta</span>
                                                        <span class="fw-bold fs-6 text-dark"><?php echo date('d/m/Y', strtotime($record['fecha_consulta'])); ?></span>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs text-muted d-block text-uppercase letter-spacing-1">Médico Responsable</span>
                                                        <span class="fw-bold text-slate-800">Dr(a). <?php echo htmlspecialchars($record['medico_responsable']); ?></span>
                                                    </div>
                                                </div>
                                                
                                                <i class="bi bi-chevron-down transition-transform collapse-icon <?php echo $index === 0 ? 'rotate-180' : ''; ?> text-muted"></i>
                                            </div>
                                        </div>

                                        <!-- Actions Area (Isolated from Trigger) -->
                                        <div class="pe-4 border-start ps-3 py-2">
                                            <div class="dropdown">
                                                <button class="btn btn-outline-secondary btn-sm rounded-circle d-flex align-items-center justify-content-center shadow-sm hover-scale transition-all" 
                                                        type="button" 
                                                        style="width: 32px; height: 32px;"
                                                        data-bs-toggle="dropdown" 
                                                        aria-expanded="false"
                                                        title="Opciones">
                                                    <i class="bi bi-three-dots-vertical"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-3 mt-2">
                                                    <li>
                                                        <a class="dropdown-item py-2 d-flex align-items-center" href="javascript:void(0)" onclick="editMedicalRecord(<?php echo $record['id_historial']; ?>)">
                                                            <div class="icon-circle bg-primary bg-opacity-10 text-primary me-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                                                <i class="bi bi-pencil-fill text-xs"></i>
                                                            </div>
                                                            <span class="fw-medium">Editar Consulta</span>
                                                        </a>
                                                    </li>
                                                    <?php if (!empty($record['receta_medica'])): ?>
                                                    <li>
                                                        <a class="dropdown-item py-2 d-flex align-items-center" href="javascript:void(0)" onclick="printPrescription(<?php echo $record['id_historial']; ?>)">
                                                            <div class="icon-circle bg-success bg-opacity-10 text-success me-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                                                <i class="bi bi-printer-fill text-xs"></i>
                                                            </div>
                                                            <span class="fw-medium">Imprimir Receta</span>
                                                        </a>
                                                    </li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider my-2"></li>
                                                    <li>
                                                        <a class="dropdown-item py-2 d-flex align-items-center text-danger" href="javascript:void(0)" onclick="deleteMedicalRecord(<?php echo $record['id_historial']; ?>)">
                                                            <div class="icon-circle bg-danger bg-opacity-10 text-danger me-2 rounded-circle d-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                                                <i class="bi bi-trash-fill text-xs"></i>
                                                            </div>
                                                            <span class="fw-medium">Eliminar Registro</span>
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>    <div id="collapseRecord<?php echo $record['id_historial']; ?>" class="collapse <?php echo $index === 0 ? 'show' : ''; ?>">
                                            <div class="card-body p-4">
                                            <div class="row g-4">
                                                <div class="col-md-7">
                                                    <div class="clinical-box mb-4">
                                                        <div class="section-title-clinical">Motivo de Consulta</div>
                                                        <p class="clinical-value mb-4 fs-5 fw-bold"><?php echo nl2br(htmlspecialchars($record['motivo_consulta'])); ?></p>
                                                        
                                                        <div class="section-title-clinical">Síntomas / Historia</div>
                                                        <p class="clinical-value text-muted"><?php echo nl2br(htmlspecialchars($record['sintomas'])); ?></p>
                                                    </div>
 
                                                    <?php if (!empty($record['examen_fisico'])): ?>
                                                    <div class="clinical-box mb-4">
                                                        <div class="section-title-clinical">Examen Físico</div>
                                                        <div class="p-3 rounded-3 bg-light border-start border-4 border-info clinical-value text-sm">
                                                            <?php echo nl2br(htmlspecialchars($record['examen_fisico'])); ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="col-md-5">
                                                    <div class="clinical-box mb-4">
                                                        <div class="section-title-clinical text-orange-600">Diagnóstico</div>
                                                        <div class="diagnosis-box">
                                                            <?php echo nl2br(htmlspecialchars($record['diagnostico'])); ?>
                                                        </div>
                                                    </div>
 
                                                    <div class="clinical-box mb-4">
                                                        <div class="section-title-clinical text-blue-600">Tratamiento</div>
                                                        <div class="treatment-box shadow-sm">
                                                            <i class="bi bi-check2-circle me-2"></i><?php echo nl2br(htmlspecialchars($record['tratamiento'])); ?>
                                                        </div>
                                                    </div>

                                                    <?php if (!empty($record['proxima_cita'])): ?>
                                                    <div class="mt-4 p-3 rounded-4 bg-warning bg-opacity-10 border border-warning border-opacity-20 text-warning-emphasis">
                                                        <div class="d-flex align-items-center">
                                                            <i class="bi bi-alarm-fill fs-3 me-3"></i>
                                                            <div>
                                                                <small class="opacity-75 d-block text-uppercase fw-bold text-xs" style="letter-spacing: 0.5px;">Próxima Cita</small>
                                                                <span class="fw-bold fs-5"><?php echo date('d/m/Y', strtotime($record['proxima_cita'])); ?></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($record['receta_medica'])): ?>
                                            <div class="receta-preview-card">
                                                <div class="receta-header">
                                                    <div class="d-flex align-items-center">
                                                        <span class="receta-logo-text me-3 fs-5">Rx</span>
                                                        <span class="opacity-75 uppercase-label mt-1" style="font-size: 0.7rem; letter-spacing: 1px;">Prescripción Médica</span>
                                                    </div>
                                                    <button class="btn btn-sm btn-success rounded-pill px-4 shadow-sm fw-bold" onclick="printPrescription(<?php echo $record['id_historial']; ?>); event.stopPropagation();">
                                                        <i class="bi bi-printer-fill me-2"></i>IMPRIMIR
                                                    </button>
                                                </div>
                                                <div class="card-body p-4">
                                                    <div class="text-dark p-3 rounded-3" style="font-family: 'Courier New', Courier, monospace; font-size: 0.95rem; line-height: 1.6; white-space: pre-wrap; border: 1px dashed #cbd5e1; background: #fafafa;">
<?php 
$clean_receta = implode("\n", array_map('trim', explode("\n", $record['receta_medica'])));
echo nl2br(htmlspecialchars($clean_receta)); 
?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="card-glass p-5 text-center">
                            <div class="opacity-25 mb-3"><i class="bi bi-clipboard-x fs-1"></i></div>
                            <h5 class="text-muted">No hay registros de historial clínico</h5>
                            <button class="btn btn-primary rounded-pill mt-3 px-4" data-bs-toggle="modal" data-bs-target="#newMedicalRecordModal">
                                Crear Primer Registro
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- New Medical Record Modal -->
<div class="modal fade" id="newMedicalRecordModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="background: rgba(255, 255, 255, 1);">
            <div class="modal-header border-0 bg-primary bg-opacity-10 py-2 px-3">
                <div class="d-flex align-items-center">
                    <i class="bi bi-journal-plus me-2 fs-5 text-primary"></i>
                    <h5 class="modal-title fw-bold text-primary-emphasis mb-0">Nueva Consulta Médica</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="newMedicalRecordForm" action="save_medical_record.php" method="POST">
                <input type="hidden" name="id_paciente" value="<?php echo $patient_id; ?>">
                
                <!-- Tab Navigation -->
                <div class="px-3 pt-2 bg-light bg-opacity-50">
                    <ul class="nav nav-tabs nav-fill border-0 custom-tabs" id="newConsultationTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-2" id="consulta-tab" data-bs-toggle="tab" data-bs-target="#tab-consulta" type="button" role="tab">
                                <i class="bi bi-chat-left-text me-1"></i> Consulta
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2" id="exploracion-tab" data-bs-toggle="tab" data-bs-target="#tab-exploracion" type="button" role="tab">
                                <i class="bi bi-person-heart me-1"></i> Exploración
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2" id="antecedentes-tab" data-bs-toggle="tab" data-bs-target="#tab-antecedentes" type="button" role="tab">
                                <i class="bi bi-clock-history me-1"></i> Antecedentes
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2" id="plan-tab" data-bs-toggle="tab" data-bs-target="#tab-plan" type="button" role="tab">
                                <i class="bi bi-file-earmark-medical me-1"></i> Plan
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="modal-body p-3" style="min-height: 350px;">
                    <div class="tab-content" id="newConsultationTabsContent">
                        
                        <!-- Tab 1: Consulta -->
                        <div class="tab-pane fade show active" id="tab-consulta" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label text-xs fw-semibold text-muted mb-1">Motivo de Consulta</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="motivo_consulta" rows="2" placeholder="Ej: Dolor abdominal persistente..." required></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-xs fw-semibold text-muted mb-1">Historia de la Enfermedad / Síntomas</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="sintomas" rows="3" placeholder="Descripción detallada de los síntomas..." required></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-xs fw-semibold text-muted text-success mb-1">Diagnóstico Inicial</label>
                                    <textarea class="form-control form-control-sm border-0 shadow-sm bg-light border-start border-4 border-success" name="diagnostico" rows="2" placeholder="Diagnóstico presuntivo o definitivo..." required></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 2: Exploración -->
                        <div class="tab-pane fade" id="tab-exploracion" role="tabpanel">
                            <div class="bg-white p-3 rounded-4 border shadow-sm mb-3">
                                <h6 class="text-xs fw-bold text-primary text-uppercase mb-3">Signos Vitales</h6>
                                <div class="row g-3">
                                    <div class="col-md-3 col-6 text-center">
                                        <label class="text-xxs text-muted d-block mb-1">PA (mmHg)</label>
                                        <input type="text" class="form-control form-control-sm text-center fw-bold" name="examen_fisico_pa" placeholder="120/80">
                                    </div>
                                    <div class="col-md-3 col-6 text-center">
                                        <label class="text-xxs text-muted d-block mb-1">FC (lpm)</label>
                                        <input type="text" class="form-control form-control-sm text-center fw-bold" name="examen_fisico_fc" placeholder="80">
                                    </div>
                                    <div class="col-md-3 col-6 text-center">
                                        <label class="text-xxs text-muted d-block mb-1">FR (rpm)</label>
                                        <input type="text" class="form-control form-control-sm text-center fw-bold" name="examen_fisico_fr" placeholder="16">
                                    </div>
                                    <div class="col-md-3 col-6 text-center">
                                        <label class="text-xxs text-muted d-block mb-1">T° (°C)</label>
                                        <input type="text" class="form-control form-control-sm text-center fw-bold" name="examen_fisico_temp" placeholder="36.5">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="text-xs fw-semibold text-muted d-block mb-1">Hallazgos Fisícos / Observaciones</label>
                                <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="examen_fisico_otros" rows="4" placeholder="Pulmones, corazón, abdomen, etc..."></textarea>
                            </div>
                            <input type="hidden" name="examen_fisico" id="examen_fis_completo">
                        </div>

                        <!-- Tab 3: Plan -->
                        <div class="tab-pane fade" id="tab-plan" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-xs fw-semibold text-muted mb-1">Plan de Tratamiento / Indicaciones</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="tratamiento" rows="5" placeholder="Reposo, cuidados, exámenes adicionales..." required></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-xs fw-semibold text-muted mb-1">Receta Médica</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm font-monospace" style="font-size: 0.8rem;" name="receta_medica" rows="5" placeholder="1. Amoxicilina 500mg..."></textarea>
                                </div>
                                <div class="col-12 mt-2">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-lg-3">
                                            <label class="form-label text-xs text-muted mb-1">Próxima Seguimiento</label>
                                            <input type="date" class="form-control form-control-sm" name="proxima_cita">
                                        </div>
                                        <div class="col-lg-2">
                                            <label class="form-label text-xs text-muted mb-1">Hora</label>
                                            <input type="time" class="form-control form-control-sm" name="hora_proxima_cita">
                                        </div>
                                        <div class="col-lg-7">
                                            <div class="p-2 rounded-3 bg-primary bg-opacity-10 border border-primary border-opacity-20 mt-1">
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-person-badge-fill text-primary me-2"></i>
                                                    <div class="flex-grow-1">
                                                        <input type="text" class="form-control form-control-sm border-0 bg-transparent fw-bold p-0" style="font-size: 0.8rem;" name="medico_responsable" value="<?php echo htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']); ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 4: Antecedentes -->
                        <div class="tab-pane fade" id="tab-antecedentes" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-xs text-muted mb-1">Antecedentes Personales</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="antecedentes_personales" rows="2" placeholder="Alergias, cirugías, etc..."></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-xs text-muted mb-1">Antecedentes Familiares</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="antecedentes_familiares" rows="2" placeholder="Diabetes, HTA en familia..."></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-xs text-muted mb-1">Exámenes Realizados</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="examenes_realizados" rows="2"></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-xs text-muted mb-1">Resultados</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="resultados_examenes" rows="2"></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-xs text-muted mb-1">Observaciones</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="observaciones" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 p-2 px-4 shadow-sm bg-light">
                    <button type="button" class="btn btn-light btn-sm rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm">Guardar Consulta</button>
                </div>
            </form>
        </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Medical Record Modal -->
<div class="modal fade" id="editMedicalRecordModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg" style="background: rgba(255, 255, 255, 1);">
            <div class="modal-header border-0 bg-success bg-opacity-10 py-2 px-3">
                <div class="d-flex align-items-center">
                    <i class="bi bi-pencil-square me-2 fs-5 text-success"></i>
                    <h5 class="modal-title fw-bold text-success-emphasis mb-0">Editar Consulta Médica</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editMedicalRecordForm" action="update_medical_record.php" method="POST">
                <input type="hidden" name="id_historial" id="edit_id_historial">
                <input type="hidden" name="id_paciente" value="<?php echo $patient_id; ?>">
                
                <!-- Tab Navigation -->
                <div class="px-3 pt-2 bg-light bg-opacity-50">
                    <ul class="nav nav-tabs nav-fill border-0 custom-tabs-success" id="editConsultationTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active py-2" id="edit-consulta-tab" data-bs-toggle="tab" data-bs-target="#edit-tab-consulta" type="button" role="tab">
                                <i class="bi bi-chat-left-text me-1"></i> Consulta
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2" id="edit-exploracion-tab" data-bs-toggle="tab" data-bs-target="#edit-tab-exploracion" type="button" role="tab">
                                <i class="bi bi-person-heart me-1"></i> Exploración
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2" id="edit-antecedentes-tab" data-bs-toggle="tab" data-bs-target="#edit-tab-antecedentes" type="button" role="tab">
                                <i class="bi bi-clock-history me-1"></i> Antecedentes
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link py-2" id="edit-plan-tab" data-bs-toggle="tab" data-bs-target="#edit-tab-plan" type="button" role="tab">
                                <i class="bi bi-file-earmark-medical me-1"></i> Plan
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="modal-body p-3" style="min-height: 350px;">
                    <div class="tab-content" id="editConsultationTabsContent">
                        
                        <!-- Tab 1: Consulta -->
                        <div class="tab-pane fade show active" id="edit-tab-consulta" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label text-xs fw-semibold text-muted mb-1">Motivo de Consulta</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="motivo_consulta" id="edit_motivo_consulta" rows="2" required></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-xs fw-semibold text-muted mb-1">Síntomas</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="sintomas" id="edit_sintomas" rows="3" required></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-xs fw-semibold text-muted text-success mb-1">Diagnóstico</label>
                                    <textarea class="form-control form-control-sm border-0 shadow-sm bg-light border-start border-4 border-success" name="diagnostico" id="edit_diagnostico" rows="2" required></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 2: Exploración -->
                        <div class="tab-pane fade" id="edit-tab-exploracion" role="tabpanel">
                            <div class="col-12">
                                <label class="form-label text-xs fw-semibold text-muted mb-1">Examen Físico / Hallazgos</label>
                                <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="examen_fisico" id="edit_examen_fisico" rows="10"></textarea>
                            </div>
                        </div>

                        <!-- Tab 3: Plan -->
                        <div class="tab-pane fade" id="edit-tab-plan" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-xs fw-semibold text-muted mb-1">Tratamiento</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="treatment" id="edit_tratamiento" rows="5" required></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-xs fw-semibold text-muted mb-1">Receta Médica</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm font-monospace" style="font-size: 0.8rem;" name="receta_medica" id="edit_receta_medica" rows="5"></textarea>
                                </div>
                                <div class="col-12 mt-2">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-lg-3">
                                            <label class="form-label text-xs text-muted mb-1">Próxima Cita</label>
                                            <input type="date" class="form-control form-control-sm" name="proxima_cita" id="edit_proxima_cita">
                                        </div>
                                        <div class="col-lg-2">
                                            <label class="form-label text-xs text-muted mb-1">Hora</label>
                                            <input type="time" class="form-control form-control-sm" name="hora_proxima_cita" id="edit_hora_proxima_cita">
                                        </div>
                                        <div class="col-lg-7">
                                            <div class="p-2 rounded-3 bg-success bg-opacity-10 border border-success border-opacity-20 mt-1">
                                                <div class="d-flex align-items-center">
                                                    <i class="bi bi-person-badge-fill text-success me-2"></i>
                                                    <div class="flex-grow-1">
                                                        <input type="text" class="form-control form-control-sm border-0 bg-transparent fw-bold p-0" style="font-size: 0.8rem;" name="medico_responsable" id="edit_medico_responsable" required>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tab 4: Antecedentes -->
                        <div class="tab-pane fade" id="edit-tab-antecedentes" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label text-xs text-muted mb-1">Ant. Personales</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="antecedentes_personales" id="edit_antecedentes_personales" rows="3"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-xs text-muted mb-1">Ant. Familiares</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="antecedentes_familiares" id="edit_antecedentes_familiares" rows="3"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-xs text-muted mb-1">Exámenes Realizados</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="examenes_realizados" id="edit_examenes_realizados" rows="2"></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-xs text-muted mb-1">Resultados</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="resultados_examenes" id="edit_resultados_examenes" rows="2"></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label text-xs text-muted mb-1">Observaciones</label>
                                    <textarea class="form-control form-control-sm border-0 bg-light shadow-sm" name="observaciones" id="edit_observaciones" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 p-2 px-4 shadow-sm bg-light">
                    <button type="button" class="btn btn-light btn-sm rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm rounded-pill px-4 shadow-sm text-white">Actualizar Registro</button>
                </div>
            </form>
        </div>
            </form>
        </div>
    </div>
</div>

<button class="mobile-nav-toggle" id="sidebarToggle">
    <i class="bi bi-list fs-4"></i>
</button>

<?php include_once '../../includes/footer.php'; ?>

<script src="../../assets/js/dashboard-reengineered.js"></script>

<script>
// Función para combinar los campos del examen físico antes de enviar el formulario
document.getElementById('newMedicalRecordForm').addEventListener('submit', function(e) {
    const pa = document.querySelector('[name="examen_fisico_pa"]').value;
    const fc = document.querySelector('[name="examen_fisico_fc"]').value;
    const fr = document.querySelector('[name="examen_fisico_fr"]').value;
    const temp = document.querySelector('[name="examen_fisico_temp"]').value;
    
    let signosVitales = '';
    if (pa) signosVitales += 'PA: ' + pa + ' mmHg, ';
    if (fc) signosVitales += 'FC: ' + fc + ' lpm, ';
    if (fr) signosVitales += 'FR: ' + fr + ' rpm, ';
    if (temp) signosVitales += 'T°: ' + temp + ' °C';
    
    signosVitales = signosVitales.replace(/,\s*$/, '');
    
    const otros = document.querySelector('[name="examen_fisico_otros"]').value;
    
    let examenCompleto = '';
    if (signosVitales.trim()) examenCompleto += 'SIGNOS VITALES:\n' + signosVitales + '\n\n';
    if (otros.trim()) examenCompleto += 'HALLAZGOS FÍSICOS:\n' + otros;
    
    document.getElementById('examen_fis_completo').value = examenCompleto.trim();
});

function editMedicalRecord(id) {
    fetch('get_medical_record.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const record = data.record;
                
                document.getElementById('edit_id_historial').value = record.id_historial;
                document.getElementById('edit_motivo_consulta').value = record.motivo_consulta;
                document.getElementById('edit_sintomas').value = record.sintomas;
                document.getElementById('edit_examen_fisico').value = record.examen_fisico || '';
                document.getElementById('edit_diagnostico').value = record.diagnostico;
                document.getElementById('edit_tratamiento').value = record.tratamiento;
                document.getElementById('edit_receta_medica').value = record.receta_medica;
                
                // Fields for additional history
                document.getElementById('edit_antecedentes_personales').value = record.antecedentes_personales || '';
                document.getElementById('edit_antecedentes_familiares').value = record.antecedentes_familiares || '';
                document.getElementById('edit_examenes_realizados').value = record.examenes_realizados || '';
                document.getElementById('edit_resultados_examenes').value = record.resultados_examenes || '';
                document.getElementById('edit_observaciones').value = record.observaciones || '';
                
                if (record.proxima_cita) {
                    document.getElementById('edit_proxima_cita').value = record.proxima_cita;
                }
                
                if (record.hora_proxima_cita) {
                    document.getElementById('edit_hora_proxima_cita').value = record.hora_proxima_cita;
                }
                
                document.getElementById('edit_medico_responsable').value = record.medico_responsable;
                // document.getElementById('edit_especialidad_medico').value = record.especialidad_medico || '';
                
                const modal = new bootstrap.Modal(document.getElementById('editMedicalRecordModal'));
                modal.show();
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'No se pudo cargar la información del registro', 'error');
        });
}

function deleteMedicalRecord(id) {
    Swal.fire({
        title: '¿Estás seguro?',
        text: "Esta acción eliminará permanentemente este registro médico.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74a3b',
        cancelButtonColor: '#858796',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('delete_medical_record.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('Eliminado', 'El registro ha sido eliminado exitosamente.', 'success')
                    .then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            });
        }
    });
}

function printPrescription(id) {
    window.open('print_prescription.php?id=' + id, '_blank');
}

// Handle collapse icon rotation
document.addEventListener('DOMContentLoaded', function() {
    const collapsibleElements = document.querySelectorAll('.collapse');
    collapsibleElements.forEach(el => {
        el.addEventListener('show.bs.collapse', function () {
            const header = document.querySelector(`[data-bs-target="#${el.id}"]`);
            if (header) {
                const icon = header.querySelector('.collapse-icon');
                if (icon) icon.classList.add('rotate-180');
            }
        });
        el.addEventListener('hide.bs.collapse', function () {
            const header = document.querySelector(`[data-bs-target="#${el.id}"]`);
            if (header) {
                const icon = header.querySelector('.collapse-icon');
                if (icon) icon.classList.remove('rotate-180');
            }
        });
    });
});
</script>