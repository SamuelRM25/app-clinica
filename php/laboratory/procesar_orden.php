<?php
// laboratory/procesar_orden.php - Clinical results entry interface
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

$id_orden = $_GET['id'] ?? null;
if (!$id_orden) {
    header("Location: index.php");
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // 1. Get order details with patient info
    $stmt = $conn->prepare("
        SELECT ol.*, p.nombre, p.apellido, p.genero, p.fecha_nacimiento,
               u.nombre as doctor_nombre, u.apellido as doctor_apellido
        FROM ordenes_laboratorio ol
        JOIN pacientes p ON ol.id_paciente = p.id_paciente
        LEFT JOIN usuarios u ON ol.id_doctor = u.idUsuario
        WHERE ol.id_orden = ?
    ");
    $stmt->execute([$id_orden]);
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$orden) {
        header("Location: index.php");
        exit;
    }
    
    // 2. Get tests in this order with their parameters
    $stmt = $conn->prepare("
        SELECT op.*, cp.nombre_prueba, cp.codigo_prueba
        FROM orden_pruebas op
        JOIN catalogo_pruebas cp ON op.id_prueba = cp.id_prueba
        WHERE op.id_orden = ?
    ");
    $stmt->execute([$id_orden]);
    $pruebas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate patient age for reference values
    $edad = date_diff(date_create($orden['fecha_nacimiento']), date_create('today'))->y;
    $genero = $orden['genero'];
    
    $page_title = "Procesar Orden #" . $orden['numero_orden'];
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
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    
    <style>
    .patient-header-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1.5rem;
    }
    
    .test-processing-section {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
    }
    
    .test-title-bar {
        background: var(--color-border-light);
        padding: 0.75rem 1.25rem;
        border-radius: var(--radius-md);
        margin-bottom: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .parameter-table {
        width: 100%;
        margin-bottom: 2rem;
    }
    
    .parameter-table th {
        padding: 0.75rem;
        font-size: 0.8rem;
        color: var(--color-text-muted);
        text-transform: uppercase;
        border-bottom: 1px solid var(--color-border);
    }
    
    .parameter-table td {
        padding: 0.75rem;
        vertical-align: middle;
        border-bottom: 1px dashed var(--color-border-light);
    }
    
    .result-input {
        width: 120px;
        text-align: center;
        font-weight: 600;
    }
    
    .flag-indicator {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: 800;
        color: white;
    }
    
    .flag-normal { background: var(--color-success); }
    .flag-high { background: var(--color-error); }
    .flag-low { background: var(--color-info); }
    .flag-critical { background: #7f1d1d; }
    
    .sample-status-bar {
        padding: 1rem;
        border-radius: var(--radius-md);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .sample-pending { background: rgba(251, 191, 36, 0.1); border: 1px solid var(--color-warning); }
    .sample-received { background: rgba(52, 211, 153, 0.1); border: 1px solid var(--color-success); }
    </style>
</head>
<body>
    <div class="marble-effect"></div>
    
    <?php 
    $active_page = 'laboratory';
    include_once '../../includes/sidebar.php'; 
    ?>
    
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <img src="../../assets/img/herrerasaenz.png" alt="CMHS" class="brand-logo">
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
            <div class="patient-header-card">
                <div>
                    <h2 class="mb-1"><?php echo htmlspecialchars($orden['nombre'] . ' ' . $orden['apellido']); ?></h2>
                    <p class="text-muted mb-0">
                        <?php echo $edad; ?> años - <?php echo $genero; ?> | 
                        Orden: <strong><?php echo $orden['numero_orden']; ?></strong> | 
                        Fecha: <?php echo date('d/m/Y H:i', strtotime($orden['fecha_orden'])); ?>
                    </p>
                </div>
                <div class="text-end">
                    <div class="badge <?php echo $orden['prioridad'] === 'Rutina' ? 'bg-info' : 'bg-danger'; ?> mb-2">
                        Prioridad: <?php echo $orden['prioridad']; ?>
                    </div>
                    <p class="small text-muted mb-0">Doctor: Dr. <?php echo htmlspecialchars($orden['doctor_nombre'] . ' ' . $orden['doctor_apellido']); ?></p>
                </div>
            </div>
            
            <form id="resultsForm" action="api/save_results.php" method="POST">
                <input type="hidden" name="id_orden" value="<?php echo $id_orden; ?>">
                
                <?php foreach ($pruebas as $prueba): ?>
                    <div class="test-processing-section" data-id-orden-prueba="<?php echo $prueba['id_orden_prueba']; ?>">
                        <div class="test-title-bar">
                            <h4 class="mb-0"><?php echo htmlspecialchars($prueba['nombre_prueba']); ?></h4>
                            <div>
                                <?php if ($prueba['estado'] === 'Pendiente'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="receiveSample(<?php echo $prueba['id_orden_prueba']; ?>)">
                                        <i class="bi bi-droplet-fill"></i> Recibir Muestra
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> Muestra Recibida</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($prueba['estado'] !== 'Pendiente'): ?>
                            <table class="parameter-table">
                                <thead>
                                    <tr>
                                        <th>Parámetro</th>
                                        <th>Resultado</th>
                                        <th>Unidad</th>
                                        <th>Referencia</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt_params = $conn->prepare("
                                        SELECT pp.*, rl.valor_resultado, rl.fuera_rango
                                        FROM parametros_pruebas pp
                                        LEFT JOIN resultados_laboratorio rl ON pp.id_parametro = rl.id_parametro AND rl.id_orden_prueba = ?
                                        WHERE pp.id_prueba = ?
                                        ORDER BY pp.orden_visualizacion
                                    ");
                                    $stmt_params->execute([$prueba['id_orden_prueba'], $prueba['id_prueba']]);
                                    $p_list = $stmt_params->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    foreach ($p_list as $p):
                                        // Determine reference range for this patient
                                        $min = 0; $max = 0;
                                        if ($edad <= 12) {
                                            $min = $p['valor_ref_pediatrico_min']; $max = $p['valor_ref_pediatrico_max'];
                                        } elseif ($genero === 'Masculino') {
                                            $min = $p['valor_ref_hombre_min']; $max = $p['valor_ref_hombre_max'];
                                        } else {
                                            $min = $p['valor_ref_mujer_min']; $max = $p['valor_ref_mujer_max'];
                                        }
                                        $ref_text = ($min !== null && $max !== null) ? "$min - $max" : "N/A";
                                    ?>
                                        <tr>
                                            <td width="30%"><?php echo htmlspecialchars($p['nombre_parametro']); ?></td>
                                            <td width="20%">
                                                <input type="text" 
                                                       name="results[<?php echo $prueba['id_orden_prueba']; ?>][<?php echo $p['id_parametro']; ?>]" 
                                                       class="form-control form-control-sm result-input" 
                                                       value="<?php echo htmlspecialchars($p['valor_resultado']); ?>"
                                                       data-min="<?php echo $min; ?>" 
                                                       data-max="<?php echo $max; ?>"
                                                       onchange="validateRange(this)">
                                            </td>
                                            <td width="15%"><small class="text-muted"><?php echo htmlspecialchars($p['unidad_medida']); ?></small></td>
                                            <td width="20%"><small><?php echo $ref_text; ?></small></td>
                                            <td width="15%" class="flag-container">
                                                <!-- Flag logic via JS -->
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-droplet" style="font-size: 2rem;"></i>
                                <p>Debe marcar la muestra como recibida para ingresar resultados</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                
                <div class="sticky-bottom bg-white p-3 border-top text-end mt-4">
                    <button type="submit" class="action-btn">
                        <i class="bi bi-save"></i> Guardar Resultados
                    </button>
                    <button type="button" class="btn btn-success ms-2" onclick="validateAndFinalize()">
                        <i class="bi bi-check-all"></i> Validar y Finalizar Orden
                    </button>
                </div>
            </form>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    function validateRange(input) {
        const val = parseFloat(input.value);
        const min = parseFloat(input.dataset.min);
        const max = parseFloat(input.dataset.max);
        const container = input.closest('tr').querySelector('.flag-container');
        
        if (isNaN(val) || isNaN(min) || isNaN(max)) {
            container.innerHTML = '';
            return;
        }
        
        let flag = '';
        if (val < min) flag = '<span class="flag-indicator flag-low" title="Bajo">L</span>';
        else if (val > max) flag = '<span class="flag-indicator flag-high" title="Alto">H</span>';
        else flag = '<span class="flag-indicator flag-normal" title="Normal">N</span>';
        
        container.innerHTML = flag;
    }

    function receiveSample(id_orden_prueba) {
        Swal.fire({
            title: '¿Confirmar Recepción?',
            text: 'Se marcará la muestra como recibida para esta prueba',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Sí, recibir'
        }).then((result) => {
            if (result.isConfirmed) {
                location.href = `api/sample_reception.php?id=${id_orden_prueba}&id_orden=<?php echo $id_orden; ?>`;
            }
        });
    }

    function validateAndFinalize() {
        Swal.fire({
            title: '¿Validar y Finalizar?',
            text: 'Una vez validada, la orden no podrá ser modificada y los resultados estarán disponibles para el doctor.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, validar todo',
            confirmButtonColor: '#34d399'
        }).then((result) => {
            if (result.isConfirmed) {
                location.href = `api/validate_order.php?id=<?php echo $id_orden; ?>`;
            }
        });
    }

    // Initialize flags on load
    document.querySelectorAll('.result-input').forEach(input => {
        if (input.value) validateRange(input);
    });

    // Theme JS
    document.addEventListener('DOMContentLoaded', function() {
        if (localStorage.getItem('dashboard-theme') === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        document.getElementById('themeSwitch')?.addEventListener('click', () => {
            const target = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', target);
            localStorage.setItem('dashboard-theme', target);
        });
    });
    </script>
</body>
</html>
