<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

// Inicializar conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Configurar parámetros de fecha
$current_year = date('Y');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-01-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Obtener datos financieros
$financial_data = [];

// Ingresos por mes (simulados para demostración)
// En una implementación real, estos datos vendrían de tablas como pagos, facturas, etc.
$stmt = $conn->prepare("
    SELECT 
        MONTH(fecha_cita) as mes,
        COUNT(*) as total_citas,
        COUNT(*) * 500 as ingresos_estimados
    FROM citas 
    WHERE fecha_cita BETWEEN ? AND ?
    GROUP BY MONTH(fecha_cita)
    ORDER BY mes
");
$stmt->execute([$start_date, $end_date]);
$ingresos_mensuales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_citas = 0;
$total_ingresos = 0;
$ingresos_por_mes = [];
$meses_nombres = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

foreach ($ingresos_mensuales as $ingreso) {
    $total_citas += $ingreso['total_citas'];
    $total_ingresos += $ingreso['ingresos_estimados'];
    $ingresos_por_mes[$ingreso['mes']] = [
        'nombre_mes' => $meses_nombres[$ingreso['mes']],
        'total_citas' => $ingreso['total_citas'],
        'ingresos' => $ingreso['ingresos_estimados']
    ];
}

// Obtener datos de pacientes para análisis de segmentación
$stmt = $conn->query("
    SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) < 18 THEN 'Menores'
            WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) BETWEEN 18 AND 30 THEN 'Jóvenes'
            WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) BETWEEN 31 AND 50 THEN 'Adultos'
            WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) BETWEEN 51 AND 65 THEN 'Adultos Mayores'
            ELSE 'Tercera Edad'
        END as grupo_edad,
        COUNT(*) as total
    FROM pacientes
    GROUP BY grupo_edad
    ORDER BY total DESC
");
$segmentacion_pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Proyección de ingresos para los próximos 3 meses (simulado)
$proyeccion_ingresos = [];
$mes_actual = (int)date('m');
$promedio_ingresos = $total_ingresos > 0 ? $total_ingresos / count($ingresos_mensuales) : 0;

for ($i = 1; $i <= 3; $i++) {
    $mes_proyeccion = ($mes_actual + $i) > 12 ? ($mes_actual + $i - 12) : ($mes_actual + $i);
    $factor_crecimiento = 1 + (0.05 * $i); // Simulamos un crecimiento del 5% mensual
    $proyeccion_ingresos[] = [
        'mes' => $meses_nombres[$mes_proyeccion],
        'ingresos_proyectados' => round($promedio_ingresos * $factor_crecimiento)
    ];
}

// Gastos operativos (simulados para demostración)
$gastos_operativos = [
    ['categoria' => 'Salarios', 'monto' => round($total_ingresos * 0.35)],
    ['categoria' => 'Suministros Médicos', 'monto' => round($total_ingresos * 0.15)],
    ['categoria' => 'Alquiler y Servicios', 'monto' => round($total_ingresos * 0.12)],
    ['categoria' => 'Equipamiento', 'monto' => round($total_ingresos * 0.08)],
    ['categoria' => 'Marketing', 'monto' => round($total_ingresos * 0.05)],
    ['categoria' => 'Otros Gastos', 'monto' => round($total_ingresos * 0.10)]
];

$total_gastos = 0;
foreach ($gastos_operativos as $gasto) {
    $total_gastos += $gasto['monto'];
}

// Calcular rentabilidad
$rentabilidad = $total_ingresos - $total_gastos;
$margen_rentabilidad = $total_ingresos > 0 ? ($rentabilidad / $total_ingresos) * 100 : 0;

