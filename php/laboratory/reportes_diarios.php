<?php
// laboratory/reportes_diarios.php - Daily laboratory reports
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get today's date
    $fecha = $_GET['fecha'] ?? date('Y-m-d');
    
    // Get today's statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT o.id_orden) as total_ordenes,
            COUNT(DISTINCT CASE WHEN o.estado = 'Pendiente' THEN o.id_orden END) as pendientes,
            COUNT(DISTINCT CASE WHEN o.estado = 'Muestra_Recibida' THEN o.id_orden END) as muestras_recibidas,
            COUNT(DISTINCT CASE WHEN o.estado = 'En_Proceso' THEN o.id_orden END) as en_proceso,
            COUNT(DISTINCT CASE WHEN o.estado = 'Completada' THEN o.id_orden END) as completadas,
            COUNT(DISTINCT CASE WHEN o.estado = 'Validada' THEN o.id_orden END) as validadas,
            COUNT(od.id_detalle) as total_pruebas,
            SUM(cp.price) as ingresos_estimados
        FROM ordenes_laboratorio o
        LEFT JOIN orden_detalles od ON o.id_orden = od.id_orden
        LEFT JOIN catalogo_pruebas cp ON od.id_prueba = cp.id_prueba
        WHERE DATE(o.fecha_orden) = ?
    ");
    $stmt->execute([$fecha]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get orders for the day
    $stmt = $conn->prepare("
        SELECT o.*, p.nombre, p.apellido,
               COUNT(od.id_detalle) as num_pruebas
        FROM ordenes_laboratorio o
        JOIN pacientes p ON o.id_paciente = p.id_paciente
        LEFT JOIN orden_detalles od ON o.id_orden = od.id_orden
        WHERE DATE(o.fecha_orden) = ?
        GROUP BY o.id_orden
        ORDER BY o.fecha_orden DESC
    ");
    $stmt->execute([$fecha]);
    $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $page_title = "Reporte Diario - Laboratorio";
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    
    <style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }
    
    .stat-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1.25rem;
        text-align: center;
    }
    
    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: var(--color-primary);
    }
    
    .stat-label {
        font-size: 0.85rem;
        color: var(--color-text-muted);
        margin-top: 0.5rem;
    }
    
    @media print {
        .no-print {
            display: none !important;
        }
        body {
            background: white !important;
        }
    }
    </style>
</head>
<body>
    <div class="marble-effect no-print"></div>
    
    <div class="dashboard-container">
        <header class="dashboard-header no-print">
            <div class="header-content">
                <img src="../../assets/img/cmrs.png" alt="CMHS" class="brand-logo">
                <div class="header-controls">
                    <div class="theme-toggle">
                        <button id="themeSwitch" class="theme-btn">
                            <i class="bi bi-sun theme-icon sun-icon"></i>
                            <i class="bi bi-moon theme-icon moon-icon"></i>
                        </button>
                    </div>
                    <a href="index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i>
                        Volver
                    </a>
                </div>
            </div>
        </header>
        
        <main class="main-content">
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title">
                            <i class="bi bi-file-earmark-text text-primary"></i>
                            Reporte Diario de Laboratorio
                        </h1>
                        <p class="page-subtitle">Resumen de actividades del día</p>
                    </div>
                    <div class="no-print">
                        <input type="date" id="fecha" class="form-control" value="<?php echo $fecha; ?>" onchange="cambiarFecha()">
                    </div>
                </div>
            </div>
            
            <div class="mb-4 text-center">
                <h3><?php echo date('d/m/Y', strtotime($fecha)); ?></h3>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_ordenes'] ?? 0; ?></div>
                    <div class="stat-label">Total Órdenes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['pendientes'] ?? 0; ?></div>
                    <div class="stat-label">Pendientes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['muestras_recibidas'] ?? 0; ?></div>
                    <div class="stat-label">Muestras Recibidas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['en_proceso'] ?? 0; ?></div>
                    <div class="stat-label">En Proceso</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['completadas'] ?? 0; ?></div>
                    <div class="stat-label">Completadas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['validadas'] ?? 0; ?></div>
                    <div class="stat-label">Validadas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_pruebas'] ?? 0; ?></div>
                    <div class="stat-label">Total Pruebas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">Q<?php echo number_format($stats['ingresos_estimados'] ?? 0, 2); ?></div>
                    <div class="stat-label">Ingresos Estimados</div>
                </div>
            </div>
            
            <div class="mb-3 d-flex justify-content-between align-items-center no-print">
                <h3>Órdenes del Día</h3>
                <button class="action-btn" onclick="window.print()">
                    <i class="bi bi-printer"></i>
                    Imprimir Reporte
                </button>
            </div>
            
            <?php if (count($ordenes) > 0): ?>
                <div class="table-responsive">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Orden #</th>
                                <th>Paciente</th>
                                <th>Hora</th>
                                <th>Pruebas</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ordenes as $orden): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($orden['numero_orden']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($orden['nombre'] . ' ' . $orden['apellido']); ?></td>
                                    <td><?php echo date('H:i', strtotime($orden['fecha_orden'])); ?></td>
                                    <td><span class="badge bg-info"><?php echo $orden['num_pruebas']; ?></span></td>
                                    <td>
                                        <?php
                                        $estado_class = '';
                                        $estado_text = '';
                                        switch ($orden['estado']) {
                                            case 'Pendiente':
                                                $estado_class = 'pendiente';
                                                $estado_text = 'Pendiente';
                                                break;
                                            case 'Muestra_Recibida':
                                                $estado_class = 'muestra';
                                                $estado_text = 'Muestra Recibida';
                                                break;
                                            case 'En_Proceso':
                                                $estado_class = 'proceso';
                                                $estado_text = 'En Proceso';
                                                break;
                                            case 'Completada':
                                                $estado_class = 'completada';
                                                $estado_text = 'Completada';
                                                break;
                                            case 'Validada':
                                                $estado_class = 'validada';
                                                $estado_text = 'Validada';
                                                break;
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $estado_class; ?>">
                                            <?php echo $estado_text; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 3rem; color: var(--color-text-muted);"></i>
                    <h4 class="text-muted mt-3">No hay órdenes para esta fecha</h4>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
    function cambiarFecha() {
        const fecha = document.getElementById('fecha').value;
        window.location.href = `?fecha=${fecha}`;
    }
    
    // Theme JS
    document.addEventListener('DOMContentLoaded', function() {
        if (localStorage.getItem('dashboard-theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        document.getElementById('themeSwitch')?.addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme');
            const target = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', target);
            localStorage.setItem('dashboard-theme', target);
        });
    });
    </script>
</body>
</html>
