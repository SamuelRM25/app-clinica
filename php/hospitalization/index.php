<?php
// hospitalization/index.php - Dashboard Principal de Encamamiento - Centro Médico Herrera Saenz
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Incluir configuraciones y funciones
require_once '../../config/database.php';
require_once '../../includes/functions.php';
verify_session();

// Set timezone
date_default_timezone_set('America/Guatemala');

// Verificar permisos
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['tipoUsuario'];
$user_name = $_SESSION['nombre'];
$user_specialty = $_SESSION['especialidad'] ?? 'Personal';

// Check permissions from JSON
$has_access = false;
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $stmt = $conn->prepare("SELECT permisos_modulos FROM usuarios WHERE idUsuario = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['permisos_modulos']) {
        $permisos = json_decode($user['permisos_modulos'], true);
        $has_access = ($permisos['hospitalization'] ?? false) || $user_type === 'admin';
    } else if ($user_type === 'admin') {
        $has_access = true;
    }
    
    if (!$has_access) {
        header("Location: ../dashboard/index.php");
        exit;
    }
    
    // ====================================
    // FETCH DASHBOARD DATA
    // ====================================
    
    // Total de camas
    $stmt_total_beds = $conn->query("SELECT COUNT(*) as total FROM camas");
    $total_beds = $stmt_total_beds->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Camas ocupadas
    $stmt_occupied = $conn->query("SELECT COUNT(*) as total FROM camas WHERE estado = 'Ocupada'");
    $camas_ocupadas = $stmt_occupied->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Camas disponibles
    $camas_disponibles = $total_beds - $camas_ocupadas;
    
    // Porcentaje de ocupación
    $porcentaje_ocupacion = $total_beds > 0 ? round(($camas_ocupadas / $total_beds) * 100, 1) : 0;
    
    // Total pacientes activos (hospitalizados)
    $stmt_active = $conn->query("SELECT COUNT(*) as total FROM encamamientos WHERE estado = 'Activo'");
    $pacientes_activos = $stmt_active->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Ingresos hoy
    $stmt_today = $conn->prepare("SELECT COUNT(*) as total FROM encamamientos WHERE DATE(fecha_ingreso) = CURDATE()");
    $stmt_today->execute();
    $ingresos_hoy = $stmt_today->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Altas hoy
    $stmt_altas = $conn->prepare("SELECT COUNT(*) as total FROM encamamientos WHERE DATE(fecha_alta) = CURDATE() AND estado IN ('Alta_Medica', 'Alta_Administrativa')");
    $stmt_altas->execute();
    $altas_hoy = $stmt_altas->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Estancia promedio (últimos 30 días)
    $stmt_estancia = $conn->query("
        SELECT AVG(DATEDIFF(COALESCE(fecha_alta, NOW()), fecha_ingreso)) as promedio
        FROM encamamientos
        WHERE fecha_ingreso >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $estancia_promedio = round($stmt_estancia->fetch(PDO::FETCH_ASSOC)['promedio'] ?? 0, 1);
    
    // Lista de habitaciones con estado
    $stmt_rooms = $conn->query("
        SELECT 
            h.id_habitacion,
            h.numero_habitacion,
            h.tipo_habitacion,
            h.piso,
            h.tarifa_por_noche,
            h.capacidad_maxima,
            COUNT(c.id_cama) as total_camas,
            SUM(CASE WHEN c.estado = 'Ocupada' THEN 1 ELSE 0 END) as camas_ocupadas,
            h.estado as estado_habitacion
        FROM habitaciones h
        LEFT JOIN camas c ON h.id_habitacion = c.id_habitacion
        GROUP BY h.id_habitacion
        ORDER BY h.piso, h.numero_habitacion
    ");
    $habitaciones = $stmt_rooms->fetchAll(PDO::FETCH_ASSOC);
    
    // Pacientes actualmente hospitalizados
    $stmt_patients = $conn->query("
        SELECT 
            e.id_encamamiento,
            e.id_paciente,
            e.fecha_ingreso,
            e.diagnostico_ingreso,
            e.tipo_ingreso,
            pac.nombre as nombre_paciente,
            pac.apellido as apellido_paciente,
            pac.fecha_nacimiento,
            pac.genero,
            hab.numero_habitacion,
            hab.tipo_habitacion,
            c.numero_cama,
            u.nombre as nombre_doctor,
            u.apellido as apellido_doctor,
            DATEDIFF(CURDATE(), DATE(e.fecha_ingreso)) as dias_hospitalizado,
            (SELECT COUNT(*) FROM signos_vitales WHERE id_encamamiento = e.id_encamamiento AND DATE(fecha_registro) = CURDATE()) as signos_hoy
        FROM encamamientos e
        INNER JOIN pacientes pac ON e.id_paciente = pac.id_paciente
        INNER JOIN camas c ON e.id_cama = c.id_cama
        INNER JOIN habitaciones hab ON c.id_habitacion = hab.id_habitacion
        LEFT JOIN usuarios u ON e.id_doctor = u.idUsuario
        WHERE e.estado = 'Activo'
        ORDER BY e.fecha_ingreso DESC
    ");
    $pacientes_hospitalizados = $stmt_patients->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$page_title = "Gestión de Hospitalización - Centro Médico Herrera Saenz";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
    /* Variables CSS para modo claro y oscuro */
    :root {
        --color-background: #f8fafc;
        --color-surface: #ffffff;
        --color-primary: #7c90db;
        --color-primary-light: #a3b1e8;
        --color-primary-dark: #5a6fca;
        --color-secondary: #8dd7bf;
        --color-accent: #f8b195;
        --color-text: #1e293b;
        --color-text-light: #64748b;
        --color-text-muted: #94a3b8;
        --color-border: #e2e8f0;
        --color-border-light: #f1f5f9;
        --color-error: #f87171;
        --color-warning: #fbbf24;
        --color-success: #34d399;
        --color-info: #38bdf8;
        
        --marble-bg: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        
        --radius-sm: 8px;
        --radius-md: 12px;
        --radius-lg: 16px;
        --radius-xl: 20px;
    }
    
    [data-theme="dark"] {
        --color-background: #0f172a;
        --color-surface: #1e293b;
        --color-text: #f1f5f9;
        --color-text-light: #cbd5e1;
        --color-text-muted: #94a3b8;
        --color-border: #334155;
        --color-border-light: #1e293b;
        --marble-bg: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: var(--color-background);
        color: var(--color-text);
        min-height: 100vh;
        line-height: 1.6;
        position: relative;
    }
    
    /* Efecto mármol animado */
    .marble-effect {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        pointer-events: none;
        z-index: -1;
        opacity: 0.4;
        background-image: 
            radial-gradient(circle at 20% 30%, rgba(124, 144, 219, 0.08) 0%, transparent 30%),
            radial-gradient(circle at 80% 70%, rgba(141, 215, 191, 0.08) 0%, transparent 30%),
            radial-gradient(circle at 40% 80%, rgba(248, 177, 149, 0.08) 0%, transparent 30%);
        animation: marbleFloat 20s ease-in-out infinite;
    }
    
    @keyframes marbleFloat {
        0%, 100% { transform: translate(0, 0) rotate(0deg); }
        25% { transform: translate(10px, 5px) rotate(0.5deg); }
        50% { transform: translate(5px, 10px) rotate(-0.5deg); }
        75% { transform: translate(-5px, 5px) rotate(0.3deg); }
    }
    
    /* Dashboard Container */
    .dashboard-container {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }
    
    /* Header */
    .dashboard-header {
        background: var(--color-surface);
        border-bottom: 1px solid var(--color-border);
        padding: 1rem 2rem;
        box-shadow: var(--shadow-sm);
        position: sticky;
        top: 0;
        z-index: 100;
    }
    
    .header-content {
        max-width: 1400px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 2rem;
    }
    
    .brand-logo {
        height: 45px;
        width: auto;
    }
    
    .header-controls {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    
    .theme-toggle {
        position: relative;
    }
    
    .theme-btn {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-md);
        border: 1px solid var(--color-border);
        background: var(--color-surface);
        color: var(--color-text);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .theme-btn:hover {
        background: var(--color-border-light);
        transform: translateY(-2px);
    }
    
    .theme-icon {
        font-size: 1.2rem;
        transition: all 0.3s ease;
        position: absolute;
    }
    
    .sun-icon { opacity: 1; }
    .moon-icon { opacity: 0; }
    [data-theme="dark"] .sun-icon { opacity: 0; }
    [data-theme="dark"] .moon-icon { opacity: 1; }
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: var(--color-primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 1.1rem;
    }
    
    .user-details {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .user-name {
        font-weight: 600;
        font-size: 0.95rem;
        color: var(--color-text);
    }
    
    .user-role {
        font-size: 0.8rem;
        color: var(--color-text-light);
    }
    
    /* Main Content */
    .main-content {
        flex: 1;
        padding: 2rem;
        max-width: 1400px;
        margin: 0 auto;
        width: 100%;
    }
    
    .page-header {
        background: var(--color-surface);
        border-radius: var(--radius-xl);
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--color-border);
    }
    
    .page-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--color-text);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .page-subtitle {
        color: var(--color-text-light);
        font-size: 0.95rem;
        margin: 0;
    }
    
    .page-actions {
        display: flex;
        gap: 1rem;
        margin-top: 1rem;
    }
    
    .action-btn {
        background: var(--color-primary);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: var(--radius-md);
        font-weight: 500;
        font-size: 0.95rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: var(--shadow-md);
    }
    
    .action-btn:hover {
        background: var(--color-primary-dark);
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .action-btn.secondary {
        background: var(--color-border);
        color: var(--color-text);
    }
    
    .action-btn.secondary:hover {
        background: var(--color-border-light);
    }
    
    /* Statistics Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: var(--color-surface);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--color-border);
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-xl);
    }
    
    .stat-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
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
    
    .stat-icon.primary { background: rgba(124, 144, 219, 0.15); color: var(--color-primary); }
    .stat-icon.success { background: rgba(52, 211, 153, 0.15); color: var(--color-success); }
    .stat-icon.warning { background: rgba(251, 191, 36, 0.15); color: var(--color-warning); }
    .stat-icon.info { background: rgba(56, 189, 248, 0.15); color: var(--color-info); }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--color-text);
        margin-bottom: 0.25rem;
    }
    
    .stat-label {
        color: var(--color-text-light);
        font-size: 0.9rem;
        font-weight: 500;
    }
    
    .stat-footer {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px solid var(--color-border);
        font-size: 0.85rem;
        color: var(--color-text-muted);
    }
    
    /* Bed Map Grid */
    .bed-map-container {
        background: var(--color-surface);
        border-radius: var(--radius-xl);
        padding: 2rem;
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--color-border);
        margin-bottom: 2rem;
    }
    
    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--color-text);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .rooms-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }
    
    .room-card {
        background: var(--color-background);
        border: 2px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.25rem;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .room-card:hover {
        border-color: var(--color-primary);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .room-card.occupied {
        border-color: var(--color-warning);
        background: rgba(251, 191, 36, 0.05);
    }
    
    .room-card.full {
        border-color: var(--color-error);
        background: rgba(248, 113, 113, 0.05);
    }
    
    .room-card.available {
        border-color: var(--color-success);
        background: rgba(52, 211, 153, 0.05);
    }
    
    .room-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .room-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--color-text);
    }
    
    .room-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .room-badge.disponible { background: var(--color-success); color: white; }
    .room-badge.ocupada { background: var(--color-warning); color: white; }
    .room-badge.llena { background: var(--color-error); color: white; }
    
    .room-type {
        font-size: 0.9rem;
        color: var(--color-text-light);
        margin-bottom: 0.5rem;
    }
    
    .room-beds {
        display: flex;
        gap: 0.5rem;
        margin-top: 1rem;
    }
    
    .bed-indicator {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.85rem;
        border: 2px solid;
    }
    
    .bed-indicator.disponible {
        background: rgba(52, 211, 153, 0.1);
        border-color: var(--color-success);
        color: var(--color-success);
    }
    
    .bed-indicator.ocupada {
        background: rgba(248, 113, 113, 0.1);
        border-color: var(--color-error);
        color: var(--color-error);
    }
    
    /* Patients Table */
    .patients-container {
        background: var(--color-surface);
        border-radius: var(--radius-xl);
        padding: 2rem;
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--color-border);
    }
    
    .table-container {
        overflow-x: auto;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table thead {
        background: var(--color-border-light);
        border-bottom: 2px solid var(--color-border);
    }
    
    .data-table th {
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--color-text-light);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .data-table td {
        padding: 1rem;
        border-bottom: 1px solid var(--color-border);
        color: var(--color-text);
    }
    
    .data-table tbody tr {
        transition: background 0.2s ease;
    }
    
    .data-table tbody tr:hover {
        background: var(--color-border-light);
    }
    
    .patient-name {
        font-weight: 600;
        color: var(--color-primary);
        cursor: pointer;
    }
    
    .patient-name:hover {
        text-decoration: underline;
    }
    
    .badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .badge-urgente { background: var(--color-error); color: white; }
    .badge-programado { background: var(--color-info); color: white; }
    .badge-referido { background: var(--color-warning); color: white; }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 3rem 1.5rem;
        color: var(--color-text-light);
    }
    
    .empty-icon {
        font-size: 4rem;
        color: var(--color-border);
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
        
        .rooms-grid {
            grid-template-columns: 1fr;
        }
        
        .main-content {
            padding: 1rem;
        }
        
        .page-header {
            padding: 1rem;
        }
    }
    </style>
