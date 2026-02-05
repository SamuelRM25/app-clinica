<?php
// laboratory/catalogo_pruebas.php - Management of Clinical Tests
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

// Only admins can manage the catalog
if ($_SESSION['tipoUsuario'] !== 'admin' && $_SESSION['user_id'] != 7) {
    header("Location: index.php");
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Fetch all tests with their parameter count
    $stmt = $conn->query("
        SELECT cp.*, COUNT(pp.id_parametro) as num_parametros
        FROM catalogo_pruebas cp
        LEFT JOIN parametros_pruebas pp ON cp.id_prueba = pp.id_prueba
        GROUP BY cp.id_prueba
        ORDER BY cp.categoria, cp.nombre_prueba
    ");
    $catalogo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by category for the UI
    $pruebas_por_categoria = [];
    foreach ($catalogo as $prueba) {
        $pruebas_por_categoria[$prueba['categoria'] ?? 'Sin Categoría'][] = $prueba;
    }

    // Estadísticas para la página
    $total_pruebas = count($catalogo);
    $total_categorias = count($pruebas_por_categoria);

    $page_title = "Catálogo de Pruebas - Laboratorio";
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Catálogo de Pruebas de Laboratorio - Centro Médico RS">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- CSS Crítico (mismo que index.php) -->
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
       COMPONENTES DE DASHBOARD - ADAPTADOS PARA CATÁLOGO
       ========================================================================== */

        /* Banner de bienvenida específico para catálogo */
        .welcome-banner {
            background: linear-gradient(135deg, var(--color-primary), var(--color-info));
            color: white;
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
            margin-bottom: var(--space-xl);
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .welcome-banner h1 {
            font-size: var(--font-size-3xl);
            font-weight: 700;
            margin-bottom: var(--space-sm);
        }

        .welcome-banner p {
            opacity: 0.9;
            font-size: var(--font-size-lg);
        }

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

        .stat-icon.danger {
            background: rgba(var(--color-danger-rgb), 0.1);
            color: var(--color-danger);
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
        .catalog-section {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            transition: all var(--transition-base);
        }

        .catalog-section:hover {
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

        .action-btn.secondary {
            background: var(--color-surface);
            color: var(--color-text);
            border: 1px solid var(--color-border);
        }

        .action-btn.secondary:hover {
            background: var(--color-primary);
            color: white;
            border-color: var(--color-primary);
        }

        /* Categorías de pruebas */
        .category-header {
            background: linear-gradient(90deg, rgba(var(--color-primary-rgb), 0.1), transparent);
            padding: var(--space-md) var(--space-lg);
            border-radius: var(--radius-md);
            margin: var(--space-xl) 0 var(--space-lg) 0;
            font-weight: 600;
            color: var(--color-primary);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            border-left: 4px solid var(--color-primary);
        }

        /* Grid de pruebas */
        .tests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--space-lg);
            margin-bottom: var(--space-xl);
        }

        /* Tarjetas de pruebas */
        .test-card {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            transition: all var(--transition-base);
            display: flex;
            flex-direction: column;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .test-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
            border-color: var(--color-primary);
        }

        .test-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--color-info), var(--color-primary));
        }

        .test-header {
            margin-bottom: var(--space-md);
        }

        .test-code {
            font-size: var(--font-size-xs);
            font-weight: 700;
            color: var(--color-text-secondary);
            text-transform: uppercase;
            margin-bottom: var(--space-xs);
        }

        .test-name {
            font-size: var(--font-size-lg);
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: var(--space-sm);
            line-height: 1.3;
        }

        .test-details {
            margin-bottom: var(--space-lg);
            flex-grow: 1;
        }

        .test-detail {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            margin-bottom: var(--space-xs);
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
        }

        .test-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: var(--space-md);
            border-top: 1px solid var(--color-border);
        }

        .test-price {
            font-size: var(--font-size-xl);
            font-weight: 700;
            color: var(--color-success);
        }

        .test-params {
            background: rgba(var(--color-primary-rgb), 0.1);
            color: var(--color-primary);
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-sm);
            font-size: var(--font-size-xs);
            font-weight: 600;
        }

        /* Botones de acción en tarjetas */
        .test-actions {
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
            cursor: pointer;
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

        .btn-icon.manage:hover {
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

        /* ==========================================================================
       RESPONSIVE DESIGN
       ========================================================================== */

        /* Tablets y pantallas medianas */
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

            .tests-grid {
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

            .welcome-banner {
                padding: var(--space-lg);
            }

            .welcome-banner h1 {
                font-size: var(--font-size-2xl);
            }

            .category-header {
                margin: var(--space-lg) 0 var(--space-md) 0;
                padding: var(--space-sm) var(--space-md);
            }
        }

        /* Móviles */
        @media (max-width: 767px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .tests-grid {
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

            .test-card {
                padding: var(--space-md);
            }

            .test-name {
                font-size: var(--font-size-base);
            }

            .test-price {
                font-size: var(--font-size-lg);
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

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: calc(var(--space-lg) * -1) 0;
        }

        .row>* {
            padding: var(--space-lg) 0;
        }

        .col-lg-8 {
            flex: 0 0 66.666667%;
            max-width: 66.666667%;
        }

        .col-lg-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }

        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
        }

        .col-md-4 {
            flex: 0 0 33.333333%;
            max-width: 33.333333%;
        }

        .col-md-3 {
            flex: 0 0 25%;
            max-width: 25%;
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
    </style>

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Bootstrap CSS/JS (Required for Modal) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
                            <?php echo strtoupper(substr($_SESSION['nombre'], 0, 1)); ?>
                        </div>
                        <div class="header-details">
                            <span class="header-name"><?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
                            <span class="header-role">Administrador de Laboratorio</span>
                        </div>
                    </div>

                    <!-- Botón de volver -->
                    <a href="index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i>
                        Volver a Laboratorios
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido Principal -->
        <main class="main-content">
            <!-- Banner de bienvenida -->
            <div class="welcome-banner animate-in">
                <h1>Catálogo de Pruebas</h1>
                <p>Administre las pruebas disponibles en el laboratorio</p>
            </div>

            <!-- Estadísticas del catálogo -->
            <div class="stats-grid">
                <div class="stat-card animate-in delay-1">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total de Pruebas</div>
                            <div class="stat-value"><?php echo $total_pruebas; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-clipboard-data"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="bi bi-box-seam"></i>
                        <span>Disponibles en sistema</span>
                    </div>
                </div>

                <div class="stat-card animate-in delay-2">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Categorías</div>
                            <div class="stat-value"><?php echo $total_categorias; ?></div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-tags"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="bi bi-diagram-3"></i>
                        <span>Grupos organizados</span>
                    </div>
                </div>

                <div class="stat-card animate-in delay-3">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Con Parámetros</div>
                            <div class="stat-value">
                                <?php
                                $con_parametros = array_filter($catalogo, function ($p) {
                                    return $p['num_parametros'] > 0;
                                });
                                echo count($con_parametros);
                                ?>
                            </div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-list-check"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-check-circle"></i>
                        <span>Configuradas completamente</span>
                    </div>
                </div>
            </div>

            <!-- Sección principal del catálogo -->
            <section class="catalog-section animate-in delay-2">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-clipboard-plus section-title-icon"></i>
                        Todas las Pruebas
                    </h3>
                    <button class="action-btn" onclick="openTestModal()">
                        <i class="bi bi-plus-lg"></i>
                        Agregar Nueva Prueba
                    </button>
                </div>

                <?php if (count($catalogo) > 0): ?>
                    <?php foreach ($pruebas_por_categoria as $categoria => $pruebas): ?>
                        <div class="category-header animate-in">
                            <i class="bi bi-folder2-open"></i>
                            <?php echo htmlspecialchars($categoria); ?>
                            <span class="badge bg-primary ms-2"><?php echo count($pruebas); ?></span>
                        </div>

                        <div class="tests-grid">
                            <?php foreach ($pruebas as $prueba): ?>
                                <div class="test-card animate-in">
                                    <div class="test-header">
                                        <div class="test-code"><?php echo htmlspecialchars($prueba['codigo_prueba']); ?></div>
                                        <div class="test-name"><?php echo htmlspecialchars($prueba['nombre_prueba']); ?></div>
                                    </div>

                                    <div class="test-details">
                                        <div class="test-detail">
                                            <i class="bi bi-droplet"></i>
                                            <span><?php echo htmlspecialchars($prueba['muestra_requerida'] ?: 'No especificada'); ?></span>
                                        </div>
                                        <div class="test-detail">
                                            <i class="bi bi-clock"></i>
                                            <span><?php echo $prueba['tiempo_procesamiento_horas']; ?> horas de procesamiento</span>
                                        </div>
                                        <div class="test-detail">
                                            <i class="bi bi-info-circle"></i>
                                            <span><?php echo htmlspecialchars($prueba['descripcion'] ?? 'Sin descripción'); ?></span>
                                        </div>
                                    </div>

                                    <div class="test-footer">
                                        <div>
                                            <div class="test-price">Q<?php echo number_format($prueba['precio'] ?? 0, 2); ?></div>
                                            <div class="test-params">
                                                <?php echo $prueba['num_parametros']; ?> parámetros
                                            </div>
                                        </div>
                                        <div class="test-actions">
                                            <button class="btn-icon manage" title="Gestionar parámetros"
                                                onclick="manageParameters(<?php echo $prueba['id_prueba']; ?>)">
                                                <i class="bi bi-list-check"></i>
                                            </button>
                                            <button class="btn-icon edit" title="Editar prueba"
                                                onclick="editTest(<?php echo htmlspecialchars(json_encode($prueba)); ?>)">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="bi bi-clipboard-x"></i>
                        </div>
                        <h4 class="text-muted mb-2">No hay pruebas registradas</h4>
                        <p class="text-muted mb-3">Comience agregando la primera prueba al catálogo</p>
                        <button class="action-btn" onclick="openTestModal()">
                            <i class="bi bi-plus-lg"></i>
                            Agregar Primera Prueba
                        </button>
                    </div>
                <?php endif; ?>
            </section>
        </main>

        <!-- Modal para Nueva/Editar Prueba -->
        <div class="modal fade" id="testModal" tabindex="-1" aria-labelledby="testModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="testModalLabel">Nueva Prueba de Laboratorio</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="testForm">
                            <input type="hidden" id="id_prueba" name="id_prueba">

                            <div class="mb-3">
                                <label for="nombre_prueba" class="form-label">
                                    <i class="bi bi-clipboard-pulse"></i> Nombre de la Prueba *
                                </label>
                                <input type="text" class="form-control" id="nombre_prueba" name="nombre" required
                                    placeholder="Ej: Hemograma Completo">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="codigo_prueba" class="form-label">
                                        <i class="bi bi-upc-scan"></i> Código *
                                    </label>
                                    <input type="text" class="form-control" id="codigo_prueba" name="codigo" required
                                        placeholder="HEM-01">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="categoria" class="form-label">
                                        <i class="bi bi-folder"></i> Categoría
                                    </label>
                                    <input type="text" class="form-control" id="categoria" name="categoria"
                                        placeholder="Hematología">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="descripcion" class="form-label">
                                    <i class="bi bi-text-paragraph"></i> Descripción
                                </label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="2"
                                    placeholder="Descripción de la prueba..."></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="precio" class="form-label">
                                        <i class="bi bi-currency-dollar"></i> Precio (Q)
                                    </label>
                                    <input type="number" step="0.01" class="form-control" id="precio" name="precio"
                                        placeholder="0.00" value="0">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="muestra_requerida" class="form-label">
                                        <i class="bi bi-droplet"></i> Muestra Requerida
                                    </label>
                                    <input type="text" class="form-control" id="muestra_requerida"
                                        name="muestra_requerida" placeholder="Sangre, Orina, etc.">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="tiempo_procesamiento_horas" class="form-label">
                                        <i class="bi bi-clock"></i> Tiempo (Hrs)
                                    </label>
                                    <input type="number" class="form-control" id="tiempo_procesamiento_horas"
                                        name="tiempo_procesamiento_horas" placeholder="24" value="24">
                                </div>
                            </div>

                            <div class="alert alert-info d-flex align-items-center" role="alert">
                                <i class="bi bi-info-circle me-2"></i>
                                <div>Los campos marcados con * son obligatorios</div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-primary" onclick="saveTest()">
                            <i class="bi bi-check-circle"></i> Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Optimizado (mismo que index.php) -->
    <script>
        // Dashboard de Laboratorio Reingenierizado

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
                themeSwitch: document.getElementById('themeSwitch')
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
                    this.setupAnimations();
                    this.setupCardInteractions();
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

                    document.querySelectorAll('.test-card, .stat-card, .catalog-section').forEach(el => {
                        observer.observe(el);
                    });
                }

                setupCardInteractions() {
                    // Agregar efecto hover a las tarjetas de prueba
                    const testCards = document.querySelectorAll('.test-card');
                    testCards.forEach(card => {
                        card.addEventListener('mouseenter', () => {
                            card.style.transform = 'translateY(-4px)';
                        });
                        card.addEventListener('mouseleave', () => {
                            card.style.transform = 'translateY(0)';
                        });
                    });
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                const themeManager = new ThemeManager();
                const dynamicComponents = new DynamicComponents();

                window.catalogDashboard = {
                    theme: themeManager,
                    components: dynamicComponents
                };

                console.log('Catálogo de Pruebas inicializado');
            });

            // ==========================================================================
            // POLYFILLS PARA NAVEGADORES ANTIGUOS
            // ==========================================================================
            if (!NodeList.prototype.forEach) {
                NodeList.prototype.forEach = Array.prototype.forEach;
            }

        })();


        // ==========================================================================
        // FUNCIONES ESPECÍFICAS DEL CATÁLOGO
        // ==========================================================================

        function openTestModal(data = null) {
            const modal = new bootstrap.Modal(document.getElementById('testModal'));
            const form = document.getElementById('testForm');
            const modalTitle = document.getElementById('testModalLabel');

            // Reset form
            form.reset();

            if (data) {
                // Edit mode
                modalTitle.textContent = 'Editar Prueba';
                document.getElementById('id_prueba').value = data.id_prueba || '';
                document.getElementById('nombre_prueba').value = data.nombre_prueba || '';
                document.getElementById('codigo_prueba').value = data.codigo_prueba || '';
                document.getElementById('categoria').value = data.categoria || '';
                document.getElementById('descripcion').value = data.notas || '';
                document.getElementById('precio').value = data.precio || '0';
                document.getElementById('muestra_requerida').value = data.muestra_requerida || '';
                document.getElementById('tiempo_procesamiento_horas').value = data.tiempo_procesamiento_horas || '24';
            } else {
                // Create mode
                modalTitle.textContent = 'Nueva Prueba de Laboratorio';
            }

            modal.show();
        }

        function saveTest() {
            const form = document.getElementById('testForm');

            // Validate required fields
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);

            console.log('Saving test...');
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }

            fetch('api/save_test.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('testModal'));
                        modal.hide();

                        // Show success message
                        alert('✓ ' + data.message);

                        // Reload page
                        location.reload();
                    } else {
                        alert('✗ Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('✗ Error de conexión: ' + error.message);
                });
        }

        function editTest(data) {
            openTestModal(data);
        }

        function manageParameters(id) {
            window.location.href = `parametros_prueba.php?id=${id}`;
        }

        function loadSweetAlert() {
            return new Promise((resolve) => {
                if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
                    resolve();
                    return;
                }

                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                script.onload = resolve;
                document.head.appendChild(script);
            });
        }

        // Efectos de carga para formularios
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function (e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Procesando...';
                    submitBtn.disabled = true;

                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 3000);
                }
            });
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
        
        
        /* Efectos adicionales para catálogo */
        .test-card {
            cursor: pointer;
        }
        
        .test-card:hover {
            border-color: var(--color-primary);
        }
        
        /* ==========================================================================
           ESTILOS PERSONALIZADOS PARA MODALES SWEETALERT2
           ========================================================================== */
        
        /* Contenedor del modal */
        .custom-modal-popup {
            font-family: var(--font-family) !important;
            border-radius: var(--radius-lg) !important;
            padding: 0 !important;
        }
        
        .custom-modal-title {
            font-size: var(--font-size-2xl) !important;
            font-weight: 700 !important;
            color: var(--color-text) !important;
            padding: var(--space-xl) var(--space-xl) var(--space-md) !important;
            border-bottom: 2px solid var(--color-border) !important;
            margin: 0 !important;
        }
        
        .custom-modal-content {
            padding: var(--space-lg) var(--space-xl) !important;
        }
        
        /* Formulario del modal */
        .modal-test-form {
            text-align: left;
        }
        
        .form-section {
            margin-bottom: var(--space-lg);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: var(--space-md);
        }
        
        .form-group,
        .form-group-full {
            display: flex;
            flex-direction: column;
        }
        
        .modal-label {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            font-weight: 600;
            font-size: var(--font-size-sm);
            color: var(--color-text);
            margin-bottom: var(--space-xs);
        }
        
        .modal-label i {
            color: var(--color-primary);
            font-size: var(--font-size-base);
        }
        
        .modal-input,
        .modal-textarea {
            width: 100%;
            padding: var(--space-sm) var(--space-md);
            border: 2px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface);
            color: var(--color-text);
            font-family: var(--font-family);
            font-size: var(--font-size-base);
            transition: all var(--transition-base);
        }
        
        .modal-input:focus,
        .modal-textarea:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.1);
            background: var(--color-card);
        }
        
        .modal-input::placeholder,
        .modal-textarea::placeholder {
            color: var(--color-text-secondary);
            opacity: 0.6;
        }
        
        .modal-textarea {
            resize: vertical;
            min-height: 60px;
        }
        
        /* Nota informativa */
        .form-note {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space-md);
            background: rgba(var(--color-info-rgb), 0.1);
            border-left: 3px solid var(--color-info);
            border-radius: var(--radius-sm);
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
            margin-top: var(--space-md);
        }
        
        .form-note i {
            color: var(--color-info);
            font-size: var(--font-size-lg);
        }
        
        /* Botones del modal */
        .custom-modal-confirm,
        .custom-modal-cancel {
            padding: var(--space-sm) var(--space-xl) !important;
            border-radius: var(--radius-md) !important;
            font-weight: 600 !important;
            font-size: var(--font-size-base) !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: var(--space-xs) !important;
            transition: all var(--transition-base) !important;
            border: none !important;
        }
        
        .custom-modal-confirm {
            background: var(--color-primary) !important;
            color: white !important;
        }
        
        .custom-modal-confirm:hover {
            background: var(--color-primary) !important;
            opacity: 0.9 !important;
            transform: translateY(-2px) !important;
            box-shadow: var(--shadow-md) !important;
        }
        
        .custom-modal-cancel {
            background: var(--color-secondary) !important;
            color: white !important;
        }
        
        .custom-modal-cancel:hover {
            background: var(--color-secondary) !important;
            opacity: 0.9 !important;
        }
        
        /* Mensaje de validación */
        .swal2-validation-message {
            background: rgba(var(--color-danger-rgb), 0.1) !important;
            border-left: 3px solid var(--color-danger) !important;
            color: var(--color-danger) !important;
            font-weight: 500 !important;
            display: flex !important;
            align-items: center !important;
            gap: var(--space-sm) !important;
        }
        
        /* Responsive para modales */
        @media (max-width: 767px) {
            .custom-modal-popup {
                width: 95% !important;
                margin: var(--space-md) !important;
            }
            
            .custom-modal-title {
                font-size: var(--font-size-xl) !important;
                padding: var(--space-lg) var(--space-md) var(--space-sm) !important;
            }
            
            .custom-modal-content {
                padding: var(--space-md) !important;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .custom-modal-confirm,
            .custom-modal-cancel {
                width: 100% !important;
                justify-content: center !important;
            }
        }
        
        /* Tema oscuro para modales */
        [data-theme="dark"] .swal2-popup {
            background: var(--color-card) !important;
            color: var(--color-text) !important;
        }
        
        [data-theme="dark"] .modal-input,
        [data-theme="dark"] .modal-textarea {
            background: var(--color-surface) !important;
            color: var(--color-text) !important;
            border-color: var(--color-border) !important;
        }
        
        [data-theme="dark"] .modal-input:focus,
        [data-theme="dark"] .modal-textarea:focus {
            background: var(--color-bg) !important;
        }
    `;
        document.head.appendChild(style);
    </script>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>