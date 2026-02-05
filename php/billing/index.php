<?php
// index.php - Módulo de Cobros - Centro Médico RS
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

    // Obtener todos los pacientes para el dropdown
    $stmt = $conn->prepare("SELECT id_paciente, CONCAT(nombre, ' ', apellido) as nombre_completo FROM pacientes ORDER BY nombre");
    $stmt->execute();
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener doctores (usuarios tipo 'doc')
    $stmtDoc = $conn->prepare("SELECT idUsuario, nombre, apellido FROM usuarios WHERE tipoUsuario = 'doc' ORDER BY nombre");
    $stmtDoc->execute();
    $doctores = $stmtDoc->fetchAll(PDO::FETCH_ASSOC);

    // Obtener cobros con paginación
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $limit = 25;
    $offset = ($page - 1) * $limit;

    // Obtener total para paginación
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM cobros");
    $stmt->execute();
    $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);

    // Obtener datos de cobros con nombre de paciente
    $stmt = $conn->prepare("
        SELECT c.*, CONCAT(p.nombre, ' ', p.apellido) as nombre_paciente 
        FROM cobros c
        JOIN pacientes p ON c.paciente_cobro = p.id_paciente
        ORDER BY c.fecha_consulta DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $cobros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Título de la página
    $page_title = "Cobros - Centro Médico RS";

    // Obtener estadísticas rápidas
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM cobros WHERE DATE(fecha_consulta) = CURDATE()");
    $stmt->execute();
    $hoy_cobros = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $stmt = $conn->prepare("SELECT SUM(cantidad_consulta) as total FROM cobros WHERE MONTH(fecha_consulta) = MONTH(CURDATE())");
    $stmt->execute();
    $mes_total = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en módulo de cobros: " . $e->getMessage());
    die("Error al cargar el módulo de cobros. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Módulo de Cobros - Centro Médico RS - Sistema de gestión de cobros médicos">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Bootstrap CSS (Required for Modals) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            width: 100%;
            transition: all var(--transition-base);
        }

        /* User Details (Footer replacement) */
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
        .billing-section {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            transition: all var(--transition-base);
        }

        .billing-section:hover {
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

        .billing-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .billing-table thead {
            background: var(--color-surface);
        }

        .billing-table th {
            padding: var(--space-md);
            text-align: left;
            font-weight: 600;
            color: var(--color-text);
            border-bottom: 2px solid var(--color-border);
            white-space: nowrap;
        }

        .billing-table td {
            padding: var(--space-md);
            border-bottom: 1px solid var(--color-border);
            vertical-align: middle;
        }

        .billing-table tbody tr {
            transition: all var(--transition-base);
        }

        .billing-table tbody tr:hover {
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

        .amount-badge {
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

        .btn-icon.view:hover {
            background: var(--color-info);
            color: white;
            border-color: var(--color-info);
        }

        .btn-icon.print:hover {
            background: var(--color-success);
            color: white;
            border-color: var(--color-success);
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

            .billing-table {
                font-size: var(--font-size-sm);
            }

            .billing-table th,
            .billing-table td {
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
            .billing-section {
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
            .billing-section,
            .alert-card {
                break-inside: avoid;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }

            .billing-table th {
                background: #f0f0f0 !important;
                color: black !important;
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
                        <h2 class="stat-value" style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Módulo de Cobros</span>
                        </h2>
                        <p class="text-muted mb-0">
                            <i class="bi bi-cash-coin me-1"></i> Gestión de recaudación y recibos médicos
                            <span class="mx-2">•</span>
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-clock me-1"></i> <span id="current-time"><?php echo date('H:i'); ?></span>
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-cash-coin text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Estadísticas principales -->
            <div class="stats-grid">
                <!-- Cobros de hoy -->
                <div class="stat-card animate-in delay-1">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Cobros Hoy</div>
                            <div class="stat-value"><?php echo $hoy_cobros; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-arrow-up-right"></i>
                        <span>Registrados hoy</span>
                    </div>
                </div>

                <!-- Total del mes -->
                <div class="stat-card animate-in delay-2">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total Mes</div>
                            <div class="stat-value">Q<?php echo number_format($mes_total, 2); ?></div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-currency-dollar"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-graph-up"></i>
                        <span>Recaudación mensual</span>
                    </div>
                </div>

                <!-- Total cobros -->
                <div class="stat-card animate-in delay-3">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total Cobros</div>
                            <div class="stat-value"><?php echo $total_records; ?></div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-receipt"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-archive"></i>
                        <span>Registros totales</span>
                    </div>
                </div>

                <!-- Páginas -->
                <div class="stat-card animate-in delay-4">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Página</div>
                            <div class="stat-value"><?php echo $page; ?>/<?php echo $total_pages; ?></div>
                        </div>
                        <div class="stat-icon warning">
                            <i class="bi bi-file-text"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-collection"></i>
                        <span>Paginación</span>
                    </div>
                </div>
            </div>

            <!-- Sección de cobros -->
            <section class="billing-section animate-in delay-1">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-receipt-cutoff section-title-icon"></i>
                        Registro de Cobros
                    </h3>
                    <div class="d-flex gap-2">
                        <button type="button" class="action-btn" data-bs-toggle="modal"
                            data-bs-target="#newBillingModal">
                            <i class="bi bi-plus-lg"></i>
                            Nuevo Cobro
                        </button>
                        <a href="export_cobros.php" class="action-btn secondary">
                            <i class="bi bi-download"></i>
                            Exportar
                        </a>
                    </div>
                </div>

                <?php if (count($cobros) > 0): ?>
                    <div class="table-responsive">
                        <table class="billing-table">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Monto</th>
                                    <th>Fecha</th>
                                    <th>ID Cobro</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cobros as $cobro): ?>
                                    <?php
                                    $patient_name = htmlspecialchars($cobro['nombre_paciente']);
                                    $patient_initials = strtoupper(
                                        substr(explode(' ', $cobro['nombre_paciente'])[0], 0, 1) .
                                        (isset(explode(' ', $cobro['nombre_paciente'])[1]) ? substr(explode(' ', $cobro['nombre_paciente'])[1], 0, 1) : '')
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
                                            <span class="amount-badge">
                                                <i class="bi bi-currency-dollar"></i>
                                                Q<?php echo number_format($cobro['cantidad_consulta'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($cobro['fecha_consulta'])); ?>
                                        </td>
                                        <td>
                                            <span
                                                class="badge bg-secondary">#<?php echo str_pad($cobro['in_cobro'], 5, '0', STR_PAD_LEFT); ?></span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="print_receipt.php?id=<?php echo $cobro['in_cobro']; ?>" target="_blank"
                                                    class="btn-icon print" title="Imprimir recibo">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                                <button type="button" class="btn-icon view view-details"
                                                    data-id="<?php echo $cobro['in_cobro']; ?>"
                                                    data-nombre="<?php echo htmlspecialchars($cobro['nombre_paciente']); ?>"
                                                    data-monto="<?php echo $cobro['cantidad_consulta']; ?>"
                                                    data-fecha="<?php echo date('d/m/Y', strtotime($cobro['fecha_consulta'])); ?>"
                                                    title="Ver detalles">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>

                                <?php
                                $range = 2;
                                $start = max(1, $page - $range);
                                $end = min($total_pages, $page + $range);

                                if ($start > 1): ?>
                                    <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
                                    <?php if ($start > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $start; $i <= $end; $i++): ?>
                                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($end < $total_pages): ?>
                                    <?php if ($end < $total_pages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item"><a class="page-link"
                                            href="?page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a></li>
                                <?php endif; ?>

                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <h4 class="text-muted mb-2">No hay cobros registrados</h4>
                        <p class="text-muted mb-3">Comience registrando un nuevo cobro</p>
                        <button type="button" class="action-btn" data-bs-toggle="modal" data-bs-target="#newBillingModal">
                            <i class="bi bi-plus-lg"></i>
                            Registrar primer cobro
                        </button>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <!-- Modal para nuevo cobro -->
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
                            <input type="text" name="paciente_nombre" class="form-control" list="datalistOptions"
                                id="paciente_input" placeholder="Nombre del paciente (o seleccione de la lista)..."
                                required autocomplete="off">
                            <datalist id="datalistOptions">
                                <?php foreach ($pacientes as $paciente): ?>
                                    <option data-id="<?php echo $paciente['id_paciente']; ?>"
                                        value="<?php echo htmlspecialchars($paciente['nombre_completo']); ?>">
                                    <?php endforeach; ?>
                            </datalist>
                            <input type="hidden" id="paciente" name="paciente">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Médico que atiende</label>
                            <select class="form-select" id="id_doctor" name="id_doctor" required>
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
                            <label class="form-label fw-bold">Tipo de Consulta</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="tipo_consulta" id="billing_btn_consulta"
                                    value="Consulta" checked autocomplete="off">
                                <label class="btn btn-outline-success" for="billing_btn_consulta">Consulta</label>

                                <input type="radio" class="btn-check" name="tipo_consulta" id="billing_btn_reconsulta"
                                    value="Reconsulta" autocomplete="off">
                                <label class="btn btn-outline-success" for="billing_btn_reconsulta">Re-Consulta</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Monto a Cobrar (Q)</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-success text-white border-0">Q</span>
                                <input type="number" class="form-control border-success text-success fw-bold"
                                    id="cantidad" name="cantidad" min="0" step="0.01" placeholder="0.00" required>
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

    <!-- JavaScript Optimizado -->
    <script>
        // Módulo de Cobros Reingenierizado - Centro Médico RS

        (function () {
            'use strict';

            // ==========================================================================
            // CONFIGURACIÓN Y CONSTANTES
            // ==========================================================================
            const CONFIG = {
                themeKey: 'dashboard-theme',
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
                saveBillingBtn: document.getElementById('saveBillingBtn'),
                newBillingForm: document.getElementById('newBillingForm')
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
            // COMPONENTES DINÁMICOS
            // ==========================================================================
            class DynamicComponents {
                constructor() {
                    this.setupGreeting();
                    this.setupClock();
                    this.setupBillingHandlers();
                    this.setupAnimations();
                    this.setupModalDetails();
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

                setupBillingHandlers() {
                    const doctorSelect = document.getElementById('id_doctor');
                    const montoInput = document.getElementById('cantidad');
                    const tipoRadios = document.getElementsByName('tipo_consulta');

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

                    doctorSelect?.addEventListener('change', calculatePrice);
                    tipoRadios.forEach(r => r.addEventListener('change', calculatePrice));

                    // Guardar nuevo cobro
                    if (DOM.saveBillingBtn) {
                        DOM.saveBillingBtn.addEventListener('click', async () => {
                            const form = DOM.newBillingForm;

                            // Sync patient ID from datalist
                            const patientInput = document.getElementById('paciente_input');
                            const patientHidden = document.getElementById('paciente');
                            const datalist = document.getElementById('datalistOptions');

                            // Reset ID
                            patientHidden.value = '';

                            // Find ID based on name value
                            if (patientInput && datalist) {
                                const val = patientInput.value;
                                const options = datalist.options;
                                for (let i = 0; i < options.length; i++) {
                                    if (options[i].value === val) {
                                        patientHidden.value = options[i].getAttribute('data-id');
                                        break;
                                    }
                                }
                            }

                            // If no ID found (custom name), it will be handled by the backend using patient_nombre
                            // Just ensure some text is present
                            if (patientInput.value.trim() === '') {
                                Swal.fire({ title: 'Campo requerido', text: 'Por favor ingrese el nombre del paciente.', icon: 'warning' });
                                return;
                            }

                            // Validar formulario
                            if (!form.checkValidity()) {
                                form.reportValidity();
                                return;
                            }

                            const formData = new FormData(form);
                            const data = Object.fromEntries(formData.entries());

                            // Mostrar indicador de carga
                            const originalText = DOM.saveBillingBtn.innerHTML;
                            DOM.saveBillingBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Guardando...';
                            DOM.saveBillingBtn.disabled = true;

                            try {
                                const response = await fetch('save_billing.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded',
                                    },
                                    body: new URLSearchParams(data)
                                });

                                const result = await response.json();

                                if (result.status === 'success') {
                                    // Mostrar notificación de éxito
                                    Swal.fire({
                                        title: '¡Éxito!',
                                        text: 'Cobro guardado correctamente',
                                        icon: 'success',
                                        confirmButtonColor: 'var(--color-primary)',
                                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                                    }).then(() => {
                                        // Cerrar modal y recargar
                                        const modal = bootstrap.Modal.getInstance(document.getElementById('newBillingModal'));
                                        modal.hide();
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'Error',
                                        text: result.message || 'Error al guardar el cobro',
                                        icon: 'error',
                                        confirmButtonColor: 'var(--color-primary)'
                                    });
                                }
                            } catch (error) {
                                console.error('Error:', error);
                                Swal.fire({
                                    title: 'Error',
                                    text: 'Error de conexión con el servidor',
                                    icon: 'error',
                                    confirmButtonColor: 'var(--color-primary)'
                                });
                            } finally {
                                DOM.saveBillingBtn.innerHTML = originalText;
                                DOM.saveBillingBtn.disabled = false;
                            }
                        });
                    }
                }

                setupModalDetails() {
                    // Mostrar detalles en modal
                    document.querySelectorAll('.view-details').forEach(btn => {
                        btn.addEventListener('click', function () {
                            const id = this.getAttribute('data-id');
                            const nombre = this.getAttribute('data-nombre');
                            const monto = this.getAttribute('data-monto');
                            const fecha = this.getAttribute('data-fecha');

                            Swal.fire({
                                title: 'Detalles del Cobro',
                                html: `
                                <div class="text-start">
                                    <p><strong>ID:</strong> #${id.toString().padStart(5, '0')}</p>
                                    <p><strong>Paciente:</strong> ${nombre}</p>
                                    <p><strong>Monto:</strong> Q${parseFloat(monto).toFixed(2)}</p>
                                    <p><strong>Fecha:</strong> ${fecha}</p>
                                </div>
                            `,
                                icon: 'info',
                                showCancelButton: true,
                                confirmButtonText: 'Imprimir Recibo',
                                cancelButtonText: 'Cerrar',
                                confirmButtonColor: 'var(--color-primary)',
                                background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                                color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    window.open(`print_receipt.php?id=${id}`, '_blank');
                                }
                            });
                        });
                    });
                }

                setupAnimations() {
                    // Animar elementos al cargar
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

                    // Observar elementos con clase de animación
                    document.querySelectorAll('.stat-card, .billing-section').forEach(el => {
                        observer.observe(el);
                    });
                }
            }

            // ==========================================================================
            // OPTIMIZACIONES DE RENDIMIENTO
            // ==========================================================================
            class PerformanceOptimizer {
                constructor() {
                    this.setupAnalytics();
                }

                setupAnalytics() {
                    console.log('Módulo de Cobros cargado - Usuario: <?php echo htmlspecialchars($user_name); ?>');
                    console.log('Total cobros: <?php echo $total_records; ?>');
                    console.log('Recaudación mensual: Q<?php echo number_format($mes_total, 2); ?>');
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                // Inicializar componentes
                const themeManager = new ThemeManager();
                const dynamicComponents = new DynamicComponents();
                const performanceOptimizer = new PerformanceOptimizer();

                // Exponer APIs necesarias globalmente
                window.cobrosModule = {
                    theme: themeManager,
                    components: dynamicComponents
                };

                // Log de inicialización
                console.log('Módulo de Cobros CMS inicializado correctamente');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Rol: <?php echo htmlspecialchars($user_type); ?>');
                console.log('Tema: ' + themeManager.theme);
            });

            // ==========================================================================
            // MANEJO DE ERRORES GLOBALES
            // ==========================================================================
            window.addEventListener('error', (event) => {
                console.error('Error en módulo de cobros:', event.error);

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
        
        /* Estilos para modal */
        .modal-content {
            background-color: var(--color-card);
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }
        
        .modal-header {
            border-bottom: 1px solid var(--color-border);
        }
        
        .modal-footer {
            border-top: 1px solid var(--color-border);
        }
        
        .btn-close {
            filter: var(--data-theme) === 'dark' ? 'invert(1)' : 'none';
        }
        
        .form-control {
            background-color: var(--color-surface);
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }
        
        .form-control:focus {
            background-color: var(--color-surface);
            color: var(--color-text);
            border-color: var(--color-primary);
            box-shadow: 0 0 0 0.25rem rgba(var(--color-primary-rgb), 0.25);
        }
        
        .input-group-text {
            background-color: var(--color-surface);
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }
    `;
        document.head.appendChild(style);

        // Modales se inicializan automáticamente vía data-attributes en Bootstrap 5
        // Eliminamos la inicialización manual para evitar conflictos
    </script>

    <!-- jQuery (required for Bootstrap modals) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>