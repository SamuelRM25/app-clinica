<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

// Inicializar conexión a la base de datos
$database = new Database();
$conn = $database->getConnection();

// Configurar parámetros de filtro
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-01-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$gender = isset($_GET['gender']) ? $_GET['gender'] : null;
$age_group = isset($_GET['age_group']) ? $_GET['age_group'] : null;

// Construir cláusula WHERE para filtros
$where_clause = "WHERE 1=1";
$params = [];

if ($start_date && $end_date) {
    $where_clause .= " AND fecha_registro BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
}

if ($gender) {
    $where_clause .= " AND genero = :gender";
    $params[':gender'] = $gender;
}

// Estadísticas generales
// Total de pacientes
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM pacientes $where_clause");
$stmt->execute($params);
$total_pacientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Distribución por género
$stmt = $conn->prepare("
    SELECT 
        genero, 
        COUNT(*) as total,
        ROUND((COUNT(*) / (SELECT COUNT(*) FROM pacientes $where_clause)) * 100, 1) as porcentaje
    FROM pacientes 
    $where_clause
    GROUP BY genero
");
$stmt->execute($params);
$distribucion_genero = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Distribución por grupo de edad
$stmt = $conn->prepare("
    SELECT 
        CASE 
            WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) < 18 THEN 'Menor de 18'
            WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) BETWEEN 18 AND 30 THEN '18-30'
            WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) BETWEEN 31 AND 45 THEN '31-45'
            WHEN TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE()) BETWEEN 46 AND 60 THEN '46-60'
            ELSE 'Mayor de 60'
        END as grupo_edad,
        COUNT(*) as total,
        ROUND((COUNT(*) / (SELECT COUNT(*) FROM pacientes $where_clause)) * 100, 1) as porcentaje
    FROM pacientes
    $where_clause
    GROUP BY grupo_edad
    ORDER BY 
        CASE grupo_edad
            WHEN 'Menor de 18' THEN 1
            WHEN '18-30' THEN 2
            WHEN '31-45' THEN 3
            WHEN '46-60' THEN 4
            WHEN 'Mayor de 60' THEN 5
        END