$page_title = "Análisis Financiero - Clínica";
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
                            <li class="breadcrumb-item"><a href="index.php">Centro de Análisis</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Análisis Financiero</li>
                        </ol>
                    </nav>
                    <div class="d-flex justify-content-between align-items-center">
                        <h2><i class="bi bi-cash-coin me-2 text-success"></i>Análisis Financiero</h2>
                        <div class="btn-group">
                            <a href="export_financial_report.php?format=pdf&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-outline-danger">
                                <i class="bi bi-file-earmark-pdf me-1"></i> Exportar PDF
                            </a>
                            <a href="export_financial_report.php?format=excel&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-outline-success">
                                <i class="bi bi-file-earmark-excel me-1"></i> Exportar Excel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtros de fecha -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <form action="" method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label for="start_date" class="form-label">Fecha Inicio</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="end_date" class="form-label">Fecha Fin</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-filter me-1"></i> Filtrar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Resumen Financiero -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient-success text-white">
                            <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Resumen Financiero</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-success">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-2">Ingresos Totales</h6>
                                            <h2 class="text-success mb-0">$<?php echo number_format($total_ingresos, 2); ?></h2>
                                            <p class="small text-muted mt-2 mb-0">Período: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-danger">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-2">Gastos Operativos</h6>
                                            <h2 class="text-danger mb-0">$<?php echo number_format($total_gastos, 2); ?></h2>
                                            <p class="small text-muted mt-2 mb-0">Incluye salarios, suministros y servicios</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-primary">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-2">Rentabilidad</h6>
                                            <h2 class="text-primary mb-0">$<?php echo number_format($rentabilidad, 2); ?></h2>
                                            <p class="small text-muted mt-2 mb-0">Margen: <?php echo number_format($margen_rentabilidad, 1); ?>%</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100 border-info">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-2">Total Citas</h6>
                                            <h2 class="text-info mb-0"><?php echo $total_citas; ?></h2>
                                            <p class="small text-muted mt-2 mb-0">Ingreso promedio: $<?php echo $total_citas > 0 ? number_format($total_ingresos / $total_citas, 2) : '0.00'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Gráficos Financieros -->
            <div class="row mb-4">
                <div class="col-md-8 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Ingresos Mensuales</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="ingresosMensualesChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Distribución de Gastos</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="gastosChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Detalles Financieros -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Desglose de Ingresos Mensuales</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Mes</th>
                                            <th class="text-center">Citas</th>
                                            <th class="text-end">Ingresos</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ingresos_por_mes as $mes => $datos): ?>
                                        <tr>
                                            <td><?php echo $datos['nombre_mes']; ?></td>
                                            <td class="text-center"><?php echo $datos['total_citas']; ?></td>
                                            <td class="text-end">$<?php echo number_format($datos['ingresos'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-success fw-bold">
                                            <td>Total</td>
                                            <td class="text-center"><?php echo $total_citas; ?></td>
                                            <td class="text-end">$<?php echo number_format($total_ingresos, 2); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Desglose de Gastos Operativos</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Categoría</th>
                                            <th class="text-end">Monto</th>
                                            <th class="text-end">Porcentaje</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($gastos_operativos as $gasto): ?>
                                        <tr>
                                            <td><?php echo $gasto['categoria']; ?></td>
                                            <td class="text-end">$<?php echo number_format($gasto['monto'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format(($gasto['monto'] / $total_gastos) * 100, 1); ?>%</td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-danger fw-bold">
                                            <td>Total Gastos</td>
                                            <td class="text-end">$<?php echo number_format($total_gastos, 2); ?></td>
                                            <td class="text-end">100%</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Proyecciones y Análisis -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Proyección de Ingresos (Próximos 3 meses)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="proyeccionChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Segmentación de Pacientes por Edad</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="segmentacionChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Indicadores Clave de Rendimiento -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-speedometer2 me-2"></i>Indicadores Clave de Rendimiento (KPIs)</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-2">Ingreso Promedio por Cita</h6>
                                            <h3 class="text-primary mb-0">$<?php echo $total_citas > 0 ? number_format($total_ingresos / $total_citas, 2) : '0.00'; ?></h3>
                                            <div class="progress mt-3" style="height: 5px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: 85%;" aria-valuenow="85" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-2">Margen de Beneficio</h6>
                                            <h3 class="text-success mb-0"><?php echo number_format($margen_rentabilidad, 1); ?>%</h3>
                                            <div class="progress mt-3" style="height: 5px;">
                                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min($margen_rentabilidad, 100); ?>%;" aria-valuenow="<?php echo $margen_rentabilidad; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-2">Costo Operativo por Cita</h6>
                                            <h3 class="text-danger mb-0">$<?php echo $total_citas > 0 ? number_format($total_gastos / $total_citas, 2) : '0.00'; ?></h3>
                                            <div class="progress mt-3" style="height: 5px;">
                                                <div class="progress-bar bg-danger" role="progressbar" style="width: 65%;" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-2">ROI (Retorno de Inversión)</h6>
                                            <h3 class="text-info mb-0"><?php echo number_format(($rentabilidad / $total_gastos) * 100, 1); ?>%</h3>
                                            <div class="progress mt-3" style="height: 5px;">
                                                <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo min(($rentabilidad / $total_gastos) * 100, 100); ?>%;" aria-valuenow="<?php echo ($rentabilidad / $total_gastos) * 100; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recomendaciones Financieras -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Recomendaciones Financieras</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100 border-left-primary">
                                        <div class="card-body">
                                            <h5 class="card-title"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Optimización de Ingresos</h5>
                                            <p class="card-text">
                                                <?php if ($margen_rentabilidad < 30): ?>
                                                Considere revisar la estructura de precios de los servicios. El margen actual está por debajo del objetivo del 30%.
                                                <?php else: ?>
                                                El margen de rentabilidad es saludable. Mantenga la estructura de precios actual y considere expandir servicios premium.
                                                <?php endif; ?>
                                            </p>
                                            <ul class="list-group list-group-flush mt-3">
                                                <li class="list-group-item"><i class="bi bi-check-circle-fill text-success me-2"></i>Revisar precios de servicios especializados</li>
                                                <li class="list-group-item"><i class="bi bi-check-circle-fill text-success me-2"></i>Implementar paquetes de servicios</li>
                                                <li class="list-group-item"><i class="bi bi-check-circle-fill text-success me-2"></i>Desarrollar programas de fidelización</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100 border-left-warning">
                                        <div class="card-body">
                                            <h5 class="card-title"><i class="bi bi-currency-dollar me-2 text-warning"></i>Control de Gastos</h5>
                                            <p class="card-text">
                                                <?php if (($total_gastos / $total_ingresos) > 0.7): ?>
                                                Los gastos operativos representan más del 70% de los ingresos. Se recomienda implementar medidas de reducción de costos.
                                                <?php else: ?>
                                                La proporción de gastos está dentro de parámetros saludables. Continúe monitoreando para mantener la eficiencia.
                                                <?php endif; ?>
                                            </p>
                                            <ul class="list-group list-group-flush mt-3">
                                                <li class="list-group-item"><i class="bi bi-check-circle-fill text-success me-2"></i>Optimizar compra de suministros</li>
                                                <li class="list-group-item"><i class="bi bi-check-circle-fill text-success me-2"></i>Revisar contratos con proveedores</li>
                                                <li class="list-group-item"><i class="bi bi-check-circle-fill text-success me-2"></i>Implementar medidas de eficiencia energética</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100 border-left-success">
                                        <div class="card-body">
                                            <h5 class="card-title"><i class="bi bi-arrow-up-right-circle me-2 text-success"></i>Crecimiento Estratégico</h5>
                                            <p class="card-text">
                                                Basado en la segmentación de pacientes y tendencias de ingresos, se recomienda enfocar esfuerzos en el segmento de 
                                                <?php 
                                                $max_segment = ['grupo' => '', 'total' => 0];
                                                foreach ($segmentacion_pacientes as $segmento) {
                                                    if ($segmento['total'] > $max_segment['total']) {
                                                        $max_segment = ['grupo' => $segmento['grupo_edad'], 'total' => $segmento['total']];
                                                    }
                                                }
                                                echo $max_segment['grupo'];
                                                ?>.
                                            </p>
                                            <ul class="list-group list-group-flush mt-3">
                                                <li class="list-group-item"><i class="bi bi-check-circle-fill text-success me-2"></i>Desarrollar servicios especializados</li>
                                                <li class="list-group-item"><i class="bi bi-check-circle-fill text-success me-2"></i>Implementar campañas de marketing dirigidas</li>
                                                <li class="list-group-item"><i class="bi bi-check-circle-fill text-success me-2"></i>Considerar alianzas estratégicas</li>
                                            </ul>
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

<?php include_once '../../includes/footer.php'; ?>

<!-- Chart.js para visualizaciones -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuración de gráfico de ingresos mensuales
    const ingresosMensualesCtx = document.getElementById('ingresosMensualesChart').getContext('2d');
    const ingresosMensualesChart = new Chart(ingresosMensualesCtx, {
        type: 'line',
        data: {
            labels: [
                <?php 
                foreach ($ingresos_por_mes as $mes => $datos) {
                    echo "'" . $datos['nombre_mes'] . "', ";
                }
                ?>
            ],
            datasets: [{
                label: 'Ingresos ($)',
                data: [
                    <?php 
                    foreach ($ingresos_por_mes as $mes => $datos) {
                        echo $datos['ingresos'] . ", ";
                    }
                    ?>
                ],
                backgroundColor: 'rgba(40, 167, 69, 0.2)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 2,
                tension: 0.3,
                pointBackgroundColor: 'rgba(40, 167, 69, 1)',
                pointBorderColor: '#fff',
                pointRadius: 5,
                pointHoverRadius: 7
            }, {
                label: 'Citas',
                data: [
                    <?php 
                    foreach ($ingresos_por_mes as $mes => $datos) {
                        echo $datos['total_citas'] . ", ";
                    }
                    ?>
                ],
                backgroundColor: 'rgba(0, 123, 255, 0.2)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 2,
                tension: 0.3,
                pointBackgroundColor: 'rgba(0, 123, 255, 1)',
                pointBorderColor: '#fff',
                pointRadius: 5,
                pointHoverRadius: 7,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.datasetIndex === 0) {
                                label += '$' + context.raw.toLocaleString();
                            } else {
                                label += context.raw;
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Ingresos ($)'
                    },
                    beginAtZero: true
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Número de Citas'
                    },
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false
                    }
                }
            }
        }
    });
    
    // Configuración de gráfico de distribución de gastos
    const gastosCtx = document.getElementById('gastosChart').getContext('2d');
    const gastosChart = new Chart(gastosCtx, {
        type: 'doughnut',
        data: {
            labels: [
                <?php 
                foreach ($gastos_operativos as $gasto) {
                    echo "'" . $gasto['categoria'] . "', ";
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php 
                    foreach ($gastos_operativos as $gasto) {
                        echo $gasto['monto'] . ", ";
                    }
                    ?>
                ],
                backgroundColor: [
                    'rgba(220, 53, 69, 0.8)',
                    'rgba(255, 193, 7, 0.8)',
                    'rgba(23, 162, 184, 0.8)',
                    'rgba(40, 167, 69, 0.8)',
                    'rgba(0, 123, 255, 0.8)',
                    'rgba(108, 117, 125, 0.8)'
                ],
                borderColor: [
                    'rgba(220, 53, 69, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(23, 162, 184, 1)',
                    'rgba(40, 167, 69, 1)',
                    'rgba(0, 123, 255, 1)',
                    'rgba(108, 117, 125, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = '$' + context.raw.toLocaleString();
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((context.raw / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '60%'
        }
    });
    
    // Configuración de gráfico de proyección de ingresos
    const proyeccionCtx = document.getElementById('proyeccionChart').getContext('2d');
    const proyeccionChart = new Chart(proyeccionCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php 
                foreach ($proyeccion_ingresos as $proyeccion) {
                    echo "'" . $proyeccion['mes'] . "', ";
                }
                ?>
            ],
            datasets: [{
                label: 'Ingresos Proyectados',
                data: [
                    <?php 
                    foreach ($proyeccion_ingresos as $proyeccion) {
                        echo $proyeccion['ingresos_proyectados'] . ", ";
                    }
                    ?>
                ],
                backgroundColor: 'rgba(0, 123, 255, 0.7)',
                borderColor: 'rgba(0, 123, 255, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Proyección: $' + context.raw.toLocaleString();
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Ingresos Proyectados ($)'
                    }
                }
            }
        }
    });
    
    // Configuración de gráfico de segmentación de pacientes
    const segmentacionCtx = document.getElementById('segmentacionChart').getContext('2d');
    const segmentacionChart = new Chart(segmentacionCtx, {
        type: 'pie',
        data: {
            labels: [
                <?php 
                foreach ($segmentacion_pacientes as $segmento) {
                    echo "'" . $segmento['grupo_edad'] . "', ";
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php 
                    foreach ($segmentacion_pacientes as $segmento) {
                        echo $segmento['total'] . ", ";
                    }
                    ?>
                ],
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
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} pacientes (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Validación del formulario de filtros
    const filterForm = document.querySelector('form');
    filterForm.addEventListener('submit', function(event) {
        const startDate = new Date(document.getElementById('start_date').value);
        const endDate = new Date(document.getElementById('end_date').value);
        
        if (startDate > endDate) {
            event.preventDefault();
            alert('La fecha de inicio no puede ser posterior a la fecha de fin.');
        }
    });
});
</script>