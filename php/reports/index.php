<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');

verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener fechas para filtros
    $fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t');

    $start_datetime = $fecha_inicio . ' 08:00:00';
    $end_datetime = date('Y-m-d', strtotime($fecha_fin . ' +1 day')) . ' 07:59:59';

    // --- Metrics Calculation ---

    // 1. Medications Sales (Revenue)
    $stmt_sales = $conn->prepare("SELECT SUM(total) as total_sales FROM ventas WHERE fecha_venta BETWEEN ? AND ?");
    $stmt_sales->execute([$start_datetime, $end_datetime]);
    $total_sales_meds = $stmt_sales->fetch(PDO::FETCH_ASSOC)['total_sales'] ?? 0;

    // 2. Medications Purchases (Expense)
    $stmt_purchases = $conn->prepare("SELECT SUM(total_amount) as total_purchases FROM purchase_headers WHERE purchase_date BETWEEN ? AND ?");
    $stmt_purchases->execute([$fecha_inicio, $fecha_fin]);
    $total_purchases_meds = $stmt_purchases->fetch(PDO::FETCH_ASSOC)['total_purchases'] ?? 0;

    // 3. Medications Profit (Margin over cost)
    $stmt_profit = $conn->prepare("
        SELECT SUM(dv.cantidad_vendida * (dv.precio_unitario - COALESCE(pi.unit_cost, 0))) as total_profit
        FROM detalle_ventas dv
        JOIN ventas v ON dv.id_venta = v.id_venta
        JOIN inventario i ON dv.id_inventario = i.id_inventario
        LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
        WHERE v.fecha_venta BETWEEN ? AND ?
    ");
    $stmt_profit->execute([$start_datetime, $end_datetime]);
    $total_profit_meds = $stmt_profit->fetch(PDO::FETCH_ASSOC)['total_profit'] ?? 0;

    // 4. Clinical Services (Procedures & Exams)
    $stmt_proc = $conn->prepare("SELECT SUM(cobro) FROM procedimientos_menores WHERE fecha_procedimiento BETWEEN ? AND ?");
    $stmt_proc->execute([$start_datetime, $end_datetime]);
    $total_procedures = $stmt_proc->fetchColumn() ?: 0;

    $stmt_exams_rec = $conn->prepare("SELECT SUM(cobro) FROM examenes_realizados WHERE fecha_examen BETWEEN ? AND ?");
    $stmt_exams_rec->execute([$start_datetime, $end_datetime]);
    $total_exams_revenue = $stmt_exams_rec->fetchColumn() ?: 0;

    // 5. Billings (Consultas) - Use pure dates for DATE column
    $stmt_billings = $conn->prepare("SELECT SUM(cantidad_consulta) FROM cobros WHERE fecha_consulta BETWEEN ? AND ?");
    $stmt_billings->execute([$fecha_inicio, $fecha_fin]);
    $total_billings = $stmt_billings->fetchColumn() ?: 0;

    // 6. Totals
    $total_gross_revenue = $total_sales_meds + $total_procedures + $total_exams_revenue + $total_billings;
    $net_performance = $total_gross_revenue - $total_purchases_meds;
} catch (Exception $e) {
    die("Error: No se pudo conectar a la base de datos");
}

$page_title = "Reportes - Clínica";
include_once '../../includes/header.php';
?>

<!-- Inject Dashboard Custom Styles -->
<link rel="stylesheet" href="../../assets/css/dashboard-reengineered.css">


