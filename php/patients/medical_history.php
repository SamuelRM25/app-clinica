<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');

verify_session();

try {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $_SESSION['patient_message'] = "ID de paciente inválido";
        $_SESSION['patient_status'] = "danger";
        header("Location: index.php");
        exit;
    }

    $patient_id = $_GET['id'];
    
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener información del paciente con estadísticas
    $stmt = $conn->prepare("SELECT p.*, 
                           COUNT(h.id_historial) as total_consultas,
                           MAX(h.fecha_consulta) as ultima_consulta
                           FROM pacientes p
                           LEFT JOIN historial_clinico h ON p.id_paciente = h.id_paciente
                           WHERE p.id_paciente = ?
                           GROUP BY p.id_paciente");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        $_SESSION['patient_message'] = "Paciente no encontrado";
        $_SESSION['patient_status'] = "danger";
        header("Location: index.php");
        exit;
    }
    
    // Obtener historial médico con información del doctor
    $stmt = $conn->prepare("SELECT h.*, 
                           u.nombre as doctor_nombre, 
                           u.apellido as doctor_apellido
                           FROM historial_clinico h
                           LEFT JOIN usuarios u ON h.medico_responsable = CONCAT(u.nombre, ' ', u.apellido)
                           WHERE h.id_paciente = ? 
                           ORDER BY h.fecha_consulta DESC, h.id_historial DESC");
    $stmt->execute([$patient_id]);
    $medical_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular edad del paciente
    $edad = isset($patient['fecha_nacimiento']) ? 
        (new DateTime())->diff(new DateTime($patient['fecha_nacimiento']))->y : 0;
    
    $page_title = "Historial Clínico - " . $patient['nombre'] . " " . $patient['apellido'] . " - Centro Médico Herrera Sáenz";
    include_once '../../includes/header.php';
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!-- Estilos Minimalistas Modernos -->
<style>
    :root {
        /* Modo Claro - Colores Pastel */
        --primary-light: #a3c4f3;        /* Azul pastel */
        --secondary-light: #b5e6d9;      /* Verde pastel */
        --warning-light: #ffd6a5;        /* Naranja pastel */
        --danger-light: #ffadad;         /* Rojo pastel */
        --accent-light: #c7ceea;         /* Lavanda pastel */
        --background-light: #ffffff;     /* Fondo blanco */
        --surface-light: #f9f9f9;        /* Superficie clara */
        --text-light: #333333;           /* Texto oscuro */
        --text-muted-light: #666666;     /* Texto secundario */
        --border-light: #e0e0e0;         /* Bordes sutiles */
        
        /* Modo Oscuro */
        --primary-dark: #3a506b;         /* Azul oscuro */
        --secondary-dark: #1c2541;       /* Azul más oscuro */
        --warning-dark: #ff9f1c;         /* Naranja */
        --danger-dark: #e71d36;          /* Rojo */
        --accent-dark: #8a89c0;          /* Lavanda oscuro */
        --background-dark: #121212;      /* Fondo oscuro */
        --surface-dark: #1e1e1e;         /* Superficie oscura */
        --text-dark: #f5f5f5;            /* Texto claro */
        --text-muted-dark: #b0b0b0;      /* Texto secundario oscuro */
        --border-dark: #333333;          /* Bordes oscuros */
        
        /* Variables activas (inician en modo claro) */
        --primary: var(--primary-light);
        --secondary: var(--secondary-light);
        --warning: var(--warning-light);
        --danger: var(--danger-light);
        --accent: var(--accent-light);
        --background: var(--background-light);
        --surface: var(--surface-light);
        --text: var(--text-light);
        --text-muted: var(--text-muted-light);
        --border: var(--border-light);
        
        /* Efecto mármol (transparencia sutil) */
        --marble-effect: linear-gradient(45deg, transparent 98%, rgba(255,255,255,0.1) 100%);
        
        /* Transiciones */
        --transition-fast: 0.2s ease;
        --transition-normal: 0.3s ease;
        --transition-slow: 0.5s ease;
    }
    
    /* Aplicar modo oscuro si está activo */
    body.dark-mode {
        --primary: var(--primary-dark);
        --secondary: var(--secondary-dark);
        --warning: var(--warning-dark);
        --danger: var(--danger-dark);
        --accent: var(--accent-dark);
        --background: var(--background-dark);
        --surface: var(--surface-dark);
        --text: var(--text-dark);
        --text-muted: var(--text-muted-dark);
        --border: var(--border-dark);
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        transition: background-color var(--transition-normal), 
                    color var(--transition-normal),
                    border-color var(--transition-normal);
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background: var(--background);
        color: var(--text);
        min-height: 100vh;
        position: relative;
        overflow-x: hidden;
    }
    
    /* Efecto de textura de mármol sutil en el fondo */
    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: var(--marble-effect);
        opacity: 0.2;
        pointer-events: none;
        z-index: -1;
    }
    
    /* Layout principal */
    .dashboard-container {
        display: flex;
        min-height: 100vh;
    }
    
    /* Sidebar minimalista */
    .minimal-sidebar {
        width: 250px;
        background: var(--surface);
        border-right: 1px solid var(--border);
        padding: 24px 0;
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        z-index: 100;
        display: flex;
        flex-direction: column;
        transition: transform var(--transition-normal);
    }
    
    .sidebar-header {
        padding: 0 24px 24px;
        border-bottom: 1px solid var(--border);
        margin-bottom: 24px;
    }
    
    .clinic-brand {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .clinic-logo {
        width: 40px;
        height: 40px;
        background: var(--primary);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
    }
    
    .clinic-name {
        font-weight: 600;
        font-size: 16px;
        color: var(--text);
    }
    
    .clinic-subtitle {
        font-size: 12px;
        color: var(--text-muted);
    }
    
    /* Navegación */
    .nav-minimal {
        list-style: none;
        padding: 0;
        flex-grow: 1;
    }
    
    .nav-item-minimal {
        margin: 4px 16px;
    }
    
    .nav-link-minimal {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        color: var(--text-muted);
        text-decoration: none;
        border-radius: 10px;
        transition: all var(--transition-fast);
        font-weight: 500;
        font-size: 14px;
    }
    
    .nav-link-minimal:hover,
    .nav-link-minimal.active {
        background: var(--primary);
        color: white;
    }
    
    .nav-link-minimal i {
        margin-right: 12px;
        font-size: 16px;
    }
    
    /* Perfil de usuario */
    .user-profile {
        padding: 16px 24px 0;
        border-top: 1px solid var(--border);
        margin-top: auto;
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        background: var(--accent);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: var(--text);
        font-size: 16px;
        margin-right: 12px;
    }
    
    /* Contenido principal */
    .main-content {
        margin-left: 250px;
        padding: 24px;
        width: calc(100% - 250px);
        animation: fadeIn 0.4s ease-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    /* Encabezado */
    .content-header {
        margin-bottom: 32px;
        padding-bottom: 24px;
        border-bottom: 1px solid var(--border);
    }
    
    .header-back {
        color: var(--text-muted);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 16px;
        font-size: 14px;
    }
    
    .header-back:hover {
        color: var(--primary);
    }
    
    .header-title {
        font-size: 28px;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 8px;
    }
    
    .header-subtitle {
        color: var(--text-muted);
        font-size: 16px;
    }
    
    /* Botón de nueva consulta */
    .btn-new-record {
        background: var(--primary);
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 500;
        font-size: 14px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all var(--transition-fast);
    }
    
    .btn-new-record:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(163, 196, 243, 0.3);
    }
    
    /* Panel de información del paciente */
    .patient-info-panel {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 24px;
        margin-bottom: 24px;
    }
    
    .patient-avatar-large {
        width: 80px;
        height: 80px;
        background: var(--primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: 600;
        color: white;
        margin-bottom: 16px;
    }
    
    .patient-name {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 4px;
    }
    
    .patient-id {
        color: var(--text-muted);
        font-size: 14px;
        margin-bottom: 20px;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
    }
    
    .info-label {
        font-size: 12px;
        color: var(--text-muted);
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .info-value {
        font-size: 14px;
        font-weight: 500;
        color: var(--text);
    }
    
    /* Timeline de historial médico */
    .medical-timeline {
        position: relative;
        padding-left: 2rem;
    }
    
    .medical-timeline::before {
        content: '';
        position: absolute;
        left: 0.75rem;
        top: 0;
        bottom: 0;
        width: 2px;
        background: linear-gradient(180deg, var(--primary), var(--accent));
        border-radius: 2px;
    }
    
    .timeline-item {
        position: relative;
        margin-bottom: 2rem;
    }
    
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -2.25rem;
        top: 1rem;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: var(--primary);
        border: 3px solid var(--surface);
        box-shadow: 0 0 0 4px rgba(163, 196, 243, 0.2);
        z-index: 1;
    }
    
    /* Tarjeta de consulta */
    .consultation-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        overflow: hidden;
        transition: all var(--transition-fast);
    }
    
    .consultation-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    }
    
    .consultation-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: background-color var(--transition-fast);
    }
    
    .consultation-header:hover {
        background: rgba(163, 196, 243, 0.05);
    }
    
    .consultation-date {
        background: rgba(163, 196, 243, 0.1);
        padding: 8px 16px;
        border-radius: 10px;
        text-align: center;
        min-width: 100px;
    }
    
    .date-day {
        font-size: 14px;
        color: var(--text-muted);
    }
    
    .date-number {
        font-size: 20px;
        font-weight: 600;
        color: var(--primary);
    }
    
    .consultation-doctor {
        flex-grow: 1;
        margin-left: 16px;
    }
    
    .doctor-name {
        font-weight: 500;
        color: var(--text);
        font-size: 16px;
    }
    
    .doctor-label {
        font-size: 12px;
        color: var(--text-muted);
    }
    
    .collapse-icon {
        transition: transform var(--transition-fast);
    }
    
    .rotate-180 {
        transform: rotate(180deg);
    }
    
    /* Contenido de la consulta */
    .consultation-content {
        padding: 24px;
    }
    
    .section-box {
        background: rgba(163, 196, 243, 0.05);
        border-left: 4px solid var(--primary);
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
    }
    
    .section-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .section-content {
        font-size: 15px;
        line-height: 1.6;
        color: var(--text);
    }
    
    /* Tarjeta de receta */
    .prescription-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 24px;
        margin-top: 24px;
        position: relative;
        overflow: hidden;
    }
    
    .prescription-card::before {
        content: 'Rx';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-15deg);
        font-size: 120px;
        font-family: 'Inter', sans-serif;
        font-style: italic;
        font-weight: 700;
        color: rgba(163, 196, 243, 0.1);
        pointer-events: none;
        z-index: 0;
    }
    
    .prescription-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        position: relative;
        z-index: 1;
    }
    
    .rx-symbol {
        font-family: 'Inter', sans-serif;
        font-size: 36px;
        font-style: italic;
        font-weight: 700;
        color: var(--primary);
    }
    
    .btn-print-prescription {
        background: var(--primary);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 14px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all var(--transition-fast);
    }
    
    .btn-print-prescription:hover {
        background: var(--primary);
        transform: translateY(-1px);
    }
    
    .prescription-content {
        position: relative;
        z-index: 1;
        font-family: 'Courier New', monospace;
        font-size: 14px;
        line-height: 1.6;
        white-space: pre-wrap;
        background: rgba(163, 196, 243, 0.05);
        padding: 20px;
        border-radius: 8px;
        border: 1px dashed rgba(163, 196, 243, 0.3);
    }
    
    /* Estado vacío */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
    }
    
    .empty-icon {
        font-size: 48px;
        margin-bottom: 16px;
        opacity: 0.3;
    }
    
    /* Interruptor de modo noche */
    .theme-toggle {
        position: fixed;
        top: 24px;
        right: 24px;
        z-index: 1000;
    }
    
    .theme-toggle-btn {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: var(--surface);
        border: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all var(--transition-fast);
        color: var(--text);
    }
    
    .theme-toggle-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    /* Modal */
    .modal-content {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        color: var(--text);
    }
    
    .modal-header {
        border-bottom: 1px solid var(--border);
        padding: 24px;
    }
    
    .modal-title {
        font-size: 20px;
        font-weight: 600;
        color: var(--text);
    }
    
    .form-control, .form-select, .form-textarea {
        background: var(--background);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text);
        padding: 12px 16px;
        font-size: 14px;
        transition: all var(--transition-fast);
    }
    
    .form-control:focus, .form-select:focus, .form-textarea:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(163, 196, 243, 0.1);
        outline: none;
    }
    
    .form-label {
        font-weight: 500;
        color: var(--text);
        font-size: 14px;
        margin-bottom: 8px;
    }
    
    /* Pestañas del modal */
    .modal-tabs {
        display: flex;
        gap: 4px;
        padding: 0 24px;
        margin-bottom: 24px;
    }
    
    .tab-btn {
        flex: 1;
        padding: 12px;
        background: none;
        border: none;
        border-bottom: 2px solid transparent;
        color: var(--text-muted);
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all var(--transition-fast);
    }
    
    .tab-btn.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }
    
    .tab-btn:hover:not(.active) {
        color: var(--text);
    }
    
    /* Responsive */
    @media (max-width: 992px) {
        .minimal-sidebar {
            transform: translateX(-100%);
        }
        
        .minimal-sidebar.show {
            transform: translateX(0);
        }
        
        .main-content {
            margin-left: 0;
            width: 100%;
        }
        
        .info-grid {
            grid-template-columns: 1fr;
        }
        
        .theme-toggle {
            top: 16px;
            right: 16px;
        }
    }
    
    @media (max-width: 768px) {
        .main-content {
            padding: 16px;
        }
        
        .consultation-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
        
        .consultation-date {
            align-self: flex-start;
        }
        
        .prescription-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
    }
