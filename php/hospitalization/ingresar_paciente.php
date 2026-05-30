<?php
// hospitalization/ingresar_paciente.php - Formulario de Ingreso de Paciente
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/breadcrumbs.php';

$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);

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
    $stmt_beds = $conn->prepare("
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
        AND c.id_hospital = ? AND h.id_hospital = ?
        ORDER BY h.piso, h.numero_habitacion, c.numero_cama
    ");
    $stmt_beds->execute([$id_hospital, $id_hospital]);
    $available_beds = $stmt_beds->fetchAll(PDO::FETCH_ASSOC);

    // Get doctors (Only users with 'doc' role as requested)
    $stmt_docs = $conn->prepare("
        SELECT idUsuario, nombre, apellido, especialidad 
        FROM usuarios 
        WHERE tipoUsuario = 'doc' AND id_hospital = ?
        ORDER BY nombre
    ");
    $stmt_docs->execute([$id_hospital]);
    $doctors = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

    // Get patients for search
    $stmt_patients = $conn->prepare("
        SELECT id_paciente, nombre, apellido, fecha_nacimiento, genero
        FROM pacientes
        WHERE id_hospital = ?
        ORDER BY nombre, apellido
    ");
    $stmt_patients->execute([$id_hospital]);
    $patients = $stmt_patients->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log('Error en hospitalization/ingresar_paciente.php: ' . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresar Paciente - Hospitalización</title>

    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">

    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <?php include '../../includes/theme_head.php'; ?>

    <style>
        /* ===== FORM SECTIONS ===== */
        .form-section {
            background: var(--color-card);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            position: relative;
        }

        .form-section-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--color-text);
            margin: 0 0 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--color-border);
        }

        .form-section-title i {
            color: var(--color-primary);
            font-size: 1.15rem;
        }

        .step-badge {
            width: 28px;
            height: 28px;
            background: var(--color-primary);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        /* ===== FORM LABELS & INPUTS ===== */
        .form-label {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--color-text-secondary);
            margin-bottom: 0.4rem;
        }

        .form-control,
        .form-select {
            padding: 0.7rem 0.875rem;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface);
            color: var(--color-text);
            font-family: var(--font-family);
            font-size: 0.875rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.13);
            outline: none;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 90px;
        }

        /* ===== BED SELECTION ===== */
        .bed-option {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            background: var(--color-surface);
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 0.5rem;
        }

        .bed-option:hover {
            border-color: var(--color-primary);
            background: rgba(var(--color-primary-rgb), 0.04);
        }

        .bed-option input[type="radio"] {
            accent-color: var(--color-primary);
            width: 16px;
            height: 16px;
        }

        .bed-option-name {
            font-weight: 700;
            font-size: 0.875rem;
            color: var(--color-text);
        }

        .bed-option-meta {
            font-size: 0.75rem;
            color: var(--color-text-secondary);
        }

        .bed-rate {
            font-weight: 700;
            color: var(--color-success);
            font-size: 0.875rem;
            margin-left: auto;
        }

        /* Select2 custom theme for this page */
        .select2-container--default .select2-selection--single {
            padding: 0.65rem 0.875rem;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface);
            color: var(--color-text);
            height: auto;
        }

        .select2-container--default .select2-selection--single:focus {
            border-color: var(--color-primary);
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: var(--color-text);
            line-height: 1.5;
            padding: 0;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            top: 50%;
            transform: translateY(-50%);
        }

        .select2-dropdown {
            background: var(--color-card);
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
        }

        .select2-container--default .select2-results__option {
            padding: 0.6rem 1rem;
            color: var(--color-text);
        }

        .select2-container--default .select2-results__option--highlighted {
            background: rgba(var(--color-primary-rgb), 0.1);
            color: var(--color-primary);
        }

        .select2-search--dropdown input {
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-sm);
            background: var(--color-surface);
            color: var(--color-text);
            padding: 0.5rem 0.75rem;
        }
    </style>
</head>