<style>
    .report-card {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(12px);
        border-radius: 20px;
        border: 1px solid rgba(255, 255, 255, 0.4);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        padding: 1.5rem;
        height: 100%;
        transition: transform 0.3s ease;
    }
    
    .report-card:hover {
        transform: translateY(-5px);
    }

    .stat-box {
        text-align: center;
        padding: 1.5rem;
        border-radius: 15px;
        background: rgba(255, 255, 255, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .stat-box h2 {
        font-size: 2.5rem;
        font-weight: 800;
        margin-bottom: 0.5rem;
    }

    .chart-placeholder {
        height: 200px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.02);
        border-radius: 15px;
        border: 2px dashed rgba(0, 0, 0, 0.05);
    }

    .accounting-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
    }

    .accounting-item {
        padding: 1.25rem;
        border-radius: 16px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        text-align: center;
    }

    .accounting-item.income { background: rgba(16, 185, 129, 0.1); color: #059669; }
    .accounting-item.expense { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
    .accounting-item.balance { background: rgba(59, 130, 246, 0.1); color: #2563eb; }

    .accounting-value {
        font-size: 1.75rem;
        font-weight: 700;
        margin: 0.5rem 0;
    }

    .accounting-label {
        font-size: 0.875rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.8;
    }

    .table-custom {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 8px;
    }

    .table-custom tr {
        background: rgba(255, 255, 255, 0.5);
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    }

    .table-custom td, .table-custom th {
        padding: 1rem;
        vertical-align: middle;
    }

    .table-custom th {
        background: transparent;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.05em;
    }
</style>

<div class="dashboard-wrapper sidebar-collapsed">
    <!-- Mobile Overlay -->
    <div class="dashboard-mobile-overlay"></div>

    <!-- Desktop Sidebar Toggle -->
    <button class="btn btn-white shadow-sm border rounded-circle position-fixed d-none d-md-flex align-items-center justify-content-center" id="desktopSidebarToggle" title="Mostrar Menú" style="top: 20px; left: 20px; width: 45px; height: 45px; z-index: 1040; transition: all 0.3s ease;">
        <i class="bi bi-list text-primary fs-4"></i>
    </button>

    <!-- Sidebar Reengineered -->
    <div class="sidebar-glass p-3 d-flex flex-column">
        <div class="brand-section d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center text-decoration-none">
                <img src="../../assets/img/siloe.png" alt="Logo" style="height: 40px; margin-right: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));"/>
            </div>
            <button class="btn btn-sm btn-light text-primary rounded-circle shadow-sm d-none d-md-flex align-items-center justify-content-center" id="sidebarCloseBtn" style="width: 32px; height: 32px;">
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>
        
        <ul class="nav nav-pills flex-column mb-auto">
            <?php $rol = $_SESSION['tipoUsuario'] ?? $_SESSION['rol'] ?? ''; ?>
            <?php if ($rol === 'admin' || $rol === 'doc' || $rol === 'user'): ?>
            <li class="nav-item"><a href="../dashboard/index.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="../patients/index.php" class="nav-link"><i class="bi bi-people"></i> Pacientes</a></li>
            <?php endif; ?>
            <?php if ($rol === 'admin' || $rol === 'user'): ?>
            <li><a href="../appointments/index.php" class="nav-link"><i class="bi bi-calendar"></i> Citas</a></li>
            <li><a href="../minor_procedures/index.php" class="nav-link"><i class="bi bi-bandaid"></i> Proc. Menores</a></li>
            <li><a href="../examinations/index.php" class="nav-link"><i class="bi bi-file-earmark-medical"></i> Exámenes</a></li>
            <li><a href="../dispensary/index.php" class="nav-link"><i class="bi bi-cart4"></i> Dispensario</a></li>
            <li><a href="../inventory/index.php" class="nav-link"><i class="bi bi-box-seam"></i> Inventario</a></li>
            <?php endif; ?>
            <?php if ($rol === 'admin'): ?>
            <li><a href="../purchases/index.php" class="nav-link"><i class="bi bi-cart-plus"></i> Compras</a></li>
            <li><a href="../sales/index.php" class="nav-link"><i class="bi bi-receipt"></i> Ventas</a></li>
            <li><a href="../reports/index.php" class="nav-link active"><i class="bi bi-bar-chart-line"></i> Reportes</a></li>
            <?php endif; ?>
            <?php if ($rol === 'admin' || $rol === 'user'): ?>
            <li><a href="../billing/index.php" class="nav-link"><i class="bi bi-cash-coin"></i> Cobros</a></li>
            <?php endif; ?>
        </ul>
        
        <div class="mt-auto">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle p-2 rounded hover-effect" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false" style="color: var(--text-color);">
                    <div class="avatar-circle me-2 bg-primary text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px;">
                        <?php echo strtoupper(substr($_SESSION['nombre'] ?? 'U', 0, 1)); ?>
                    </div>
                    <strong><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></strong>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="dropdownUser1">
                    <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Main Content Reengineered -->
    <div class="main-content-glass">
        <div class="container-fluid">
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Centro de Reportes</h2>
                    <p class="text-muted text-sm mb-0">Análisis detallado y métricas de la clínica</p>
                </div>
                <div class="d-flex gap-3 align-items-center">
                    <?php if ($rol === 'admin'): ?>
                    <div>
                        <button class="btn btn-success btn-sm rounded-pill px-3 shadow-sm" type="button" data-bs-toggle="modal" data-bs-target="#exportModal">
                            <i class="bi bi-download me-1"></i> Exportar Jornada
                        </button>
                    </div>
                    <?php endif; ?>
                    <form method="GET" class="d-flex gap-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-0 shadow-sm text-muted">Desde</span>
                            <input type="date" name="fecha_inicio" class="form-control border-0 shadow-sm" value="<?php echo $fecha_inicio; ?>">
                        </div>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-0 shadow-sm text-muted">Hasta</span>
                            <input type="date" name="fecha_fin" class="form-control border-0 shadow-sm" value="<?php echo $fecha_fin; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm rounded-pill px-3 shadow-sm">
                            <i class="bi bi-filter me-1"></i> Filtrar
                        </button>
                    </form>
                </div>
            </div>

            <!-- Global Key Metrics -->
            <div class="row g-4 mb-5">
                <?php
                // Get Metrics
                $totalPacientes = $conn->query("SELECT COUNT(*) FROM pacientes")->fetchColumn();
                $totalCitas = $conn->prepare("SELECT COUNT(*) FROM citas WHERE fecha_cita BETWEEN ? AND ?");
                $totalCitas->execute([$start_datetime, $end_datetime]);
                $citasCount = $totalCitas->fetchColumn();
                
                $totalExamenes = $conn->prepare("SELECT COUNT(*) FROM examenes_realizados WHERE fecha_examen BETWEEN ? AND ?");
                $totalExamenes->execute([$start_datetime, $end_datetime]);
                $examenesCount = $totalExamenes->fetchColumn();
                
                $totalMedicamentos = $conn->query("SELECT COUNT(*) FROM inventario WHERE cantidad_med > 0")->fetchColumn();
                ?>
                
                <div class="col-md-3">
                    <div class="report-card text-center">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary mx-auto mb-3" style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                            <i class="bi bi-people"></i>
                        </div>
                        <h4 class="fw-bold mb-1"><?php echo $totalPacientes; ?></h4>
                        <p class="text-muted text-xs uppercase-label mb-0">Pacientes Registrados</p>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="report-card text-center">
                        <div class="stat-icon bg-success bg-opacity-10 text-success mx-auto mb-3" style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <h4 class="fw-bold mb-1"><?php echo $citasCount; ?></h4>
                        <p class="text-muted text-xs uppercase-label mb-0">Citas en Período</p>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="report-card text-center">
                        <div class="stat-icon bg-info bg-opacity-10 text-info mx-auto mb-3" style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                            <i class="bi bi-patch-check"></i>
                        </div>
                        <h4 class="fw-bold mb-1"><?php echo $examenesCount; ?></h4>
                        <p class="text-muted text-xs uppercase-label mb-0">Exámenes Realizados</p>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="report-card text-center">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning mx-auto mb-3" style="width: 50px; height: 50px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                            <i class="bi bi-capsule"></i>
                        </div>
                        <h4 class="fw-bold mb-1"><?php echo $totalMedicamentos; ?></h4>
                        <p class="text-muted text-xs uppercase-label mb-0">Medicamentos en Stock</p>
                    </div>
                </div>
            </div>

            <!-- Detailed Accounting Section -->
            <div class="row mb-5">
                <div class="col-12">
                    <div class="report-card">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-cash-coin me-2"></i>Contabilidad Detallada</h5>
                            <span class="badge bg-light text-dark border rounded-pill px-3 py-2">Estado de Resultados</span>
                        </div>
                        
                        <?php
                        // Sales of Medications
                        $stmt = $conn->prepare("SELECT SUM(dv.cantidad_vendida * dv.precio_unitario) FROM detalle_ventas dv JOIN ventas v ON dv.id_venta = v.id_venta WHERE v.fecha_venta BETWEEN ? AND ?");
                        $stmt->execute([$start_datetime, $end_datetime]);
                        $total_sales_meds = $stmt->fetchColumn() ?: 0;

                        // Purchases of Medications (Cost of Goods)
                        $stmt = $conn->prepare("SELECT SUM(total_amount) FROM purchase_headers WHERE purchase_date BETWEEN ? AND ?");
                        $stmt->execute([$fecha_inicio, $fecha_fin]);
                        $total_purchases_meds = $stmt->fetchColumn() ?: 0;

                        // Calculate profit from medications (assuming profit is sales - purchases for simplicity here, or a more complex COGS calculation if available)
                        // For a more accurate profit, you'd need to track the cost of each item sold.
                        // Here, we'll use a simplified approach:
                        $total_profit_meds = $total_sales_meds - $total_purchases_meds; // This is a simplified net profit, not gross profit margin.

                        // Revenue from Procedures
                        $stmt = $conn->prepare("SELECT SUM(cobro) FROM procedimientos_menores WHERE fecha_procedimiento BETWEEN ? AND ?");
                        $stmt->execute([$start_datetime, $end_datetime]);
                        $total_procedures = $stmt->fetchColumn() ?: 0;

                        // Revenue from Exams
                        $stmt = $conn->prepare("SELECT SUM(cobro) FROM examenes_realizados WHERE fecha_examen BETWEEN ? AND ?");
                        $stmt->execute([$start_datetime, $end_datetime]);
                        $total_exams_revenue = $stmt->fetchColumn() ?: 0;

                        // Revenue from Billings (Consultas)
                        $stmt = $conn->prepare("SELECT SUM(cantidad_consulta) FROM cobros WHERE fecha_consulta BETWEEN ? AND ?");
                        $stmt->execute([$fecha_inicio, $fecha_fin]);
                        $total_billings = $stmt->fetchColumn() ?: 0;

                        // Total Gross Revenue (Medications Sales + Services + Billings)
                        $total_gross_revenue = $total_sales_meds + $total_procedures + $total_exams_revenue + $total_billings;

                        // Net Performance (Gross Revenue - Total Purchases)
                        $net_performance = $total_gross_revenue - $total_purchases_meds;
                        ?>
                        
                        <div class="row g-4">
                            <!-- Medications Column -->
                            <div class="col-lg-4 border-end">
                                <h6 class="text-xs fw-bold text-muted uppercase-label mb-3">Inventario y Medicamentos</h6>
                                <div class="d-flex flex-column gap-3">
                                    <div class="p-3 rounded-4" style="background: rgba(59, 130, 246, 0.05);">
                                        <div class="small text-muted mb-1">Ventas de Medicamentos</div>
                                        <div class="h5 fw-bold text-primary mb-0">Q<?php echo number_format($total_sales_meds, 2); ?></div>
                                    </div>
                                    <div class="p-3 rounded-4" style="background: rgba(239, 68, 68, 0.05);">
                                        <div class="small text-muted mb-1">Compras Realizadas</div>
                                        <div class="h5 fw-bold text-danger mb-0">Q<?php echo number_format($total_purchases_meds, 2); ?></div>
                                    </div>
                                    <div class="p-3 rounded-4" style="background: rgba(16, 185, 129, 0.05); border: 1px dashed rgba(16, 185, 129, 0.3);">
                                        <div class="small text-dark fw-semibold mb-1">Ganancia Neta (Meds)</div>
                                        <div class="h5 fw-bold text-success mb-0">Q<?php echo number_format($total_profit_meds, 2); ?></div>
                                        <div class="text-xs text-muted mt-1">Margen sobre costo de compra</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Services Column -->
                            <div class="col-lg-4 border-end">
                                <h6 class="text-xs fw-bold text-muted uppercase-label mb-3">Servicios Clínicos</h6>
                                <div class="d-flex flex-column gap-3">
                                    <div class="p-3 rounded-4" style="background: rgba(139, 92, 246, 0.05);">
                                        <div class="small text-muted mb-1">Procedimientos Menores</div>
                                        <div class="h5 fw-bold mb-0" style="color: #8b5cf6;">Q<?php echo number_format($total_procedures, 2); ?></div>
                                    </div>
                                    <div class="p-3 rounded-4" style="background: rgba(6, 182, 212, 0.05);">
                                        <div class="small text-muted mb-1">Exámenes Clínicos</div>
                                        <div class="h5 fw-bold mb-0" style="color: #06b6d4;">Q<?php echo number_format($total_exams_revenue, 2); ?></div>
                                    </div>
                                    <div class="p-3 rounded-4" style="background: rgba(0, 0, 0, 0.02); height: 100%; display: flex; align-items: center; justify-content: center;">
                                        <div class="text-center">
                                            <div class="small text-muted mb-1">Total Servicios</div>
                                            <div class="h4 fw-bold text-dark mb-0">Q<?php echo number_format($total_procedures + $total_exams_revenue + $total_billings, 2); ?></div>
                                            <div class="text-xs text-muted mt-1">Incluye Consultas (Q<?php echo number_format($total_billings, 2); ?>)</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Final Summary Column -->
                            <div class="col-lg-4">
                                <h6 class="text-xs fw-bold text-muted uppercase-label mb-3">Resumen de Periodo</h6>
                                <div class="p-4 rounded-4 h-100 d-flex flex-column justify-content-between" style="background: linear-gradient(135deg, rgba(255,255,255,0.8) 0%, rgba(255,255,255,0.4) 100%); border: 1px solid white;">
                                    <div>
                                        <div class="mb-4">
                                            <div class="text-xs text-muted text-uppercase mb-1">Ingresos Brutos</div>
                                            <div class="h3 fw-extrabold text-dark mb-0">Q<?php echo number_format($total_gross_revenue, 2); ?></div>
                                        </div>
                                        <div class="mb-4">
                                            <div class="text-xs text-muted text-uppercase mb-1">Gastos Totales (Compras)</div>
                                            <div class="h4 fw-bold text-muted mb-0">Q<?php echo number_format($total_purchases_meds, 2); ?></div>
                                        </div>
                                    </div>
                                    <div class="pt-3 border-top mt-3">
                                        <div class="text-xs text-uppercase mb-1 <?php echo $net_performance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            Desempeño Neto
                                        </div>
                                        <div class="h2 fw-extrabold mb-0 <?php echo $net_performance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            Q<?php echo number_format($net_performance, 2); ?>
                                        </div>
                                        <div class="text-xs text-muted mt-2">
                                            <i class="bi bi-info-circle me-1"></i> (Meds + Servicios) - Compras
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Multi-Section Data Grids -->
            <div class="row g-4 mb-5">
                <!-- Patient Stats -->
                <div class="col-lg-6">
                    <div class="report-card">
                        <h5 class="fw-bold mb-4">Demografía de Pacientes</h5>
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <table class="table table-sm table-borderless">
                                    <thead>
                                        <tr>
                                            <th>Género</th>
                                            <th class="text-end">Cant.</th>
                                            <th class="text-end">%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt = $conn->query("SELECT genero, COUNT(*) AS total FROM pacientes GROUP BY genero");
                                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                            $porcentaje = ($row['total'] / max($totalPacientes, 1)) * 100;
                                            echo "<tr>
                                                <td class='fw-medium'>{$row['genero']}</td>
                                                <td class='text-end'>{$row['total']}</td>
                                                <td class='text-end text-muted'>" . number_format($porcentaje, 1) . "%</td>
                                            </tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="col-md-6 text-center border-start py-3">
                                <p class="text-muted text-xs uppercase-label mb-1">Edad Promedio</p>
                                <?php
                                $stmt = $conn->query("SELECT AVG(TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE())) FROM pacientes");
                                $edadPromedio = $stmt->fetchColumn();
                                ?>
                                <h2 class="fw-extrabold text-primary mb-0"><?php echo number_format($edadPromedio, 1); ?></h2>
                                <span class="text-sm fw-medium text-dark">Años</span>
                            </div>
                        </div>
                        <hr class="my-4 opacity-10">
                        <h6 class="fw-bold mb-3">Top Pacientes Frecuentes</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Paciente</th>
                                        <th class="text-center">Citas</th>
                                        <th class="text-end">Última</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->query("
                                        SELECT CONCAT(p.nombre, ' ', p.apellido) AS paciente, COUNT(c.id_cita) AS num_citas, MAX(c.fecha_cita) AS ultima_cita
                                        FROM pacientes p
                                        JOIN citas c ON p.id_paciente = c.historial_id
                                        GROUP BY p.id_paciente
                                        ORDER BY num_citas DESC
                                        LIMIT 3
                                    ");
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<tr>
                                            <td class='fw-medium'>{$row['paciente']}</td>
                                            <td class='text-center'><span class='badge bg-primary bg-opacity-10 text-primary'>{$row['num_citas']}</span></td>
                                            <td class='text-end text-xs'>" . date('d/m/y', strtotime($row['ultima_cita'])) . "</td>
                                        </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Medication Stats -->
                <div class="col-lg-6">
                    <div class="report-card">
                        <h5 class="fw-bold mb-4">Análisis de Despacho</h5>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Medicamento</th>
                                        <th class="text-center">Cant.</th>
                                        <th class="text-end">Recaudación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->query("
                                        SELECT i.nom_medicamento, SUM(dv.cantidad_vendida) AS cantidad, SUM(dv.cantidad_vendida * dv.precio_unitario) AS total
                                        FROM detalle_ventas dv
                                        JOIN inventario i ON dv.id_inventario = i.id_inventario
                                        GROUP BY dv.id_inventario
                                        ORDER BY cantidad DESC
                                        LIMIT 5
                                    ");
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo "<tr>
                                            <td class='fw-medium'>{$row['nom_medicamento']}</td>
                                            <td class='text-center'><span class='badge bg-info bg-opacity-10 text-info'>{$row['cantidad']}</span></td>
                                            <td class='text-end fw-bold'>Q" . number_format($row['total'], 2) . "</td>
                                        </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            <div class="p-3 bg-danger bg-opacity-10 rounded-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="fw-bold text-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Próximos a Vencer (30d)</h6>
                                    <?php
                                    $stmt = $conn->query("SELECT COUNT(*) FROM inventario WHERE fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
                                    $vencerCount = $stmt->fetchColumn();
                                    ?>
                                    <span class="badge bg-danger rounded-pill"><?php echo $vencerCount; ?> Items</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless text-danger text-xs mb-0">
                                        <thead>
                                            <tr>
                                                <th>Medicamento</th>
                                                <th>Stock</th>
                                                <th class="text-end">Fecha</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $stmt = $conn->query("SELECT nom_medicamento, cantidad_med, fecha_vencimiento FROM inventario WHERE fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY fecha_vencimiento ASC LIMIT 3");
                                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                echo "<tr>
                                                    <td>{$row['nom_medicamento']}</td>
                                                    <td>{$row['cantidad_med']}</td>
                                                    <td class='text-end'>" . date('d/m/y', strtotime($row['fecha_vencimiento'])) . "</td>
                                                </tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Procedures and Exams Section -->
            <div class="row g-4 mb-5">
                <!-- Procedures Detail -->
                <div class="col-lg-6">
                    <div class="report-card">
                        <h5 class="fw-bold mb-4"><i class="bi bi-bandaid me-2"></i>Procedimientos Menores del Período</h5>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Paciente</th>
                                        <th>Procedimiento</th>
                                        <th class="text-end">Cobro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->prepare("
                                        SELECT fecha_procedimiento, nombre_paciente, procedimiento, cobro 
                                        FROM procedimientos_menores 
                                        WHERE fecha_procedimiento BETWEEN ? AND ? 
                                        ORDER BY fecha_procedimiento DESC 
                                        LIMIT 10
                                    ");
                                    $stmt->execute([$start_datetime, $end_datetime]);
                                    $hasProc = false;
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $hasProc = true;
                                        echo "<tr>
                                            <td class='text-xs'>" . date('d/m/y', strtotime($row['fecha_procedimiento'])) . "</td>
                                            <td class='fw-medium'>" . htmlspecialchars($row['nombre_paciente']) . "</td>
                                            <td class='text-xs'>" . htmlspecialchars(substr($row['procedimiento'], 0, 30)) . "...</td>
                                            <td class='text-end fw-bold text-primary'>Q" . number_format($row['cobro'], 2) . "</td>
                                        </tr>";
                                    }
                                    if (!$hasProc) {
                                        echo "<tr><td colspan='4' class='text-center text-muted py-4'>No hay procedimientos en este período</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-3">
                            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2">
                                Total: Q<?php echo number_format($total_procedures, 2); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Exams Detail -->
                <div class="col-lg-6">
                    <div class="report-card">
                        <h5 class="fw-bold mb-4"><i class="bi bi-file-earmark-medical me-2"></i>Exámenes Realizados del Período</h5>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Paciente</th>
                                        <th>Examen</th>
                                        <th class="text-end">Cobro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->prepare("
                                        SELECT fecha_examen, nombre_paciente, tipo_examen, cobro 
                                        FROM examenes_realizados 
                                        WHERE fecha_examen BETWEEN ? AND ? 
                                        ORDER BY fecha_examen DESC 
                                        LIMIT 10
                                    ");
                                    $stmt->execute([$start_datetime, $end_datetime]);
                                    $hasExam = false;
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $hasExam = true;
                                        echo "<tr>
                                            <td class='text-xs'>" . date('d/m/y', strtotime($row['fecha_examen'])) . "</td>
                                            <td class='fw-medium'>" . htmlspecialchars($row['nombre_paciente']) . "</td>
                                            <td class='text-xs'>" . htmlspecialchars(substr($row['tipo_examen'], 0, 30)) . "...</td>
                                            <td class='text-end fw-bold text-info'>Q" . number_format($row['cobro'], 2) . "</td>
                                        </tr>";
                                    }
                                    if (!$hasExam) {
                                        echo "<tr><td colspan='4' class='text-center text-muted py-4'>No hay exámenes en este período</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-3">
                            <span class="badge bg-info bg-opacity-10 text-info px-3 py-2">
                                Total: Q<?php echo number_format($total_exams_revenue, 2); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales and Purchases Detail -->
            <div class="row g-4 mb-5">
                <!-- Recent Sales -->
                <div class="col-lg-6">
                    <div class="report-card">
                        <h5 class="fw-bold mb-4"><i class="bi bi-cart-check me-2"></i>Ventas Recientes del Período</h5>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Cliente</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->prepare("
                                        SELECT fecha_venta, nombre_cliente, total 
                                        FROM ventas 
                                        WHERE fecha_venta BETWEEN ? AND ? 
                                        ORDER BY fecha_venta DESC 
                                        LIMIT 10
                                    ");
                                    $stmt->execute([$start_datetime, $end_datetime]);
                                    $hasSales = false;
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $hasSales = true;
                                        echo "<tr>
                                            <td class='text-xs'>" . date('d/m/y H:i', strtotime($row['fecha_venta'])) . "</td>
                                            <td class='fw-medium'>" . htmlspecialchars($row['nombre_cliente']) . "</td>
                                            <td class='text-end fw-bold text-success'>Q" . number_format($row['total'], 2) . "</td>
                                        </tr>";
                                    }
                                    if (!$hasSales) {
                                        echo "<tr><td colspan='3' class='text-center text-muted py-4'>No hay ventas en este período</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-3">
                            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2">
                                Total: Q<?php echo number_format($total_sales_meds, 2); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Recent Purchases -->
                <div class="col-lg-6">
                    <div class="report-card">
                        <h5 class="fw-bold mb-4"><i class="bi bi-cart-plus me-2"></i>Compras Realizadas del Período</h5>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Proveedor</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->prepare("
                                        SELECT purchase_date, provider_name, total_amount 
                                        FROM purchase_headers 
                                        WHERE purchase_date BETWEEN ? AND ? 
                                        ORDER BY purchase_date DESC 
                                        LIMIT 10
                                    ");
                                    $stmt->execute([$fecha_inicio, $fecha_fin]);
                                    $hasPurchases = false;
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $hasPurchases = true;
                                        echo "<tr>
                                            <td class='text-xs'>" . date('d/m/y', strtotime($row['purchase_date'])) . "</td>
                                            <td class='fw-medium'>" . htmlspecialchars($row['provider_name'] ?? 'N/A') . "</td>
                                            <td class='text-end fw-bold text-danger'>Q" . number_format($row['total_amount'], 2) . "</td>
                                        </tr>";
                                    }
                                    if (!$hasPurchases) {
                                        echo "<tr><td colspan='3' class='text-center text-muted py-4'>No hay compras en este período</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-3">
                            <span class="badge bg-danger bg-opacity-10 text-danger px-3 py-2">
                                Total: Q<?php echo number_format($total_purchases_meds, 2); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>

<!-- Export Jornada Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-success bg-opacity-10 border-0">
                <h5 class="modal-title fw-bold text-success"><i class="bi bi-download me-2"></i>Exportar Reporte de Jornada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label for="exportDate" class="form-label fw-bold">Seleccionar Fecha de Jornada</label>
                    <input type="date" class="form-control form-control-lg rounded-3 border-light bg-light" id="exportDate" value="<?php echo date('Y-m-d'); ?>">
                    <div class="form-text mt-2 text-muted">
                        <i class="bi bi-info-circle me-1"></i> La jornada comprende de <strong>8:00 AM</strong> de la fecha seleccionada a <strong>8:00 AM</strong> del día siguiente.
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Formato de Exportación</label>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-danger text-start" onclick="exportReport('pdf')">
                            <i class="bi bi-file-pdf me-2"></i>Ver Jornada (PDF)
                        </button>
                        <button type="button" class="btn btn-outline-success text-start" onclick="exportReport('csv')">
                            <i class="bi bi-file-earmark-spreadsheet me-2"></i>Descargar CSV
                        </button>
                        <button type="button" class="btn btn-outline-success text-start" onclick="exportReport('excel')">
                            <i class="bi bi-file-earmark-excel me-2"></i>Descargar Excel
                        </button>
                        <button type="button" class="btn btn-outline-primary text-start" onclick="exportReport('word')">
                            <i class="bi bi-file-earmark-word me-2"></i>Descargar Word
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<script src="../../assets/js/dashboard-reengineered.js"></script>
<script>
function exportReport(format) {
    const date = document.getElementById('exportDate').value;
    const url = `export_jornada.php?date=${date}&format=${format}`;
    
    if (format === 'pdf') {
        window.open(url, '_blank');
    } else {
        window.location.href = url;
    }
    
    // Close modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('exportModal'));
    modal.hide();
}
</script>