</style>

<div class="dashboard-container">
    <!-- Contenido principal -->
    <main class="main-content">
        <!-- Interruptor de modo noche -->
        <div class="theme-toggle">
            <button class="theme-toggle-btn" id="themeToggle" aria-label="Cambiar tema">
                <i class="bi bi-moon"></i>
            </button>
        </div>
        
        <!-- Botón para mostrar sidebar en móvil -->
        <button class="btn btn-outline-secondary d-md-none mb-3" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>
        
        <div class="content-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <a href="index.php" class="header-back">
                        <i class="bi bi-arrow-left"></i>
                        Volver a Pacientes
                    </a>
                    <h1 class="header-title">
                        <i class="bi bi-file-medical text-primary me-2"></i>
                        Historial Clínico
                    </h1>
                    <p class="header-subtitle">
                        <?php echo htmlspecialchars($patient['nombre'] . ' ' . $patient['apellido']); ?>
                        • ID: #<?php echo str_pad($patient_id, 5, '0', STR_PAD_LEFT); ?>
                    </p>
                </div>
                <button type="button" class="btn-new-record" data-bs-toggle="modal" data-bs-target="#newMedicalRecordModal">
                    <i class="bi bi-plus-circle"></i>
                    Nueva Consulta
                </button>
                <a href="../hospitalization/ingresar_paciente.php?id_paciente=<?php echo $patient_id; ?>" class="btn-new-record ms-2" style="background-color: var(--secondary);">
                    <i class="bi bi-hospital"></i>
                    Ingresar
                </a>
            </div>
        </div>
        
        <div class="row">
            <!-- Panel de información del paciente -->
            <div class="col-lg-4 mb-4">
                <div class="patient-info-panel">
                    <div class="text-center mb-4">
                        <div class="patient-avatar-large mx-auto mb-3">
                            <?php echo strtoupper(substr($patient['nombre'], 0, 1) . substr($patient['apellido'], 0, 1)); ?>
                        </div>
                        <h3 class="patient-name"><?php echo htmlspecialchars($patient['nombre'] . ' ' . $patient['apellido']); ?></h3>
                        <div class="patient-id">ID: #<?php echo str_pad($patient['id_paciente'], 5, '0', STR_PAD_LEFT); ?></div>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Edad</span>
                            <span class="info-value"><?php echo $edad; ?> años</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Género</span>
                            <span class="info-value"><?php echo htmlspecialchars($patient['genero']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Nacimiento</span>
                            <span class="info-value"><?php echo date('d/m/Y', strtotime($patient['fecha_nacimiento'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Teléfono</span>
                            <span class="info-value"><?php echo htmlspecialchars($patient['telefono'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Consultas</span>
                            <span class="info-value"><?php echo $patient['total_consultas']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Última visita</span>
                            <span class="info-value">
                                <?php echo $patient['ultima_consulta'] ? date('d/m/Y', strtotime($patient['ultima_consulta'])) : 'N/A'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Timeline de historial médico -->
            <div class="col-lg-8">
                <?php if (count($medical_records) > 0): ?>
                    <div class="medical-timeline">
                        <?php foreach ($medical_records as $index => $record): ?>
                            <div class="timeline-item">
                                <div class="consultation-card">
                                    <div class="consultation-header" 
                                         data-bs-toggle="collapse" 
                                         data-bs-target="#collapseRecord<?php echo $record['id_historial']; ?>" 
                                         aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                                        <div class="consultation-date">
                                            <div class="date-day"><?php echo date('D', strtotime($record['fecha_consulta'])); ?></div>
                                            <div class="date-number"><?php echo date('d', strtotime($record['fecha_consulta'])); ?></div>
                                            <div class="date-day"><?php echo date('M/y', strtotime($record['fecha_consulta'])); ?></div>
                                        </div>
                                        <div class="consultation-doctor">
                                            <div class="doctor-name">Dr. <?php echo htmlspecialchars($record['medico_responsable']); ?></div>
                                            <div class="doctor-label">Médico responsable</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary rounded-circle" 
                                                        data-bs-toggle="dropdown" 
                                                        onclick="event.stopPropagation()">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" href="#" 
                                                           onclick="editMedicalRecord(<?php echo $record['id_historial']; ?>); event.stopPropagation();">
                                                            <i class="bi bi-pencil me-2"></i>Editar
                                                        </a>
                                                    </li>
                                                    <?php if (!empty($record['receta_medica'])): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="#" 
                                                           onclick="printPrescription(<?php echo $record['id_historial']; ?>); event.stopPropagation();">
                                                            <i class="bi bi-printer me-2"></i>Imprimir Receta
                                                        </a>
                                                    </li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-danger" href="#" 
                                                           onclick="deleteMedicalRecord(<?php echo $record['id_historial']; ?>); event.stopPropagation();">
                                                            <i class="bi bi-trash me-2"></i>Eliminar
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                            <i class="bi bi-chevron-down collapse-icon <?php echo $index === 0 ? 'rotate-180' : ''; ?>"></i>
                                        </div>
                                    </div>
                                    
                                    <div id="collapseRecord<?php echo $record['id_historial']; ?>" 
                                         class="collapse <?php echo $index === 0 ? 'show' : ''; ?>">
                                        <div class="consultation-content">
                                            <div class="row g-4">
                                                <div class="col-md-7">
                                                    <div class="section-box">
                                                        <div class="section-title">
                                                            <i class="bi bi-chat-left-text"></i>
                                                            Motivo de Consulta
                                                        </div>
                                                        <div class="section-content">
                                                            <?php echo nl2br(htmlspecialchars($record['motivo_consulta'])); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="section-box">
                                                        <div class="section-title">
                                                            <i class="bi bi-list-check"></i>
                                                            Síntomas / Historia
                                                        </div>
                                                        <div class="section-content">
                                                            <?php echo nl2br(htmlspecialchars($record['sintomas'])); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($record['examen_fisico'])): ?>
                                                    <div class="section-box">
                                                        <div class="section-title">
                                                            <i class="bi bi-heart-pulse"></i>
                                                            Examen Físico
                                                        </div>
                                                        <div class="section-content">
                                                            <?php echo nl2br(htmlspecialchars($record['examen_fisico'])); ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="col-md-5">
                                                    <div class="section-box" style="border-left-color: var(--warning);">
                                                        <div class="section-title" style="color: var(--warning);">
                                                            <i class="bi bi-clipboard-check"></i>
                                                            Diagnóstico
                                                        </div>
                                                        <div class="section-content">
                                                            <?php echo nl2br(htmlspecialchars($record['diagnostico'])); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="section-box" style="border-left-color: var(--secondary);">
                                                        <div class="section-title" style="color: var(--secondary);">
                                                            <i class="bi bi-prescription2"></i>
                                                            Tratamiento
                                                        </div>
                                                        <div class="section-content">
                                                            <?php echo nl2br(htmlspecialchars($record['tratamiento'])); ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($record['proxima_cita'])): ?>
                                                    <div class="section-box" style="border-left-color: var(--accent);">
                                                        <div class="section-title" style="color: var(--accent);">
                                                            <i class="bi bi-calendar-check"></i>
                                                            Próxima Cita
                                                        </div>
                                                        <div class="section-content">
                                                            <strong><?php echo date('d/m/Y', strtotime($record['proxima_cita'])); ?></strong>
                                                            <?php if (!empty($record['hora_proxima_cita'])): ?>
                                                            <br><?php echo $record['hora_proxima_cita']; ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <?php if (!empty($record['receta_medica'])): ?>
                                            <div class="prescription-card">
                                                <div class="prescription-header">
                                                    <div class="d-flex align-items-center">
                                                        <div class="rx-symbol me-3">Rx</div>
                                                        <div>
                                                            <h6 class="mb-0">Prescripción Médica</h6>
                                                            <small class="text-muted">Receta oficial</small>
                                                        </div>
                                                    </div>
                                                    <button class="btn-print-prescription" 
                                                            onclick="printPrescription(<?php echo $record['id_historial']; ?>)">
                                                        <i class="bi bi-printer"></i>
                                                        Imprimir
                                                    </button>
                                                </div>
                                                <div class="prescription-content">
                                                    <?php 
                                                    $clean_receta = implode("\n", array_map('trim', explode("\n", $record['receta_medica'])));
                                                    echo nl2br(htmlspecialchars($clean_receta)); 
                                                    ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-clipboard-x"></i>
                        </div>
                        <h5 class="mb-2">No hay registros médicos</h5>
                        <p class="text-muted mb-4">Este paciente aún no tiene consultas registradas</p>
                        <button class="btn-new-record" data-bs-toggle="modal" data-bs-target="#newMedicalRecordModal">
                            <i class="bi bi-plus-circle"></i>
                            Crear primer registro
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Modal para nueva consulta -->
<div class="modal fade" id="newMedicalRecordModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-journal-plus me-2"></i>
                    Nueva Consulta Médica
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <form id="newMedicalRecordForm" action="save_medical_record.php" method="POST">
                <input type="hidden" name="id_paciente" value="<?php echo $patient_id; ?>">
                
                <div class="modal-tabs">
                    <button type="button" class="tab-btn active" data-tab="consulta">Consulta</button>
                    <button type="button" class="tab-btn" data-tab="exploracion">Exploración</button>
                    <button type="button" class="tab-btn" data-tab="plan">Plan</button>
                </div>
                
                <div class="modal-body">
                    <!-- Pestaña de Consulta -->
                    <div class="tab-pane active" id="tab-consulta-content">
                        <div class="mb-3">
                            <label class="form-label">Motivo de Consulta</label>
                            <textarea class="form-control form-textarea" name="motivo_consulta" rows="3" placeholder="Describa el motivo principal de la consulta..." required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Síntomas / Historia</label>
                            <textarea class="form-control form-textarea" name="sintomas" rows="4" placeholder="Detalle los síntomas y evolución..." required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Diagnóstico</label>
                            <textarea class="form-control form-textarea" name="diagnostico" rows="3" placeholder="Diagnóstico presuntivo o definitivo..." required></textarea>
                        </div>
                    </div>
                    
                    <!-- Pestaña de Exploración -->
                    <div class="tab-pane d-none" id="tab-exploracion-content">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Presión Arterial</label>
                                <input type="text" class="form-control" name="examen_pa" placeholder="120/80">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Frecuencia Cardíaca</label>
                                <input type="text" class="form-control" name="examen_fc" placeholder="80 lpm">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Frecuencia Respiratoria</label>
                                <input type="text" class="form-control" name="examen_fr" placeholder="16 rpm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Temperatura</label>
                                <input type="text" class="form-control" name="examen_temp" placeholder="36.5°C">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Examen Físico</label>
                            <textarea class="form-control form-textarea" name="examen_fisico" rows="4" placeholder="Hallazgos físicos, inspección, palpación, etc..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Pestaña de Plan -->
                    <div class="tab-pane d-none" id="tab-plan-content">
                        <div class="mb-3">
                            <label class="form-label">Plan de Tratamiento</label>
                            <textarea class="form-control form-textarea" name="tratamiento" rows="4" placeholder="Medicamentos, terapias, indicaciones..." required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Receta Médica</label>
                            <textarea class="form-control form-textarea" name="receta_medica" rows="4" placeholder="1. Amoxicilina 500mg...&#10;2. Ibuprofeno 400mg..."></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Próxima Cita</label>
                                <input type="date" class="form-control" name="proxima_cita">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Hora</label>
                                <input type="time" class="form-control" name="hora_proxima_cita">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Consulta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>

<script>
// Gestión del modo noche
const themeToggle = document.getElementById('themeToggle');
const body = document.body;

// Comprobar preferencia guardada
const savedTheme = localStorage.getItem('theme') || 'light';
if (savedTheme === 'dark') {
    body.classList.add('dark-mode');
    themeToggle.innerHTML = '<i class="bi bi-sun"></i>';
}

// Alternar tema
themeToggle.addEventListener('click', function() {
    body.classList.toggle('dark-mode');
    
    if (body.classList.contains('dark-mode')) {
        localStorage.setItem('theme', 'dark');
        themeToggle.innerHTML = '<i class="bi bi-sun"></i>';
    } else {
        localStorage.setItem('theme', 'light');
        themeToggle.innerHTML = '<i class="bi bi-moon"></i>';
    }
});

// Mostrar/ocultar sidebar en móvil
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');

sidebarToggle?.addEventListener('click', () => {
    sidebar.classList.toggle('show');
});

// Cerrar sidebar al hacer clic fuera (en móvil)
document.addEventListener('click', (event) => {
    if (window.innerWidth < 992 && !sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
        sidebar.classList.remove('show');
    }
});

// Navegación por pestañas en el modal
const tabBtns = document.querySelectorAll('.tab-btn');
tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        // Remover clase activa de todas las pestañas
        tabBtns.forEach(b => b.classList.remove('active'));
        
        // Ocultar todos los contenidos
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.add('d-none');
            pane.classList.remove('active');
        });
        
        // Activar pestaña clickeada
        btn.classList.add('active');
        
        // Mostrar contenido correspondiente
        const tabId = btn.getAttribute('data-tab');
        const content = document.getElementById(`tab-${tabId}-content`);
        if (content) {
            content.classList.remove('d-none');
            content.classList.add('active');
        }
    });
});

// Funciones para gestionar registros médicos
function editMedicalRecord(id) {
    // Implementar lógica para editar registro
    console.log('Editar registro:', id);
    alert('Función de edición en desarrollo');
}

function deleteMedicalRecord(id) {
    if (confirm('¿Está seguro de eliminar este registro médico? Esta acción no se puede deshacer.')) {
        fetch('delete_medical_record.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error al eliminar el registro');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al eliminar el registro');
        });
    }
}

function printPrescription(id) {
    window.open('print_prescription.php?id=' + id, '_blank');
}

// Animaciones de entrada
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.consultation-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100);
    });
});

// Manejar iconos de colapso
document.addEventListener('DOMContentLoaded', function() {
    const collapsibleElements = document.querySelectorAll('.collapse');
    collapsibleElements.forEach(el => {
        el.addEventListener('show.bs.collapse', function() {
            const icon = this.previousElementSibling?.querySelector('.collapse-icon');
            if (icon) icon.classList.add('rotate-180');
        });
        
        el.addEventListener('hide.bs.collapse', function() {
            const icon = this.previousElementSibling?.querySelector('.collapse-icon');
            if (icon) icon.classList.remove('rotate-180');
        });
    });
});
</script>