<?php
// export_jornada.php - Reporte de Jornada - Centro Médico RS
// Versión 4.0 - Integrado al Diseño del Dashboard Principal
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



// Establecer zona horaria
date_default_timezone_set('America/Guatemala');
verify_session();

// Obtener información del usuario
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['tipoUsuario'];
$user_name = $_SESSION['nombre'];
$user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

// Solo administradores pueden generar este reporte
if ($user_type !== 'admin') {
    die("Acceso denegado.");
}

// Obtener parámetros de fecha, formato y jornada
$date = $_GET['date'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'html'; // html, csv, excel, word
$shift = $_GET['shift'] ?? 'morning';

// Calcular rango de jornada
if ($shift === 'morning') {
    $start_time = $date . ' 08:00:00';
    $end_time = $date . ' 17:00:00';
    $shift_label = "Mañana (08:00 - 17:00)";
} else {
    $start_time = $date . ' 17:00:00';
    $end_time = date('Y-m-d', strtotime($date . ' +1 day')) . ' 07:59:59';
    $shift_label = "Noche (17:00 - 08:00)";
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // ============ CÁLCULO DE MÉTRICAS ============

    // 1. Total de pacientes atendidos
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT historial_id) FROM citas WHERE fecha_cita BETWEEN ? AND ?");
    $stmt->execute([$start_time, $end_time]);
    $total_patients = $stmt->fetchColumn() ?: 0;

    // 2. Procedimientos menores
    $stmt = $conn->prepare("SELECT SUM(cobro) FROM procedimientos_menores WHERE fecha_procedimiento BETWEEN ? AND ?");
    $stmt->execute([$start_time, $end_time]);
    $total_procedures = $stmt->fetchColumn() ?: 0;

    // 3. Exámenes realizados
    $stmt = $conn->prepare("SELECT SUM(cobro) FROM examenes_realizados WHERE fecha_examen BETWEEN ? AND ?");
    $stmt->execute([$start_time, $end_time]);
    $total_exams = $stmt->fetchColumn() ?: 0;

    // 4. Compras de medicamentos
    $stmt = $conn->prepare("SELECT SUM(total_amount) FROM purchase_headers WHERE purchase_date BETWEEN ? AND ?");
    $stmt->execute([$date, date('Y-m-d', strtotime($date . ' +1 day'))]);
    $total_purchases = $stmt->fetchColumn() ?: 0;

    // 5. Ventas de medicamentos
    $stmt = $conn->prepare("SELECT SUM(total) FROM ventas WHERE fecha_venta BETWEEN ? AND ?");
    $stmt->execute([$start_time, $end_time]);
    $total_sales = $stmt->fetchColumn() ?: 0;

    // 6. Cobros de consultas
    // Updated to use strict shift range
    $stmt = $conn->prepare("SELECT SUM(cantidad_consulta) FROM cobros WHERE fecha_consulta BETWEEN ? AND ?");
    $stmt->execute([$start_time, $end_time]);
    $total_billings = $stmt->fetchColumn() ?: 0;

    // 7. Ingresos totales
    $total_revenue = $total_sales + $total_procedures + $total_exams + $total_billings;

    // 8. Desempeño neto
    $net_performance = $total_revenue - $total_purchases;

    // ============ PREPARAR DATOS PARA EXPORTACIÓN ============

    // Exportación CSV
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="reporte_jornada_' . $date . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Concepto', 'Monto / Cantidad']);
        fputcsv($output, ['Fecha', $date]);
        fputcsv($output, ['Pacientes Atendidos', $total_patients]);
        fputcsv($output, ['Ventas Medicamentos', number_format($total_sales, 2)]);
        fputcsv($output, ['Cobros Realizados', number_format($total_billings, 2)]);
        fputcsv($output, ['Procedimientos Menores', number_format($total_procedures, 2)]);
        fputcsv($output, ['Exámenes Médicos', number_format($total_exams, 2)]);
        fputcsv($output, ['Total Compras', number_format($total_purchases, 2)]);
        fputcsv($output, ['Total Ingresos', number_format($total_revenue, 2)]);
        fputcsv($output, ['Desempeño Neto', number_format($net_performance, 2)]);
        fclose($output);
        exit;
    }

    // Exportación Excel o Word
    if ($format === 'excel' || $format === 'word') {
        $ext = ($format === 'excel' ? ".xls" : ".doc");
        header("Content-Type: application/vnd.ms-" . ($format === 'excel' ? "excel" : "word"));
        header("Content-Disposition: attachment; filename=\"reporte_jornada_$date$ext\"");
        echo "
        <table border='1'>
            <tr><th colspan='2'><h1>Reporte de Jornada</h1></th></tr>
            <tr><td><b>Fecha:</b></td><td>$date</td></tr>
            <tr><td><b>Pacientes Atendidos:</b></td><td>$total_patients</td></tr>
            <tr><td><b>Ventas Medicamentos:</b></td><td>Q" . number_format($total_sales, 2) . "</td></tr>
            <tr><td><b>Cobros Realizados:</b></td><td>Q" . number_format($total_billings, 2) . "</td></tr>
            <tr><td><b>Procedimientos Menores:</b></td><td>Q" . number_format($total_procedures, 2) . "</td></tr>
            <tr><td><b>Exámenes Médicos:</b></td><td>Q" . number_format($total_exams, 2) . "</td></tr>
            <tr><td><b>Total Ingresos:</b></td><td><b>Q" . number_format($total_revenue, 2) . "</b></td></tr>
            <tr><td><b>Total Compras:</b></td><td>Q" . number_format($total_purchases, 2) . "</td></tr>
            <tr><td><b>Desempeño Neto:</b></td><td><b>Q" . number_format($net_performance, 2) . "</b></td></tr>
        </table>";
        exit;
    }

    // ============ CONSULTAS ADICIONALES PARA EL DASHBOARD ============

    // Citas de hoy
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas WHERE fecha_cita = ?");
    $stmt->execute([date('Y-m-d')]);
    $today_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Total de citas en el sistema
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM citas");
    $stmt->execute();
    $total_appointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Hospitalizaciones Activas
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM encamamientos WHERE estado = 'Activo'");
    $stmt->execute();
    $active_hospitalizations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Compras pendientes
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventario WHERE estado = 'Pendiente'");
    $stmt->execute();
    $pending_purchases = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Preparar mensaje para WhatsApp
    $wa_text = "*REPORTE DE JORNADA*\n";
    $wa_text .= "*Fecha:* " . date('d/m/Y', strtotime($date)) . "\n";
    $wa_text .= "--------------------------\n";
    $wa_text .= "*Pacientes:* " . $total_patients . "\n";
    $wa_text .= "*Ventas Meds:* Q" . number_format($total_sales, 2) . "\n";
    $wa_text .= "*Cobros Inf:* Q" . number_format($total_billings, 2) . "\n";
    $wa_text .= "*Proc. Menores:* Q" . number_format($total_procedures, 2) . "\n";
    $wa_text .= "*Exámenes:* Q" . number_format($total_exams, 2) . "\n";
    $wa_text .= "--------------------------\n";
    $wa_text .= "*TOTAL INGRESOS:* Q" . number_format($total_revenue, 2) . "\n";
    $wa_text .= "*TOTAL COMPRAS:* Q" . number_format($total_purchases, 2) . "\n";
    $wa_url = "https://wa.me/50239029076?text=" . urlencode($wa_text);

    // Título de la página
    $page_title = "Reporte de Jornada - $date - Centro Médico RS";

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reporte de Jornada - Centro Médico RS - Sistema de gestión médica">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- CSS Crítico (incrustado para máxima velocidad) -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">

