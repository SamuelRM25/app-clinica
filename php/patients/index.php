<?php
// patients/index.php - Módulo de Gestión de Pacientes
// Versión: 4.0 - Diseño Dashboard con Efecto Mármol y Modo Noche
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Incluir configuraciones y funciones
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer zona horaria
date_default_timezone_set('America/Guatemala');
verify_session();

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener información del usuario
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_name = $_SESSION['nombre'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';
    
    // Título de la página
    $page_title = "Gestión de Pacientes - Centro Médico Herrera Saenz";
    
    // Consulta optimizada según tipo de usuario
    if ($user_type === 'doc') {
        // Pacientes atendidos por este médico
        $stmt = $conn->prepare("
            SELECT DISTINCT p.*, 
                   COUNT(c.id_cita) as total_citas,
                   MAX(c.fecha_cita) as ultima_cita
            FROM pacientes p
            LEFT JOIN citas c ON (p.nombre = c.nombre_pac AND p.apellido = c.apellido_pac)
            WHERE c.id_doctor = ? OR p.id_paciente IN (
                SELECT DISTINCT id_paciente FROM historial_clinico 
                WHERE medico_responsable LIKE ?
            )
            GROUP BY p.id_paciente
            ORDER BY p.apellido, p.nombre
        ");
        $doctor_name = $_SESSION['nombre'] . ' ' . $_SESSION['apellido'];
        $stmt->execute([$user_id, '%' . $doctor_name . '%']);
    } else {
        // Todos los pacientes para admin/usuarios
        $stmt = $conn->prepare("
            SELECT p.*, 
                   COUNT(c.id_cita) as total_citas,
                   MAX(c.fecha_cita) as ultima_cita
            FROM pacientes p
            LEFT JOIN citas c ON (p.nombre = c.nombre_pac AND p.apellido = c.apellido_pac)
            GROUP BY p.id_paciente
            ORDER BY p.apellido, p.nombre
        ");
        $stmt->execute();
    }
    
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas
    $total_patients = count($patients);
    $patients_with_appointments = count(array_filter($patients, function($p) { 
        return $p['total_citas'] > 0; 
    }));
    $patients_without_history = count(array_filter($patients, function($p) { 
        return !isset($p['ultima_cita']); 
    }));
    $active_today = count(array_filter($patients, function($p) { 
        return isset($p['ultima_cita']) && $p['ultima_cita'] === date('Y-m-d'); 
    }));
    
    // Obtener médicos para el modal de citas rápidas
    $stmt_doctors = $conn->prepare("
        SELECT idUsuario, nombre, apellido 
        FROM usuarios 
        WHERE tipoUsuario = 'doc' 
        ORDER BY nombre, apellido
    ");
    $stmt_doctors->execute();
    $doctors = $stmt_doctors->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en módulo de pacientes: " . $e->getMessage());
    die("Error al cargar el módulo de pacientes. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gestión de Pacientes - Centro Médico Herrera Saenz">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- CSS Crítico (mismo que el dashboard) -->
    <style>
    /* ==========================================================================
       VARIABLES CSS PARA TEMA DÍA/NOCHE
       ========================================================================== */
    :root {
        /* Colores Modo Día (Escala Grises + Mármol) */
        --color-bg-day: #ffffff;
        --color-surface-day: #f8f9fa;
        --color-card-day: #ffffff;
        --color-text-day: #1a1a1a;
        --color-text-secondary-day: #6c757d;
        --color-border-day: #e9ecef;
        --color-primary-day: #0d6efd;
        --color-secondary-day: #6c757d;
        --color-success-day: #198754;
        --color-warning-day: #ffc107;
        --color-danger-day: #dc3545;
        --color-info-day: #0dcaf0;
        
        /* Colores Modo Noche (Tonalidades Azules) */
        --color-bg-night: #0f172a;
        --color-surface-night: #1e293b;
        --color-card-night: #1e293b;
        --color-text-night: #e2e8f0;
        --color-text-secondary-night: #94a3b8;
        --color-border-night: #2d3748;
        --color-primary-night: #3b82f6;
        --color-secondary-night: #64748b;
        --color-success-night: #10b981;
        --color-warning-night: #f59e0b;
        --color-danger-night: #ef4444;
        --color-info-night: #06b6d4;
        
        /* Versiones RGB para opacidad */
        --color-primary-rgb: 13, 110, 253;
        --color-success-rgb: 25, 135, 84;
        --color-warning-rgb: 255, 193, 7;
        --color-danger-rgb: 220, 53, 69;
        --color-info-rgb: 13, 202, 240;
        --color-card-rgb: 255, 255, 255;
        
        /* Efecto Mármol */
        --marble-color-1: rgba(255, 255, 255, 0.95);
        --marble-color-2: rgba(248, 249, 250, 0.8);
        --marble-pattern: linear-gradient(135deg, var(--marble-color-1) 25%, transparent 25%),
                          linear-gradient(225deg, var(--marble-color-1) 25%, transparent 25%),
                          linear-gradient(45deg, var(--marble-color-1) 25%, transparent 25%),
                          linear-gradient(315deg, var(--marble-color-1) 25%, var(--marble-color-2) 25%);
        
        /* Transiciones */
        --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
        --transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);
        
        /* Sombras */
        --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
        --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
        --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
        --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1);
        
        /* Bordes */
        --radius-sm: 0.375rem;
        --radius-md: 0.5rem;
        --radius-lg: 0.75rem;
        --radius-xl: 1rem;
        
        /* Espaciado */
        --space-xs: 0.25rem;
        --space-sm: 0.5rem;
        --space-md: 1rem;
        --space-lg: 1.5rem;
        --space-xl: 2rem;
        
        /* Tipografía */
        --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        --font-size-xs: 0.75rem;
        --font-size-sm: 0.875rem;
        --font-size-base: 1rem;
        --font-size-lg: 1.125rem;
        --font-size-xl: 1.25rem;
        --font-size-2xl: 1.5rem;
        --font-size-3xl: 1.875rem;
        --font-size-4xl: 2.25rem;
    }
    
    /* ==========================================================================
       ESTILOS BASE Y RESET
       ========================================================================== */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    html {
        font-size: 16px;
        scroll-behavior: smooth;
    }
    
    body {
        font-family: var(--font-family);
        font-weight: 400;
        line-height: 1.6;
        overflow-x: hidden;
        transition: background-color var(--transition-base);
    }
    
    /* ==========================================================================
       TEMA DÍA (POR DEFECTO)
       ========================================================================== */
    [data-theme="light"] {
        --color-bg: var(--color-bg-day);
        --color-surface: var(--color-surface-day);
        --color-card: var(--color-card-day);
        --color-text: var(--color-text-day);
        --color-text-secondary: var(--color-text-secondary-day);
        --color-border: var(--color-border-day);
        --color-primary: var(--color-primary-day);
        --color-secondary: var(--color-secondary-day);
        --color-success: var(--color-success-day);
        --color-warning: var(--color-warning-day);
        --color-danger: var(--color-danger-day);
        --color-info: var(--color-info-day);
        
        --marble-color-1: rgba(255, 255, 255, 0.95);
        --marble-color-2: rgba(248, 249, 250, 0.8);
    }
    
    /* ==========================================================================
       TEMA NOCHE
       ========================================================================== */
    [data-theme="dark"] {
        --color-bg: var(--color-bg-night);
        --color-surface: var(--color-surface-night);
        --color-card: var(--color-card-night);
        --color-text: var(--color-text-night);
        --color-text-secondary: var(--color-text-secondary-night);
        --color-border: var(--color-border-night);
        --color-primary: var(--color-primary-night);
        --color-secondary: var(--color-secondary-night);
        --color-success: var(--color-success-night);
        --color-warning: var(--color-warning-night);
        --color-danger: var(--color-danger-night);
        --color-info: var(--color-info-night);
        
        --color-primary-rgb: 59, 130, 246;
        --color-success-rgb: 16, 185, 129;
        --color-warning-rgb: 245, 158, 11;
        --color-danger-rgb: 239, 68, 68;
        --color-info-rgb: 6, 182, 212;
        --color-card-rgb: 30, 41, 59;
        
        --marble-color-1: rgba(15, 23, 42, 0.95);
        --marble-color-2: rgba(30, 41, 59, 0.8);
    }
    
    /* ==========================================================================
       APLICACIÓN DE VARIABLES
       ========================================================================== */
    body {
        background-color: var(--color-bg);
        color: var(--color-text);
        min-height: 100vh;
        position: relative;
    }
    
    /* ==========================================================================
       EFECTO MÁRMOL (FONDO)
       ========================================================================== */
    .marble-effect {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: -1;
        background: 
            radial-gradient(circle at 20% 80%, var(--marble-color-1) 0%, transparent 50%),
            radial-gradient(circle at 80% 20%, var(--marble-color-2) 0%, transparent 50%),
            var(--color-bg);
        background-blend-mode: overlay;
        background-size: 200% 200%;
        animation: marbleFloat 20s ease-in-out infinite alternate;
        opacity: 0.7;
        pointer-events: none;
    }
    
    @keyframes marbleFloat {
        0% { background-position: 0% 0%; }
        100% { background-position: 100% 100%; }
    }
    
    /* ==========================================================================
       LAYOUT PRINCIPAL
       ========================================================================== */
    .dashboard-container {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        position: relative;
        width: 100%;
        transition: all var(--transition-base);
    }
    
    /* User Avatar (Sidebar replacement) */
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--color-primary), var(--color-info));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: var(--font-size-lg);
        flex-shrink: 0;
    }
    
    .user-details {
        flex: 1;
        min-width: 0;
        transition: opacity var(--transition-base);
    }
    
    .user-name {
        font-weight: 600;
        display: block;
        font-size: var(--font-size-sm);
        color: var(--color-text);
        line-height: 1.2;
    }
    
    .user-role {
        font-size: var(--font-size-xs);
        color: var(--color-text-secondary);
        display: block;
        line-height: 1.2;
    }
    
    /* ==========================================================================
       HEADER SUPERIOR
       ========================================================================== */
    .dashboard-header {
        position: sticky;
        top: 0;
        left: 0;
        right: 0;
        background-color: var(--color-card);
        border-bottom: 1px solid var(--color-border);
        z-index: 900;
        backdrop-filter: blur(10px);
        background-color: rgba(var(--color-card-rgb), 0.95); 
        box-shadow: var(--shadow-sm);
    }
    
    .header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: var(--space-md) var(--space-lg);
        gap: var(--space-lg);
    }
    
    .brand-container {
        display: flex;
        align-items: center;
        gap: var(--space-md);
        margin-left: 0;
    }
    
    .brand-logo {
        height: 40px;
        width: auto;
        object-fit: contain;
    }
    
    .mobile-toggle {
        display: none;
    }
    
    .header-controls {
        display: flex;
        align-items: center;
        gap: var(--space-lg);
    }
    
    /* Control de tema */
    .theme-toggle {
        position: relative;
    }
    
    .theme-btn {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        border: none;
        background: var(--color-surface);
        color: var(--color-text);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all var(--transition-base);
        position: relative;
        overflow: hidden;
    }
    
    .theme-btn:hover {
        transform: scale(1.05);
        box-shadow: var(--shadow-md);
    }
    
    .theme-btn:active {
        transform: scale(0.95);
    }
    
    .theme-icon {
        position: absolute;
        font-size: 1.25rem;
        transition: all var(--transition-base);
    }
    
    .sun-icon {
        opacity: 1;
        transform: rotate(0);
    }
    
    .moon-icon {
        opacity: 0;
        transform: rotate(-90deg);
    }
    
    [data-theme="dark"] .sun-icon {
        opacity: 0;
        transform: rotate(90deg);
    }
    
    [data-theme="dark"] .moon-icon {
        opacity: 1;
        transform: rotate(0);
    }
    
    /* Información usuario en header */
    .header-user {
        display: flex;
        align-items: center;
        gap: var(--space-md);
    }
    
    .header-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--color-primary), var(--color-info));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: var(--font-size-lg);
    }
    
    .header-details {
        display: flex;
        flex-direction: column;
    }
    
    .header-name {
        font-weight: 600;
        font-size: var(--font-size-sm);
        color: var(--color-text);
    }
    
    .header-role {
        font-size: var(--font-size-xs);
        color: var(--color-text-secondary);
    }
    
    /* Botón de cerrar sesión */
    .logout-btn {
        display: flex;
        align-items: center;
        gap: var(--space-sm);
        padding: var(--space-sm) var(--space-md);
        background: var(--color-surface);
        color: var(--color-text);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        text-decoration: none;
        font-weight: 500;
        transition: all var(--transition-base);
    }
    
    .logout-btn:hover {
        background: var(--color-danger);
        color: white;
        border-color: var(--color-danger);
        transform: translateY(-2px);
    }
    
    /* ==========================================================================
       CONTENIDO PRINCIPAL
       ========================================================================== */
    .main-content {
        flex: 1;
        padding: var(--space-lg);
        transition: all var(--transition-base);
        min-height: 100vh;
        background-color: transparent;
        width: 100%;
    }
    
    /* ==========================================================================
       COMPONENTES DE DASHBOARD
       ========================================================================== */
    
    /* Tarjetas de estadísticas */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: var(--space-lg);
        margin-bottom: var(--space-xl);
    }
    
    .stat-card {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        transition: all var(--transition-base);
        position: relative;
        overflow: hidden;
        cursor: pointer;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-xl);
        border-color: var(--color-primary);
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, var(--color-primary), var(--color-info));
    }
    
    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: var(--space-md);
    }
    
    .stat-title {
        font-size: var(--font-size-sm);
        color: var(--color-text-secondary);
        font-weight: 500;
        margin-bottom: var(--space-xs);
    }
    
    .stat-value {
        font-size: var(--font-size-3xl);
        font-weight: 700;
        color: var(--color-text);
        line-height: 1;
    }
    
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    
    .stat-icon.primary {
        background: rgba(var(--color-primary-rgb), 0.1);
        color: var(--color-primary);
    }
    
    .stat-icon.success {
        background: rgba(var(--color-success-rgb), 0.1);
        color: var(--color-success);
    }
    
    .stat-icon.warning {
        background: rgba(var(--color-warning-rgb), 0.1);
        color: var(--color-warning);
    }
    
    .stat-icon.info {
        background: rgba(var(--color-info-rgb), 0.1);
        color: var(--color-info);
    }
    
    .stat-change {
        display: flex;
        align-items: center;
        gap: var(--space-xs);
        font-size: var(--font-size-sm);
        color: var(--color-text-secondary);
    }
    
    .stat-change.positive {
        color: var(--color-success);
    }
    
    /* Secciones */
    .appointments-section {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: var(--space-lg);
        margin-bottom: var(--space-lg);
        transition: all var(--transition-base);
    }
    
    .appointments-section:hover {
        box-shadow: var(--shadow-lg);
    }
    
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: var(--space-lg);
        flex-wrap: wrap;
        gap: var(--space-md);
    }
    
    .section-title {
        font-size: var(--font-size-xl);
        font-weight: 600;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: var(--space-sm);
    }
    
    .section-title-icon {
        color: var(--color-primary);
    }
    
    .action-btn {
        display: inline-flex;
        align-items: center;
        gap: var(--space-sm);
        padding: var(--space-sm) var(--space-md);
        background: var(--color-primary);
        color: white;
        border: none;
        border-radius: var(--radius-md);
        font-weight: 500;
        text-decoration: none;
        transition: all var(--transition-base);
        cursor: pointer;
    }
    
    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        background: var(--color-primary);
        opacity: 0.9;
        color: white;
    }
    
    /* Tablas */
    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .appointments-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .appointments-table thead {
        background: var(--color-surface);
    }
    
    .appointments-table th {
        padding: var(--space-md);
        text-align: left;
        font-weight: 600;
        color: var(--color-text);
        border-bottom: 2px solid var(--color-border);
        white-space: nowrap;
    }
    
    .appointments-table td {
        padding: var(--space-md);
        border-bottom: 1px solid var(--color-border);
        vertical-align: middle;
    }
    
    .appointments-table tbody tr {
        transition: all var(--transition-base);
    }
    
    .appointments-table tbody tr:hover {
        background: var(--color-surface);
        transform: translateX(4px);
    }
    
    /* Celdas personalizadas */
    .patient-cell {
        display: flex;
        align-items: center;
        gap: var(--space-md);
    }
    
    .patient-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--color-primary), var(--color-info));
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: var(--font-size-base);
        flex-shrink: 0;
    }
    
    .patient-info {
        min-width: 0;
    }
    
    .patient-name {
        font-weight: 600;
        color: var(--color-text);
        margin-bottom: 2px;
    }
    
    .patient-contact {
        color: var(--color-text-secondary);
        font-size: var(--font-size-sm);
    }
    
    /* Botones de acción */
    .action-buttons {
        display: flex;
        gap: var(--space-xs);
    }
    
    .btn-icon {
        width: 32px;
        height: 32px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--color-border);
        background: var(--color-surface);
        color: var(--color-text);
        display: flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        transition: all var(--transition-base);
    }
    
    .btn-icon:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }
    
    .btn-icon.history:hover {
        background: var(--color-success);
        color: white;
        border-color: var(--color-success);
    }
    
    .btn-icon.appointment:hover {
        background: var(--color-info);
        color: white;
        border-color: var(--color-info);
    }
    
    /* Estado vacío */
    .empty-state {
        text-align: center;
        padding: var(--space-xl);
        color: var(--color-text-secondary);
    }
    
    .empty-icon {
        font-size: 3rem;
        color: var(--color-border);
        margin-bottom: var(--space-md);
        opacity: 0.5;
    }
    
    /* Formularios */
    .form-group {
        margin-bottom: var(--space-md);
    }
    
    .form-label {
        display: block;
        margin-bottom: var(--space-xs);
        font-weight: 500;
        color: var(--color-text);
        font-size: var(--font-size-sm);
    }
    
    .form-input, .form-select, .form-textarea {
        width: 100%;
        padding: var(--space-sm) var(--space-md);
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        color: var(--color-text);
        font-size: var(--font-size-base);
        transition: all var(--transition-base);
    }
    
    .form-input:focus, .form-select:focus, .form-textarea:focus {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.1);
        outline: none;
    }
    
    .form-textarea {
        min-height: 100px;
        resize: vertical;
    }
    
    /* Grid de formularios */
    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: var(--space-md);
    }
    
    /* Input groups */
    .input-group {
        position: relative;
        display: flex;
        align-items: center;
    }
    
    .input-group .form-input {
        padding-left: 40px;
    }
    
    .input-icon {
        position: absolute;
        left: var(--space-md);
        color: var(--color-text-secondary);
    }
    
    /* Filtros */
    .filters-container {
        display: flex;
        gap: var(--space-sm);
        margin-bottom: var(--space-lg);
        flex-wrap: wrap;
    }
    
    .filter-btn {
        padding: var(--space-sm) var(--space-md);
        background: var(--color-surface);
        color: var(--color-text);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: var(--font-size-sm);
        font-weight: 500;
        cursor: pointer;
        transition: all var(--transition-base);
    }
    
    .filter-btn:hover {
        background: var(--color-border);
    }
    
    .filter-btn.active {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
    }
    
    /* Badges de género */
    .gender-badge {
        padding: 2px 8px;
        border-radius: 12px;
        font-size: var(--font-size-xs);
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .gender-male {
        background: rgba(59, 130, 246, 0.1);
        color: var(--color-primary);
        border: 1px solid rgba(59, 130, 246, 0.2);
    }
    
    .gender-female {
        background: rgba(248, 113, 113, 0.1);
        color: var(--color-danger);
        border: 1px solid rgba(248, 113, 113, 0.2);
    }
    
    .gender-other {
        background: rgba(148, 163, 184, 0.1);
        color: var(--color-text-secondary);
        border: 1px solid rgba(148, 163, 184, 0.2);
    }
    
    /* Status badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: var(--font-size-xs);
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .status-active {
        background: rgba(34, 197, 94, 0.1);
        color: var(--color-success);
        border: 1px solid rgba(34, 197, 94, 0.2);
    }
    
    .status-inactive {
        background: rgba(239, 68, 68, 0.1);
        color: var(--color-danger);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
    
    /* Modales */
    .custom-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1100;
        padding: var(--space-md);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .custom-modal-overlay.active {
        display: flex;
        opacity: 1;
    }
    
    .custom-modal {
        background: var(--color-card);
        border-radius: var(--radius-xl);
        width: 100%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: var(--shadow-2xl);
        transform: translateY(20px);
        transition: transform 0.3s ease;
        border: 1px solid var(--color-border);
    }
    
    .custom-modal-overlay.active .custom-modal {
        transform: translateY(0);
    }
    
    .custom-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: var(--space-lg);
        border-bottom: 1px solid var(--color-border);
    }
    
    .custom-modal-title {
        font-size: var(--font-size-lg);
        font-weight: 600;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: var(--space-sm);
    }
    
    .custom-modal-close {
        background: transparent;
        border: none;
        color: var(--color-text-secondary);
        font-size: 1.5rem;
        cursor: pointer;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all var(--transition-base);
    }
    
    .custom-modal-close:hover {
        background: var(--color-surface);
        color: var(--color-danger);
    }
    
    .custom-modal-body {
        padding: var(--space-lg);
    }
    
    .custom-modal-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: var(--space-md);
        padding: var(--space-lg);
        border-top: 1px solid var(--color-border);
        background: var(--color-surface);
    }
    
    .btn-outline {
        display: inline-flex;
        align-items: center;
        gap: var(--space-sm);
        padding: var(--space-sm) var(--space-md);
        background: transparent;
        color: var(--color-primary);
        border: 1px solid var(--color-primary);
        border-radius: var(--radius-md);
        font-weight: 500;
        text-decoration: none;
        transition: all var(--transition-base);
        cursor: pointer;
    }
    
    .btn-outline:hover {
        background: var(--color-primary);
        color: white;
    }
    
    /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */
    
    @media (min-width: 1600px) {
        
        .stats-grid {
            grid-template-columns: repeat(4, 1fr);
        }
        
        .main-content {
            max-width: 1800px;
            margin: 0 auto;
            padding: var(--space-xl);
        }
    }
    
    /* Escritorio estándar */
    @media (max-width: 1399px) {
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }
    }
    
    @media (max-width: 991px) {
        
        .dashboard-container {
            width: 100%;
        }
        
        .main-content {
            padding: var(--space-md);
        }
        
        .mobile-toggle {
            display: none;
        }
        
        .header-content {
            padding: var(--space-md);
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: var(--space-md);
        }
        
        .section-header {
            flex-direction: column;
            align-items: stretch;
            gap: var(--space-md);
        }
        
        .section-title {
            font-size: var(--font-size-lg);
        }
        
        .action-btn {
            width: 100%;
            justify-content: center;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 767px) {
        
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .brand-logo {
            height: 32px;
        }
        
        .header-content {
            flex-wrap: wrap;
        }
        
        .header-controls {
            order: 3;
            width: 100%;
            justify-content: space-between;
            margin-top: var(--space-md);
        }
        
        .theme-btn {
            width: 40px;
            height: 40px;
        }
        
        .logout-btn span {
            display: none;
        }
        
        .logout-btn {
            padding: var(--space-sm);
        }
        
        .appointments-table {
            font-size: var(--font-size-sm);
        }
        
        .appointments-table th,
        .appointments-table td {
            padding: var(--space-sm);
        }
        
        .patient-cell {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-xs);
        }
        
        .patient-avatar {
            width: 32px;
            height: 32px;
            font-size: var(--font-size-sm);
        }
        
        .stat-card {
            padding: var(--space-md);
        }
        
        .stat-value {
            font-size: var(--font-size-2xl);
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            font-size: 1.25rem;
        }
        
        .filters-container {
            overflow-x: auto;
            flex-wrap: nowrap;
            padding-bottom: var(--space-sm);
        }
    }
    
    /* Móviles pequeños */
    @media (max-width: 480px) {
        .main-content {
            padding: var(--space-sm);
        }
        
        .stat-card {
            padding: var(--space-md);
        }
        
        .appointments-section {
            padding: var(--space-md);
        }
        
        .section-title {
            font-size: var(--font-size-base);
        }
        
        .action-buttons {
            flex-direction: column;
            gap: var(--space-xs);
        }
        
        .btn-icon {
            width: 28px;
            height: 28px;
            font-size: 0.875rem;
        }
    }
    
    /* ==========================================================================
       ANIMACIONES DE ENTRADA
       ========================================================================== */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .animate-in {
        animation: fadeInUp 0.6s ease-out forwards;
    }
    
    .delay-1 { animation-delay: 0.1s; }
    .delay-2 { animation-delay: 0.2s; }
    .delay-3 { animation-delay: 0.3s; }
    .delay-4 { animation-delay: 0.4s; }
    
    /* ==========================================================================
       UTILIDADES
       ========================================================================== */
    .text-primary { color: var(--color-primary); }
    .text-success { color: var(--color-success); }
    .text-warning { color: var(--color-warning); }
    .text-danger { color: var(--color-danger); }
    .text-info { color: var(--color-info); }
    .text-muted { color: var(--color-text-secondary); }
    
    .mb-0 { margin-bottom: 0; }
    .mb-1 { margin-bottom: var(--space-xs); }
    .mb-2 { margin-bottom: var(--space-sm); }
    .mb-3 { margin-bottom: var(--space-md); }
    .mb-4 { margin-bottom: var(--space-lg); }
    .mb-5 { margin-bottom: var(--space-xl); }
    
    .mt-0 { margin-top: 0; }
    .mt-1 { margin-top: var(--space-xs); }
    .mt-2 { margin-top: var(--space-sm); }
    .mt-3 { margin-top: var(--space-md); }
    .mt-4 { margin-top: var(--space-lg); }
    .mt-5 { margin-top: var(--space-xl); }
    
    .d-none { display: none; }
    .d-block { display: block; }
    .d-flex { display: flex; }
    
    .gap-1 { gap: var(--space-xs); }
    .gap-2 { gap: var(--space-sm); }
    .gap-3 { gap: var(--space-md); }
    .gap-4 { gap: var(--space-lg); }
    .gap-5 { gap: var(--space-xl); }
    
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    
    .fw-bold { font-weight: 700; }
    .fw-semibold { font-weight: 600; }
    .fw-medium { font-weight: 500; }
    .fw-normal { font-weight: 400; }
    .fw-light { font-weight: 300; }
    
    /* ==========================================================================
       PRINT STYLES
       ========================================================================== */
    @media print {
        .dashboard-header,
        .theme-btn,
        .logout-btn,
        .action-btn {
            display: none !important;
        }
        
        .main-content {
            margin-left: 0 !important;
            padding: 0 !important;
        }
        
        .marble-effect {
            display: none;
        }
        
        body {
            background: white !important;
            color: black !important;
        }
        
        .stat-card,
        .appointments-section {
            break-inside: avoid;
            border: 1px solid #ddd !important;
            box-shadow: none !important;
        }
    }
    </style>
    