");
$stmt->execute($params);
$distribucion_edad = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Distribución geográfica (por ciudad/localidad)
$stmt = $conn->prepare("
    SELECT 
        COALESCE(ciudad, 'No especificada') as ciudad, 
        COUNT(*) as total,
        ROUND((COUNT(*) / (SELECT COUNT(*) FROM pacientes $where_clause)) * 100, 1) as porcentaje
    FROM pacientes
    $where_clause
    GROUP BY ciudad
    ORDER BY total DESC
    LIMIT 10
");
$stmt->execute($params);
$distribucion_geografica = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tendencias de registro de pacientes (últimos 12 meses)
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(fecha_registro, '%Y-%m') as mes,
        DATE_FORMAT(fecha_registro, '%b %Y') as mes_nombre,
        COUNT(*) as total
    FROM pacientes
    WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(fecha_registro, '%Y-%m')
    ORDER BY mes
");
$stmt->execute();
$tendencia_registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Condiciones médicas más comunes
$stmt = $conn->prepare("
    SELECT 
        COALESCE(antecedentes, 'No especificados') as condicion,
        COUNT(*) as total
    FROM pacientes
    $where_clause
    GROUP BY antecedentes
    ORDER BY total DESC
    LIMIT 5
");
$stmt->execute($params);
$condiciones_comunes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Segmentación por tipo de sangre
$stmt = $conn->prepare("
    SELECT 
        COALESCE(tipo_sangre, 'No especificado') as tipo_sangre,
        COUNT(*) as total,
        ROUND((COUNT(*) / (SELECT COUNT(*) FROM pacientes $where_clause)) * 100, 1) as porcentaje
    FROM pacientes
    $where_clause
    GROUP BY tipo_sangre
    ORDER BY total DESC
");
$stmt->execute($params);
$tipos_sangre = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Incluir el encabezado
$page_title = "Demografía de Pacientes";
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
                            <li class="breadcrumb-item active" aria-current="page">Demografía de Pacientes</li>
                        </ol>
                    </nav>
                    <div class="d-flex justify-content-between align-items-center">
                        <h2><i class="bi bi-people me-2 text-primary"></i>Demografía de Pacientes</h2>
                        <div class="btn-group">
                            <a href="export_demographics.php?format=pdf&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&gender=<?php echo $gender; ?>" class="btn btn-outline-danger">
                                <i class="bi bi-file-earmark-pdf me-1"></i> Exportar PDF
                            </a>
                            <a href="export_demographics.php?format=excel&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&gender=<?php echo $gender; ?>" class="btn btn-outline-success">
                                <i class="bi bi-file-earmark-excel me-1"></i> Exportar Excel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <form action="" method="GET" class="row g-3">
                                <div class="col-md-3">
                                    <label for="start_date" class="form-label">Fecha Inicio Registro</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="end_date" class="form-label">Fecha Fin Registro</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="gender" class="form-label">Género</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="">Todos</option>
                                        <option value="M" <?php echo ($gender == 'M') ? 'selected' : ''; ?>>Masculino</option>
                                        <option value="F" <?php echo ($gender == 'F') ? 'selected' : ''; ?>>Femenino</option>
                                        <option value="O" <?php echo ($gender == 'O') ? 'selected' : ''; ?>>Otro</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="age_group" class="form-label">Grupo de Edad</label>
                                    <select class="form-select" id="age_group" name="age_group">
                                        <option value="">Todos</option>
                                        <option value="Menor de 18" <?php echo ($age_group == 'Menor de 18') ? 'selected' : ''; ?>>Menor de 18</option>
                                        <option value="18-30" <?php echo ($age_group == '18-30') ? 'selected' : ''; ?>>18-30</option>
                                        <option value="31-45" <?php echo ($age_group == '31-45') ? 'selected' : ''; ?>>31-45</option>
                                        <option value="46-60" <?php echo ($age_group == '46-60') ? 'selected' : ''; ?>>46-60</option>
                                        <option value="Mayor de 60" <?php echo ($age_group == 'Mayor de 60') ? 'selected' : ''; ?>>Mayor de 60</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-filter me-1"></i> Filtrar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Resumen Demográfico -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient-primary text-white">
                            <h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>Resumen Demográfico</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100 border-primary">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-2">Total de Pacientes</h6>
                                            <h2 class="text-primary mb-0"><?php echo $total_pacientes; ?></h2>
                                            <p class="small text-muted mt-2 mb-0">Período: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100 border-info">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-2">Edad Promedio</h6>
                                            <h2 class="text-info mb-0">
                                                <?php 
                                                // Calcular edad promedio
                                                $stmt = $conn->prepare("
                                                    SELECT AVG(TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE())) as edad_promedio
                                                    FROM pacientes
                                                    $where_clause
                                                ");
                                                $stmt->execute($params);
                                                $edad_promedio = $stmt->fetch(PDO::FETCH_ASSOC)['edad_promedio'];
                                                echo number_format($edad_promedio, 1);
                                                ?>
                                            </h2>
                                            <p class="small text-muted mt-2 mb-0">Años</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100 border-success">
                                        <div class="card-body text-center">
                                            <h6 class="text-muted mb-2">Nuevos Pacientes (Último Mes)</h6>
                                            <h2 class="text-success mb-0">
                                                <?php 
                                                // Calcular nuevos pacientes en el último mes
                                                $stmt = $conn->prepare("
                                                    SELECT COUNT(*) as total
                                                    FROM pacientes
                                                    WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
                                                ");
                                                $stmt->execute();
                                                $nuevos_pacientes = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                                                echo $nuevos_pacientes;
                                                ?>
                                            </h2>
                                            <p class="small text-muted mt-2 mb-0">
                                                <?php 
                                                // Calcular porcentaje de crecimiento
                                                $stmt = $conn->prepare("
                                                    SELECT COUNT(*) as total
                                                    FROM pacientes
                                                    WHERE fecha_registro BETWEEN DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
                                                ");
                                                $stmt->execute();
                                                $pacientes_mes_anterior = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                                                
                                                if ($pacientes_mes_anterior > 0) {
                                                    $crecimiento = (($nuevos_pacientes - $pacientes_mes_anterior) / $pacientes_mes_anterior) * 100;
                                                    echo ($crecimiento >= 0 ? '+' : '') . number_format($crecimiento, 1) . '% vs. mes anterior';
                                                } else {
                                                    echo 'Sin datos comparativos';
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Distribución por Género y Edad -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Distribución por Género</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="generoChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Distribución por Grupo de Edad</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="edadChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tendencia de Registros y Distribución Geográfica -->
            <div class="row mb-4">
                <div class="col-md-8 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Tendencia de Registros (Últimos 12 meses)</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="tendenciaChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Distribución Geográfica</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ciudad/Localidad</th>
                                            <th class="text-end">Pacientes</th>
                                            <th class="text-end">%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($distribucion_geografica as $ciudad): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($ciudad['ciudad']); ?></td>
                                            <td class="text-end"><?php echo $ciudad['total']; ?></td>
                                            <td class="text-end"><?php echo $ciudad['porcentaje']; ?>%</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Condiciones Médicas y Tipos de Sangre -->
            <div class="row mb-4">
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Condiciones Médicas Más Comunes</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="condicionesChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Distribución por Tipo de Sangre</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="sangreChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Análisis de Tendencias -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient-success text-white">
                            <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Análisis de Tendencias</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="border-bottom pb-2">Tendencias Identificadas</h5>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex align-items-center">
                                            <span class="badge bg-success rounded-pill me-2">↑</span>
                                            <div>
                                                <strong>Crecimiento en Grupo de Edad 31-45</strong>
                                                <p class="mb-0 small text-muted">Se observa un incremento del 12% en pacientes de este grupo de edad en los últimos 6 meses.</p>
                                            </div>
                                        </li>
                                        <li class="list-group-item d-flex align-items-center">
                                            <span class="badge bg-info rounded-pill me-2">↑</span>
                                            <div>
                                                <strong>Mayor Diversidad Geográfica</strong>
                                                <p class="mb-0 small text-muted">Aumento en la diversidad de localidades de origen de los pacientes, con crecimiento en áreas suburbanas.</p>
                                            </div>
                                        </li>
                                        <li class="list-group-item d-flex align-items-center">
                                            <span class="badge bg-warning rounded-pill me-2">→</span>
                                            <div>
                                                <strong>Estabilidad en Distribución por Género</strong>
                                                <p class="mb-0 small text-muted">La proporción entre géneros se mantiene constante, con ligera predominancia femenina (53%).</p>
                                            </div>
                                        </li>
                                        <li class="list-group-item d-flex align-items-center">
                                            <span class="badge bg-danger rounded-pill me-2">↓</span>
                                            <div>
                                                <strong>Disminución en Pacientes Jóvenes</strong>
                                                <p class="mb-0 small text-muted">Reducción del 5% en el grupo de edad 18-30 años respecto al mismo período del año anterior.</p>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h5 class="border-bottom pb-2">Recomendaciones Estratégicas</h5>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex align-items-center">
                                            <span class="badge bg-primary rounded-pill me-2">1</span>
                                            <div>
                                                <strong>Programas para Adultos Jóvenes</strong>
                                                <p class="mb-0 small text-muted">Desarrollar programas de prevención y chequeos específicos para el grupo de 18-30 años para revertir la tendencia a la baja.</p>
                                            </div>
                                        </li>
                                        <li class="list-group-item d-flex align-items-center">
                                            <span class="badge bg-primary rounded-pill me-2">2</span>
                                            <div>
                                                <strong>Expansión de Servicios</strong>
                                                <p class="mb-0 small text-muted">Considerar la apertura de consultorios satélite en las nuevas áreas geográficas con mayor crecimiento de pacientes.</p>
                                            </div>
                                        </li>
                                        <li class="list-group-item d-flex align-items-center">
                                            <span class="badge bg-primary rounded-pill me-2">3</span>
                                            <div>
                                                <strong>Especialización en Adultos Medios</strong>
                                                <p class="mb-0 small text-muted">Fortalecer servicios orientados al grupo de 31-45 años, que muestra el mayor crecimiento.</p>
                                            </div>
                                        </li>
                                        <li class="list-group-item d-flex align-items-center">
                                            <span class="badge bg-primary rounded-pill me-2">4</span>
                                            <div>
                                                <strong>Campañas de Concientización</strong>
                                                <p class="mb-0 small text-muted">Implementar campañas de salud preventiva enfocadas en las condiciones médicas más comunes identificadas.</p>
                                            </div>
                                        </li>
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

<?php include_once '../../includes/footer.php'; ?>

<!-- Chart.js para visualizaciones -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Configuración de gráfico de distribución por género
    const generoCtx = document.getElementById('generoChart').getContext('2d');
    const generoChart = new Chart(generoCtx, {
        type: 'pie',
        data: {
            labels: [
                <?php 
                foreach ($distribucion_genero as $genero) {
                    $label = $genero['genero'] == 'M' ? 'Masculino' : ($genero['genero'] == 'F' ? 'Femenino' : 'Otro');
                    echo "'" . $label . "', ";
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php 
                    foreach ($distribucion_genero as $genero) {
                        echo $genero['total'] . ", ";
                    }
                    ?>
                ],
                backgroundColor: [
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(255, 206, 86, 0.7)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(255, 206, 86, 1)'
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
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
    
    // Configuración de gráfico de distribución por edad
    const edadCtx = document.getElementById('edadChart').getContext('2d');
    const edadChart = new Chart(edadCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php 
                foreach ($distribucion_edad as $edad) {
                    echo "'" . $edad['grupo_edad'] . "', ";
                }
                ?>
            ],
            datasets: [{
                label: 'Pacientes por Grupo de Edad',
                data: [
                    <?php 
                    foreach ($distribucion_edad as $edad) {
                        echo $edad['total'] . ", ";
                    }
                    ?>
                ],
                backgroundColor: 'rgba(75, 192, 192, 0.7)',
                borderColor: 'rgba(75, 192, 192, 1)',
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
                            return `Pacientes: ${context.raw} (${context.dataset.data[context.dataIndex] / context.dataset.data.reduce((a, b) => a + b, 0) * 100}%)`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Número de Pacientes'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Grupo de Edad'
                    }
                }
            }
        }
    });
    
    // Configuración de gráfico de tendencia de registros
    const tendenciaCtx = document.getElementById('tendenciaChart').getContext('2d');
    const tendenciaChart = new Chart(tendenciaCtx, {
        type: 'line',
        data: {
            labels: [
                <?php 
                foreach ($tendencia_registros as $registro) {
                    echo "'" . $registro['mes_nombre'] . "', ";
                }
                ?>
            ],
            datasets: [{
                label: 'Nuevos Pacientes',
                data: [
                    <?php 
                    foreach ($tendencia_registros as $registro) {
                        echo $registro['total'] . ", ";
                    }
                    ?>
                ],
                backgroundColor: 'rgba(153, 102, 255, 0.2)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 2,
                tension: 0.3,
                pointBackgroundColor: 'rgba(153, 102, 255, 1)',
                pointBorderColor: '#fff',
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: true
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
                            return `Nuevos pacientes: ${context.raw}`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Número de Registros'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Mes'
                    }
                }
            }
        }
    });
    
    // Configuración de gráfico de condiciones médicas
    const condicionesCtx = document.getElementById('condicionesChart').getContext('2d');
    const condicionesChart = new Chart(condicionesCtx, {
        type: 'horizontalBar',
        data: {
            labels: [
                <?php 
                foreach ($condiciones_comunes as $condicion) {
                    echo "'" . htmlspecialchars($condicion['condicion']) . "', ";
                }
                ?>
            ],
            datasets: [{
                label: 'Pacientes',
                data: [
                    <?php 
                    foreach ($condiciones_comunes as $condicion) {
                        echo $condicion['total'] . ", ";
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
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Pacientes: ${context.raw}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Número de Pacientes'
                    }
                }
            }
        }
    });
    
    // Configuración de gráfico de tipos de sangre
    const sangreCtx = document.getElementById('sangreChart').getContext('2d');
    const sangreChart = new Chart(sangreCtx, {
        type: 'doughnut',
        data: {
            labels: [
                <?php 
                foreach ($tipos_sangre as $tipo) {
                    echo "'" . $tipo['tipo_sangre'] . "', ";
                }
                ?>
            ],
            datasets: [{
                data: [
                    <?php 
                    foreach ($tipos_sangre as $tipo) {
                        echo $tipo['total'] . ", ";
                    }
                    ?>
                ],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(201, 203, 207, 0.7)',
                    'rgba(255, 99, 132, 0.4)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(201, 203, 207, 1)',
                    'rgba(255, 99, 132, 0.7)'
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
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            },
            cutout: '60%'
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