</head>

<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>

    <!-- Overlay para sidebar móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Barra Lateral Moderna -->
    <aside class="sidebar" id="sidebar">
        <!-- Header sidebar -->
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="../../assets/img/Logo.png" alt="Logo CMS">
            </div>
            <h2>CMS Reportes</h2>
        </div>

        <!-- Navegación -->
        <nav class="sidebar-nav">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="../dashboard/index.php" class="nav-link">
                        <i class="bi bi-speedometer2 nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
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
                <li class="nav-item">
                    <a href="../hospitalization/index.php" class="nav-link">
                        <i class="bi bi-hospital nav-icon"></i>
                        <span class="nav-text">Hospitalización</span>
                        <span class="badge bg-info"><?php echo $active_hospitalizations; ?></span>
                    </a>
                </li>
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
                    <a href="../laboratory/index.php" class="nav-link">
                        <i class="bi bi-flask nav-icon"></i>
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
                <li class="nav-item">
                    <a href="../billing/index.php" class="nav-link">
                        <i class="bi bi-cash-coin nav-icon"></i>
                        <span class="nav-text">Cobros</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="../dispensary/index.php" class="nav-link">
                        <i class="bi bi-capsule nav-icon"></i>
                        <span class="nav-text">Dispensario</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php" class="nav-link active">
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
            </ul>
        </nav>

        <!-- Footer sidebar -->
        <div class="sidebar-footer">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role"><?php echo htmlspecialchars($user_specialty); ?></span>
            </div>
        </div>
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
                    <img src="../../assets/img/Logo.png" alt="Centro Médico RS" class="brand-logo">
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
        </header>

        <!-- Botón para colapsar/expandir sidebar (solo escritorio) -->
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Colapsar/expandir menú">
            <i class="bi bi-chevron-left" id="sidebarToggleIcon"></i>
        </button>

        <!-- Contenido Principal -->
        <main class="main-content">
            <!-- Tarjeta de reporte -->
            <div class="report-card animate-in">
                <!-- Encabezado del reporte -->
                <div class="report-header">
                    <div class="report-title-section">
                        <h1 class="report-title">Reporte Diario de Jornada</h1>
                        <p class="report-subtitle">
                            Período: <?php echo date('d/m/Y 08:00 AM', strtotime($start_time)); ?> -
                            <?php echo date('d/m/Y 05:00 PM', strtotime($end_time)); ?>
                        </p>
                    </div>
                    <div class="report-actions">
                        <button onclick="window.print()" class="action-btn secondary">
                            <i class="bi bi-printer"></i>
                            <span>Imprimir</span>
                        </button>
                        <a href="<?php echo $wa_url; ?>" target="_blank" class="action-btn success">
                            <i class="bi bi-whatsapp"></i>
                            <span>WhatsApp</span>
                        </a>
                    </div>
                </div>

                <!-- Métricas principales -->
                <div class="metrics-list">
                    <div class="metric-item">
                        <span class="metric-label">
                            <i class="bi bi-people"></i>
                            Total Pacientes Atendidos
                        </span>
                        <span class="metric-value"><?php echo $total_patients; ?></span>
                    </div>

                    <div class="metric-item">
                        <span class="metric-label">
                            <i class="bi bi-capsule"></i>
                            Ventas de Medicamentos
                        </span>
                        <span class="metric-value text-success">Q<?php echo number_format($total_sales, 2); ?></span>
                    </div>

                    <div class="metric-item">
                        <span class="metric-label">
                            <i class="bi bi-cash-coin"></i>
                            Cobros Realizados
                        </span>
                        <span class="metric-value text-primary">Q<?php echo number_format($total_billings, 2); ?></span>
                    </div>

                    <div class="metric-item">
                        <span class="metric-label">
                            <i class="bi bi-bandaid"></i>
                            Procedimientos Menores
                        </span>
                        <span class="metric-value text-info">Q<?php echo number_format($total_procedures, 2); ?></span>
                    </div>

                    <div class="metric-item">
                        <span class="metric-label">
                            <i class="bi bi-clipboard2-pulse"></i>
                            Exámenes Médicos
                        </span>
                        <span class="metric-value text-info">Q<?php echo number_format($total_exams, 2); ?></span>
                    </div>
                </div>

                <!-- Secciones destacadas -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="highlight-section income">
                            <div class="highlight-title">Total Ingresos Brutos</div>
                            <div class="highlight-value">Q<?php echo number_format($total_revenue, 2); ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="highlight-section expense">
                            <div class="highlight-title">Total Compras (Egresos)</div>
                            <div class="highlight-value">Q<?php echo number_format($total_purchases, 2); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Desempeño neto -->
                <div class="highlight-section net">
                    <div class="highlight-title">Desempeño Neto</div>
                    <div class="highlight-value">Q<?php echo number_format($net_performance, 2); ?></div>
                    <div class="mt-2 opacity-75">
                        <?php if ($net_performance >= 0): ?>
                                <i class="bi bi-arrow-up-right"></i> Resultado positivo
                        <?php else: ?>
                                <i class="bi bi-arrow-down-right"></i> Resultado negativo
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Firmas -->
                <div class="signature-row">
                    <div class="signature-item">
                        <div class="signature-line"></div>
                        <div class="text-muted mt-2">Firma Administrador</div>
                    </div>
                    <div class="signature-item">
                        <div class="signature-line"></div>
                        <div class="text-muted mt-2">Firma Responsable</div>
                    </div>
                </div>

                <!-- Información de generación -->
                <div class="generation-info">
                    Generado automáticamente por Centro Médico RS Management System -
                    <?php echo date('d/m/Y H:i'); ?>
                </div>
            </div>

            <!-- Acciones adicionales -->
            <div class="report-card animate-in delay-1">
                <h3 class="report-title mb-4">Exportar Reporte</h3>
                <div class="row g-3">
                    <div class="col-md-6">
                        <a href="export_jornada.php?date=<?php echo $date; ?>&format=csv"
                            class="action-btn secondary w-100">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                            <span>Descargar CSV</span>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="export_jornada.php?date=<?php echo $date; ?>&format=excel"
                            class="action-btn secondary w-100">
                            <i class="bi bi-file-earmark-excel"></i>
                            <span>Descargar Excel</span>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="export_jornada.php?date=<?php echo $date; ?>&format=word"
                            class="action-btn secondary w-100">
                            <i class="bi bi-file-earmark-word"></i>
                            <span>Descargar Word</span>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="index.php" class="action-btn w-100">
                            <i class="bi bi-arrow-left"></i>
                            <span>Volver a Reportes</span>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript Optimizado -->
    <script>
        (function () {
            'use strict';

            // ==========================================================================
            // CONFIGURACIÓN Y CONSTANTES
            // ==========================================================================
            const CONFIG = {
                themeKey: 'dashboard-theme',
                sidebarKey: 'sidebar-collapsed',
                transitionDuration: 300
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
                mobileSidebarToggle: document.getElementById('mobileSidebarToggle')
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
            // ANIMACIONES
            // ==========================================================================
            class AnimationManager {
                constructor() {
                    this.setupAnimations();
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

                    document.querySelectorAll('.report-card').forEach(el => {
                        observer.observe(el);
                    });
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                // Inicializar componentes
                const themeManager = new ThemeManager();
                const sidebarManager = new SidebarManager();
                const animationManager = new AnimationManager();

                // Exponer APIs necesarias globalmente
                window.dashboard = {
                    theme: themeManager,
                    sidebar: sidebarManager
                };

                // Log de inicialización
                console.log('Reporte de Jornada - CMS v4.0');
                console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
                console.log('Fecha del reporte: <?php echo $date; ?>');
            });

            // ==========================================================================
            // POLYFILLS
            // ==========================================================================
            if (!NodeList.prototype.forEach) {
                NodeList.prototype.forEach = Array.prototype.forEach;
            }

        })();
    </script>
</body>

</html>