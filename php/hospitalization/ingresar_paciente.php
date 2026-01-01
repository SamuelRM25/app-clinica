<?php
// hospitalization/ingresar_paciente.php - Formulario de Ingreso de Paciente
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
verify_session();
date_default_timezone_set('America/Guatemala');

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['tipoUsuario'];
$user_name = $_SESSION['nombre'];

// Fetch available beds
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get available beds grouped by room
    $stmt_beds = $conn->query("
        SELECT 
            c.id_cama,
            c.numero_cama,
            c.estado,
            h.id_habitacion,
            h.numero_habitacion,
            h.tipo_habitacion,
            h.piso,
            h.tarifa_por_noche,
            h.descripcion
        FROM camas c
        INNER JOIN habitaciones h ON c.id_habitacion = h.id_habitacion
        WHERE c.estado = 'Disponible' AND h.estado != 'Mantenimiento'
        ORDER BY h.piso, h.numero_habitacion, c.numero_cama
    ");
    $available_beds = $stmt_beds->fetchAll(PDO::FETCH_ASSOC);
    
    // Get doctors
    $stmt_docs = $conn->query("
        SELECT idUsuario, nombre, apellido, especialidad 
        FROM usuarios 
        WHERE tipoUsuario IN ('admin', 'doc')
        ORDER BY nombre
    ");
    $doctors = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);
    
    // Get patients for search
    $stmt_patients = $conn->query("
        SELECT id_paciente, nombre, apellido, fecha_nacimiento, genero
        FROM pacientes
        ORDER BY nombre, apellido
    ");
    $patients = $stmt_patients->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresar Paciente - Hospitalización</title>
    
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    
    <style>
    /* Variables CSS para modo claro y oscuro */
    :root {
        --color-background: #f8fafc;
        --color-surface: #ffffff;
        --color-primary: #7c90db;
        --color-primary-light: #a3b1e8;
        --color-primary-dark: #5a6fca;
        --color-secondary: #8dd7bf;
        --color-accent: #f8b195;
        --color-text: #1e293b;
        --color-text-light: #64748b;
        --color-text-muted: #94a3b8;
        --color-border: #e2e8f0;
        --color-border-light: #f1f5f9;
        --color-error: #f87171;
        --color-warning: #fbbf24;
        --color-success: #34d399;
        --color-info: #38bdf8;
        
        --marble-bg: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        
        --radius-sm: 8px;
        --radius-md: 12px;
        --radius-lg: 16px;
        --radius-xl: 20px;
    }
    
    [data-theme="dark"] {
        --color-background: #0f172a;
        --color-surface: #1e293b;
        --color-text: #f1f5f9;
        --color-text-light: #cbd5e1;
        --color-text-muted: #94a3b8;
        --color-border: #334155;
        --color-border-light: #1e293b;
        --marble-bg: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: var(--color-background);
        color: var(--color-text);
        min-height: 100vh;
        line-height: 1.6;
    }
    
    /* Efecto mármol animado */
    .marble-effect {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        pointer-events: none;
        z-index: -1;
        opacity: 0.4;
        background-image: 
            radial-gradient(circle at 20% 30%, rgba(124, 144, 219, 0.08) 0%, transparent 30%),
            radial-gradient(circle at 80% 70%, rgba(141, 215, 191, 0.08) 0%, transparent 30%),
            radial-gradient(circle at 40% 80%, rgba(248, 177, 149, 0.08) 0%, transparent 30%);
    }
    
    /* Dashboard Container */
    .dashboard-container {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }
    
    /* Header */
    .dashboard-header {
        background: var(--color-surface);
        border-bottom: 1px solid var(--color-border);
        padding: 1rem 2rem;
        box-shadow: var(--shadow-sm);
        position: sticky;
        top: 0;
        z-index: 100;
    }
    
    .header-content {
        max-width: 1400px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 2rem;
    }
    
    .brand-logo {
        height: 45px;
        width: auto;
    }
    
    .header-controls {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    
    .theme-btn {
        width: 40px;
        height: 40px;
        border-radius: var(--radius-md);
        border: 1px solid var(--color-border);
        background: var(--color-surface);
        color: var(--color-text);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    
    .theme-btn:hover {
        background: var(--color-border-light);
        transform: translateY(-2px);
    }
    
    .theme-icon {
        font-size: 1.2rem;
        transition: all 0.3s ease;
        position: absolute;
    }
    
    .sun-icon { opacity: 1; }
    .moon-icon { opacity: 0; }
    [data-theme="dark"] .sun-icon { opacity: 0; }
    [data-theme="dark"] .moon-icon { opacity: 1; }
    
    /* Main Content */
    .main-content {
        flex: 1;
        padding: 2rem;
        max-width: 1400px;
        margin: 0 auto;
        width: 100%;
    }
    
    .page-header {
        background: var(--color-surface);
        border-radius: var(--radius-xl);
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--color-border);
    }
    
    .page-title {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--color-text);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .page-subtitle {
        color: var(--color-text-light);
        font-size: 0.95rem;
        margin: 0;
    }
    
    .action-btn {
        background: var(--color-primary);
        color: white;
        border: none;
        padding: 0.75rem 1.5rem;
        border-radius: var(--radius-md);
        font-weight: 500;
        font-size: 0.95rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: var(--shadow-md);
    }
    
    .action-btn:hover {
        background: var(--color-primary-dark);
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    .action-btn.secondary {
        background: var(--color-border);
        color: var(--color-text);
    }
    
    .action-btn.secondary:hover {
        background: var(--color-border-light);
    }
        
        .form-section {
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--color-border);
        }
        
        .form-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--color-border);
        }
        
        .form-label {
            font-weight: 500;
            color: var(--color-text);
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 0.75rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: var(--color-surface);
            color: var(--color-text);
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px var(--color-primary-light);
            outline: none;
        }
        
        .bed-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .bed-option {
            border: 2px solid var(--color-border);
            border-radius: var(--radius-md);
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--color-background);
        }
        
        .bed-option:hover {
            border-color: var(--color-primary);
            background: var(--color-primary-light);
            transform: translateY(-2px);
        }
        
        .bed-option.selected {
            border-color: var(--color-primary);
            background: rgba(124, 144, 219, 0.15);
            box-shadow: 0 0 0 3px var(--color-primary-light);
        }
        
        .bed-option input[type="radio"] {
            display: none;
        }
        
        .bed-option-header {
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .bed-option-details {
            font-size: 0.85rem;
            color: var(--color-text-light);
        }
        
        .bed-option-price {
            font-weight: 600;
            color: var(--color-primary);
            margin-top: 0.5rem;
        }
        
        .btn-submit {
            background: var(--color-primary);
            color: white;
            padding: 0.875rem 2rem;
            border-radius: var(--radius-md);
            border: none;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
        }
        
        .btn-submit:hover {
            background: var(--color-primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-cancel {
            background: var(--color-border);
            color: var(--color-text);
            padding: 0.875rem 2rem;
            border-radius: var(--radius-md);
            border: none;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: var(--color-border-light);
        }
        
        .select2-container--default .select2-selection--single {
            height: 45px;
            padding: 0.5rem;
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface);
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 30px;
            color: var(--color-text);
        }
        
        [data-theme="dark"] .select2-dropdown {
            background-color: var(--color-surface);
            border-color: var(--color-border);
        }
        
        [data-theme="dark"] .select2-results__option {
            background-color: var(--color-surface);
            color: var(--color-text);
        }
        
        [data-theme="dark"] .select2-results__option--highlighted {
            background-color: var(--color-primary);
            color: white;
        }
    </style>
</head>
<body>
    <div class="marble-effect"></div>
    
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <div class="brand-container">
                    <img src="../../assets/img/herrerasaenz.png" alt="CMHS" class="brand-logo">
                </div>
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
                    <i class="bi bi-person-plus-fill text-primary"></i>
                    Ingreso de Paciente a Hospitalización
                </h1>
                <p class="page-subtitle">Complete el formulario para ingresar un paciente</p>
            </div>
            
            <form id="ingresoForm" action="api/create_ingreso.php" method="POST">
                <!-- Sección: Datos del Paciente -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="bi bi-person-vcard"></i>
                        Datos del Paciente
                    </h3>
                    
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Buscar Paciente Existente</label>
                            <select class="form-select" id="paciente_select" name="id_paciente" required>
                                <option value="">Seleccionar paciente...</option>
                                <?php foreach ($patients as $pac): ?>
                                    <option value="<?php echo $pac['id_paciente']; ?>" 
                                            data-nombre="<?php echo htmlspecialchars($pac['nombre'] . ' ' . $pac['apellido']); ?>"
                                            data-nacimiento="<?php echo $pac['fecha_nacimiento']; ?>"
                                            data-genero="<?php echo $pac['genero']; ?>"
                                            <?php echo (isset($_GET['id_paciente']) && $_GET['id_paciente'] == $pac['id_paciente']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($pac['nombre'] . ' ' . $pac['apellido']); ?> - 
                                        <?php 
                                            $edad = date_diff(date_create($pac['fecha_nacimiento']),date_create('today'))->y;
                                            echo $edad . ' años';
                                        ?> - 
                                        <?php echo htmlspecialchars($pac['genero']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Sección: Detalles del Ingreso -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="bi bi-clipboard-pulse"></i>
                        Detalles del Ingreso
                    </h3>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Fecha y Hora de Ingreso</label>
                            <input type="datetime-local" class="form-control" name="fecha_ingreso" 
                                   value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Tipo de Ingreso</label>
                            <select class="form-select" name="tipo_ingreso" required>
                                <option value="Programado">Programado</option>
                                <option value="Emergencia" selected>Emergencia</option>
                                <option value="Referido">Referido</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Médico Responsable</label>
                            <select class="form-select" name="id_doctor" required>
                                <option value="">Seleccionar médico...</option>
                                <?php foreach ($doctors as $doc): ?>
                                    <option value="<?php echo $doc['idUsuario']; ?>">
                                        Dr(a). <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido']); ?>
                                        <?php if ($doc['especialidad']): ?>
                                            - <?php echo htmlspecialchars($doc['especialidad']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label">Motivo de Ingreso</label>
                            <textarea class="form-control" name="motivo_ingreso" rows="3" required 
                                      placeholder="Describa el motivo principal del ingreso..."></textarea>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label">Diagnóstico de Ingreso</label>
                            <input type="text" class="form-control" name="diagnostico_ingreso" 
                                   placeholder="Ej: Neumonía adquirida en la comunidad" required>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label">Notas Adicionales (Opcional)</label>
                            <textarea class="form-control" name="notas_ingreso" rows="2" 
                                      placeholder="Información adicional relevante..."></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Sección: Asignación de Cama -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="bi bi-hospital"></i>
                        Asignación de Cama
                    </h3>
                    
                    <?php if (count($available_beds) > 0): ?>
                        <div class="bed-grid">
                            <?php 
                            $current_room = null;
                            foreach ($available_beds as $bed): 
                                if ($current_room !== $bed['id_habitacion']) {
                                    $current_room = $bed['id_habitacion'];
                                }
                            ?>
                                <label class="bed-option">
                                    <input type="radio" name="id_cama" value="<?php echo $bed['id_cama']; ?>" required>
                                    <div class="bed-option-header">
                                        Hab. <?php echo htmlspecialchars($bed['numero_habitacion']); ?> - Cama <?php echo htmlspecialchars($bed['numero_cama']); ?>
                                    </div>
                                    <div class="bed-option-details">
                                        <?php echo htmlspecialchars($bed['tipo_habitacion']); ?><br>
                                        Piso: <?php echo htmlspecialchars($bed['piso']); ?>
                                    </div>
                                    <div class="bed-option-price">
                                        Q<?php echo number_format($bed['tarifa_por_noche'], 2); ?> / noche
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            No hay camas disponibles en este momento.
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Botones de Acción -->
                <div class="d-flex justify-content-end gap-3">
                    <button type="button" class="btn-cancel" onclick="window.location.href='index.php'">
                        <i class="bi bi-x-circle"></i>
                        Cancelar
                    </button>
                    <button type="submit" class="btn-submit" <?php echo (count($available_beds) == 0 ? 'disabled' : ''); ?>>
                        <i class="bi bi-check-circle-fill"></i>
                        Ingresar Paciente
                    </button>
                </div>
            </form>
        </main>
    </div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Theme management
        const themeSwitch = document.getElementById('themeSwitch');
        function initializeTheme() {
            const savedTheme = localStorage.getItem('dashboard-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        }
        function toggleTheme() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('dashboard-theme', newTheme);
        }
        initializeTheme();
        themeSwitch.addEventListener('click', toggleTheme);
        
        // Initialize Select2
        $('#paciente_select').select2({
            placeholder: 'Buscar paciente por nombre...',
            allowClear: true,
            width: '100%'
        });
        
        // Bed selection highlighting
        document.querySelectorAll('.bed-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.bed-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
        
        // Form submission
        document.getElementById('ingresoForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Procesando...';
            submitBtn.disabled = true;
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        title: '¡Éxito!',
                        text: 'Paciente ingresado correctamente',
                        icon: 'success',
                        confirmButtonColor: '#7c90db'
                    }).then(() => {
                        window.location.href = 'detalle_encamamiento.php?id=' + data.id_encamamiento;
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'No se pudo ingresar el paciente',
                        icon: 'error',
                        confirmButtonColor: '#7c90db'
                    });
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error',
                    text: 'Ocurrió un error al procesar la solicitud',
                    icon: 'error',
                    confirmButtonColor: '#7c90db'
                });
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
    });
    </script>
</body>
</html>