</head>
<body>
    <!-- Efecto mármol -->
    <div class="marble-effect"></div>
    
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-content">
                <div class="brand-container">
                    <img src="../../assets/img/herrerasaenz.png" alt="Centro Médico Herrera Saenz" class="brand-logo">
                </div>
                
                <div class="header-controls">
                    <!-- Theme Toggle -->
                    <div class="theme-toggle">
                        <button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema">
                            <i class="bi bi-sun theme-icon sun-icon"></i>
                            <i class="bi bi-moon theme-icon moon-icon"></i>
                        </button>
                    </div>
                    
                    <!-- User Info -->
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="user-role"><?php echo htmlspecialchars($user_specialty); ?></span>
                        </div>
                    </div>
                    
                    <!-- Back Button -->
                    <a href="../dashboard/index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i>
                        Dashboard
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">
                    <i class="bi bi-hospital text-primary"></i>
                    Gestión de Hospitalización
                </h1>
                <p class="page-subtitle">Control de camas, pacientes hospitalizados y seguimiento médico</p>
                
                <div class="page-actions">
                    <button class="action-btn" onclick="window.location.href='ingresar_paciente.php'">
                        <i class="bi bi-person-plus-fill"></i>
                        Ingresar Paciente
                    </button>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $pacientes_activos; ?></div>
                            <div class="stat-label">Pacientes Hospitalizados</div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <i class="bi bi-arrow-up"></i>
                        <?php echo $ingresos_hoy; ?> ingresos hoy
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $camas_disponibles; ?> / <?php echo $total_beds; ?></div>
                            <div class="stat-label">Camas Disponibles</div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-hospital"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <i class="bi bi-circle-fill" style="font-size: 0.5rem; color: var(--color-success);"></i>
                        <?php echo $porcentaje_ocupacion; ?>% ocupación
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $altas_hoy; ?></div>
                            <div class="stat-label">Altas Hoy</div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-door-open"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <i class="bi bi-calendar-check"></i>
                        Total del día
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?php echo $estancia_promedio; ?></div>
                            <div class="stat-label">Días Promedio</div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-clock-history"></i>
                        </div>
                    </div>
                    <div class="stat-footer">
                        <i class="bi bi-info-circle"></i>
                        Estancia promedio
                    </div>
                </div>
            </div>

            <!-- Active Patients Table -->
            <div class="patients-container">
                <h2 class="section-title">
                    <i class="bi bi-person-lines-fill"></i>
                    Pacientes Actualmente Hospitalizados
                </h2>
                
                <?php if (count($pacientes_hospitalizados) > 0): ?>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Paciente</th>
                                <th>Habitación / Cama</th>
                                <th>Diagnóstico</th>
                                <th>Médico</th>
                                <th>Ingreso</th>
                                <th>Días</th>
                                <th>Signos Hoy</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pacientes_hospitalizados as $pac): ?>
                                <tr>
                                    <td>
                                        <div class="patient-name" onclick="viewPatientDetails(<?php echo $pac['id_encamamiento']; ?>)">
                                            <?php echo htmlspecialchars($pac['nombre_paciente'] . ' ' . $pac['apellido_paciente']); ?>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($pac['genero']); ?> - 
                                            <?php 
                                                $edad = date_diff(date_create($pac['fecha_nacimiento']), date_create('today'))->y;
                                                echo $edad . ' años';
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($pac['numero_habitacion'] . ' - ' . $pac['numero_cama']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($pac['tipo_habitacion']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($pac['diagnostico_ingreso']); ?></td>
                                    <td>
                                        Dr(a). <?php echo htmlspecialchars($pac['nombre_doctor'] . ' ' . $pac['apellido_doctor']); ?>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($pac['fecha_ingreso'])); ?><br>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($pac['fecha_ingreso'])); ?></small>
                                    </td>
                                    <td><strong><?php echo $pac['dias_hospitalizado']; ?></strong> días</td>
                                    <td>
                                        <?php if ($pac['signos_hoy'] > 0): ?>
                                            <span class="badge badge-programado"><?php echo $pac['signos_hoy']; ?> registros</span>
                                        <?php else: ?>
                                            <span class="badge badge-urgente">Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" onclick="viewPatientDetails(<?php echo $pac['id_encamamiento']; ?>)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="bi bi-hospital"></i>
                    </div>
                    <h3>No hay pacientes hospitalizados actualmente</h3>
                    <p>Los pacientes ingresados aparecerán aquí</p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Bed Map -->
            <div class="bed-map-container">
                <h2 class="section-title">
                    <i class="bi bi-grid-3x3"></i>
                    Mapa de Habitaciones y Camas
                </h2>
                
                <div class="rooms-grid">
                    <?php foreach ($habitaciones as $hab): ?>
                        <?php
                            $estado_class = 'available';
                            $badge_text = 'Disponible';
                            $badge_class = 'disponible';
                            
                            if ($hab['camas_ocupadas'] > 0) {
                                if ($hab['camas_ocupadas'] >= $hab['total_camas']) {
                                    $estado_class = 'full';
                                    $badge_text = 'Llena';
                                    $badge_class = 'llena';
                                } else {
                                    $estado_class = 'occupied';
                                    $badge_text = 'Ocupada';
                                    $badge_class = 'ocupada';
                                }
                            }
                        ?>
                        <div class="room-card <?php echo $estado_class; ?>" onclick="viewRoomDetails(<?php echo $hab['id_habitacion']; ?>)">
                            <div class="room-header">
                                <span class="room-number"><?php echo htmlspecialchars($hab['numero_habitacion']); ?></span>
                                <span class="room-badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                            </div>
                            <div class="room-type">
                                <?php echo htmlspecialchars($hab['tipo_habitacion']); ?> - Piso <?php echo htmlspecialchars($hab['piso']); ?>
                            </div>
                            <div class="room-type">
                                Q<?php echo number_format($hab['tarifa_por_noche'], 2); ?> / noche
                            </div>
                            <div class="room-beds">
                                <?php
                                    $stmt_beds = $conn->prepare("SELECT numero_cama, estado FROM camas WHERE id_habitacion = ? ORDER BY numero_cama");
                                    $stmt_beds->execute([$hab['id_habitacion']]);
                                    $beds = $stmt_beds->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($beds as $bed):
                                        $bed_estado = strtolower($bed['estado']);
                                ?>
                                    <div class="bed-indicator <?php echo $bed_estado; ?>" title="Cama <?php echo $bed['numero_cama']; ?> - <?php echo $bed['estado']; ?>">
                                        <?php echo htmlspecialchars($bed['numero_cama']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Theme management
    document.addEventListener('DOMContentLoaded', function() {
        const themeSwitch = document.getElementById('themeSwitch');
        
        // Initialize theme
        function initializeTheme() {
            const savedTheme = localStorage.getItem('dashboard-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        }
        
        // Toggle theme
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('dashboard-theme', newTheme);
            
            themeSwitch.style.transform = 'rotate(180deg)';
            setTimeout(() => { themeSwitch.style.transform = 'rotate(0)'; }, 300);
        }
        
        initializeTheme();
        themeSwitch.addEventListener('click', toggleTheme);
    });
    
    // Navigation functions
    function viewRoomDetails(roomId) {
        console.log('View room details:', roomId);
        // TODO: Implement modal or navigation to room details
    }
    
    function viewPatientDetails(encamamentoId) {
        window.location.href = 'detalle_encamamiento.php?id=' + encamamentoId;
    }
    
    console.log('Hospitalización Dashboard - CMHS v3.0');
    </script>
</body>
</html>
