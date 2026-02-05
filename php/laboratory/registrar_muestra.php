<?php
// laboratory/registrar_muestra.php - Register sample reception
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get pending orders
    $stmt = $conn->query("
        SELECT o.*, p.nombre, p.apellido, p.dpi,
               COUNT(od.id_detalle) as num_pruebas
        FROM ordenes_laboratorio o
        JOIN pacientes p ON o.id_paciente = p.id_paciente
        LEFT JOIN orden_detalles od ON o.id_orden = od.id_orden
        WHERE o.estado = 'Pendiente'
        GROUP BY o.id_orden
        ORDER BY o.fecha_orden DESC
        LIMIT 50
    ");
    $ordenes_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $page_title = "Registrar Muestra - Laboratorio";
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
    .order-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 1.25rem;
        margin-bottom: 1rem;
        transition: all 0.2s ease;
    }
    
    .order-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
        border-color: var(--color-primary);
    }
    
    .order-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.75rem;
    }
    
    .order-number {
        font-weight: 700;
        color: var(--color-primary);
        font-size: 1.1rem;
    }
    
    .patient-name {
        font-weight: 600;
        color: var(--color-text);
    }
    </style>
</head>
<body>
    <div class="marble-effect"></div>
    
    <div class="dashboard-container">
        <header class="dashboard-header">
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
                <h1 class="page-title">
                    <i class="bi bi-droplet text-primary"></i>
                    Registrar Recepción de Muestra
                </h1>
                <p class="page-subtitle">Marque las órdenes cuyas muestras han sido recibidas</p>
            </div>
            
            <?php if (count($ordenes_pendientes) > 0): ?>
                <?php foreach ($ordenes_pendientes as $orden): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <div class="order-number"><?php echo htmlspecialchars($orden['numero_orden']); ?></div>
                                <div class="patient-name"><?php echo htmlspecialchars($orden['nombre'] . ' ' . $orden['apellido']); ?></div>
                            </div>
                            <div>
                                <span class="badge bg-info"><?php echo $orden['num_pruebas']; ?> pruebas</span>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="bi bi-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($orden['fecha_orden'])); ?>
                            </small>
                            <button class="action-btn" onclick="registerSample(<?php echo $orden['id_orden']; ?>, '<?php echo htmlspecialchars($orden['numero_orden']); ?>')">
                                <i class="bi bi-check-circle"></i>
                                Registrar Muestra
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 3rem; color: var(--color-text-muted);"></i>
                    <h4 class="text-muted mt-3">No hay órdenes pendientes</h4>
                    <p class="text-muted">Todas las muestras han sido registradas</p>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function registerSample(orderId, orderNumber) {
        Swal.fire({
            title: 'Registrar Muestra',
            html: `
                <div class="text-start">
                    <p>Orden: <strong>${orderNumber}</strong></p>
                    <div class="mb-3">
                        <label class="form-label">Fecha y hora de recepción</label>
                        <input type="datetime-local" id="fecha_recepcion" class="form-control" value="${new Date().toISOString().slice(0, 16)}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observaciones (opcional)</label>
                        <textarea id="observaciones" class="form-control" rows="3" placeholder="Estado de la muestra, condiciones especiales..."></textarea>
                    </div>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Registrar',
            confirmButtonColor: '#7c90db',
            preConfirm: () => {
                return {
                    id_orden: orderId,
                    fecha_recepcion: document.getElementById('fecha_recepcion').value,
                    observaciones: document.getElementById('observaciones').value
                };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('id_orden', result.value.id_orden);
                formData.append('fecha_recepcion', result.value.fecha_recepcion);
                formData.append('observaciones', result.value.observaciones);
                
                fetch('api/register_sample.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Muestra Registrada!',
                            text: data.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'Error al registrar la muestra', 'error');
                });
            }
        });
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