</head>
<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>
    
    <!-- Contenedor Principal -->
    <div class="dashboard-container">
        <!-- Header Superior -->
        <header class="dashboard-header">
            <div class="header-content">
                <!-- Logo -->
                <div class="brand-container">
                    <img src="../../assets/img/herrerasaenz.png" alt="Centro Médico Herrera Saenz" class="brand-logo">
                </div>
                
                <!-- Controles -->
                <div class="header-controls">
                    <!-- Control de tema -->
                    <div class="theme-toggle">
                        <button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema claro/oscuro">
                            <i class="bi bi-sun theme-icon sun-icon"></i>
                            <i class="bi bi-moon theme-icon moon-icon"></i>
                        </button>
                    </div>
                    
                    <!-- Información del usuario -->
                    <div class="header-user">
                        <div class="header-avatar">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                        <div class="header-details">
                            <span class="header-name"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="header-role"><?php echo htmlspecialchars($user_specialty); ?></span>
                        </div>
                    </div>
                    
                    <!-- Back Button -->
                    <a href="../dashboard/index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i>
                        Dashboard
                    </a>
                    
                    <!-- Botón de cerrar sesión -->
                    <a href="../auth/logout.php" class="logout-btn">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Salir</span>
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Contenido Principal -->
        <main class="main-content">
            <!-- Bienvenida personalizada -->
            <div class="stat-card mb-4 animate-in">
                <div class="stat-header">
                    <div>
                        <h2 id="greeting" class="stat-value" style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Buenos días</span>, <?php echo htmlspecialchars($user_name); ?>
                        </h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-clock me-1"></i> <span id="current-time"><?php echo date('H:i'); ?></span>
                            <span class="mx-2">•</span>
                            <i class="bi bi-building me-1"></i> Centro Médico Herrera Saenz
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-people text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            
            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <!-- Total de pacientes -->
                <div class="stat-card animate-in delay-1" onclick="filterPatients('all')">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total Pacientes</div>
                            <div class="stat-value"><?php echo $total_patients; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up-right"></i>
                        <span>Registrados en sistema</span>
                    </div>
                </div>
                
                <!-- Pacientes con citas -->
                <div class="stat-card animate-in delay-2" onclick="filterPatients('with_appointments')">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Con Citas</div>
                            <div class="stat-value"><?php echo $patients_with_appointments; ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-person-check"></i>
                        <span>Con historial de citas</span>
                    </div>
                </div>
                
                <!-- Pacientes sin historial -->
                <div class="stat-card animate-in delay-3" onclick="filterPatients('without_history')">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Sin Historial</div>
                            <div class="stat-value"><?php echo $patients_without_history; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-person-x"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span>Requieren primera consulta</span>
                    </div>
                </div>
                
                <!-- Activos hoy -->
                <div class="stat-card animate-in delay-4" onclick="filterPatients('active_today')">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Activos Hoy</div>
                            <div class="stat-value"><?php echo $active_today; ?></div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-activity"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-calendar-day"></i>
                        <span>Atendidos hoy</span>
                    </div>
                </div>
            </div>
            
            <!-- Barra de búsqueda y acciones -->
            <section class="appointments-section animate-in delay-1">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-search section-title-icon"></i>
                        Buscar Pacientes
                    </h3>
                    <button type="button" class="action-btn" id="newPatientButton">
                        <i class="bi bi-person-plus"></i>
                        Nuevo Paciente
                    </button>
                </div>
                
                <div class="mb-4">
                    <div class="input-group" style="max-width: 500px;">
                        <span class="input-icon">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" 
                               id="searchInput" 
                               class="form-input" 
                               placeholder="Buscar por nombre, apellido, teléfono o correo..."
                               aria-label="Buscar pacientes">
                    </div>
                </div>
                
                <!-- Filtros rápidos -->
                <div class="filters-container mb-4" id="filtersContainer">
                    <button type="button" class="filter-btn active" onclick="filterPatients('all')">
                        Todos
                    </button>
                    <button type="button" class="filter-btn" onclick="filterPatients('with_appointments')">
                        Con Citas
                    </button>
                    <button type="button" class="filter-btn" onclick="filterPatients('without_history')">
                        Sin Historial
                    </button>
                    <button type="button" class="filter-btn" onclick="filterPatients('active_today')">
                        Activos Hoy
                    </button>
                    <button type="button" class="filter-btn" onclick="filterPatients('male')">
                        Masculino
                    </button>
                    <button type="button" class="filter-btn" onclick="filterPatients('female')">
                        Femenino
                    </button>
                </div>
            </section>
            
            <!-- Tabla de pacientes -->
            <section class="appointments-section animate-in delay-2">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-list-ul section-title-icon"></i>
                        Lista de Pacientes
                    </h3>
                    <div class="text-muted" id="patientCount">
                        Mostrando <?php echo $total_patients; ?> pacientes
                    </div>
                </div>
                
                <?php if (count($patients) > 0): ?>
                    <div class="table-responsive">
                        <table class="appointments-table" id="patientsTable">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Contacto</th>
                                    <th>Información</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="patientsTableBody">
                                <?php foreach($patients as $patient): 
                                    $edad = isset($patient['fecha_nacimiento']) ? 
                                        (new DateTime())->diff(new DateTime($patient['fecha_nacimiento']))->y : 0;
                                    $patient_initials = strtoupper(
                                        substr($patient['nombre'] ?? '', 0, 1) . 
                                        substr($patient['apellido'] ?? '', 0, 1)
                                    );
                                    $has_appointments = $patient['total_citas'] > 0;
                                    $has_history = isset($patient['ultima_cita']);
                                    $active_today = $has_history && $patient['ultima_cita'] === date('Y-m-d');
                                ?>
                                <tr class="patient-row" 
                                    data-id="<?php echo $patient['id_paciente']; ?>"
                                    data-name="<?php echo htmlspecialchars(strtolower(($patient['nombre'] ?? '') . ' ' . ($patient['apellido'] ?? ''))); ?>"
                                    data-phone="<?php echo htmlspecialchars(strtolower($patient['telefono'] ?? '')); ?>"
                                    data-email="<?php echo htmlspecialchars(strtolower($patient['correo'] ?? '')); ?>"
                                    data-has-appointments="<?php echo $has_appointments ? 'true' : 'false'; ?>"
                                    data-has-history="<?php echo $has_history ? 'true' : 'false'; ?>"
                                    data-active-today="<?php echo $active_today ? 'true' : 'false'; ?>"
                                    data-gender="<?php echo htmlspecialchars(strtolower($patient['genero'] ?? '')); ?>">
                                    <td>
                                        <div class="patient-cell">
                                            <div class="patient-avatar">
                                                <?php echo $patient_initials; ?>
                                            </div>
                                            <div class="patient-info">
                                                <div class="patient-name">
                                                    <?php echo htmlspecialchars(($patient['nombre'] ?? '') . ' ' . ($patient['apellido'] ?? '')); ?>
                                                </div>
                                                <div class="patient-contact">
                                                    ID: #<?php echo str_pad($patient['id_paciente'], 5, '0', STR_PAD_LEFT); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-telephone text-muted" style="font-size: 0.875rem;"></i>
                                                <span><?php echo htmlspecialchars($patient['telefono'] ?? 'No disponible'); ?></span>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-envelope text-muted" style="font-size: 0.875rem;"></i>
                                                <span><?php echo htmlspecialchars($patient['correo'] ?? 'No disponible'); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-calendar3 text-muted" style="font-size: 0.875rem;"></i>
                                                <span><?php echo htmlspecialchars($patient['fecha_nacimiento'] ?? 'N/A'); ?> (<?php echo $edad; ?> años)</span>
                                            </div>
                                            <?php if (isset($patient['genero'])): ?>
                                                <span class="gender-badge <?php 
                                                    echo strtolower($patient['genero']) === 'masculino' ? 'gender-male' : 
                                                           (strtolower($patient['genero']) === 'femenino' ? 'gender-female' : 'gender-other');
                                                ?>">
                                                    <?php echo htmlspecialchars($patient['genero']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($active_today): ?>
                                            <span class="status-badge status-active">
                                                <i class="bi bi-check-circle"></i>
                                                Activo Hoy
                                            </span>
                                        <?php elseif ($has_history): ?>
                                            <span class="status-badge status-active">
                                                <i class="bi bi-check-circle"></i>
                                                Activo
                                            </span>
                                        <?php else: ?>
                                            <span class="status-badge status-inactive">
                                                <i class="bi bi-clock-history"></i>
                                                Sin Visitas
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($has_appointments): ?>
                                            <div class="text-muted" style="font-size: 0.75rem; margin-top: 0.25rem;">
                                                <i class="bi bi-calendar-check"></i>
                                                <?php echo $patient['total_citas']; ?> citas
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="medical_history.php?id=<?php echo $patient['id_paciente']; ?>" 
                                               class="btn-icon history" 
                                               title="Historial Clínico">
                                                <i class="bi bi-clipboard2-pulse"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn-icon appointment" 
                                                    title="Nueva Cita"
                                                    onclick="quickAppointment(<?php echo $patient['id_paciente']; ?>, '<?php echo htmlspecialchars($patient['nombre']); ?>', '<?php echo htmlspecialchars($patient['apellido']); ?>')">
                                                <i class="bi bi-calendar-plus"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-people"></i>
                        </div>
                        <h4 class="text-muted mb-2">No se encontraron pacientes</h4>
                        <p class="text-muted mb-3">Comienza agregando tu primer paciente</p>
                        <button type="button" class="action-btn" id="emptyNewPatientButton">
                            <i class="bi bi-person-plus"></i>
                            Agregar Primer Paciente
                        </button>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    
    <!-- Modal para nuevo paciente -->
    <div class="custom-modal-overlay" id="newPatientModal">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h3 class="custom-modal-title">
                    <i class="bi bi-person-plus"></i>
                    Nuevo Paciente
                </h3>
                <button type="button" class="custom-modal-close" onclick="closeModal('newPatientModal')">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <form id="newPatientForm" action="save_patient.php" method="POST">
                <div class="custom-modal-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nombre" class="form-label">Nombres *</label>
                            <input type="text" 
                                   id="nombre" 
                                   name="nombre" 
                                   class="form-input" 
                                   placeholder="Ej: Juan Antonio" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="apellido" class="form-label">Apellidos *</label>
                            <input type="text" 
                                   id="apellido" 
                                   name="apellido" 
                                   class="form-input" 
                                   placeholder="Ej: Pérez Sosa" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento *</label>
                            <input type="date" 
                                   id="fecha_nacimiento" 
                                   name="fecha_nacimiento" 
                                   class="form-input" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="genero" class="form-label">Género *</label>
                            <select id="genero" name="genero" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                <option value="Masculino">Masculino</option>
                                <option value="Femenino">Femenino</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <div class="input-group">
                                <i class="bi bi-telephone input-icon"></i>
                                <input type="tel" 
                                       id="telefono" 
                                       name="telefono" 
                                       class="form-input" 
                                       placeholder="Ej: 46232418">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="correo" class="form-label">Correo Electrónico</label>
                            <div class="input-group">
                                <i class="bi bi-envelope input-icon"></i>
                                <input type="email" 
                                       id="correo" 
                                       name="correo" 
                                       class="form-input" 
                                       placeholder="Ej: juan@gmail.com">
                            </div>
                        </div>
                        
                        <div class="form-group" style="grid-column: span 2;">
                            <label for="direccion" class="form-label">Dirección</label>
                            <textarea id="direccion" 
                                      name="direccion" 
                                      class="form-textarea" 
                                      placeholder="Ej: Barrio San Juan, Nentón"
                                      rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal('newPatientModal')">
                        Cancelar
                    </button>
                    <button type="submit" class="action-btn">
                        Guardar Paciente
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal para cita rápida -->
    <div class="custom-modal-overlay" id="quickAppointmentModal">
        <div class="custom-modal">
            <div class="custom-modal-header">
                <h3 class="custom-modal-title">
                    <i class="bi bi-calendar-plus"></i>
                    Nueva Cita Rápida
                </h3>
                <button type="button" class="custom-modal-close" onclick="closeModal('quickAppointmentModal')">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            <form id="quickAppointmentForm">
                <div class="custom-modal-body">
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Paciente</label>
                            <input type="text" 
                                   id="quickPatientName" 
                                   class="form-input" 
                                   readonly>
                            <input type="hidden" id="quickPatientId" name="patient_id">
                        </div>
                        
                        <div class="form-group">
                            <label for="quickDate" class="form-label">Fecha *</label>
                            <input type="date" 
                                   id="quickDate" 
                                   name="fecha_cita" 
                                   class="form-input" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="quickTime" class="form-label">Hora *</label>
                            <input type="time" 
                                   id="quickTime" 
                                   name="hora_cita" 
                                   class="form-input" 
                                   required>
                        </div>
                        
                        <div class="form-group" style="grid-column: span 2;">
                            <label for="quickDoctor" class="form-label">Médico *</label>
                            <select id="quickDoctor" name="id_doctor" class="form-select" required>
                                <option value="">Seleccionar Médico...</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['idUsuario']; ?>">
                                        Dr(a). <?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellido']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="grid-column: span 2;">
                            <label for="quickReason" class="form-label">Motivo de Consulta</label>
                            <textarea id="quickReason" 
                                      name="motivo_consulta" 
                                      class="form-textarea" 
                                      placeholder="Describa el motivo de la consulta"
                                      rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="custom-modal-footer">
                    <button type="button" class="btn-outline" onclick="closeModal('quickAppointmentModal')">
                        Cancelar
                    </button>
                    <button type="submit" class="action-btn">
                        Programar Cita
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- JavaScript Optimizado -->
    <script>
    // Dashboard Reingenierizado - Centro Médico Herrera Saenz
    (function() {
        'use strict';
        
        // ==========================================================================
        // CONFIGURACIÓN Y CONSTANTES
        // ==========================================================================
        const CONFIG = {
            themeKey: 'dashboard-theme',

            greetingKey: 'last-jornada-summary',
            transitionDuration: 300,
            animationDelay: 100
        };
        
        // ==========================================================================
        // REFERENCIAS A ELEMENTOS DOM
        // ==========================================================================
        const DOM = {
            html: document.documentElement,
            body: document.body,
            themeSwitch: document.getElementById('themeSwitch'),
            greetingElement: document.getElementById('greeting-text'),
            currentTimeElement: document.getElementById('current-time'),
            searchInput: document.getElementById('searchInput'),
            newPatientButton: document.getElementById('newPatientButton'),
            emptyNewPatientButton: document.getElementById('emptyNewPatientButton'),
            patientRows: document.querySelectorAll('.patient-row'),
            patientCount: document.getElementById('patientCount')
        };
        
        // ==========================================================================
        // MANEJO DE TEMA (DÍA/NOCHE)
        // ==========================================================================
        class ThemeManager {
            constructor() {
                this.theme = this.getInitialTheme();
                this.applyTheme(this.theme);
                this.setupEventListeners();
            }
            
            getInitialTheme() {
                const savedTheme = localStorage.getItem(CONFIG.themeKey);
                if (savedTheme) return savedTheme;
                
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (prefersDark) return 'dark';
                
                return 'light';
            }
            
            applyTheme(theme) {
                DOM.html.setAttribute('data-theme', theme);
                localStorage.setItem(CONFIG.themeKey, theme);
                
                const metaTheme = document.querySelector('meta[name="theme-color"]');
                if (metaTheme) {
                    metaTheme.setAttribute('content', theme === 'dark' ? '#0f172a' : '#ffffff');
                }
            }
            
            toggleTheme() {
                const newTheme = this.theme === 'light' ? 'dark' : 'light';
                this.theme = newTheme;
                this.applyTheme(newTheme);
                
                if (DOM.themeSwitch) {
                    DOM.themeSwitch.style.transform = 'rotate(180deg)';
                    setTimeout(() => {
                        DOM.themeSwitch.style.transform = 'rotate(0)';
                    }, CONFIG.transitionDuration);
                }
            }
            
            setupEventListeners() {
                if (DOM.themeSwitch) {
                    DOM.themeSwitch.addEventListener('click', () => this.toggleTheme());
                }
                
                window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                    if (!localStorage.getItem(CONFIG.themeKey)) {
                        this.theme = e.matches ? 'dark' : 'light';
                        this.applyTheme(this.theme);
                    }
                });
            }
        }
        
        // ==========================================================================
        // COMPONENTES DINÁMICOS
        // ==========================================================================
        class DynamicComponents {
            constructor() {
                this.setupGreeting();
                this.setupClock();
                this.setupPatientSearch();
                this.setupModals();
                this.setupAnimations();
                this.handleAutoOpen();
            }

            handleAutoOpen() {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('new') === 'true') {
                    const nombre = urlParams.get('nombre');
                    const apellido = urlParams.get('apellido');
                    
                    if (nombre) document.getElementById('nombre').value = nombre;
                    if (apellido) document.getElementById('apellido').value = apellido;
                    
                    setTimeout(() => {
                        this.openModal('newPatientModal');
                    }, 500);
                }
            }
            
            setupGreeting() {
                if (!DOM.greetingElement) return;
                
                const hour = new Date().getHours();
                let greeting = '';
                
                if (hour < 12) {
                    greeting = 'Buenos días';
                } else if (hour < 19) {
                    greeting = 'Buenas tardes';
                } else {
                    greeting = 'Buenas noches';
                }
                
                DOM.greetingElement.textContent = greeting;
            }
            
            setupClock() {
                if (!DOM.currentTimeElement) return;
                
                const updateClock = () => {
                    const now = new Date();
                    const timeString = now.toLocaleTimeString('es-GT', { 
                        hour: '2-digit', 
                        minute: '2-digit',
                        hour12: false
                    });
                    DOM.currentTimeElement.textContent = timeString;
                };
                
                updateClock();
                setInterval(updateClock, 60000);
            }
            
            setupPatientSearch() {
                if (!DOM.searchInput) return;
                
                DOM.searchInput.addEventListener('input', () => this.searchPatients());
            }
            
            searchPatients() {
                const searchTerm = DOM.searchInput.value.toLowerCase().trim();
                let visibleCount = 0;
                
                DOM.patientRows.forEach(row => {
                    const name = row.dataset.name || '';
                    const phone = row.dataset.phone || '';
                    const email = row.dataset.email || '';
                    
                    const matches = name.includes(searchTerm) || 
                                   phone.includes(searchTerm) || 
                                   email.includes(searchTerm);
                    
                    if (matches || searchTerm === '') {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                if (DOM.patientCount) {
                    DOM.patientCount.textContent = `Mostrando ${visibleCount} de ${DOM.patientRows.length} pacientes`;
                }
            }
            
            setupModals() {
                if (DOM.newPatientButton) {
                    DOM.newPatientButton.addEventListener('click', () => this.openModal('newPatientModal'));
                }
                
                if (DOM.emptyNewPatientButton) {
                    DOM.emptyNewPatientButton.addEventListener('click', () => this.openModal('newPatientModal'));
                }
                
                document.addEventListener('click', (e) => {
                    if (e.target.classList.contains('custom-modal-overlay')) {
                        this.closeModal(e.target.id);
                    }
                });
            }
            
            setupAnimations() {
                const observerOptions = {
                    root: null,
                    rootMargin: '0px',
                    threshold: 0.1
                };
                
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('animate-in');
                            observer.unobserve(entry.target);
                        }
                    });
                }, observerOptions);
                
                document.querySelectorAll('.stat-card, .appointments-section').forEach(el => {
                    observer.observe(el);
                });
            }
            
            openModal(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
            }
            
            closeModal(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }
        }
        
        // ==========================================================================
        // INICIALIZACIÓN DE LA APLICACIÓN
        // ==========================================================================
        document.addEventListener('DOMContentLoaded', () => {
            const themeManager = new ThemeManager();
            const dynamicComponents = new DynamicComponents();
            
            window.dashboard = {
                theme: themeManager,
                components: dynamicComponents
            };
            
            console.log('Módulo de Pacientes inicializado correctamente');
            console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
            console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
            console.log('Tema: ' + themeManager.theme);
        });
        
        // ==========================================================================
        // FUNCIONES GLOBALES PARA PACIENTES
        // ==========================================================================
        
        window.quickAppointment = function(patientId, nombre, apellido) {
            document.getElementById('quickPatientId').value = patientId;
            document.getElementById('quickPatientName').value = nombre + ' ' + apellido;
            
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('quickDate').valueAsDate = tomorrow;
            document.getElementById('quickTime').value = '09:00';
            
            document.getElementById('quickAppointmentModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        };
        
        window.closeModal = function(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.body.style.overflow = '';
        };
        
        window.filterPatients = function(filterType) {
            const filterButtons = document.querySelectorAll('.filter-btn');
            filterButtons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            let visibleCount = 0;
            
            document.querySelectorAll('.patient-row').forEach(row => {
                const hasAppointments = row.dataset.hasAppointments === 'true';
                const hasHistory = row.dataset.hasHistory === 'true';
                const activeToday = row.dataset.activeToday === 'true';
                const gender = row.dataset.gender || '';
                
                let show = false;
                
                switch(filterType) {
                    case 'all':
                        show = true;
                        break;
                    case 'with_appointments':
                        show = hasAppointments;
                        break;
                    case 'without_history':
                        show = !hasHistory;
                        break;
                    case 'active_today':
                        show = activeToday;
                        break;
                    case 'male':
                        show = gender.includes('masculino');
                        break;
                    case 'female':
                        show = gender.includes('femenino');
                        break;
                    default:
                        show = true;
                }
                
                if (show) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            document.getElementById('patientCount').textContent = 
                `Mostrando ${visibleCount} de ${document.querySelectorAll('.patient-row').length} pacientes`;
        };
        
        // ==========================================================================
        // MANEJO DE ERRORES GLOBALES
        // ==========================================================================
        window.addEventListener('error', (event) => {
            console.error('Error en módulo de pacientes:', event.error);
        });
        
    })();
    
    // Manejar envío del formulario de nuevo paciente
    document.getElementById('newPatientForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Guardando...';
        submitBtn.disabled = true;
        
        setTimeout(() => {
            this.submit();
        }, 1000);
    });
    
    // Manejar envío del formulario de cita rápida
    document.getElementById('quickAppointmentForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Programando...';
        submitBtn.disabled = true;
        
        setTimeout(() => {
            alert('Cita programada exitosamente (simulación)');
            closeModal('quickAppointmentModal');
            
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            this.reset();
        }, 1500);
    });
    
    // Estilos para spinner
    const style = document.createElement('style');
    style.textContent = `
        .spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>