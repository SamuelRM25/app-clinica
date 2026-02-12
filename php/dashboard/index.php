<?php
// dashboard.php - Dashboard Centro Médico RS
// Diseño Responsive, Barra Lateral Moderna, Efecto Mármol
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

    // Configurar filtros según tipo de usuario
    $is_doctor = $user_type === 'doc';
    $doctor_filter = $is_doctor ? " AND id_doctor = ?" : "";
    $today = date('Y-m-d');

    // ============ CONSULTAS ESTADÍSTICAS ============

    // Obtener Pacientes (para Cobros y Laboratorio)
    $stmt = $conn->prepare("SELECT id_paciente, CONCAT(nombre, ' ', apellido) as nombre_completo FROM pacientes ORDER BY nombre");
    $stmt->execute();
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener Doctores (para Cobros y Laboratorio)
    $stmtDoc = $conn->prepare("SELECT idUsuario, nombre, apellido FROM usuarios WHERE tipoUsuario = 'doc' ORDER BY nombre");
    $stmtDoc->execute();
    $doctores = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);

    // Obtener Catálogo de Pruebas (para Laboratorio)
    $stmtCat = $conn->query("SELECT id_prueba, codigo_prueba, nombre_prueba, categoria, precio FROM catalogo_pruebas ORDER BY categoria, nombre_prueba");
    $catalogo = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

    $pruebas_por_categoria = [];
    foreach ($catalogo as $prueba) {
        $pruebas_por_categoria[$prueba['categoria'] ?? 'Sin Categoría'][] = $prueba;
    }

    // 1. Citas de hoy
    $params = $is_doctor ? [$today, $user_id] : [$today];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE fecha_cita = ?" . $doctor_filter);
    $stmt->execute($params);
    $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 2. Pacientes del año actual
    $current_year = date('Y');
    $year_start = $current_year . '-01-01';
    $year_end = $current_year . '-12-31';
    $year_params = $is_doctor ? [$year_start, $year_end, $user_id] : [$year_start, $year_end];

    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT CONCAT(nombre_pac, ' ', apellido_pac)) as count 
        FROM citas 
        WHERE fecha_cita BETWEEN ? AND ?" . $doctor_filter
    );
    $stmt->execute($year_params);
    $year_patients = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 3. Citas pendientes (futuras)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE fecha_cita > ?" . $doctor_filter);
    $stmt->execute($params);
    $pending_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 4. Consultas del mes actual
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $month_params = $is_doctor ? [$month_start, $month_end, $user_id] : [$month_start, $month_end];

    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM citas 
        WHERE fecha_cita BETWEEN ? AND ?" . $doctor_filter
    );
    $stmt->execute($month_params);
    $month_consultations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 5. Citas de hoy con detalles
    $stmt = $conn->prepare("
        SELECT id_cita, nombre_pac, apellido_pac, hora_cita, telefono 
        FROM citas 
        WHERE fecha_cita = ?" . $doctor_filter . "
        ORDER BY hora_cita
    ");
    $stmt->execute($params);
    $todays_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Total de citas en el sistema
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas");
    $stmt->execute();
    $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // ============ INVENTARIO ============

    // 7. Medicamentos en inventario
    $stmt = $conn->prepare("SELECT SUM(cantidad_med) as total FROM inventario WHERE cantidad_med > 0");
    $stmt->execute();
    $total_medications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 8. Medicamentos próximos a caducar (30 días)
    $next_month = date('Y-m-d', strtotime('+30 days'));
    $stmt = $conn->prepare("
        SELECT id_inventario, nom_medicamento, fecha_vencimiento, cantidad_med 
        FROM inventario 
        WHERE fecha_vencimiento BETWEEN ? AND ? AND cantidad_med > 0
        ORDER BY fecha_vencimiento ASC
    ");
    $stmt->execute([$today, $next_month]);
    $expiring_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 9. Medicamentos con stock bajo (< 5 unidades)
    $stmt = $conn->prepare("
        SELECT id_inventario, nom_medicamento, cantidad_med 
        FROM inventario 
        WHERE cantidad_med > 0 AND cantidad_med < 5
        ORDER BY cantidad_med ASC
    ");
    $stmt->execute();
    $low_stock_medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 10. Compras pendientes
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventario WHERE estado = 'Pendiente'");
    $stmt->execute();
    $pending_purchases = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // ============ HOSPITALIZACIÓN ============

    // 11. Hospitalizaciones Activas
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM encamamientos WHERE estado = 'Activo'");
    $stmt->execute();
    $active_hospitalizations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // 12. Camas Disponibles
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM camas");
    $stmt->execute();
    $total_beds = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $available_beds_count = $total_beds - $active_hospitalizations;

    // 13. Últimos ingresos hospitalarios
    $stmt = $conn->prepare("
        SELECT e.id_encamamiento, p.nombre, p.apellido, h.numero_habitacion, e.fecha_ingreso, e.diagnostico_ingreso
        FROM encamamientos e
        JOIN pacientes p ON e.id_paciente = p.id_paciente
        JOIN camas c ON e.id_cama = c.id_cama
        JOIN habitaciones hab ON c.id_habitacion = hab.id_habitacion
        JOIN habitaciones h ON c.id_habitacion = h.id_habitacion 
        WHERE e.estado = 'Activo'
        ORDER BY e.fecha_ingreso DESC
        LIMIT 5
    ");
    $stmt->execute();
    $hospitalized_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Título de la página
    $page_title = "Dashboard - Centro Médico RS";

} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en dashboard: " . $e->getMessage());
    die("Error al cargar el dashboard. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dashboard del Centro Médico RS - Sistema de gestión médica">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Bootstrap CSS y JS Bundle -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- CSS Crítico (incrustado para máxima velocidad) -->
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
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);

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

            /* Ancho Sidebar */
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 100px;
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
            0% {
                background-position: 0% 0%;
            }

            100% {
                background-position: 100% 100%;
            }
        }

        /* ==========================================================================
       LAYOUT PRINCIPAL
       ========================================================================== */
        .dashboard-container {
            display: flex;
            flex-direction: column;
            /* Apilar Header y Main verticalmente */
            min-height: 100vh;
            position: relative;
            margin-left: var(--sidebar-width);
            /* Mover todo el contenido a la derecha del sidebar */
            transition: margin-left var(--transition-base);
            width: calc(100% - var(--sidebar-width));
            /* Asegurar que no se desborde */
        }

        /* ==========================================================================
       BARRA LATERAL MODERNA
       ========================================================================== */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background-color: var(--color-card);
            border-right: 1px solid var(--color-border);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: all var(--transition-base);
            transform: translateX(0);
            box-shadow: var(--shadow-lg);
        }

        /* Estado colapsado */
        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
            transform: translateX(0);
        }

        .sidebar.collapsed .sidebar-header h2,
        .sidebar.collapsed .nav-link span:not(.badge),
        .sidebar.collapsed .user-info .user-details {
            opacity: 0;
            width: 0;
            height: 0;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }

        /* Overlay para móvil */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity var(--transition-base);
        }

        /* Header sidebar */
        .sidebar-header {
            padding: var(--space-lg);
            border-bottom: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

        .sidebar-logo {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            overflow: hidden;
            flex-shrink: 0;
        }

        .sidebar-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .sidebar-header h2 {
            font-size: var(--font-size-xl);
            font-weight: 700;
            color: var(--color-primary);
            margin: 0;
            white-space: nowrap;
            transition: opacity var(--transition-base);
        }

        /* Navegación */
        .sidebar-nav {
            flex: 1;
            padding: var(--space-md);
            overflow-y: auto;
        }

        .nav-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
        }

        .nav-item {
            position: relative;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-md);
            color: var(--color-text-secondary);
            text-decoration: none;
            border-radius: var(--radius-md);
            transition: all var(--transition-base);
            position: relative;
            overflow: hidden;
        }

        .nav-link:hover {
            background-color: var(--color-surface);
            color: var(--color-text);
            transform: translateX(4px);
        }

        .nav-link.active {
            background-color: var(--color-primary);
            color: white;
            box-shadow: var(--shadow-md);
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 60%;
            background-color: currentColor;
            border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
        }

        .nav-icon {
            font-size: 1.25rem;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .nav-text {
            font-weight: 500;
            white-space: nowrap;
            transition: opacity var(--transition-base);
        }

        .badge {
            margin-left: auto;
            font-size: var(--font-size-xs);
            padding: 0.25em 0.5em;
            min-width: 1.5rem;
            height: 1.5rem;
        }

        /* Footer sidebar */
        .sidebar-footer {
            padding: var(--space-md);
            border-top: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: var(--space-md);
        }

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
            /* Usar fallback sólido si rgba falla, pero definir rgb variables arriba */
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
            background: none;
            border: none;
            color: var(--color-text);
            font-size: 1.5rem;
            cursor: pointer;
            padding: var(--space-xs);
            border-radius: var(--radius-sm);
        }

        .mobile-toggle:hover {
            background-color: var(--color-surface);
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
            /* Margin-left movido al contenedor padre */
            transition: all var(--transition-base);
            min-height: 100vh;
            background-color: transparent;
            width: 100%;
        }

        .sidebar.collapsed~.dashboard-container {
            margin-left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }

        /* Botón toggle sidebar (escritorio) */
        .sidebar-toggle {
            position: fixed;
            /* Ajustado para estar dentro del container que tiene margen */
            left: -12px;
            /* Relativo al dashboard-container */
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 48px;
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-left: none;
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
            color: var(--color-text);
            cursor: pointer;
            z-index: 1001;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-base);
            box-shadow: var(--shadow-md);
        }

        .sidebar-toggle {
            left: calc(var(--sidebar-width) - 12px);
        }

        .sidebar.collapsed+.dashboard-container .sidebar-toggle {
            left: calc(var(--sidebar-collapsed-width) - 12px);
        }

        .sidebar.collapsed .sidebar-toggle i {
            transform: rotate(180deg);
        }
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
            /* Margin-left movido al contenedor padre */
            transition: all var(--transition-base);
            min-height: 100vh;
            background-color: transparent;
            width: 100%;
        }

        .sidebar.collapsed~.dashboard-container {
            margin-left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }

        /* Botón toggle sidebar (escritorio) */
        .sidebar-toggle {
            position: fixed;
            /* Ajustado para estar dentro del container que tiene margen */
            left: -12px;
            /* Relativo al dashboard-container */
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 48px;
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-left: none;
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
            color: var(--color-text);
            cursor: pointer;
            z-index: 1001;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--transition-base);
            box-shadow: var(--shadow-md);
        }

        .sidebar-toggle:hover {
            background: var(--color-primary);
            color: white;
            transform: translateY(-50%) scale(1.1);
        }

        /* Cuando el sidebar está colapsado, el dashboard-container reduce su margen,
       y el botón se mueve con él porque está dentro y es relative o fixed dentro?
       Espera, el botón tiene position: fixed. Fixed es relativo al VIEWPORT.
       Entonces `left: -12px` NO SIRVE si es relativo al viewport.
       Debe ser relativo al container O calculado desde la izquierda.
    */
        .sidebar-toggle {
            left: calc(var(--sidebar-width) - 12px);
        }

        .sidebar.collapsed+.dashboard-container .sidebar-toggle {
            left: calc(var(--sidebar-collapsed-width) - 12px);
        }

        .sidebar.collapsed .sidebar-toggle i {
            transform: rotate(180deg);
        }

        /* ==========================================================================
       COMPONENTES DE DASHBOARD
       ========================================================================== */

        /* Tarjetas de estadísticas */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            opacity: 0;
            transition: opacity var(--transition-base);
            pointer-events: none;
        }

        .stat-card:hover::after {
            opacity: 1;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-md);
        }

        .stat-label {
            color: var(--color-text-light);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--color-text);
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            position: relative;
            transition: all var(--transition-base);
        }


        .stat-icon.primary {
            background: rgba(124, 144, 219, 0.15);
            color: var(--color-primary);
        }

        .stat-icon.success {
            background: rgba(52, 211, 153, 0.15);
            color: var(--color-success);
        }

        .stat-icon.warning {
            background: rgba(251, 191, 36, 0.15);
            color: var(--color-warning);
        }

        .stat-icon.info {
            background: rgba(56, 189, 248, 0.15);
            color: var(--color-info);
        }

        .stat-card:hover .stat-icon {
            transform: scale(1.05) rotate(3deg);
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

        /* Secciones del dashboard */
        .appointments-section,
        .billing-section {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: var(--space-lg);
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
        }

        .appointments-section:hover,
        .billing-section:hover {
            box-shadow: var(--shadow-xl);
            transform: translateY(-2px);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--color-border);
            padding-bottom: var(--space-sm);
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--color-text);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title-icon {
            color: var(--color-primary);
            font-size: 1.5rem;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            padding: 0.75rem 1.5rem;
            background: var(--color-primary);
            color: white;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 500;
            font-size: 0.95rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            background: var(--color-primary-dark);
            color: white;
        }

        /* Tablas */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .appointments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .appointments-table thead {
            background: var(--color-border-light);
            border-bottom: 2px solid var(--color-border);
        }

        .appointments-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--color-text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .appointments-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--color-border);
            color: var(--color-text);
        }

        .appointments-table tbody tr {
            transition: background 0.2s ease;
        }

        .appointments-table tbody tr:hover {
            background: var(--color-border-light);
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
            background: var(--color-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
        }

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

        .time-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            padding: var(--space-xs) var(--space-sm);
            background: var(--color-surface);
            color: var(--color-text);
            border-radius: var(--radius-sm);
            font-size: var(--font-size-sm);
            font-weight: 500;
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

        .btn-icon.edit:hover {
            background: var(--color-warning);
            color: white;
            border-color: var(--color-warning);
        }

        .btn-icon.history:hover {
            background: var(--color-info);
            color: white;
            border-color: var(--color-info);
        }

        /* Estado vacío */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--color-text-light);
            border: 2px dashed var(--color-border);
            border-radius: var(--radius-lg);
            margin: var(--space-lg) 0;
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--color-border);
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        margin-bottom: var(--space-md);
        opacity: 0.3;
        }

        /* Grid de alertas */
        .alerts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        .alert-card {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            transition: all var(--transition-base);
        }

        .alert-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .alert-header {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
        }

        .alert-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .alert-icon.warning {
            background: rgba(var(--color-warning-rgb), 0.1);
            color: var(--color-warning);
        }

        .alert-icon.danger {
            background: rgba(var(--color-danger-rgb), 0.1);
            color: var(--color-danger);
        }

        .alert-title {
            font-size: var(--font-size-lg);
            font-weight: 600;
            color: var(--color-text);
            margin: 0;
        }

        .alert-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: var(--space-md);
        }

        .alert-item {
            padding: var(--space-md);
            background: var(--color-surface);
            border-radius: var(--radius-md);
            border-left: 4px solid var(--color-border);
            transition: all var(--transition-base);
        }

        .alert-item:hover {
            transform: translateX(4px);
            border-left-color: var(--color-warning);
        }

        .alert-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-xs);
        }

        .alert-item-name {
            font-weight: 500;
            color: var(--color-text);
        }

        .alert-badge {
            padding: 0.25em 0.5em;
            border-radius: var(--radius-sm);
            font-size: var(--font-size-xs);
            font-weight: 600;
        }

        .alert-badge.warning {
            background: rgba(var(--color-warning-rgb), 0.1);
            color: var(--color-warning);
        }

        .alert-badge.danger {
            background: rgba(var(--color-danger-rgb), 0.1);
            color: var(--color-danger);
        }

        .alert-badge.expired {
            background: rgba(var(--color-danger-rgb), 0.1);
            color: var(--color-danger);
        }

        .alert-item-details {
            display: flex;
            justify-content: space-between;
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
        }

        .no-alerts {
            text-align: center;
            padding: var(--space-lg);
            color: var(--color-text-secondary);
        }

        .no-alerts-icon {
            font-size: 2rem;
            color: var(--color-success);
            margin-bottom: var(--space-md);
            opacity: 0.5;
        }

        /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */

        /* Pantallas grandes (TV, monitores 4K) */
        @media (min-width: 1600px) {
            :root {
                --sidebar-width: 320px;
                --sidebar-collapsed-width: 100px;
            }

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

        /* Tablets y pantallas medianas */
        @media (max-width: 991px) {
            :root {
                --sidebar-width: 280px;
            }

            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .dashboard-container {
                margin-left: 0;
                width: 100%;
            }

            .sidebar-overlay.show {
                display: block;
                opacity: 1;
            }

            .main-content {
                margin-left: 0;
                padding: var(--space-md);
            }

            .sidebar-toggle {
                display: none;
            }

            .mobile-toggle {
                display: block;
            }

            .header-content {
                padding: var(--space-md);
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--space-md);
            }

            .alerts-grid {
                grid-template-columns: 1fr;
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
        }

        /* Móviles */
        @media (max-width: 767px) {
            :root {
                --sidebar-width: 100%;
            }

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
        }

        /* Móviles pequeños */
        @media (max-width: 480px) {
            .main-content {
                padding: var(--space-sm);
            }

            .stat-card {
                padding: var(--space-md);
            }

            .alert-card,
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

        .delay-1 {
            animation-delay: 0.1s;
        }

        .delay-2 {
            animation-delay: 0.2s;
        }

        .delay-3 {
            animation-delay: 0.3s;
        }

        .delay-4 {
            animation-delay: 0.4s;
        }

        /* ==========================================================================
       ESTADOS DE CARGA
       ========================================================================== */
        .loading {
            position: relative;
            overflow: hidden;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% {
                left: -100%;
            }

            100% {
                left: 100%;
            }
        }

        /* ==========================================================================
       UTILIDADES
       ========================================================================== */
        .text-primary {
            color: var(--color-primary);
        }

        .text-success {
            color: var(--color-success);
        }

        .text-warning {
            color: var(--color-warning);
        }

        .text-danger {
            color: var(--color-danger);
        }

        .text-info {
            color: var(--color-info);
        }

        .text-muted {
            color: var(--color-text-secondary);
        }

        .bg-primary {
            background-color: var(--color-primary);
        }

        .bg-success {
            background-color: var(--color-success);
        }

        .bg-warning {
            background-color: var(--color-warning);
        }

        .bg-danger {
            background-color: var(--color-danger);
        }

        .bg-info {
            background-color: var(--color-info);
        }

        .mb-0 {
            margin-bottom: 0;
        }

        .mb-1 {
            margin-bottom: var(--space-xs);
        }

        .mb-2 {
            margin-bottom: var(--space-sm);
        }

        .mb-3 {
            margin-bottom: var(--space-md);
        }

        .mb-4 {
            margin-bottom: var(--space-lg);
        }

        .mb-5 {
            margin-bottom: var(--space-xl);
        }

        .mt-0 {
            margin-top: 0;
        }

        .mt-1 {
            margin-top: var(--space-xs);
        }

        .mt-2 {
            margin-top: var(--space-sm);
        }

        .mt-3 {
            margin-top: var(--space-md);
        }

        .mt-4 {
            margin-top: var(--space-lg);
        }

        .mt-5 {
            margin-top: var(--space-xl);
        }

        .d-none {
            display: none;
        }

        .d-block {
            display: block;
        }

        .d-flex {
            display: flex;
        }

        .gap-1 {
            gap: var(--space-xs);
        }

        .gap-2 {
            gap: var(--space-sm);
        }

        .gap-3 {
            gap: var(--space-md);
        }

        .gap-4 {
            gap: var(--space-lg);
        }

        .gap-5 {
            gap: var(--space-xl);
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .fw-bold {
            font-weight: 700;
        }

        .fw-semibold {
            font-weight: 600;
        }

        .fw-medium {
            font-weight: 500;
        }

        .fw-normal {
            font-weight: 400;
        }

        .fw-light {
            font-weight: 300;
        }

        /* ==========================================================================
       PRINT STYLES
       ========================================================================== */
        @media print {

            .sidebar,
            .dashboard-header,
            .sidebar-toggle,
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
            .appointments-section,
            .alert-card {
                break-inside: avoid;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
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

        .delay-1 {
            animation-delay: 0.1s;
        }

        .delay-2 {
            animation-delay: 0.2s;
        }

        .delay-3 {
            animation-delay: 0.3s;
        }

        .delay-4 {
            animation-delay: 0.4s;
        }

        /* ==========================================================================
       ESTADOS DE CARGA
       ========================================================================== */
        .loading {
            position: relative;
            overflow: hidden;
        }

        .loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% {
                left: -100%;
            }

            100% {
                left: 100%;
            }
        }

        /* ==========================================================================
       UTILIDADES
       ========================================================================== */
        .text-primary {
            color: var(--color-primary);
        }

        .text-success {
            color: var(--color-success);
        }

        .text-warning {
            color: var(--color-warning);
        }

        .text-danger {
            color: var(--color-danger);
        }

        .text-info {
            color: var(--color-info);
        }

        .text-muted {
            color: var(--color-text-secondary);
        }

        .bg-primary {
            background-color: var(--color-primary);
        }

        .bg-success {
            background-color: var(--color-success);
        }

        .bg-warning {
            background-color: var(--color-warning);
        }

        .bg-danger {
            background-color: var(--color-danger);
        }

        .bg-info {
            background-color: var(--color-info);
        }

        .mb-0 {
            margin-bottom: 0;
        }

        .mb-1 {
            margin-bottom: var(--space-xs);
        }

        .mb-2 {
            margin-bottom: var(--space-sm);
        }

        .mb-3 {
            margin-bottom: var(--space-md);
        }

        .mb-4 {
            margin-bottom: var(--space-lg);
        }

        .mb-5 {
            margin-bottom: var(--space-xl);
        }

        .mt-0 {
            margin-top: 0;
        }

        .mt-1 {
            margin-top: var(--space-xs);
        }

        .mt-2 {
            margin-top: var(--space-sm);
        }

        .mt-3 {
            margin-top: var(--space-md);
        }

        .mt-4 {
            margin-top: var(--space-lg);
        }

        .mt-5 {
            margin-top: var(--space-xl);
        }

        .d-none {
            display: none;
        }

        .d-block {
            display: block;
        }

        .d-flex {
            display: flex;
        }

        .gap-1 {
            gap: var(--space-xs);
        }

        .gap-2 {
            gap: var(--space-sm);
        }

        .gap-3 {
            gap: var(--space-md);
        }

        .gap-4 {
            gap: var(--space-lg);
        }

        .gap-5 {
            gap: var(--space-xl);
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .fw-bold {
            font-weight: 700;
        }

        .fw-semibold {
            font-weight: 600;
        }

        .fw-medium {
            font-weight: 500;
        }

        .fw-normal {
            font-weight: 400;
        }

        .fw-light {
            font-weight: 300;
        }

        /* ==========================================================================
       PRINT STYLES
       ========================================================================== */
        @media print {

            .sidebar,
            .dashboard-header,
            .sidebar-toggle,
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
            .appointments-section,
            .alert-card {
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

    <!-- Overlay para sidebar móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Barra Lateral Moderna -->
    <aside class="sidebar" id="sidebar">

        <!-- Navegación -->
        <nav class="sidebar-nav">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="index.php" class="nav-link active">
                        <i class="bi bi-speedometer2 nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                <?php if ($user_type === 'admin' || $user_type === 'user'): ?>
                    <li class="nav-item">
                        <a href="../dispensary/index.php" class="nav-link">
                            <span class="nav-icon" style="font-weight: 900; font-family: serif; font-size: 1.5rem;">Q</span>
                            <span class="nav-text">Punto de Venta</span>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="../appointments/index.php" class="nav-link">
                        <i class="bi bi-calendar-check nav-icon"></i>
                        <span class="nav-text">Citas</span>
                        <span class="badge bg-primary"><?php echo $total_appointments; ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../patients/index.php" class="nav-link">
                        <i class="bi bi-people nav-icon"></i>
                        <span class="nav-text">Pacientes</span>
                    </a>
                </li>
                <?php if ($user_type === 'admin' || $user_type === 'user'): ?>
                    <li class="nav-item">
                        <a href="../hospitalization/index.php" class="nav-link">
                            <i class="bi bi-hospital nav-icon"></i>
                            <span class="nav-text">Hospitalización</span>
                            <span class="badge bg-info"><?php echo $active_hospitalizations; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../laboratory/index.php" class="nav-link">
                            <i class="bi bi-virus nav-icon"></i>
                            <span class="nav-text">Laboratorio</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../inventory/index.php" class="nav-link">
                            <i class="bi bi-box-seam nav-icon"></i>
                            <span class="nav-text">Inventario</span>
                            <?php if ($pending_purchases > 0): ?>
                                <span class="badge bg-warning"><?php echo $pending_purchases; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($user_type === 'admin'): ?>
                    <li class="nav-item">
                        <a href="../minor_procedures/index.php" class="nav-link">
                            <i class="bi bi-bandaid nav-icon"></i>
                            <span class="nav-text">Procedimientos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../examinations/index.php" class="nav-link">
                            <i class="bi bi-file-earmark-medical nav-icon"></i>
                            <span class="nav-text">Exámenes</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../purchases/index.php" class="nav-link">
                            <i class="bi bi-cart nav-icon"></i>
                            <span class="nav-text">Compras</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../sales/index.php" class="nav-link">
                            <i class="bi bi-receipt nav-icon"></i>
                            <span class="nav-text">Ventas</span>
                        </a>
                    </li>
                <?php endif; ?>

                <?php if ($user_type === 'admin'): ?>
                    <li class="nav-item">
                        <a href="../billing/index.php" class="nav-link">
                            <i class="bi bi-cash-coin nav-icon"></i>
                            <span class="nav-text">Cobros</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../reports/index.php" class="nav-link">
                            <i class="bi bi-graph-up nav-icon"></i>
                            <span class="nav-text">Reportes</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../settings/index.php" class="nav-link">
                            <i class="bi bi-gear nav-icon"></i>
                            <span class="nav-text">Configuración</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </aside>

    <!-- Contenedor Principal -->
    <div class="dashboard-container">
        <!-- Header Superior -->
        <header class="dashboard-header">
            <div class="header-content">
                <!-- Botón hamburguesa para móvil -->
                <button class="mobile-toggle" id="mobileSidebarToggle" aria-label="Abrir menú">
                    <i class="bi bi-list"></i>
                </button>

                <!-- Logo -->
                <div class="brand-container">
                    <img src="../../assets/img/cmrs.png" alt="Centro Médico RS" class="brand-logo">
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

                    <!-- Botón de cerrar sesión -->
                    <a href="../auth/logout.php" class="logout-btn">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Salir</span>
                    </a>
                </div>
            </div>
            <!-- Botón Corte de Turno -->
            <?php if ($user_type === 'admin'): ?>
                <div style="position: absolute; right: 2rem; bottom: -3.5rem;">
                    <button type="button" class="btn btn-warning shadow-sm border-0 px-4 py-2 fw-bold"
                        style="border-radius: 50px; background: linear-gradient(135deg, #ffc107, #ff9800); color: #fff;"
                        onclick="verifyShiftCode()">
                        <i class="bi bi-receipt-cutoff me-2"></i>
                        Corte de Turno
                    </button>
                </div>
            <?php endif; ?>
        </header>

        <!-- Modal Corte de Turno -->
        <div class="modal fade" id="shiftCutModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-warning text-white border-0">
                        <h5 class="modal-title fw-bold">
                            <i class="bi bi-receipt-cutoff me-2"></i>Resumen de Corte de Turno
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="shiftDate" class="form-label fw-semibold">Fecha del Turno</label>
                                <input type="date" class="form-control" id="shiftDate"
                                    value="<?php echo date('Y-m-d'); ?>" onchange="loadShiftData()">
                            </div>
                            <div class="col-md-6">
                                <label for="shiftType" class="form-label fw-semibold">Jornada</label>
                                <select class="form-select" id="shiftType" onchange="loadShiftData()">
                                    <option value="morning">Mañana (08:00 AM - 05:00 PM)</option>
                                    <option value="night">Tarde/Noche (05:00 PM - 08:00 AM)</option>
                                </select>
                            </div>
                        </div>

                        <div id="shiftLoading" class="text-center py-5">
                            <div class="spinner-grow text-warning" role="status" style="width: 3rem; height: 3rem;">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mt-3 text-muted tracking-tight">Calculando totales y desgloses...</p>
                        </div>

                        <div id="shiftContent" style="display: none;">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle border-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Categoría</th>
                                            <th class="text-center">Efectivo</th>
                                            <th class="text-center">Tarjeta</th>
                                            <th class="text-center">Transf.</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody id="shiftTableBody">
                                        <!-- Data will be injected here -->
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-dark">
                                            <th class="fw-bold">TOTAL GENERAL</th>
                                            <td colspan="3"></td>
                                            <td class="text-end fw-bold fs-5">Q<span id="cut-grand-total">0.00</span>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <div id="consultationBreakdown" class="mt-4" style="display:none;">
                                <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">Detalle de Consultas por Médico
                                </h6>
                                <div id="doctorsList"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-warning px-4 text-white" onclick="window.print()">
                            <i class="bi bi-printer me-2"></i>Imprimir Reporte
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Funciones para el Corte de Turno
            async function verifyShiftCode() {
                const { value: code } = await Swal.fire({
                    title: 'Código de Seguridad',
                    text: 'Ingrese el código para autorizar el corte de turno',
                    input: 'password',
                    confirmButtonColor: '#ffc107',
                    inputPlaceholder: 'Ingrese su código',
                    inputAttributes: {
                        autocapitalize: 'off',
                        autocorrect: 'off'
                    }
                });

                if (code === 'cmhs') {
                    openShiftCutModal();
                } else if (code) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Código Incorrecto',
                        text: 'El código ingresado no es válido para esta operación.',
                        confirmButtonColor: '#ffc107'
                    });
                }
            }

            function openShiftCutModal() {
                const modal = new bootstrap.Modal(document.getElementById('shiftCutModal'));
                modal.show();
                loadShiftData();
            }

            function loadShiftData() {
                const date = document.getElementById('shiftDate').value;
                const shift = document.getElementById('shiftType').value;
                const loading = document.getElementById('shiftLoading');
                const content = document.getElementById('shiftContent');
                const tableBody = document.getElementById('shiftTableBody');

                loading.style.display = 'block';
                content.style.display = 'none';

                fetch(`get_shift_cut_data.php?date=${date}&shift=${shift}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const d = data.data;

                            // Build main table
                            const categories = [
                                { label: 'Farmacia', data: d.pharmacy, icon: 'bi-capsule text-primary' },
                                { label: 'Consultas', data: d.consultations, icon: 'bi-person-video text-success' },
                                { label: 'Laboratorio', data: d.laboratory, icon: 'bi-eyedropper text-danger' },
                                { label: 'Procedimientos', data: d.procedures, icon: 'bi-bandaid text-warning' },
                                { label: 'Ultrasonido', data: d.ultrasound, icon: 'bi-activity text-info' },
                                { label: 'Rayos X', data: d.xray, icon: 'bi-radioactive text-secondary' }
                            ];

                            let html = '';
                            categories.forEach(cat => {
                                html += `
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi ${cat.icon} fs-5 me-2"></i>
                                                <span class="fw-semibold">${cat.label}</span>
                                            </div>
                                        </td>
                                        <td class="text-center">Q${cat.data.breakdown?.['Efectivo']?.toFixed(2) || '0.00'}</td>
                                        <td class="text-center">Q${cat.data.breakdown?.['Tarjeta']?.toFixed(2) || '0.00'}</td>
                                        <td class="text-center">Q${cat.data.breakdown?.['Transferencia']?.toFixed(2) || '0.00'}</td>
                                        <td class="text-end fw-bold">Q${cat.data.total.toFixed(2)}</td>
                                    </tr>
                                `;
                            });
                            tableBody.innerHTML = html;
                            document.getElementById('cut-grand-total').textContent = d.grand_total.toFixed(2);

                            // Build doctors breakdown
                            if (d.consultations.by_doctor && d.consultations.by_doctor.length > 0) {
                                document.getElementById('consultationBreakdown').style.display = 'block';
                                let docHtml = '<div class="row g-2">';
                                d.consultations.by_doctor.forEach(doc => {
                                    docHtml += `
                                        <div class="col-md-6">
                                            <div class="card bg-light border-0 p-3 h-100">
                                                <div class="fw-bold text-primary mb-2">${doc.doctor}</div>
                                                <div class="d-flex justify-content-between small text-muted">
                                                    <span>Efectivo: Q${doc.breakdown.Efectivo.toFixed(2)}</span>
                                                    <span>Tarjeta: Q${doc.breakdown.Tarjeta.toFixed(2)}</span>
                                                    <span>Transf: Q${doc.breakdown.Transferencia.toFixed(2)}</span>
                                                </div>
                                                <div class="text-end fw-bold mt-1">Total: Q${doc.total.toFixed(2)}</div>
                                            </div>
                                        </div>
                                    `;
                                });
                                docHtml += '</div>';
                                document.getElementById('doctorsList').innerHTML = docHtml;
                            } else {
                                document.getElementById('consultationBreakdown').style.display = 'none';
                            }

                            loading.style.display = 'none';
                            content.style.display = 'block';
                        } else {
                            Swal.fire('Error', 'Error al cargar datos: ' + (data.error || 'Desconocido'), 'error');
                            loading.style.display = 'none';
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        Swal.fire('Error', 'Error de conexión', 'error');
                        loading.style.display = 'none';
                    });
            }
        </script>

        <!-- Botón para colapsar/expandir sidebar (solo escritorio) -->
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Colapsar/expandir menú">
            <i class="bi bi-chevron-left" id="sidebarToggleIcon"></i>
        </button>

        <!-- Contenido Principal -->
        <main class="main-content">
            <!-- Notificación de compras pendientes -->
            <?php if ($pending_purchases > 0 && $_SESSION['user_id'] == 6): ?>
                <div class="alert-card mb-4 animate-in delay-1">
                    <div class="alert-header">
                        <div class="alert-icon warning">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <h3 class="alert-title">Compras Pendientes</h3>
                    </div>
                    <p class="text-muted mb-0">
                        Hay <strong><?php echo $pending_purchases; ?></strong> productos por recibir en inventario.
                        <a href="../inventory/index.php" class="text-primary text-decoration-none ms-1">
                            Revisar inventario <i class="bi bi-arrow-right"></i>
                        </a>
                    </p>
                </div>
            <?php endif; ?>

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
                            <i class="bi bi-building me-1"></i> Centro Médico RS
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-heart-pulse text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Acciones Rápidas -->
            <?php if ($_SESSION['user_id'] == 7 || $_SESSION['user_id'] == 1): ?>
                <div class="stats-grid mb-4 animate-in delay-1">
                    <a href="#" class="stat-card" data-bs-toggle="modal" data-bs-target="#newBillingModal"
                        style="text-decoration: none; border-left: 4px solid var(--color-success);">
                        <div class="stat-header mb-0">
                            <div>
                                <div class="stat-title text-success fw-bold">Cobros</div>
                                <div class="stat-value" style="font-size: 1.25rem;">Registrar Cobro</div>
                            </div>
                            <div class="stat-icon success">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                        </div>
                    </a>
                    <a href="#" class="stat-card" data-bs-toggle="modal" data-bs-target="#electroBillingModal"
                        style="text-decoration: none; border-left: 4px solid var(--color-danger);">
                        <div class="stat-header mb-0">
                            <div>
                                <div class="stat-title text-danger fw-bold">Electro</div>
                                <div class="stat-value" style="font-size: 1.25rem;">Cobrar Electro</div>
                            </div>
                            <div class="stat-icon danger">
                                <i class="bi bi-heart-pulse"></i>
                            </div>
                        </div>
                    </a>
                    <a href="#" class="stat-card" data-bs-toggle="modal" data-bs-target="#newLabOrderModal"
                        style="text-decoration: none; border-left: 4px solid var(--color-info);">
                        <div class="stat-header mb-0">
                            <div>
                                <div class="stat-title text-info fw-bold">Laboratorio</div>
                                <div class="stat-value" style="font-size: 1.25rem;">Nueva Orden</div>
                            </div>
                            <div class="stat-icon info">
                                <i class="bi bi-virus"></i>
                            </div>
                        </div>
                    </a>
                    <a href="#" class="stat-card" data-bs-toggle="modal" data-bs-target="#procedureBillingModal"
                        style="text-decoration: none; border-left: 4px solid var(--color-warning);">
                        <div class="stat-header mb-0">
                            <div>
                                <div class="stat-title text-warning fw-bold">Procedimientos</div>
                                <div class="stat-value" style="font-size: 1.25rem;">Cobro Proc.</div>
                            </div>
                            <div class="stat-icon warning">
                                <i class="bi bi-bandaid"></i>
                            </div>
                        </div>
                    </a>
                    <a href="#" class="stat-card" data-bs-toggle="modal" data-bs-target="#xrayBillingModal"
                        style="text-decoration: none; border-left: 4px solid var(--color-secondary);">
                        <div class="stat-header mb-0">
                            <div>
                                <div class="stat-title text-secondary fw-bold">Rayos X</div>
                                <div class="stat-value" style="font-size: 1.25rem;">Cobro RX</div>
                            </div>
                            <div class="stat-icon secondary">
                                <i class="bi bi-file-medical"></i>
                            </div>
                        </div>
                    </a>
                    <a href="#" class="stat-card" data-bs-toggle="modal" data-bs-target="#ultrasoundBillingModal"
                        style="text-decoration: none; border-left: 4px solid var(--color-primary);">
                        <div class="stat-header mb-0">
                            <div>
                                <div class="stat-title text-primary fw-bold">Ultrasonido</div>
                                <div class="stat-value" style="font-size: 1.25rem;">Cobro US</div>
                            </div>
                            <div class="stat-icon primary">
                                <i class="bi bi-activity"></i>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endif; ?>

            <!-- Estadísticas principales -->
            <?php if ($user_type === 'admin'): ?>
                <div class="stats-grid">
                    <!-- Citas de hoy -->
                    <div class="stat-card animate-in delay-1">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Citas Hoy</div>
                                <div class="stat-value"><?php echo $today_appointments; ?></div>
                            </div>
                            <div class="stat-icon primary">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-arrow-up-right"></i>
                            <span>Programadas para hoy</span>
                        </div>
                    </div>

                    <!-- Pacientes del año -->
                    <div class="stat-card animate-in delay-2">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Pacientes Año</div>
                                <div class="stat-value"><?php echo $year_patients; ?></div>
                            </div>
                            <div class="stat-icon success">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-person-plus"></i>
                            <span>Año <?php echo date('Y'); ?></span>
                        </div>
                    </div>

                    <!-- Citas pendientes -->
                    <div class="stat-card animate-in delay-3">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Citas Pendientes</div>
                                <div class="stat-value"><?php echo $pending_appointments; ?></div>
                            </div>
                            <div class="stat-icon warning">
                                <i class="bi bi-clock-history"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-calendar-plus"></i>
                            <span>Próximas citas</span>
                        </div>
                    </div>

                    <!-- Consultas del mes -->
                    <div class="stat-card animate-in delay-4">
                        <div class="stat-header">
                            <div>
                                <div class="stat-title">Consultas Mes</div>
                                <div class="stat-value"><?php echo $month_consultations; ?></div>
                            </div>
                            <div class="stat-icon info">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                        </div>
                        <div class="stat-change positive">
                            <i class="bi bi-calendar-month"></i>
                            <span>Mes actual</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Sección de citas de hoy -->
            <section class="appointments-section animate-in delay-1">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-calendar-day section-title-icon"></i>
                        Citas de Hoy
                    </h3>
                    <a href="../appointments/index.php" class="action-btn">
                        <i class="bi bi-plus-lg"></i>
                        Nueva Cita
                    </a>
                </div>

                <?php if (count($todays_appointments) > 0): ?>
                    <div class="table-responsive">
                        <table class="appointments-table">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Hora</th>
                                    <th>Contacto</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todays_appointments as $appointment): ?>
                                    <?php
                                    $patient_name = htmlspecialchars($appointment['nombre_pac'] . ' ' . $appointment['apellido_pac']);
                                    $patient_initials = strtoupper(
                                        substr($appointment['nombre_pac'], 0, 1) .
                                        substr($appointment['apellido_pac'], 0, 1)
                                    );
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="patient-cell">
                                                <div class="patient-avatar">
                                                    <?php echo $patient_initials; ?>
                                                </div>
                                                <div class="patient-info">
                                                    <div class="patient-name"><?php echo $patient_name; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="time-badge">
                                                <i class="bi bi-clock"></i>
                                                <?php echo htmlspecialchars($appointment['hora_cita']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="patient-contact">
                                                <?php echo htmlspecialchars($appointment['telefono'] ?? 'No disponible'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="#" class="btn-icon history check-patient" title="Ver historial"
                                                    data-nombre="<?php echo htmlspecialchars($appointment['nombre_pac']); ?>"
                                                    data-apellido="<?php echo htmlspecialchars($appointment['apellido_pac']); ?>">
                                                    <i class="bi bi-file-medical"></i>
                                                </a>
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
                            <i class="bi bi-calendar-x"></i>
                        </div>
                        <h4 class="text-muted mb-2">No hay citas programadas para hoy</h4>
                        <p class="text-muted mb-3">Total de citas en sistema: <?php echo $total_appointments; ?></p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Sección de Hospitalización -->
            <section class="appointments-section animate-in delay-2">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-hospital text-primary section-title-icon"></i>
                        Pacientes Hospitalizados
                    </h3>
                    <div class="d-flex gap-2">
                        <div class="badge bg-primary d-flex align-items-center p-2">
                            <i class="bi bi-people-fill me-2"></i>
                            <?php echo $active_hospitalizations; ?> Activos
                        </div>
                        <div class="badge bg-success d-flex align-items-center p-2">
                            <i class="bi bi-hospital-fill me-2"></i>
                            <?php echo $available_beds_count; ?> Camas Disp.
                        </div>
                    </div>
                </div>

                <?php if (count($hospitalized_patients) > 0): ?>
                    <div class="table-responsive">
                        <table class="appointments-table">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Habitación</th>
                                    <th>Ingreso</th>
                                    <th>Diagnóstico</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hospitalized_patients as $hosp): ?>
                                    <?php
                                    $patient_name = htmlspecialchars($hosp['nombre'] . ' ' . $hosp['apellido']);
                                    $patient_initials = strtoupper(
                                        substr($hosp['nombre'], 0, 1) .
                                        substr($hosp['apellido'], 0, 1)
                                    );
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="patient-cell">
                                                <div class="patient-avatar" style="background: var(--color-secondary);">
                                                    <?php echo $patient_initials; ?>
                                                </div>
                                                <div class="patient-info">
                                                    <div class="patient-name"><?php echo $patient_name; ?></div>
                                                    <small class="text-muted">ID:
                                                        #<?php echo $hosp['id_encamamiento']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info text-white">
                                                Hab. <?php echo htmlspecialchars($hosp['numero_habitacion']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($hosp['fecha_ingreso'])); ?>
                                            <br>
                                            <small
                                                class="text-muted"><?php echo date('H:i', strtotime($hosp['fecha_ingreso'])); ?></small>
                                        </td>
                                        <td>
                                            <small class="d-block text-truncate" style="max-width: 150px;">
                                                <?php echo htmlspecialchars($hosp['diagnostico_ingreso']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <a href="../hospitalization/detalle_encamamiento.php?id=<?php echo $hosp['id_encamamiento']; ?>"
                                                class="btn-icon" title="Ver detalles"
                                                style="color: var(--color-primary); border-color: var(--color-primary);">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 text-center">
                        <a href="../hospitalization/index.php" class="text-primary text-decoration-none">
                            Ver todos los pacientes hospitalizados <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-hospital"></i>
                        </div>
                        <h4 class="text-muted mb-2">No hay hospitalizaciones activas</h4>
                        <p class="text-muted mb-3">Todas las camas están disponibles</p>
                        <a href="../hospitalization/ingresar_paciente.php" class="action-btn">
                            <i class="bi bi-plus-lg"></i>
                            Ingresar Paciente
                        </a>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Panel de alertas -->
            <div class="alerts-grid animate-in delay-3">
                <!-- Medicamentos por caducar -->
                <div class="alert-card">
                    <div class="alert-header">
                        <div class="alert-icon warning">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <h3 class="alert-title">Caducidad Próxima</h3>
                    </div>

                    <?php if (count($expiring_medications) > 0): ?>
                        <ul class="alert-list">
                            <?php foreach (array_slice($expiring_medications, 0, 5) as $medication): ?>
                                <?php
                                $expiry_date = new DateTime($medication['fecha_vencimiento']);
                                $today = new DateTime();
                                $days_diff = $today->diff($expiry_date)->days;
                                $is_expired = $expiry_date < $today;
                                ?>
                                <li class="alert-item">
                                    <div class="alert-item-header">
                                        <span
                                            class="alert-item-name"><?php echo htmlspecialchars($medication['nom_medicamento']); ?></span>
                                        <span class="alert-badge <?php echo $is_expired ? 'expired' : 'warning'; ?>">
                                            <?php echo $is_expired ? 'Vencido' : $days_diff . ' días'; ?>
                                        </span>
                                    </div>
                                    <div class="alert-item-details">
                                        <span>Vence: <?php echo $expiry_date->format('d/m/Y'); ?></span>
                                        <span>Stock: <?php echo $medication['cantidad_med']; ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if (count($expiring_medications) > 5): ?>
                            <div class="text-center mt-3">
                                <a href="../inventory/index.php?filter=expiring" class="text-primary text-decoration-none">
                                    Ver todas (<?php echo count($expiring_medications); ?>) <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-alerts">
                            <div class="no-alerts-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <p class="text-muted mb-0">Sin medicamentos próximos a caducar</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Stock bajo -->
                <div class="alert-card">
                    <div class="alert-header">
                        <div class="alert-icon danger">
                            <i class="bi bi-arrow-down-circle"></i>
                        </div>
                        <h3 class="alert-title">Stock Bajo</h3>
                    </div>

                    <?php if (count($low_stock_medications) > 0): ?>
                        <ul class="alert-list">
                            <?php foreach (array_slice($low_stock_medications, 0, 5) as $medication): ?>
                                <li class="alert-item">
                                    <div class="alert-item-header">
                                        <span
                                            class="alert-item-name"><?php echo htmlspecialchars($medication['nom_medicamento']); ?></span>
                                        <span class="alert-badge danger">
                                            <?php echo $medication['cantidad_med']; ?> unidades
                                        </span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if (count($low_stock_medications) > 5): ?>
                            <div class="text-center mt-3">
                                <a href="../inventory/index.php?filter=low_stock" class="text-primary text-decoration-none">
                                    Ver todas (<?php echo count($low_stock_medications); ?>) <i class="bi bi-arrow-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-alerts">
                            <div class="no-alerts-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <p class="text-muted mb-0">Inventario con stock suficiente</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal para nuevo cobro (Billing) -->
    <div class="modal fade" id="newBillingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-cash-coin me-2"></i>
                        Nuevo Cobro
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="newBillingForm">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Paciente</label>
                            <input type="text" name="paciente_nombre" class="form-control" list="billingDatalistOptions"
                                id="billing_paciente_input"
                                placeholder="Nombre del paciente (o seleccione de la lista)..." required
                                autocomplete="off">
                            <datalist id="billingDatalistOptions">
                                <?php foreach ($pacientes as $paciente): ?>
                                    <option data-id="<?php echo $paciente['id_paciente']; ?>"
                                        value="<?php echo htmlspecialchars($paciente['nombre_completo']); ?>">
                                    <?php endforeach; ?>
                            </datalist>
                            <input type="hidden" id="billing_paciente" name="paciente">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Médico que atiende</label>
                            <select class="form-select" id="billing_id_doctor" name="id_doctor" required>
                                <option value="">Seleccione un médico...</option>
                                <?php foreach ($doctores as $doctor): ?>
                                    <option value="<?php echo $doctor['idUsuario']; ?>">
                                        Dr(a).
                                        <?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellido']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Tipo de Consulta <span id="consultationHistoryBadge"
                                    class="badge bg-info ms-2 d-none"></span></label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="tipo_consulta" id="btn_consulta"
                                    value="Consulta" checked autocomplete="off">
                                <label class="btn btn-outline-success" for="btn_consulta">Consulta</label>

                                <input type="radio" class="btn-check" name="tipo_consulta" id="btn_reconsulta"
                                    value="Reconsulta" autocomplete="off">
                                <label class="btn btn-outline-success" for="btn_reconsulta">Re-Consulta</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Monto a Cobrar (Q)</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-success text-white border-0">Q</span>
                                <input type="number" class="form-control border-success text-success fw-bold"
                                    id="billing_monto" name="cantidad" min="0" step="0.01" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Tipo de Pago</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="tipo_pago" id="pago_efectivo"
                                    value="Efectivo" checked autocomplete="off">
                                <label class="btn btn-outline-success" for="pago_efectivo">
                                    <i class="bi bi-cash me-1"></i>Efectivo
                                </label>

                                <input type="radio" class="btn-check" name="tipo_pago" id="pago_transferencia"
                                    value="Transferencia" autocomplete="off">
                                <label class="btn btn-outline-success" for="pago_transferencia">
                                    <i class="bi bi-bank me-1"></i>
                                </label>

                                <input type="radio" class="btn-check" name="tipo_pago" id="pago_tarjeta" value="Tarjeta"
                                    autocomplete="off">
                                <label class="btn btn-outline-success" for="pago_tarjeta">
                                    <i class="bi bi-credit-card me-1"></i>Tarjeta
                                </label>
                            </div>
                        </div>

                        <div class="small text-muted mb-0">
                            <i class="bi bi-info-circle me-1"></i> El monto se calcula automáticamente al seleccionar
                            médico y tipo.
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success px-4" id="saveBillingBtn">
                        <i class="bi bi-check-lg me-1"></i>Guardar Cobro
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Nueva Orden de Laboratorio -->
    <div class="modal fade" id="newLabOrderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title d-flex align-items-center">
                        <div class="icon-shape bg-white bg-opacity-20 rounded-3 p-2 me-3">
                            <i class="bi bi-virus fs-4"></i>
                        </div>
                        <div>
                            <span class="d-block fw-bold">Nueva Orden de Laboratorio</span>
                            <small class="text-white text-opacity-75 fw-normal">Seleccione pruebas para el
                                paciente</small>
                        </div>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 bg-light bg-opacity-50">
                    <div class="d-flex h-100 flex-column flex-lg-row" style="min-height: 600px;">
                        <!-- Panel Izquierdo: Selección -->
                        <div class="p-4 flex-grow-1 overflow-auto bg-white">
                            <form id="newLabOrderForm">
                                <!-- Datos del Paciente -->
                                <div class="row g-3 mb-4 p-3 bg-light rounded-3 border">
                                    <div class="col-md-6">
                                        <label
                                            class="form-label fw-bold small text-uppercase text-muted">Paciente</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0"><i
                                                    class="bi bi-person text-primary"></i></span>
                                            <input class="form-control border-start-0 ps-0" list="labDatalistOptions"
                                                id="lab_paciente_input" placeholder="Buscar por nombre..." required
                                                autocomplete="off">
                                        </div>
                                        <datalist id="labDatalistOptions">
                                            <?php foreach ($pacientes as $paciente): ?>
                                                <option data-id="<?php echo $paciente['id_paciente']; ?>"
                                                    value="<?php echo htmlspecialchars($paciente['nombre_completo']); ?>">
                                                <?php endforeach; ?>
                                        </datalist>
                                        <input type="hidden" id="lab_id_paciente" name="id_paciente">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold small text-uppercase text-muted">Doctor
                                            Referente</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-white border-end-0"><i
                                                    class="bi bi-person-badge text-primary"></i></span>
                                            <select class="form-select border-start-0 ps-0" id="lab_id_doctor"
                                                name="id_doctor" required>
                                                <option value="">Seleccionar doctor...</option>
                                                <?php foreach ($doctores as $doctor): ?>
                                                    <option value="<?php echo $doctor['idUsuario']; ?>">
                                                        Dr(a).
                                                        <?php echo htmlspecialchars($doctor['nombre'] . ' ' . $doctor['apellido']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label fw-bold small text-uppercase text-muted">Indicaciones u
                                            Observaciones</label>
                                        <textarea class="form-control" name="observaciones" rows="1"
                                            placeholder="Nota para el analista..."></textarea>
                                    </div>
                                </div>

                                <!-- Buscador de Pruebas -->
                                <div class="sticky-top bg-white py-2 mb-3">
                                    <div class="input-group shadow-sm">
                                        <span class="input-group-text bg-white border-end-0"><i
                                                class="bi bi-search text-primary"></i></span>
                                        <input type="text" id="labTestSearch"
                                            class="form-control border-start-0 ps-0 py-2"
                                            placeholder="Filtrar pruebas por nombre o categoría...">
                                    </div>
                                </div>

                                <!-- Listado de Pruebas -->
                                <div class="accordion accordion-flush" id="testsAccordion">
                                    <?php foreach ($pruebas_por_categoria as $categoria => $pruebas): ?>
                                        <?php $catID = 'cat_v2_' . md5($categoria); ?>
                                        <div class="accordion-item border rounded-3 mb-2 category-container"
                                            data-category="<?php echo htmlspecialchars($categoria); ?>">
                                            <h2 class="accordion-header" id="heading_<?php echo $catID; ?>">
                                                <button class="accordion-button rounded-3 fw-bold" type="button"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#collapse_<?php echo $catID; ?>" aria-expanded="true">
                                                    <i class="bi bi-tags me-2 text-primary"></i>
                                                    <?php echo htmlspecialchars($categoria); ?>
                                                    <span
                                                        class="badge bg-light text-primary ms-2 border"><?php echo count($pruebas); ?></span>
                                                </button>
                                            </h2>
                                            <div id="collapse_<?php echo $catID; ?>"
                                                class="accordion-collapse collapse show" data-bs-parent="#testsAccordion">
                                                <div class="accordion-body p-2">
                                                    <div class="row g-2">
                                                        <?php foreach ($pruebas as $prueba): ?>
                                                            <div class="col-md-6 test-item"
                                                                data-name="<?php echo strtolower(htmlspecialchars($prueba['nombre_prueba'])); ?>">
                                                                <div class="test-card-v2 p-2 border rounded-3 position-relative transition-all d-flex align-items-center gap-3 h-100 hover-shadow cursor-pointer"
                                                                    onclick="toggleLabCheckbox('test_v2_<?php echo $prueba['id_prueba']; ?>')">
                                                                    <div class="check-indicator">
                                                                        <input
                                                                            class="form-check-input test-checkbox stretched-link"
                                                                            type="checkbox" name="pruebas[]"
                                                                            value="<?php echo $prueba['id_prueba']; ?>"
                                                                            id="test_v2_<?php echo $prueba['id_prueba']; ?>"
                                                                            data-price="<?php echo $prueba['precio']; ?>"
                                                                            data-name="<?php echo htmlspecialchars($prueba['nombre_prueba']); ?>">
                                                                    </div>
                                                                    <div class="flex-grow-1">
                                                                        <div class="fw-semibold small lh-1 mb-1">
                                                                            <?php echo htmlspecialchars($prueba['nombre_prueba']); ?>
                                                                        </div>
                                                                        <div class="text-success fw-bold small">
                                                                            Q<?php echo number_format($prueba['precio'], 2); ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </form>
                        </div>

                        <!-- Panel Derecho: Resumen -->
                        <div class="bg-light border-start p-4 d-flex flex-column" style="min-width: 350px;">
                            <div class="flex-grow-1">
                                <h6 class="fw-bold d-flex justify-content-between align-items-center mb-3">
                                    <span>Resumen de Selección</span>
                                    <span class="badge bg-primary rounded-pill pruebas-count">0</span>
                                </h6>
                                <div id="selectedTestsList" class="mb-3 custom-scrollbar"
                                    style="max-height: 400px; overflow-y: auto;">
                                    <div class="text-center py-5 text-muted empty-summary">
                                        <i class="bi bi-cart-x fs-1 opacity-25"></i>
                                        <p class="mt-2 small">No hay pruebas seleccionadas</p>
                                    </div>
                                </div>
                            </div>

                            <div class="border-top pt-3 bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted small fw-bold text-uppercase">Subtotal:</span>
                                    <span class="fw-bold text-dark" id="orderSubtotal">Q0.00</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold text-uppercase">Total a Pagar:</span>
                                        <button type="button" class="btn btn-sm btn-outline-warning" id="toggleEPSBtn"
                                            title="Activar modo EPS (Editar precios)">
                                            <i class="bi bi-pencil-square"></i> EPS
                                        </button>
                                    </div>
                                    <span class="fs-3 fw-bold text-primary" id="orderTotal">Q0.00</span>
                                </div>

                                <!-- Payment Method Selection -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold small text-uppercase text-muted">Método de
                                        Pago</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="order_tipo_pago"
                                            id="order_pago_efectivo" value="Efectivo" checked autocomplete="off">
                                        <label class="btn btn-outline-primary"
                                            for="order_pago_efectivo">Efectivo</label>

                                        <input type="radio" class="btn-check" name="order_tipo_pago"
                                            id="order_pago_transferencia" value="Transferencia" autocomplete="off">
                                        <label class="btn btn-outline-primary"
                                            for="order_pago_transferencia">Transf.</label>

                                        <input type="radio" class="btn-check" name="order_tipo_pago"
                                            id="order_pago_tarjeta" value="Tarjeta" autocomplete="off">
                                        <label class="btn btn-outline-primary" for="order_pago_tarjeta">Tarjeta</label>
                                    </div>
                                    <small class="text-muted d-block mt-2">Sólo aplica si el paciente no está
                                        hospitalizado.</small>
                                </div>
                            </div>
                            <button type="button"
                                class="btn btn-primary w-100 py-3 rounded-3 shadow-sm d-flex justify-content-center align-items-center gap-2"
                                id="saveLabOrderBtn" disabled>
                                <i class="bi bi-printer fs-5"></i>
                                <span class="fw-bold">Generar Orden</span>
                            </button>
                            <p class="text-center small text-muted mt-2">
                                <i class="bi bi-shield-check me-1"></i> Se generará cobro automático
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <style>
        .test-card-v2 {
            background: #fff;
        }

        .test-card-v2:hover {
            background: #f8f9ff;
            border-color: var(--color-primary);
        }

        .test-card-v2.active {
            background: #eff6ff;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 1px var(--color-primary);
        }

        .hover-shadow:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .icon-shape {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>

    <script>
        function toggleLabCheckbox(id) {
            const cb = document.getElementById(id);
            if (cb) {
                cb.checked = !cb.checked;
                cb.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
    </script>

    <!-- JavaScript Optimizado -->
    <script>
        // Dashboard Reingenierizado - Centro Médico RS

        (function () {
            'use strict';

            // ==========================================================================
            // CONFIGURACIÓN Y CONSTANTES
            // ==========================================================================
            const CONFIG = {
                themeKey: 'dashboard-theme',
                sidebarKey: 'sidebar-collapsed',
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
                sidebar: document.getElementById('sidebar'),
                sidebarToggle: document.getElementById('sidebarToggle'),
                sidebarToggleIcon: document.getElementById('sidebarToggleIcon'),
                sidebarOverlay: document.getElementById('sidebarOverlay'),
                mobileSidebarToggle: document.getElementById('mobileSidebarToggle'),
                greetingElement: document.getElementById('greeting-text'),
                currentTimeElement: document.getElementById('current-time'),
                checkPatientButtons: document.querySelectorAll('.check-patient')
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
                    // 1. Verificar preferencia guardada
                    const savedTheme = localStorage.getItem(CONFIG.themeKey);
                    if (savedTheme) return savedTheme;

                    // 2. Verificar preferencia del sistema
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (prefersDark) return 'dark';

                    // 3. Tema por defecto (día)
                    return 'light';
                }

                applyTheme(theme) {
                    DOM.html.setAttribute('data-theme', theme);
                    localStorage.setItem(CONFIG.themeKey, theme);

                    // Actualizar meta tag para navegadores móviles
                    const metaTheme = document.querySelector('meta[name="theme-color"]');
                    if (metaTheme) {
                        metaTheme.setAttribute('content', theme === 'dark' ? '#0f172a' : '#ffffff');
                    }
                }

                toggleTheme() {
                    const newTheme = this.theme === 'light' ? 'dark' : 'light';
                    this.theme = newTheme;
                    this.applyTheme(newTheme);

                    // Animación sutil en el botón
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

                    // Escuchar cambios en preferencias del sistema
                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                        if (!localStorage.getItem(CONFIG.themeKey)) {
                            this.theme = e.matches ? 'dark' : 'light';
                            this.applyTheme(this.theme);
                        }
                    });
                }
            }

            // ==========================================================================
            // MANEJO DE BARRA LATERAL
            // ==========================================================================
            class SidebarManager {
                constructor() {
                    this.isCollapsed = this.getInitialState();
                    this.isMobile = window.innerWidth < 992;
                    this.setupEventListeners();
                    this.applyState();
                }

                getInitialState() {
                    if (this.isMobile) return false;
                    const savedState = localStorage.getItem(CONFIG.sidebarKey);
                    return savedState === 'true';
                }

                applyState() {
                    if (this.isCollapsed && !this.isMobile) {
                        DOM.sidebar.classList.add('collapsed');
                        if (DOM.sidebarToggleIcon) {
                            DOM.sidebarToggleIcon.classList.remove('bi-chevron-left');
                            DOM.sidebarToggleIcon.classList.add('bi-chevron-right');
                        }
                    } else {
                        DOM.sidebar.classList.remove('collapsed');
                        if (DOM.sidebarToggleIcon) {
                            DOM.sidebarToggleIcon.classList.remove('bi-chevron-right');
                            DOM.sidebarToggleIcon.classList.add('bi-chevron-left');
                        }
                    }
                }

                toggle() {
                    if (this.isMobile) {
                        this.toggleMobile();
                    } else {
                        this.toggleDesktop();
                    }
                }

                toggleDesktop() {
                    this.isCollapsed = !this.isCollapsed;
                    this.applyState();
                    localStorage.setItem(CONFIG.sidebarKey, this.isCollapsed);
                }

                toggleMobile() {
                    const isShowing = DOM.sidebar.classList.toggle('show');

                    if (isShowing) {
                        DOM.sidebarOverlay.classList.add('show');
                        DOM.body.style.overflow = 'hidden';
                    } else {
                        DOM.sidebarOverlay.classList.remove('show');
                        DOM.body.style.overflow = '';
                    }
                }

                closeMobile() {
                    DOM.sidebar.classList.remove('show');
                    DOM.sidebarOverlay.classList.remove('show');
                    DOM.body.style.overflow = '';
                }

                setupEventListeners() {
                    // Toggle escritorio
                    if (DOM.sidebarToggle) {
                        DOM.sidebarToggle.addEventListener('click', () => this.toggle());
                    }

                    // Toggle móvil
                    if (DOM.mobileSidebarToggle) {
                        DOM.mobileSidebarToggle.addEventListener('click', () => this.toggle());
                    }

                    // Overlay móvil
                    if (DOM.sidebarOverlay) {
                        DOM.sidebarOverlay.addEventListener('click', () => this.closeMobile());
                    }

                    // Cerrar sidebar al hacer clic en enlace (móvil)
                    const navLinks = DOM.sidebar.querySelectorAll('.nav-link');
                    navLinks.forEach(link => {
                        link.addEventListener('click', () => {
                            if (this.isMobile) this.closeMobile();
                        });
                    });

                    // Escuchar cambios de tamaño
                    window.addEventListener('resize', this.debounce(() => {
                        const wasMobile = this.isMobile;
                        this.isMobile = window.innerWidth < 992;

                        if (wasMobile !== this.isMobile) {
                            if (!this.isMobile) this.closeMobile();
                            this.applyState();
                        }
                    }, 250));
                }

                debounce(func, wait) {
                    let timeout;
                    return function executedFunction(...args) {
                        const later = () => {
                            clearTimeout(timeout);
                            func(...args);
                        };
                        clearTimeout(timeout);
                        timeout = setTimeout(later, wait);
                    };
                }
            }

            // ==========================================================================
            // COMPONENTES DINÁMICOS
            // ==========================================================================
            class DynamicComponents {
                constructor() {
                    this.setupGreeting();
                    this.setupClock();
                    this.setupPatientHandlers();
                    this.setupBillingHandlers();
                    this.setupElectroHandlers();
                    this.setupLabOrderHandlers();
                    this.setupXrayHandlers();
                    this.setupUltrasoundHandlers();
                    this.setupAnimations();
                    this.setupAdminNotifications();
                }

                setupGreeting() {
                    const el = document.getElementById('greeting-text');
                    if (!el) return;
                    const hour = new Date().getHours();
                    let greeting = hour < 12 ? 'Buenos días' : (hour < 19 ? 'Buenas tardes' : 'Buenas noches');
                    el.textContent = greeting;
                }

                setupClock() {
                    const el = document.getElementById('current-time');
                    if (!el) return;
                    const update = () => {
                        el.textContent = new Date().toLocaleTimeString('es-GT', { hour: '2-digit', minute: '2-digit', hour12: false });
                    };
                    update();
                    setInterval(update, 60000);
                }

                setupPatientHandlers() {
                    document.querySelectorAll('.check-patient').forEach(btn => {
                        btn.addEventListener('click', async (e) => {
                            e.preventDefault();
                            const nombre = btn.getAttribute('data-nombre');
                            const apellido = btn.getAttribute('data-apellido');
                            if (!nombre || !apellido) return;

                            const icon = btn.querySelector('i');
                            const originalClass = icon ? icon.className : '';
                            if (icon) icon.className = 'bi bi-arrow-clockwise spin';
                            btn.style.pointerEvents = 'none';

                            try {
                                const response = await fetch(`../patients/check_patient.php?nombre=${encodeURIComponent(nombre)}&apellido=${encodeURIComponent(apellido)}`);
                                const data = await response.json();

                                if (data.status === 'success' && data.exists) {
                                    window.location.href = `../patients/medical_history.php?id=${data.id}`;
                                } else {
                                    Swal.fire({
                                        title: 'Paciente no encontrado',
                                        text: `El paciente ${nombre} ${apellido} no está registrado. ¿Desea registrarlo?`,
                                        icon: 'question',
                                        showCancelButton: true,
                                        confirmButtonText: 'Sí, registrar',
                                        cancelButtonText: 'Cancelar',
                                        confirmButtonColor: 'var(--color-primary)',
                                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                                    }).then((result) => {
                                        if (result.isConfirmed) {
                                            window.location.href = `../patients/index.php?new=true&nombre=${encodeURIComponent(nombre)}&apellido=${encodeURIComponent(apellido)}`;
                                        }
                                    });
                                }
                            } catch (error) {
                                Swal.fire('Error', 'Problema al conectar con el servidor', 'error');
                            } finally {
                                if (icon) icon.className = originalClass;
                                btn.style.pointerEvents = '';
                            }
                        });
                    });
                }

                setupBillingHandlers() {
                    const doctorSelect = document.getElementById('billing_id_doctor');
                    const montoInput = document.getElementById('billing_monto');
                    const tipoRadios = document.getElementsByName('tipo_consulta');

                    if (!doctorSelect || !montoInput) return;

                    const calculatePrice = () => {
                        const doctorId = doctorSelect.value;
                        let type = 'Consulta';
                        tipoRadios.forEach(r => { if (r.checked) type = r.value; });

                        let price = 0;
                        const date = new Date();
                        const day = date.getDay();
                        const hour = date.getHours();

                        switch (doctorId) {
                            case '17': price = (type === 'Consulta') ? 200 : 150; break;
                            case '13': price = (type === 'Consulta') ? 250 : 150; break;
                            case '18': case '11': price = (type === 'Consulta') ? 200 : 100; break;
                            case '16':
                                if (type === 'Reconsulta') price = 150;
                                else {
                                    if (day >= 1 && day <= 5) {
                                        if (hour >= 8 && hour < 16) price = 250;
                                        else if (hour >= 16 && hour < 22) price = 300;
                                        else price = 400;
                                    } else if (day === 6) {
                                        if (hour < 13) price = 250;
                                        else if (hour >= 13 && hour < 22) price = 300;
                                        else price = 400;
                                    } else {
                                        if (hour >= 8 && hour < 20) price = 350;
                                        else price = 400;
                                    }
                                }
                                break;
                            default: price = (type === 'Consulta') ? 100 : 0; break;
                        }
                        montoInput.value = price;
                    };

                    doctorSelect.addEventListener('change', calculatePrice);
                    tipoRadios.forEach(r => r.addEventListener('change', calculatePrice));
                    calculatePrice();

                    // Listener para historial de paciente
                    const billingPatientInput = document.getElementById('billing_paciente_input');
                    const billingDatalist = document.getElementById('billingDatalistOptions');

                    if (billingPatientInput && billingDatalist) {
                        billingPatientInput.addEventListener('input', () => {
                            const val = billingPatientInput.value;
                            let patientId = null;
                            const options = billingDatalist.options;
                            for (let i = 0; i < options.length; i++) {
                                if (options[i].value === val) {
                                    patientId = options[i].getAttribute('data-id');
                                    break;
                                }
                            }

                            if (patientId) {
                                fetch(`api/check_consultation_history.php?id_paciente=${patientId}`)
                                    .then(r => r.json())
                                    .then(data => {
                                        const badge = document.getElementById('consultationHistoryBadge');
                                        const btnReconsulta = document.getElementById('btn_reconsulta'); // Si se desea auto-seleccionar

                                        if (badge) {
                                            if (data.status === 'success' && data.has_prior) {
                                                badge.textContent = `${data.count} Citas Previas`;
                                                badge.classList.remove('d-none');

                                                Swal.fire({
                                                    toast: true,
                                                    position: 'top-end',
                                                    icon: 'info',
                                                    title: 'Paciente Recurrente',
                                                    text: `Tiene ${data.count} citas previas. Verifique si aplica Re-consulta.`,
                                                    showConfirmButton: false,
                                                    timer: 4000
                                                });
                                            } else {
                                                badge.classList.add('d-none');
                                            }
                                        }
                                    })
                                    .catch(e => console.error("Error checking history", e));
                            }
                        });
                    }

                    const saveBtn = document.getElementById('saveBillingBtn');
                    if (saveBtn) {
                        saveBtn.addEventListener('click', async () => {
                            const form = document.getElementById('newBillingForm');
                            const patientInput = document.getElementById('billing_paciente_input');
                            const patientHidden = document.getElementById('billing_paciente');
                            const datalist = document.getElementById('billingDatalistOptions');

                            if (!form || !patientInput || !datalist) return;

                            let patientId = '';
                            const val = patientInput.value;
                            if (!val.trim()) {
                                Swal.fire('Aviso', 'Nombre de paciente requerido', 'warning');
                                return;
                            }

                            const options = datalist.options;
                            for (let i = 0; i < options.length; i++) {
                                if (options[i].value === val) {
                                    patientId = options[i].getAttribute('data-id');
                                    break;
                                }
                            }
                            patientHidden.value = patientId;

                            if (!form.checkValidity()) {
                                form.reportValidity();
                                return;
                            }

                            const originalText = saveBtn.innerHTML;
                            saveBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Guardando...';
                            saveBtn.disabled = true;

                            try {
                                const response = await fetch('../billing/save_billing.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: new URLSearchParams(new FormData(form))
                                });
                                const result = await response.json();
                                if (result.status === 'success') {
                                    Swal.fire({
                                        title: 'Éxito',
                                        text: 'Cobro guardado correctamente',
                                        icon: 'success',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        // Abrir recibo
                                        if (result.id_cobro) {
                                            window.open(`../billing/print_billing.php?id=${result.id_cobro}`, '_blank');
                                        }
                                        location.reload();
                                    });
                                } else {
                                    throw new Error(result.message);
                                }
                            } catch (error) {
                                Swal.fire('Error', error.message || 'Error de conexión', 'error');
                            } finally {
                                saveBtn.innerHTML = originalText;
                                saveBtn.disabled = false;
                            }
                        });
                    }
                }

                setupElectroHandlers() {
                    const saveBtn = document.getElementById('saveElectroBtn');
                    if (saveBtn) {
                        saveBtn.addEventListener('click', async () => {
                            const form = document.getElementById('electroBillingForm');
                            const patientInput = document.getElementById('electro_paciente_input');
                            const patientHidden = document.getElementById('electro_paciente');
                            const datalist = document.getElementById('electroDatalistOptions');

                            if (!form || !patientInput || !datalist) return;

                            let patientId = '';
                            const val = patientInput.value;
                            if (!val.trim()) {
                                Swal.fire('Aviso', 'Nombre de paciente requerido', 'warning');
                                return;
                            }

                            const options = datalist.options;
                            for (let i = 0; i < options.length; i++) {
                                if (options[i].value === val) {
                                    patientId = options[i].getAttribute('data-id');
                                    break;
                                }
                            }

                            patientHidden.value = patientId;

                            if (!form.checkValidity()) {
                                form.reportValidity();
                                return;
                            }

                            const originalText = saveBtn.innerHTML;
                            saveBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Procesando...';
                            saveBtn.disabled = true;

                            try {
                                const response = await fetch('api/save_electro.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: new URLSearchParams(new FormData(form))
                                });
                                const result = await response.json();
                                if (result.status === 'success') {
                                    Swal.fire({
                                        title: 'Éxito',
                                        text: 'Electrocardiograma registrado',
                                        icon: 'success',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        if (result.id) {
                                            window.open(`../billing/print_electro.php?id=${result.id}`, '_blank');
                                        }
                                        setTimeout(() => location.reload(), 500);
                                    });
                                } else {
                                    throw new Error(result.message);
                                }
                            } catch (error) {
                                Swal.fire('Error', error.message || 'Error de conexión', 'error');
                            } finally {
                                saveBtn.innerHTML = originalText;
                                saveBtn.disabled = false;
                            }
                        });
                    }
                }

                setupLabOrderHandlers() {
                    const checkboxes = document.querySelectorAll('.test-checkbox');
                    const selectedList = document.getElementById('selectedTestsList');
                    const subtotalElement = document.getElementById('orderSubtotal');
                    const totalElement = document.getElementById('orderTotal');
                    const countElements = document.querySelectorAll('.pruebas-count');
                    const saveBtn = document.getElementById('saveLabOrderBtn');
                    const searchInput = document.getElementById('labTestSearch');

                    const epsBtn = document.getElementById('toggleEPSBtn');
                    let isEPSMode = false;

                    if (!selectedList) return;

                    const updateSummary = () => {
                        const emptySummary = selectedList.querySelector('.empty-summary');
                        const fragment = document.createDocumentFragment();
                        let total = 0, count = 0;

                        checkboxes.forEach(cb => {
                            const card = cb.closest('.test-card-v2');
                            if (cb.checked) {
                                count++;
                                const price = parseFloat(cb.getAttribute('data-price'));
                                total += price;
                                if (card) card.classList.add('active');

                                const item = document.createElement('div');
                                item.className = 'd-flex justify-content-between align-items-center p-2 mb-2 bg-white border rounded shadow-sm animate-in';
                                item.innerHTML = `
                                    <div class="small w-100">
                                        <div class="fw-bold text-dark">${cb.getAttribute('data-name')}</div>
                                        ${isEPSMode
                                        ? `<input type="number" class="form-control form-control-sm mt-1 eps-price-input" value="${price.toFixed(2)}" step="0.01" min="0" data-id="${cb.value}">`
                                        : `<div class="text-primary fw-bold">Q${price.toFixed(2)}</div>`
                                    }
                                    </div>
                                    <button type="button" class="btn btn-link text-danger p-0 ms-2" onclick="document.getElementById('${cb.id}').click()">
                                        <i class="bi bi-trash"></i>
                                    </button>`;
                                fragment.appendChild(item);
                            } else {
                                if (card) card.classList.remove('active');
                            }
                        });

                        selectedList.innerHTML = '';
                        if (count > 0) {
                            selectedList.appendChild(fragment);
                        } else {
                            selectedList.innerHTML = `
                                <div class="text-center py-5 text-muted empty-summary">
                                    <i class="bi bi-cart-x fs-1 opacity-25"></i>
                                    <p class="mt-2 small">No hay pruebas seleccionadas</p>
                                </div>`;
                        }

                        const totalStr = `Q${total.toFixed(2)}`;
                        if (subtotalElement) subtotalElement.textContent = totalStr;
                        if (totalElement) totalElement.textContent = totalStr;
                        countElements.forEach(el => el.textContent = count);
                        if (saveBtn) saveBtn.disabled = (count === 0);
                    };

                    const toggleEPS = () => {
                        isEPSMode = !isEPSMode;
                        epsBtn.classList.toggle('active', isEPSMode);
                        epsBtn.classList.toggle('btn-warning', isEPSMode);
                        epsBtn.classList.toggle('btn-outline-warning', !isEPSMode);
                        updateSummary();
                    };

                    if (epsBtn) {
                        epsBtn.addEventListener('click', toggleEPS);
                    }

                    // Delegación de eventos para inputs de precio en modo EPS
                    if (selectedList) {
                        selectedList.addEventListener('input', (e) => {
                            if (e.target.classList.contains('eps-price-input')) {
                                let total = 0;
                                document.querySelectorAll('.eps-price-input').forEach(input => {
                                    total += parseFloat(input.value) || 0;
                                });
                                const totalStr = `Q${total.toFixed(2)}`;
                                if (subtotalElement) subtotalElement.textContent = totalStr;
                                if (totalElement) totalElement.textContent = totalStr;
                            }
                        });
                    }

                    checkboxes.forEach(cb => {
                        cb.addEventListener('change', updateSummary);
                    });

                    if (saveBtn) {
                        saveBtn.addEventListener('click', async () => {
                            const form = document.getElementById('newLabOrderForm');
                            const patientHidden = document.getElementById('lab_id_paciente');

                            if (!form || !patientHidden) return;

                            if (!patientHidden.value) {
                                Swal.fire('Aviso', 'Seleccione un paciente de la lista', 'warning');
                                return;
                            }

                            if (!document.getElementById('lab_id_doctor').value) {
                                Swal.fire('Aviso', 'Seleccione un doctor referente', 'warning');
                                return;
                            }

                            const pruebas = [];
                            document.querySelectorAll('.test-checkbox:checked').forEach(cb => pruebas.push(cb.value));

                            if (pruebas.length === 0) {
                                Swal.fire('Aviso', 'Seleccione al menos una prueba', 'warning');
                                return;
                            }

                            const data = {
                                id_paciente: patientHidden.value,
                                id_doctor: document.getElementById('lab_id_doctor').value,
                                observaciones: form.observaciones.value,
                                pruebas: pruebas,
                                tipo_pago: document.querySelector('input[name="order_tipo_pago"]:checked')?.value || 'Efectivo',
                                is_eps: isEPSMode,
                                custom_prices: {}
                            };

                            if (isEPSMode) {
                                document.querySelectorAll('.eps-price-input').forEach(input => {
                                    const id = input.getAttribute('data-id');
                                    const val = parseFloat(input.value) || 0; // Si está vacío o es 0, se guarda como 0
                                    data.custom_prices[id] = val;
                                });
                            }

                            const originalText = saveBtn.innerHTML;
                            saveBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Procesando...';
                            saveBtn.disabled = true;

                            try {
                                const response = await fetch('../laboratory/save_order.php', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/json' },
                                    body: JSON.stringify(data)
                                });
                                const result = await response.json();
                                if (result.status === 'success') {
                                    Swal.fire({
                                        title: '¡Orden Creada!',
                                        text: 'La orden y el cobro se han generado correctamente.',
                                        icon: 'success',
                                        showConfirmButton: false,
                                        timer: 1500
                                    }).then(() => {
                                        if (result.id_pago) {
                                            window.open(`../laboratory/print_lab_receipt.php?id=${result.id_pago}`, '_blank');
                                        }
                                        location.reload();
                                    });
                                } else {
                                    throw new Error(result.message);
                                }
                            } catch (e) {
                                Swal.fire('Error', e.message || 'Error al guardar orden', 'error');
                            } finally {
                                saveBtn.innerHTML = originalText;
                                saveBtn.disabled = false;
                            }
                        });
                    }

                    if (searchInput) {
                        searchInput.addEventListener('input', function () {
                            const term = this.value.toLowerCase().trim();
                            const items = document.querySelectorAll('.test-item');
                            const categories = document.querySelectorAll('.category-container');

                            items.forEach(item => {
                                const name = item.getAttribute('data-name');
                                item.classList.toggle('d-none', !name.includes(term));
                            });

                            categories.forEach(cat => {
                                const visibleItems = cat.querySelectorAll('.test-item:not(.d-none)');
                                cat.classList.toggle('d-none', visibleItems.length === 0);
                            });
                        });
                    }

                    const labPatientInput = document.getElementById('lab_paciente_input');
                    if (labPatientInput) {
                        labPatientInput.addEventListener('change', function () {
                            const datalist = document.getElementById('labDatalistOptions');
                            const val = this.value;
                            const hidden = document.getElementById('lab_id_paciente');
                            hidden.value = '';

                            for (let option of datalist.options) {
                                if (option.value === val) {
                                    hidden.value = option.getAttribute('data-id');
                                    break;
                                }
                            }
                        });
                    }
                }




                setupUltrasoundHandlers() {
                    const select = document.getElementById('ultrasoundSelect');
                    const amountInput = document.getElementById('ultrasound_amount');
                    const saveBtn = document.getElementById('saveUltrasoundBtn');

                    if (!select || !amountInput) return;

                    // Update price on select
                    // Update price on select or radio change
                    const updatePrice = () => {
                        const option = select.options[select.selectedIndex];
                        const rateType = document.querySelector('input[name="us_rate_type"]:checked').value;

                        let price = 0;
                        if (option.value) {
                            if (option.getAttribute('data-price') === 'Manual' || option.getAttribute('data-p-normal') === 'Manual') {
                                price = 'Manual';
                            } else {
                                switch (rateType) {
                                    case 'inhabil':
                                        price = parseFloat(option.getAttribute('data-p-inhabil')) || 0;
                                        break;
                                    case 'radio':
                                        price = parseFloat(option.getAttribute('data-p-radio')) || 0;
                                        break;
                                    case 'iradio':
                                        price = parseFloat(option.getAttribute('data-p-iradio')) || 0;
                                        break;
                                    default: // normal
                                        price = parseFloat(option.getAttribute('data-p-normal')) || 0;
                                }
                            }
                        }

                        if (price === 'Manual') {
                            amountInput.value = '';
                            amountInput.readOnly = false;
                            amountInput.placeholder = 'Ingrese monto...';
                        } else if (price > 0) {
                            amountInput.value = price.toFixed(2);
                            amountInput.readOnly = true;
                        } else {
                            amountInput.value = '';
                            amountInput.readOnly = true;
                        }
                    };

                    select.addEventListener('change', updatePrice);
                    document.querySelectorAll('.us-rate-type').forEach(radio => {
                        radio.addEventListener('change', updatePrice);
                    });

                    if (saveBtn) {
                        saveBtn.addEventListener('click', async () => {
                            const form = document.getElementById('ultrasoundBillingForm');
                            const patientInput = document.getElementById('ultrasound_patient_input');
                            const patientHidden = document.getElementById('ultrasound_patient_id');
                            const datalist = document.getElementById('ultrasoundPatientDatalist');

                            if (!form || !patientInput || !datalist) return;

                            patientHidden.value = '';
                            const val = patientInput.value;
                            const options = datalist.options;
                            for (let i = 0; i < options.length; i++) {
                                if (options[i].value === val) {
                                    patientHidden.value = options[i].getAttribute('data-id');
                                    break;
                                }
                            }

                            if (!patientHidden.value) {
                                Swal.fire('Aviso', 'Seleccione un paciente válido', 'warning');
                                return;
                            }

                            if (!form.checkValidity()) {
                                form.reportValidity();
                                return;
                            }

                            const originalText = saveBtn.innerHTML;
                            saveBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Guardando...';
                            saveBtn.disabled = true;

                            const formData = new FormData(form);
                            // Ensure name is passed if backend needs it, usually ID is enough but safe_us_charge might use it
                            // My implementation uses ID to fetch name from DB

                            try {
                                const response = await fetch('api/save_us_charge.php', {
                                    method: 'POST',
                                    body: formData
                                });
                                const result = await response.json();
                                if (result.status === 'success') {
                                    Swal.fire({
                                        title: 'Éxito',
                                        text: 'Ultrasonido registrado',
                                        icon: 'success',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        if (result.id) {
                                            window.open(`../ultrasonidos/print_us_receipt.php?id=${result.id}`, '_blank');
                                        }
                                        setTimeout(() => location.reload(), 500);
                                    });
                                } else {
                                    throw new Error(result.message);
                                }
                            } catch (e) {
                                Swal.fire('Error', e.message || 'Error de conexión', 'error');
                            } finally {
                                saveBtn.innerHTML = originalText;
                                saveBtn.disabled = false;
                            }
                        });
                    }
                }

                setupXrayHandlers() {
                    const saveBtn = document.getElementById('saveXrayBtn');
                    if (saveBtn) {
                        saveBtn.addEventListener('click', async () => {
                            const form = document.getElementById('xrayBillingForm');
                            const patientInput = document.getElementById('xray_patient_input');
                            const patientHidden = document.getElementById('xray_patient_id');
                            const datalist = document.getElementById('xrayPatientDatalist');

                            if (!form || !patientInput || !datalist) return;

                            patientHidden.value = '';
                            const val = patientInput.value;
                            const options = datalist.options;
                            for (let i = 0; i < options.length; i++) {
                                if (options[i].value === val) {
                                    patientHidden.value = options[i].getAttribute('data-id');
                                    break;
                                }
                            }

                            if (!patientHidden.value) {
                                Swal.fire('Aviso', 'Seleccione un paciente válido', 'warning');
                                return;
                            }

                            if (!form.checkValidity()) {
                                form.reportValidity();
                                return;
                            }

                            const originalText = saveBtn.innerHTML;
                            saveBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Guardando...';
                            saveBtn.disabled = true;

                            const formData = new FormData(form);

                            try {
                                const response = await fetch('api/save_rx_charge.php', {
                                    method: 'POST',
                                    body: formData
                                });
                                const result = await response.json();
                                if (result.status === 'success') {
                                    Swal.fire({
                                        title: 'Éxito',
                                        text: 'Rayos X registrado',
                                        icon: 'success',
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        if (result.id) {
                                            window.open(`../rayos_x/print_rx_receipt.php?id=${result.id}`, '_blank');
                                        }
                                        setTimeout(() => location.reload(), 500);
                                    });
                                } else {
                                    throw new Error(result.message);
                                }
                            } catch (e) {
                                Swal.fire('Error', e.message || 'Error de conexión', 'error');
                            } finally {
                                saveBtn.innerHTML = originalText;
                                saveBtn.disabled = false;
                            }
                        });
                    }
                }

                setupAnimations() {
                    const observer = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('animate-in');
                                observer.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.1 });

                    document.querySelectorAll('.stat-card, .appointments-section, .alert-card').forEach(el => observer.observe(el));
                }

                setupAdminNotifications() {
                    <?php if ($user_type === 'admin'): ?>
                        const lastDate = localStorage.getItem('dailyReportDate');
                        const today = new Date().toISOString().split('T')[0];
                        if (new Date().getHours() >= 8 && lastDate !== today) {
                            setTimeout(() => this.showDailyReportNotification(today), 2000);
                        }
                    <?php endif; ?>
                }
            }

            // ==========================================================================
            // OPTIMIZACIONES DE RENDIMIENTO
            // ==========================================================================
            class PerformanceOptimizer {
                constructor() {
                    this.setupLazyLoading();
                    this.setupAnalytics();
                }

                setupLazyLoading() {
                    if ('IntersectionObserver' in window) {
                        const lazyImages = document.querySelectorAll('img[data-src]');

                        const imageObserver = new IntersectionObserver((entries) => {
                            entries.forEach(entry => {
                                if (entry.isIntersecting) {
                                    const img = entry.target;
                                    img.src = img.dataset.src;
                                    img.removeAttribute('data-src');
                                    imageObserver.unobserve(img);
                                }
                            });
                        });

                        lazyImages.forEach(img => imageObserver.observe(img));
                    }
                }



                setupAnalytics() {
                    // Aquí iría la configuración de Google Analytics u otro sistema de análisis
                    console.log('Dashboard cargado - Usuario: <?php echo htmlspecialchars($user_name); ?>');
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                // Inicializar componentes
                const themeManager = new ThemeManager();
                const sidebarManager = new SidebarManager();
                const dynamicComponents = new DynamicComponents();
                const performanceOptimizer = new PerformanceOptimizer();

                // Exponer APIs necesarias globalmente
                window.dashboard = {
                    theme: themeManager,
                    sidebar: sidebarManager,
                    components: dynamicComponents
                };

                // Log de inicialización
                console.log('Dashboard CMS v4.0 inicializado correctamente');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
                console.log('Tema: ' + themeManager.theme);
                console.log('Sidebar: ' + (sidebarManager.isCollapsed ? 'colapsado' : 'expandido'));
            });

            // ==========================================================================
            // MANEJO DE ERRORES GLOBALES
            // ==========================================================================
            window.addEventListener('error', (event) => {
                console.error('Error en dashboard:', event.error);

                // En producción, enviar error al servidor
                if (window.location.hostname !== 'localhost') {
                    const errorData = {
                        message: event.message,
                        source: event.filename,
                        lineno: event.lineno,
                        colno: event.colno,
                        user: '<?php echo htmlspecialchars($user_name); ?>',
                        timestamp: new Date().toISOString()
                    };

                    // Aquí iría una petición fetch para enviar el error al servidor
                    console.log('Error reportado:', errorData);
                }
            });

            // ==========================================================================
            // POLYFILLS PARA NAVEGADORES ANTIGUOS
            // ==========================================================================
            if (!NodeList.prototype.forEach) {
                NodeList.prototype.forEach = Array.prototype.forEach;
            }

            if (!Element.prototype.matches) {
                Element.prototype.matches =
                    Element.prototype.matchesSelector ||
                    Element.prototype.mozMatchesSelector ||
                    Element.prototype.msMatchesSelector ||
                    Element.prototype.oMatchesSelector ||
                    Element.prototype.webkitMatchesSelector ||
                    function (s) {
                        const matches = (this.document || this.ownerDocument).querySelectorAll(s);
                        let i = matches.length;
                        while (--i >= 0 && matches.item(i) !== this) { }
                        return i > -1;
                    };
            }

        })();

        // Manejar envío del formulario de nuevo paciente
        document.getElementById('newPatientForm')?.addEventListener('submit', function (e) {
            e.preventDefault();

            // Mostrar indicador de carga
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Guardando...';
            submitBtn.disabled = true;

            // Simular envío asíncrono
            setTimeout(() => {
                // En un sistema real, aquí se haría una petición fetch
                this.submit();
            }, 1000);
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

    <!-- Inyectar script de mantenimiento de sesión activo (Global) -->
    <?php output_keep_alive_script(); ?>
    <!-- Modal Cobro Procedimientos -->
    <div class="modal fade" id="procedureBillingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-bandaid me-2"></i>Cobro de Procedimiento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="procedureBillingForm">
                        <div class="mb-3">
                            <label class="form-label">Paciente</label>
                            <input class="form-control" list="procedurePatientDatalist" id="procedure_patient_input"
                                placeholder="Buscar paciente..." required autocomplete="off">
                            <datalist id="procedurePatientDatalist">
                                <?php foreach ($pacientes as $paciente): ?>
                                    <option data-id="<?php echo $paciente['id_paciente']; ?>"
                                        value="<?php echo htmlspecialchars($paciente['nombre_completo']); ?>">
                                    <?php endforeach; ?>
                            </datalist>
                            <input type="hidden" id="procedure_patient_id" name="patient_id">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Procedimiento</label>
                            <select class="form-select" id="procedureSelect" name="procedure" required
                                onchange="updateProcedurePrice()">
                                <option value="">Seleccione...</option>
                                <option value="Inyeccion">Inyección</option>
                                <option value="Toma de Presion">Toma de Presión</option>
                                <option value="Glucometria">Glucometría</option>
                                <option value="Unicotomia">Unicotomía</option>
                                <option value="Lavado de Oido">Lavado de Oído</option>
                                <option value="Colacacion de Sonda Foley">Colocación de Sonda Foley</option>
                                <option value="Canalizacion con Solucion">Canalización con Solución</option>
                                <option value="Canalizacion con Stopper">Canalización con Stopper</option>
                                <option value="Sutura 1-5 pts">Sutura 1-5 pts</option>
                                <option value="Sutura 6-10 pts">Sutura 6-10 pts</option>
                                <option value="Sutura 11-15 pts">Sutura 11-15 pts</option>
                                <option value="Nebulizacion">Nebulización</option>
                                <option value="Curacion de herida">Curación de Herida</option>
                                <option value="Retiro de Puntos">Retiro de Puntos</option>
                                <option value="Suero Vitaminado">Suero Vitaminado</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Horario</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="schedule_type" id="scheduleHabil"
                                    value="habil" checked onchange="updateProcedurePrice()">
                                <label class="btn btn-outline-primary" for="scheduleHabil">Hábil</label>

                                <input type="radio" class="btn-check" name="schedule_type" id="scheduleInhabil"
                                    value="inhabil" onchange="updateProcedurePrice()">
                                <label class="btn btn-outline-primary" for="scheduleInhabil">Inhábil</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Precio (Q)</label>
                            <input type="number" class="form-control" name="amount" id="procedurePrice" readonly>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Tipo de Pago</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="tipo_pago" id="proc_pago_efectivo"
                                    value="Efectivo" checked autocomplete="off">
                                <label class="btn btn-outline-primary" for="proc_pago_efectivo">
                                    <i class="bi bi-cash me-1"></i>Efectivo
                                </label>

                                <input type="radio" class="btn-check" name="tipo_pago" id="proc_pago_transferencia"
                                    value="Transferencia" autocomplete="off">
                                <label class="btn btn-outline-primary" for="proc_pago_transferencia">
                                    <i class="bi bi-bank me-1"></i>Transferencia
                                </label>

                                <input type="radio" class="btn-check" name="tipo_pago" id="proc_pago_tarjeta"
                                    value="Tarjeta" autocomplete="off">
                                <label class="btn btn-outline-primary" for="proc_pago_tarjeta">
                                    <i class="bi bi-credit-card me-1"></i>Tarjeta
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="submitProcedureBilling()">Cobrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Ultrasonido -->
    <div class="modal fade" id="ultrasoundBillingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-activity me-2"></i>Cobro de Ultrasonido</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="ultrasoundBillingForm">
                        <div class="mb-3">
                            <label class="form-label">Paciente</label>
                            <input class="form-control" list="ultrasoundPatientDatalist" id="ultrasound_patient_input"
                                placeholder="Buscar paciente..." required autocomplete="off">
                            <datalist id="ultrasoundPatientDatalist">
                                <?php foreach ($pacientes as $paciente): ?>
                                    <option data-id="<?php echo $paciente['id_paciente']; ?>"
                                        value="<?php echo htmlspecialchars($paciente['nombre_completo']); ?>">
                                    <?php endforeach; ?>
                            </datalist>
                            <input type="hidden" id="ultrasound_patient_id" name="patient_id">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Ultrasonido</label>
                            <select class="form-select" id="ultrasoundSelect" name="ultrasound_type" required>
                                <option value="">Seleccione...</option>
                                <option value="ABDOMINAL SUPERIOR" data-p-normal="300" data-p-inhabil="350"
                                    data-p-radio="150" data-p-iradio="200">ABDOMINAL SUPERIOR</option>
                                <option value="CADERA" data-p-normal="500" data-p-inhabil="700" data-p-radio="250"
                                    data-p-iradio="450">CADERA</option>
                                <option value="CUELLO O TIROIDEO" data-p-normal="500" data-p-inhabil="700"
                                    data-p-radio="250" data-p-iradio="450">CUELLO O TIROIDEO</option>
                                <option value="HOMBRO" data-p-normal="500" data-p-inhabil="700" data-p-radio="250"
                                    data-p-iradio="450">HOMBRO</option>
                                <option value="MUÑECA" data-p-normal="500" data-p-inhabil="700" data-p-radio="250"
                                    data-p-iradio="450">MUÑECA</option>
                                <option value="INGUINAL" data-p-normal="500" data-p-inhabil="700" data-p-radio="250"
                                    data-p-iradio="400">INGUINAL</option>
                                <option value="OBSTETRICO" data-p-normal="300" data-p-inhabil="350" data-p-radio="150"
                                    data-p-iradio="200">OBSTETRICO</option>
                                <option value="ABDOMINAL INFERIOR (PELVICO)" data-p-normal="300" data-p-inhabil="350"
                                    data-p-radio="150" data-p-iradio="200">ABDOMINAL INFERIOR (PELVICO)</option>
                                <option value="ABDOMEN INFERIOR + FID" data-p-normal="300" data-p-inhabil="400"
                                    data-p-radio="150" data-p-iradio="250">ABDOMEN INFERIOR + FID</option>
                                <option value="ABDOMINAL COMPLETO" data-p-normal="500" data-p-inhabil="700"
                                    data-p-radio="250" data-p-iradio="400">ABDOMINAL COMPLETO</option>
                                <option value="ABDOMINAL PEDIATRICO MENORES A 2 AÑOS" data-p-normal="600"
                                    data-p-inhabil="750" data-p-radio="400" data-p-iradio="500">ABDOMINAL PEDIATRICO
                                    MENORES A 2 AÑOS</option>
                                <option value="ABDOMINAL PEDIATRICO" data-p-normal="450" data-p-inhabil="700"
                                    data-p-radio="300" data-p-iradio="500">ABDOMINAL PEDIATRICO</option>
                                <option value="ABDOMINAL SUPERIOR + FID" data-p-normal="350" data-p-inhabil="450"
                                    data-p-radio="150" data-p-iradio="250">ABDOMINAL SUPERIOR + FID</option>
                                <option value="AMBAS RODILLAS" data-p-normal="1000" data-p-inhabil="1400"
                                    data-p-radio="400" data-p-iradio="600">AMBAS RODILLAS</option>
                                <option value="RODILLA" data-p-normal="500" data-p-inhabil="700" data-p-radio="250"
                                    data-p-iradio="450">RODILLA</option>
                                <option value="DOPLER ARTERIAL UNA EXTREMIDAD" data-p-normal="700" data-p-inhabil="900"
                                    data-p-radio="600" data-p-iradio="1000">DOPLER ARTERIAL UNA EXTREMIDAD</option>
                                <option value="DOPPLER CAROTIDEO" data-p-normal="700" data-p-inhabil="900"
                                    data-p-radio="500" data-p-iradio="900">DOPPLER CAROTIDEO</option>
                                <option value="DOPPLER VENOSO UNA EXTREMIDAD" data-p-normal="700" data-p-inhabil="900"
                                    data-p-radio="600" data-p-iradio="1000">DOPPLER VENOSO UNA EXTREMIDAD</option>
                                <option value="ENDOVAGINAL" data-p-normal="350" data-p-inhabil="450" data-p-radio="150"
                                    data-p-iradio="250">ENDOVAGINAL</option>
                                <option value="GUIA ECOGAFRICA PARA BIOPSIA" data-p-normal="550" data-p-inhabil="700"
                                    data-p-radio="350" data-p-iradio="450">GUIA ECOGAFRICA PARA BIOPSIA</option>
                                <option value="GUIA ECOGRAFICA PARA DRENAJE DE ABSCESO" data-p-normal="500"
                                    data-p-inhabil="700" data-p-radio="300" data-p-iradio="400">GUIA ECOGRAFICA PARA
                                    DRENAJE DE ABSCESO</option>
                                <option value="GUIA PARA PARACENTESIS" data-p-normal="400" data-p-inhabil="550"
                                    data-p-radio="250" data-p-iradio="350">GUIA PARA PARACENTESIS</option>
                                <option value="HEPATICO Y VIAS BILIARES PEDIATRICO MENORES A 2 AÑOS" data-p-normal="380"
                                    data-p-inhabil="580" data-p-radio="200" data-p-iradio="400">HEPATICO Y VIAS BILIARES
                                    PEDIATRICO MENORES A 2 AÑOS</option>
                                <option value="HEPATICO Y VIAS BILIARES" data-p-normal="350" data-p-inhabil="500"
                                    data-p-radio="150" data-p-iradio="250">HEPATICO Y VIAS BILIARES</option>
                                <option value="INGUINO- ESCROTAL" data-p-normal="350" data-p-inhabil="550"
                                    data-p-radio="200" data-p-iradio="400">INGUINO- ESCROTAL</option>
                                <option value="MAMARIO" data-p-normal="500" data-p-inhabil="700" data-p-radio="250"
                                    data-p-iradio="450">MAMARIO</option>
                                <option value="MUSCULAR PARTES BLANDAS" data-p-normal="500" data-p-inhabil="700"
                                    data-p-radio="250" data-p-iradio="450">MUSCULAR PARTES BLANDAS</option>
                                <option value="OBSTETRICO GEMELAR" data-p-normal="400" data-p-inhabil="450"
                                    data-p-radio="200" data-p-iradio="250">OBSTETRICO GEMELAR</option>
                                <option value="PARED ABDMINAL E INGUINAL" data-p-normal="500" data-p-inhabil="700"
                                    data-p-radio="200" data-p-iradio="400">PARED ABDMINAL E INGUINAL</option>
                                <option value="PILORO" data-p-normal="250" data-p-inhabil="350" data-p-radio="250"
                                    data-p-iradio="450">PILORO</option>
                                <option value="PROSTATICO" data-p-normal="250" data-p-inhabil="350" data-p-radio="150"
                                    data-p-iradio="250">PROSTATICO</option>
                                <option value="PROSTATICO ENDORECTAL" data-p-normal="350" data-p-inhabil="600"
                                    data-p-radio="200" data-p-iradio="400">PROSTATICO ENDORECTAL</option>
                                <option value="RENAL PEDIATRICO MENOR A 2 AÑOS" data-p-normal="300" data-p-inhabil="600"
                                    data-p-radio="250" data-p-iradio="400">RENAL PEDIATRICO MENOR A 2 AÑOS</option>
                                <option value="RENAL" data-p-normal="250" data-p-inhabil="350" data-p-radio="150"
                                    data-p-iradio="200">RENAL</option>
                                <option value="renal y vias urinarias" data-p-normal="450" data-p-inhabil="350"
                                    data-p-radio="150" data-p-iradio="200">renal y vias urinarias</option>
                                <option value="TEJIDOS BLANDOS - MUSCULAR" data-p-normal="350" data-p-inhabil="600"
                                    data-p-radio="250" data-p-iradio="450">TEJIDOS BLANDOS - MUSCULAR</option>
                                <option value="TENDON DE AQUILES" data-p-normal="500" data-p-inhabil="700"
                                    data-p-radio="200" data-p-iradio="400">TENDON DE AQUILES</option>
                                <option value="TESTICULAR O ESCROTAL" data-p-normal="500" data-p-inhabil="700"
                                    data-p-radio="200" data-p-iradio="400">TESTICULAR O ESCROTAL</option>
                                <option value="4D" data-p-normal="500" data-p-inhabil="650" data-p-radio="500"
                                    data-p-iradio="650">4D</option>
                                <option value="5D" data-p-normal="600" data-p-inhabil="700" data-p-radio="600"
                                    data-p-iradio="700">5D</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">Horario / Tarifa</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check us-rate-type" name="us_rate_type"
                                    id="us_rate_normal" value="normal" checked>
                                <label class="btn btn-outline-secondary" for="us_rate_normal">Normal</label>

                                <input type="radio" class="btn-check us-rate-type" name="us_rate_type"
                                    id="us_rate_inhabil" value="inhabil">
                                <label class="btn btn-outline-secondary" for="us_rate_inhabil">Inhabil</label>

                                <input type="radio" class="btn-check us-rate-type" name="us_rate_type"
                                    id="us_rate_radio" value="radio">
                                <label class="btn btn-outline-secondary" for="us_rate_radio">Radiólogo</label>

                                <input type="radio" class="btn-check us-rate-type" name="us_rate_type"
                                    id="us_rate_iradio" value="iradio">
                                <label class="btn btn-outline-secondary" for="us_rate_iradio">Inh. Rad.</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Monto a Cobrar (Q)</label>
                            <input type="number" class="form-control" id="ultrasound_amount" name="amount" readonly
                                step="0.01" placeholder="0.00">
                            <small class="text-muted">El monto se actualiza al seleccionar el tipo y tarifa</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Pago</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="tipo_pago" id="ultrasound_pago_efectivo"
                                    value="Efectivo" checked autocomplete="off">
                                <label class="btn btn-outline-info" for="ultrasound_pago_efectivo">
                                    <i class="bi bi-cash me-1"></i>Efectivo
                                </label>
                                <input type="radio" class="btn-check" name="tipo_pago"
                                    id="ultrasound_pago_transferencia" value="Transferencia" autocomplete="off">
                                <label class="btn btn-outline-info" for="ultrasound_pago_transferencia">
                                    <i class="bi bi-bank me-1"></i>Transferencia
                                </label>
                                <input type="radio" class="btn-check" name="tipo_pago" id="ultrasound_pago_tarjeta"
                                    value="Tarjeta" autocomplete="off">
                                <label class="btn btn-outline-info" for="ultrasound_pago_tarjeta">
                                    <i class="bi bi-credit-card me-1"></i>Tarjeta
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-info" id="saveUltrasoundBtn">Guardar Cobro</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Rayos X -->
    <div class="modal fade" id="xrayBillingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-file-medical me-2"></i>Cobro de Rayos X</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="xrayBillingForm">
                        <div class="mb-3">
                            <label class="form-label">Paciente</label>
                            <input class="form-control" list="xrayPatientDatalist" id="xray_patient_input"
                                placeholder="Buscar paciente..." required autocomplete="off">
                            <datalist id="xrayPatientDatalist">
                                <?php foreach ($pacientes as $paciente): ?>
                                    <option data-id="<?php echo $paciente['id_paciente']; ?>"
                                        value="<?php echo htmlspecialchars($paciente['nombre_completo']); ?>">
                                    <?php endforeach; ?>
                            </datalist>
                            <input type="hidden" id="xray_patient_id" name="patient_id">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Estudio (Rayos X)</label>
                            <input type="text" class="form-control" name="xray_type" required
                                placeholder="Ej: Torax, Mano, etc.">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Monto a Cobrar (Q)</label>
                            <input type="number" class="form-control" id="xray_amount" name="amount" required
                                step="0.01" placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Tipo de Pago</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="tipo_pago" id="xray_pago_efectivo"
                                    value="Efectivo" checked autocomplete="off">
                                <label class="btn btn-outline-secondary" for="xray_pago_efectivo">
                                    <i class="bi bi-cash me-1"></i>Efectivo
                                </label>
                                <input type="radio" class="btn-check" name="tipo_pago" id="xray_pago_transferencia"
                                    value="Transferencia" autocomplete="off">
                                <label class="btn btn-outline-secondary" for="xray_pago_transferencia">
                                    <i class="bi bi-bank me-1"></i>Transferencia
                                </label>
                                <input type="radio" class="btn-check" name="tipo_pago" id="xray_pago_tarjeta"
                                    value="Tarjeta" autocomplete="off">
                                <label class="btn btn-outline-secondary" for="xray_pago_tarjeta">
                                    <i class="bi bi-credit-card me-1"></i>Tarjeta
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-secondary" id="saveXrayBtn">Guardar Cobro</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const procedurePrices = {
            'Inyeccion': { habil: 5, inhabil: 10 },
            'Toma de Presion': { habil: 5, inhabil: 10 },
            'Glucometria': { habil: 25, inhabil: 30 },
            'Unicotomia': { habil: 125, inhabil: 150 },
            'Lavado de Oido': { habil: 100, inhabil: 150 },
            'Colacacion de Sonda Foley': { habil: 200, inhabil: 250 },
            'Canalizacion con Solucion': { habil: 175, inhabil: 250 },
            'Canalizacion con Stopper': { habil: 75, inhabil: 125 },
            'Sutura 1-5 pts': { habil: 300, inhabil: 400 },
            'Sutura 6-10 pts': { habil: 500, inhabil: 650 },
            'Sutura 11-15 pts': { habil: 750, inhabil: 900 },
            'Nebulizacion': { habil: 40, inhabil: 65 },
            'Curacion de herida': { habil: 100, inhabil: 150 },
            'Retiro de Puntos': { habil: 50, inhabil: 100 },
            'Suero Vitaminado': { habil: 800, inhabil: 1100 }
        };

        function updateProcedurePrice() {
            const procedure = document.getElementById('procedureSelect').value;
            const isHabil = document.getElementById('scheduleHabil').checked;
            const priceField = document.getElementById('procedurePrice');

            if (procedure && procedurePrices[procedure]) {
                const price = isHabil ? procedurePrices[procedure].habil : procedurePrices[procedure].inhabil;
                priceField.value = price.toFixed(2);
            } else {
                priceField.value = '';
            }
        }

        function submitProcedureBilling() {
            const form = document.getElementById('procedureBillingForm');
            const patientInput = document.getElementById('procedure_patient_input');
            const patientHidden = document.getElementById('procedure_patient_id');
            const datalist = document.getElementById('procedurePatientDatalist');
            const procedure = document.getElementById('procedureSelect').value;

            // Validar paciente seleccionado del datalist
            patientHidden.value = '';
            const val = patientInput.value;
            const options = datalist.options;
            for (let i = 0; i < options.length; i++) {
                if (options[i].value === val) {
                    patientHidden.value = options[i].getAttribute('data-id');
                    break;
                }
            }

            if (!patientHidden.value) {
                Swal.fire('Aviso', 'Por favor seleccione un paciente válido de la lista', 'warning');
                return;
            }

            if (!procedure) {
                Swal.fire('Aviso', 'Por favor seleccione un procedimiento', 'warning');
                return;
            }

            const formData = new FormData(form);

            fetch('api/save_procedure_charge.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire('Éxito', 'Cobro registrado exitosamente', 'success').then(() => {
                            window.open('print_procedure_receipt.php?id=' + data.id, '_blank');
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.message || 'Error desconocido', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'Error al procesar el cobro', 'error');
                });
        }
    </script>
</body>

</html>