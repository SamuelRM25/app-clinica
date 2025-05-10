<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

// Inicializar conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Obtener estadísticas generales para el dashboard de reportes
$stats = [];

// Total de pacientes
$stmt = $conn->query("SELECT COUNT(*) as total FROM pacientes");
$stats['total_pacientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total de citas
$stmt = $conn->query("SELECT COUNT(*) as total FROM citas");
$stats['total_citas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total de consultas (historial clínico)
$stmt = $conn->query("SELECT COUNT(*) as total FROM historial_clinico");
$stats['total_consultas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Diagnósticos más comunes
$stmt = $conn->query("
    SELECT diagnostico, COUNT(*) as total 
    FROM (
        SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(diagnostico, '\r\n', n.digit+1), '\r\n', -1) as diagnostico
        FROM historial_clinico
        CROSS JOIN (SELECT 0 as digit UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) as n
        WHERE LENGTH(diagnostico) - LENGTH(REPLACE(diagnostico, '\r\n', '')) >= n.digit
    ) as diagnoses
    WHERE diagnostico != ''
    GROUP BY diagnostico
    ORDER BY total DESC
    LIMIT 5
");
$stats['diagnosticos_comunes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Medicamentos más recetados
$stmt = $conn->query("
    SELECT tratamiento, COUNT(*) as total 
    FROM (
        SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(tratamiento, '\r\n', n.digit+1), '\r\n', -1) as tratamiento
        FROM historial_clinico
        CROSS JOIN (SELECT 0 as digit UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) as n
        WHERE LENGTH(tratamiento) - LENGTH(REPLACE(tratamiento, '\r\n', '')) >= n.digit
    ) as treatments
    WHERE tratamiento != ''
    GROUP BY tratamiento
    ORDER BY total DESC
    LIMIT 5
");
$stats['medicamentos_comunes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener reportes recientes (simulados para la demostración)
$reportes_recientes = [
    [
        'tipo' => 'Financiero',
        'fecha' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'periodo' => 'Último mes',
        'generado_por' => $_SESSION['nombre'] . ' ' . $_SESSION['apellido']
    ],
    [
        'tipo' => 'Estadísticas de Citas',
        'fecha' => date('Y-m-d H:i:s', strtotime('-2 day')),
        'periodo' => 'Último trimestre',
        'generado_por' => $_SESSION['nombre'] . ' ' . $_SESSION['apellido']
    ],
    [
        'tipo' => 'Demografía de Pacientes',
        'fecha' => date('Y-m-d H:i:s', strtotime('-3 day')),
        'periodo' => 'Todos los pacientes',
        'generado_por' => $_SESSION['nombre'] . ' ' . $_SESSION['apellido']
    ],
    [
        'tipo' => 'Inventario',
        'fecha' => date('Y-m-d H:i:s', strtotime('-4 day')),
        'periodo' => 'Stock actual',
        'generado_por' => $_SESSION['nombre'] . ' ' . $_SESSION['apellido']
    ]
];

$page_title = "Centro de Análisis y Reportes - Clínica";
include_once '../../includes/header.php';
?>

<div class="d-flex">
    <?php include_once '../../includes/sidebar.php'; ?>
    
    <div class="main-content flex-grow-1">
        <div class="container-fluid">
            <div class="row mb-4">
                <div class="col-12">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../dashboard/index.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Centro de Análisis</li>
                        </ol>
                    </nav>
                    <h2><i class="bi bi-graph-up-arrow me-2"></i>Centro de Análisis y Reportes</h2>
                    <p class="lead">Analice datos clínicos, genere informes detallados y visualice tendencias para mejorar la toma de decisiones.</p>
                </div>
            </div>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Dashboard de Estadísticas -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>Dashboard de Análisis</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-primary">
                                        <div class="card-body text-center">
                                            <h1 class="display-4 text-primary"><?php echo $stats['total_pacientes']; ?></h1>
                                            <p class="text-muted mb-0">Pacientes Registrados</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-success">
                                        <div class="card-body text-center">
                                            <h1 class="display-4 text-success"><?php echo $stats['total_citas']; ?></h1>
                                            <p class="text-muted mb-0">Citas Programadas</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-info">
                                        <div class="card-body text-center">
                                            <h1 class="display-4 text-info"><?php echo $stats['total_consultas']; ?></h1>
                                            <p class="text-muted mb-0">Consultas Realizadas</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-warning">
                                        <div class="card-body text-center">
                                            <h1 class="display-4 text-warning"><?php echo count($stats['diagnosticos_comunes']); ?></h1>
                                            <p class="text-muted mb-0">Diagnósticos Principales</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mt-4">
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Diagnósticos Más Frecuentes</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="diagnosticosChart" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0">Medicamentos Más Recetados</h6>
                                        </div>
                                        <div class="card-body">
                                            <canvas id="medicamentosChart" height="200"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Reportes Predefinidos y Personalizados -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-gradient-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-file-earmark-bar-graph me-2"></i>Reportes Predefinidos</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <a href="financial_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-cash-coin me-2 text-success"></i>
                                        <strong>Análisis Financiero</strong>
                                        <p class="text-muted mb-0 small">Ingresos, gastos, rentabilidad y proyecciones</p>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">
                                        <i class="bi bi-arrow-right"></i>
                                    </span>
                                </a>
                                <a href="appointments_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-calendar-check me-2 text-primary"></i>
                                        <strong>Análisis de Citas</strong>
                                        <p class="text-muted mb-0 small">Patrones, distribución y eficiencia de citas</p>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">
                                        <i class="bi bi-arrow-right"></i>
                                    </span>
                                </a>
                                <a href="patients_demographics.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-people me-2 text-info"></i>
                                        <strong>Análisis Demográfico</strong>
                                        <p class="text-muted mb-0 small">Perfiles de pacientes y segmentación</p>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">
                                        <i class="bi bi-arrow-right"></i>
                                    </span>
                                </a>
                                <a href="clinical_analytics.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-activity me-2 text-danger"></i>
                                        <strong>Análisis Clínico</strong>
                                        <p class="text-muted mb-0 small">Tendencias de diagnósticos y tratamientos</p>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">
                                        <i class="bi bi-arrow-right"></i>
                                    </span>
                                </a>
                                <a href="inventory_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-box-seam me-2 text-warning"></i>
                                        <strong>Gestión de Inventario</strong>
                                        <p class="text-muted mb-0 small">Análisis de stock, rotación y valoración</p>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">
                                        <i class="bi bi-arrow-right"></i>
                                    </span>
                                </a>
                                <a href="sales_report.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-cart-check me-2 text-success"></i>
                                        <strong>Análisis de Ventas</strong>
                                        <p class="text-muted mb-0 small">Tendencias de ventas y productos populares</p>
                                    </div>
                                    <span class="badge bg-primary rounded-pill">
                                        <i class="bi bi-arrow-right"></i>
                                    </span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-header bg-gradient-success text-white">
                            <h5 class="mb-0"><i class="bi bi-gear-fill me-2"></i>Análisis Personalizado</h5>
                        </div>
                        <div class="card-body">
                            <form action="generate_custom_report.php" method="POST" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label for="reportType" class="form-label">Tipo de Análisis</label>
                                    <select class="form-select" id="reportType" name="reportType" required>
                                        <option value="" selected disabled>Seleccione un tipo de análisis</option>
                                        <option value="citas">Citas y Agenda</option>
                                        <option value="pacientes">Demografía de Pacientes</option>
                                        <option value="diagnosticos">Tendencias de Diagnósticos</option>
                                        <option value="tratamientos">Análisis de Tratamientos</option>
                                        <option value="inventario">Gestión de Inventario</option>
                                        <option value="compras">Análisis de Compras</option>
                                        <option value="ventas">Análisis de Ventas</option>
                                        <option value="cobros">Gestión de Cobros</option>
                                        <option value="financiero">Análisis Financiero</option>
                                    </select>
                                    <div class="invalid-feedback">
                                        Por favor seleccione un tipo de análisis.
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="startDate" class="form-label">Fecha Inicio</label>
                                        <input type="date" class="form-control" id="startDate" name="startDate" required value="<?php echo date('Y-m-01'); ?>">
                                        <div class="invalid-feedback">
                                            Por favor seleccione una fecha de inicio.
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="endDate" class="form-label">Fecha Fin</label>
                                        <input type="date" class="form-control" id="endDate" name="endDate" required value="<?php echo date('Y-m-d'); ?>">
                                        <div class="invalid-feedback">
                                            Por favor seleccione una fecha de fin.
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="groupBy" class="form-label">Agrupar por</label>
                                        <select class="form-select" id="groupBy" name="groupBy">
                                            <option value="dia">Día</option>
                                            <option value="semana">Semana</option>
                                            <option value="mes" selected>Mes</option>
                                            <option value="trimestre">Trimestre</option>
                                            <option value="año">Año</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="format" class="form-label">Formato de Salida</label>
                                        <select class="form-select" id="format" name="format">
                                            <option value="web" selected>Visualización Interactiva</option>
                                            <option value="pdf">Exportar como PDF</option>
                                            <option value="excel">Exportar como Excel</option>
                                            <option value="csv">Exportar como CSV</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="visualization" class="form-label">Tipo de Visualización</label>
                                    <select class="form-select" id="visualization" name="visualization">
                                        <option value="table" selected>Tabla de Datos</option>
                                        <option value="bar">Gráfico de Barras</option>
                                        <option value="line">Gráfico de Líneas</option>
                                        <option value="pie">Gráfico Circular</option>
                                        <option value="mixed">Visualización Mixta</option>
                                    </select>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-graph-up me-2"></i>Generar Análisis
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Reportes Recientes -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient-info text-white">
                            <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Análisis Recientes</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tipo de Análisis</th>
                                            <th>Fecha de Generación</th>
                                            <th>Período Analizado</th>
                                            <th>Generado por</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reportes_recientes as $reporte): ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                $icon_class = '';
                                                switch ($reporte['tipo']) {
                                                    case 'Financiero': $icon_class = 'bi-cash-coin text-success'; break;
                                                    case 'Estadísticas de Citas': $icon_class = 'bi-calendar-check text-primary'; break;
                                                    case 'Demografía de Pacientes': $icon_class = 'bi-people text-info'; break;
                                                    case 'Inventario': $icon_class = 'bi-box-seam text-warning'; break;
                                                    default: $icon_class = 'bi-file-earmark-text'; break;
                                                }
                                                ?>
                                                <i class="bi <?php echo $icon_class; ?> me-2"></i>
                                                <?php echo htmlspecialchars($reporte['tipo']); ?>
                                            </td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($reporte['fecha'])); ?></td>
                                            <td><?php echo htmlspecialchars($reporte['periodo']); ?></td>
                                            <td><?php echo htmlspecialchars($reporte['generado_por']); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="#" class="btn btn-outline-primary" title="Ver reporte">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-outline-danger" title="Exportar PDF">
                                                        <i class="bi bi-file-earmark-pdf"></i>
                                                    </a>
                                                    <a href="#" class="btn btn-outline-success" title="Exportar Excel">
                                                        <i class="bi bi-file-earmark-excel"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Ayuda y Guía de Reportes -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient-dark text-white">
                            <h5 class="mb-0"><i class="bi bi-question-circle me-2"></i>Guía de Análisis y Reportes</h5>
                        </div>
                        <div class="card-body">
                            <div class="accordion" id="accordionReports">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingOne">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                                            ¿Cómo generar un análisis personalizado?
                                        </button>
                                    </h2>
                                    <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionReports">
                                        <div class="accordion-body">
                                            <p>Para generar un análisis personalizado, siga estos pasos:</p>
                                            <ol>
                                                <li>Seleccione el tipo de análisis que desea generar.</li>
                                                <li>Defina el período de tiempo para el análisis seleccionando las fechas de inicio y fin.</li>
                                                <li>Elija cómo desea agrupar los datos (por día, semana, mes, etc.).</li>
                                                <li>Seleccione el formato de salida deseado (visualización web, PDF, Excel, etc.).</li>
                                                <li>Elija el tipo de visualización que prefiere para sus datos.</li>
                                                <li>Haga clic en "Generar Análisis" para procesar los datos.</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingTwo">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                            ¿Qué tipos de análisis están disponibles?
                                        </button>
                                    </h2>
                                    <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#accordionReports">
                                        <div class="accordion-body">
                                            <p>El sistema ofrece varios tipos de análisis predefinidos:</p>
                                            <ul>
                                                <li><strong>Análisis Financiero:</strong> Ingresos, gastos, rentabilidad y proyecciones financieras.</li>
                                                <li><strong>Análisis de Citas:</strong> Patrones de programación, distribución por días/horas y eficiencia de citas.</li>
                                                <li><strong>Análisis Demográfico:</strong> Perfiles de pacientes, distribución por edad, género y ubicación.</li>
                                                <li><strong>Análisis Clínico:</strong> Tendencias de diagnósticos, tratamientos y resultados clínicos.</li>
                                                <li><strong>Gestión de Inventario:</strong> Análisis de stock, rotación de productos y valoración de inventario.</li>
                                                <li><strong>Análisis de Ventas:</strong> Tendencias de ventas, productos más vendidos y comportamiento de clientes.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingThree">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                            ¿Cómo interpretar los gráficos y visualizaciones?
                                        </button>
                                    </h2>
                                    <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#accordionReports">
                                        <div class="accordion-body">
                                            <p>Para interpretar correctamente los gráficos y visualizaciones:</p>
                                            <ul>
                                                <li><strong>Gráficos de Barras:</strong> Útiles para comparar categorías o períodos de tiempo. La altura de cada barra representa el valor de cada categoría.</li>
                                                <li><strong>Gráficos de Líneas:</strong> Ideales para mostrar tendencias a lo largo del tiempo. Observe la dirección de la línea para identificar patrones ascendentes o descendentes.</li>
                                                <li><strong>Gráficos Circulares:</strong> Muestran la proporción de cada categoría respecto al total. Útiles para visualizar distribuciones porcentuales.</li>
                                                <li><strong>Tablas de Datos:</strong> Proporcionan información detallada y precisa sobre cada elemento analizado.</li>
                                            </ul>
                                            <p>Recuerde que puede exportar cualquier visualización a PDF o Excel para un análisis más detallado.</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="headingFour">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                            ¿Cómo exportar y compartir reportes?
                                        </button>
                                    </h2>
                                    <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#accordionReports">
                                        <div class="accordion-body">
                                            <p>Para exportar y compartir sus reportes:</p>
                                            <ol>
                                                <li>Genere el reporte deseado utilizando el formulario de análisis personalizado.</li>
                                                <li>En la visualización del reporte, utilice los botones de exportación (PDF, Excel, CSV) según el formato que necesite.</li>
                                                <li>Para reportes predefinidos, use los botones de exportación disponibles en la sección de acciones.</li>
                                                <li>Los archivos exportados se pueden compartir por correo electrónico o imprimir para presentaciones.</li>
                                            </ol>
                                            <p>Nota: Los reportes en formato PDF incluyen automáticamente el logotipo de la clínica y la fecha de generación para fines de documentación.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts para gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script>
// Validación del formulario
(function() {
    'use strict';
    
    // Fetch all forms we want to apply custom validation styles to
    var forms = document.querySelectorAll('.needs-validation');
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
})();

// Configuración de los gráficos
document.addEventListener('DOMContentLoaded', function() {
    // Preparar datos para el gráfico de diagnósticos
    const diagnosticosLabels = [
        <?php 
        foreach ($stats['diagnosticos_comunes'] as $diagnostico) {
            echo "'" . addslashes($diagnostico['diagnostico']) . "', ";
        }
        ?>
    ];
    
    const diagnosticosData = [
        <?php 
        foreach ($stats['diagnosticos_comunes'] as $diagnostico) {
            echo $diagnostico['total'] . ", ";
        }
        ?>
    ];
    
    // Preparar datos para el gráfico de medicamentos
    const medicamentosLabels = [
        <?php 
        foreach ($stats['medicamentos_comunes'] as $medicamento) {
            echo "'" . addslashes($medicamento['tratamiento']) . "', ";
        }
        ?>
    ];
    
    const medicamentosData = [
        <?php 
        foreach ($stats['medicamentos_comunes'] as $medicamento) {
            echo $medicamento['total'] . ", ";
        }
        ?>
    ];
    
    // Gráfico de diagnósticos
    const diagnosticosCtx = document.getElementById('diagnosticosChart').getContext('2d');
    const diagnosticosChart = new Chart(diagnosticosCtx, {
        type: 'bar',
        data: {
            labels: diagnosticosLabels,
            datasets: [{
                label: 'Frecuencia',
                data: diagnosticosData,
                backgroundColor: [
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(255, 99, 132, 0.7)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(255, 99, 132, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Frecuencia: ${context.raw} casos`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Gráfico de medicamentos
    const medicamentosCtx = document.getElementById('medicamentosChart').getContext('2d');
    const medicamentosChart = new Chart(medicamentosCtx, {
        type: 'pie',
        data: {
            labels: medicamentosLabels,
            datasets: [{
                data: medicamentosData,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.raw / total) * 100);
                            return `${context.label}: ${context.raw} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Configuración dinámica del formulario de reportes
    const reportTypeSelect = document.getElementById('reportType');
    const groupBySelect = document.getElementById('groupBy');
    const visualizationSelect = document.getElementById('visualization');
    
    reportTypeSelect.addEventListener('change', function() {
        // Ajustar opciones de agrupación según el tipo de reporte
        const reportType = this.value;
        
        // Limpiar opciones actuales
        while (groupBySelect.options.length > 0) {
            groupBySelect.remove(0);
        }
        
        // Añadir opciones según el tipo de reporte
        if (reportType === 'citas' || reportType === 'ventas' || reportType === 'compras' || reportType === 'financiero') {
            addOption(groupBySelect, 'dia', 'Día');
            addOption(groupBySelect, 'semana', 'Semana');
            addOption(groupBySelect, 'mes', 'Mes', true);
            addOption(groupBySelect, 'trimestre', 'Trimestre');
            addOption(groupBySelect, 'año', 'Año');
        } else if (reportType === 'pacientes') {
            addOption(groupBySelect, 'edad', 'Edad');
            addOption(groupBySelect, 'genero', 'Género', true);
            addOption(groupBySelect, 'ubicacion', 'Ubicación');
        } else if (reportType === 'diagnosticos' || reportType === 'tratamientos') {
            addOption(groupBySelect, 'frecuencia', 'Frecuencia', true);
            addOption(groupBySelect, 'mes', 'Mes');
            addOption(groupBySelect, 'edad_paciente', 'Edad del Paciente');
            addOption(groupBySelect, 'genero_paciente', 'Género del Paciente');
        } else if (reportType === 'inventario') {
            addOption(groupBySelect, 'categoria', 'Categoría', true);
            addOption(groupBySelect, 'proveedor', 'Proveedor');
            addOption(groupBySelect, 'stock', 'Nivel de Stock');
            addOption(groupBySelect, 'vencimiento', 'Fecha de Vencimiento');
        }
        
        // Ajustar visualizaciones recomendadas
        while (visualizationSelect.options.length > 0) {
            visualizationSelect.remove(0);
        }
        
        addOption(visualizationSelect, 'table', 'Tabla de Datos');
        
        if (reportType === 'citas' || reportType === 'ventas' || reportType === 'compras' || reportType === 'financiero') {
            addOption(visualizationSelect, 'line', 'Gráfico de Líneas', true);
            addOption(visualizationSelect, 'bar', 'Gráfico de Barras');
            addOption(visualizationSelect, 'mixed', 'Visualización Mixta');
        } else if (reportType === 'pacientes' || reportType === 'diagnosticos' || reportType === 'tratamientos') {
            addOption(visualizationSelect, 'pie', 'Gráfico Circular', true);
            addOption(visualizationSelect, 'bar', 'Gráfico de Barras');
            addOption(visualizationSelect, 'mixed', 'Visualización Mixta');
        } else if (reportType === 'inventario') {
            addOption(visualizationSelect, 'bar', 'Gráfico de Barras', true);
            addOption(visualizationSelect, 'pie', 'Gráfico Circular');
            addOption(visualizationSelect, 'mixed', 'Visualización Mixta');
        }
    });
    
    // Función auxiliar para añadir opciones a un select
    function addOption(selectElement, value, text, selected = false) {
        const option = document.createElement('option');
        option.value = value;
        option.text = text;
        option.selected = selected;
        selectElement.add(option);
    }
});
</script>
<?php include_once '../../includes/footer.php'; ?>

<!-- Chart.js para visualizaciones -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

<script>
// Validación del formulario
(function() {
    'use strict';
    
    // Fetch all forms we want to apply custom validation styles to
    var forms = document.querySelectorAll('.needs-validation');
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
})();

// Configuración de los gráficos
document.addEventListener('DOMContentLoaded', function() {
    // Preparar datos para el gráfico de diagnósticos
    const diagnosticosLabels = [
        <?php 
        foreach ($stats['diagnosticos_comunes'] as $diagnostico) {
            echo "'" . addslashes($diagnostico['diagnostico']) . "', ";
        }
        ?>
    ];
    
    const diagnosticosData = [
        <?php 
        foreach ($stats['diagnosticos_comunes'] as $diagnostico) {
            echo $diagnostico['total'] . ", ";
        }
        ?>
    ];
    
    // Preparar datos para el gráfico de medicamentos
    const medicamentosLabels = [
        <?php 
        foreach ($stats['medicamentos_comunes'] as $medicamento) {
            echo "'" . addslashes($medicamento['tratamiento']) . "', ";
        }
        ?>
    ];
    
    const medicamentosData = [
        <?php 
        foreach ($stats['medicamentos_comunes'] as $medicamento) {
            echo $medicamento['total'] . ", ";
        }
        ?>
    ];
    
    // Gráfico de diagnósticos
    const diagnosticosCtx = document.getElementById('diagnosticosChart').getContext('2d');
    const diagnosticosChart = new Chart(diagnosticosCtx, {
        type: 'bar',
        data: {
            labels: diagnosticosLabels,
            datasets: [{
                label: 'Frecuencia',
                data: diagnosticosData,
                backgroundColor: [
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(255, 99, 132, 0.7)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(255, 99, 132, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Frecuencia: ${context.raw} casos`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
    
    // Gráfico de medicamentos
    const medicamentosCtx = document.getElementById('medicamentosChart').getContext('2d');
    const medicamentosChart = new Chart(medicamentosCtx, {
        type: 'pie',
        data: {
            labels: medicamentosLabels,
            datasets: [{
                data: medicamentosData,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.raw / total) * 100);
                            return `${context.label}: ${context.raw} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Configuración dinámica del formulario de reportes
    const reportTypeSelect = document.getElementById('reportType');
    const groupBySelect = document.getElementById('groupBy');
    const visualizationSelect = document.getElementById('visualization');
    
    reportTypeSelect.addEventListener('change', function() {
        // Ajustar opciones de agrupación según el tipo de reporte
        const reportType = this.value;
        
        // Limpiar opciones actuales
        while (groupBySelect.options.length > 0) {
            groupBySelect.remove(0);
        }
        
        // Añadir opciones según el tipo de reporte
        if (reportType === 'citas' || reportType === 'ventas' || reportType === 'compras' || reportType === 'financiero') {
            addOption(groupBySelect, 'dia', 'Día');
            addOption(groupBySelect, 'semana', 'Semana');
            addOption(groupBySelect, 'mes', 'Mes', true);
            addOption(groupBySelect, 'trimestre', 'Trimestre');
            addOption(groupBySelect, 'año', 'Año');
        } else if (reportType === 'pacientes') {
            addOption(groupBySelect, 'edad', 'Edad');
            addOption(groupBySelect, 'genero', 'Género', true);
            addOption(groupBySelect, 'ubicacion', 'Ubicación');
        } else if (reportType === 'diagnosticos' || reportType === 'tratamientos') {
            addOption(groupBySelect, 'frecuencia', 'Frecuencia', true);
            addOption(groupBySelect, 'mes', 'Mes');
            addOption(groupBySelect, 'edad_paciente', 'Edad del Paciente');
            addOption(groupBySelect, 'genero_paciente', 'Género del Paciente');
        } else if (reportType === 'inventario') {
            addOption(groupBySelect, 'categoria', 'Categoría', true);
            addOption(groupBySelect, 'proveedor', 'Proveedor');
            addOption(groupBySelect, 'stock', 'Nivel de Stock');
            addOption(groupBySelect, 'vencimiento', 'Fecha de Vencimiento');
        }
        
        // Ajustar visualizaciones recomendadas
        while (visualizationSelect.options.length > 0) {
            visualizationSelect.remove(0);
        }
        
        addOption(visualizationSelect, 'table', 'Tabla de Datos');
        
        if (reportType === 'citas' || reportType === 'ventas' || reportType === 'compras' || reportType === 'financiero') {
            addOption(visualizationSelect, 'line', 'Gráfico de Líneas', true);
            addOption(visualizationSelect, 'bar', 'Gráfico de Barras');
            addOption(visualizationSelect, 'mixed', 'Visualización Mixta');
        } else if (reportType === 'pacientes' || reportType === 'diagnosticos' || reportType === 'tratamientos') {
            addOption(visualizationSelect, 'pie', 'Gráfico Circular', true);
            addOption(visualizationSelect, 'bar', 'Gráfico de Barras');
            addOption(visualizationSelect, 'mixed', 'Visualización Mixta');
        } else if (reportType === 'inventario') {
            addOption(visualizationSelect, 'bar', 'Gráfico de Barras', true);
            addOption(visualizationSelect, 'pie', 'Gráfico Circular');
            addOption(visualizationSelect, 'mixed', 'Visualización Mixta');
        }
    });
    
    // Función auxiliar para añadir opciones a un select
    function addOption(selectElement, value, text, selected = false) {
        const option = document.createElement('option');
        option.value = value;
        option.text = text;
        option.selected = selected;
        selectElement.add(option);
    }
});
</script>