<body>
    <div class="marble-effect"></div>

    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <div class="brand-container">
                    <img src="../../assets/img/cmhs.png" alt="logo" class="brand-logo" width="40" height="40">
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
            <?php render_breadcrumbs([
                ['label' => 'Dashboard', 'url' => '../dashboard/index.php'],
                ['label' => 'Hospitalización', 'url' => 'index.php'],
                ['label' => 'Ingresar Paciente'],
            ]); ?>
            <div class="page-header">
                <h1 class="page-title">
                    <i class="bi bi-person-plus-fill text-primary"></i>
                    Ingreso de Paciente a Hospitalización
                </h1>
                <p class="page-subtitle">Complete el formulario para ingresar un paciente</p>
            </div>

            <form id="ingresoForm" action="api/create_ingreso.php" method="POST">
                <!-- Sección: Datos del Paciente -->
                <div class="stat-card p-4 mb-4 animate-in">
                    <h3 class="section-title mb-4">
                        <i class="bi bi-person-vcard text-primary me-2"></i>
                        Datos del Paciente
                    </h3>

                    <div class="row g-4">
                        <div class="col-md-12" id="search_paciente_div">
                            <label class="form-label">Buscar Paciente Existente</label>
                            <select class="form-select" id="paciente_select" name="id_paciente">
                                <option value="">Seleccionar paciente...</option>
                                <?php foreach ($patients as $pac): ?>
                                    <option value="<?php echo $pac['id_paciente']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($pac['nombre'] . ' ' . $pac['apellido']); ?>"
                                        data-nacimiento="<?php echo $pac['fecha_nacimiento']; ?>"
                                        data-genero="<?php echo $pac['genero']; ?>" <?php echo (isset($_GET['id_paciente']) && $_GET['id_paciente'] == $pac['id_paciente']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($pac['nombre'] . ' ' . $pac['apellido']); ?> -
                                        <?php
                                        $edad_p = date_diff(date_create($pac['fecha_nacimiento']), date_create('today'))->y;
                                        echo $edad_p . ' años';
                                        ?> -
                                        <?php echo htmlspecialchars($pac['genero']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6" id="referido_nombre_div" style="display: none;">
                            <label class="form-label">Nombre del Paciente (Referido)</label>
                            <input type="text" class="form-control" name="referido_nombre" id="referido_nombre"
                                placeholder="Nombres">
                        </div>
                        <div class="col-md-6" id="referido_apellido_div" style="display: none;">
                            <label class="form-label">Apellido del Paciente (Referido)</label>
                            <input type="text" class="form-control" name="referido_apellido" id="referido_apellido"
                                placeholder="Apellidos">
                        </div>
                    </div>
                </div>

                <!-- Sección: Detalles del Ingreso -->
                <div class="stat-card p-4 mb-4 animate-in delay-1">
                    <h3 class="section-title mb-4">
                        <i class="bi bi-clipboard-pulse text-success me-2"></i>
                        Detalles del Ingreso
                    </h3>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label d-flex justify-content-between align-items-center">
                                Fecha y Hora de Ingreso
                                <button type="button" id="btn_retrasado" class="btn btn-sm btn-outline-warning">
                                    <i class="bi bi-clock-history"></i> Retrasado
                                </button>
                            </label>
                            <input type="datetime-local" class="form-control" name="fecha_ingreso" id="fecha_ingreso"
                                value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                            <input type="hidden" name="is_retrasado" id="is_retrasado" value="0">
                        </div>

                        <div class="col-md-6" id="div_fecha_alta" style="display: none;">
                            <label class="form-label">Fecha y Hora de Alta (Manual)</label>
                            <input type="datetime-local" class="form-control" name="fecha_alta" id="fecha_alta"
                                value="<?php echo date('Y-m-d\TH:i'); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Tipo de Ingreso</label>
                            <select class="form-select" name="tipo_ingreso" required>
                                <option value="Programado">Programado</option>
                                <option value="Emergencia" selected>Emergencia</option>
                                <option value="Referido">Referido</option>
                            </select>
                        </div>

                        <div class="col-md-6" id="doctor_select_div">
                            <label class="form-label">Médico Responsable</label>
                            <select class="form-select" id="id_doctor" name="id_doctor">
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

                        <div class="col-md-6" id="referido_doctor_div" style="display: none;">
                            <label class="form-label">Médico Referente (Nombre Completo)</label>
                            <input type="text" class="form-control" name="referido_doctor" id="referido_doctor"
                                placeholder="Dr. Nombre Apellido">
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
                                        Hab. <?php echo htmlspecialchars($bed['numero_habitacion']); ?> - Cama
                                        <?php echo htmlspecialchars($bed['numero_cama']); ?>
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
        document.addEventListener('DOMContentLoaded', function () {
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

            // Tipo de Ingreso toggle logic (Referido)
            const tipoIngresoSelect = document.querySelector('select[name="tipo_ingreso"]');
            const searchPacienteDiv = document.getElementById('search_paciente_div');
            const pacienteSelect = document.getElementById('paciente_select');
            const referidoNombreDiv = document.getElementById('referido_nombre_div');
            const referidoApellidoDiv = document.getElementById('referido_apellido_div');
            const referidoNombre = document.getElementById('referido_nombre');
            const referidoApellido = document.getElementById('referido_apellido');

            const doctorSelectDiv = document.getElementById('doctor_select_div');
            const doctorSelect = document.getElementById('id_doctor');
            const referidoDoctorDiv = document.getElementById('referido_doctor_div');
            const referidoDoctor = document.getElementById('referido_doctor');

            function togglePatientInput() {
                if (tipoIngresoSelect.value === 'Referido') {
                    searchPacienteDiv.style.display = 'none';
                    pacienteSelect.required = false;

                    referidoNombreDiv.style.display = 'block';
                    referidoApellidoDiv.style.display = 'block';
                    referidoNombre.required = true;
                    referidoApellido.required = true;

                    doctorSelectDiv.style.display = 'none';
                    doctorSelect.required = false;
                    referidoDoctorDiv.style.display = 'block';
                    referidoDoctor.required = true;
                } else {
                    searchPacienteDiv.style.display = 'block';
                    pacienteSelect.required = true;

                    referidoNombreDiv.style.display = 'none';
                    referidoApellidoDiv.style.display = 'none';
                    referidoNombre.required = false;
                    referidoApellido.required = false;

                    doctorSelectDiv.style.display = 'block';
                    doctorSelect.required = true;
                    referidoDoctorDiv.style.display = 'none';
                    referidoDoctor.required = false;
                }
            }

            tipoIngresoSelect.addEventListener('change', togglePatientInput);
            togglePatientInput(); // Initialize on load

            // Retrasado logic
            const btnRetrasado = document.getElementById('btn_retrasado');
            const isRetrasadoInput = document.getElementById('is_retrasado');
            const divFechaAlta = document.getElementById('div_fecha_alta');
            const inputFechaAlta = document.getElementById('fecha_alta');

            btnRetrasado.addEventListener('click', function () {
                if (isRetrasadoInput.value === '0') {
                    isRetrasadoInput.value = '1';
                    this.classList.remove('btn-outline-warning');
                    this.classList.add('btn-warning');
                    divFechaAlta.style.display = 'block';
                    inputFechaAlta.required = true;
                } else {
                    isRetrasadoInput.value = '0';
                    this.classList.remove('btn-warning');
                    this.classList.add('btn-outline-warning');
                    divFechaAlta.style.display = 'none';
                    inputFechaAlta.required = false;
                }
            });

            // Bed selection highlighting
            document.querySelectorAll('.bed-option').forEach(option => {
                option.addEventListener('click', function () {
                    document.querySelectorAll('.bed-option').forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    this.querySelector('input[type="radio"]').checked = true;
                });
            });

            // Form submission
            document.getElementById('ingresoForm').addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                formData.append('csrf_token', '<?php echo csrf_token(); ?>');
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