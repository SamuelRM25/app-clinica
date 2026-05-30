<?php
// laboratory/procesar_orden.php - Clinical results entry interface
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Incluir configuraciones y funciones
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/breadcrumbs.php';

$id_hospital = hospital_id();

// Establecer zona horaria
date_default_timezone_set('America/Guatemala');
verify_session();

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener información del usuario para el header
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_name = $_SESSION['nombre'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional de Laboratorio';

    $id_orden = $_GET['id'] ?? null;
    if (!$id_orden) {
        header("Location: index.php");
        exit;
    }

    // 1. Get order details with patient info
    $stmt = $conn->prepare("
        SELECT ol.*, p.nombre, p.apellido, p.genero, p.fecha_nacimiento,
               u.nombre as doctor_nombre, u.apellido as doctor_apellido
        FROM ordenes_laboratorio ol
        JOIN pacientes p ON ol.id_paciente = p.id_paciente
        LEFT JOIN usuarios u ON ol.id_doctor = u.idUsuario
        WHERE ol.id_orden = ? AND ol.id_hospital = ?
    ");
    $stmt->execute([$id_orden, $id_hospital]);
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orden) {
        header("Location: index.php");
        exit;
    }

    // 2. Get tests in this order with their parameters
    $stmt = $conn->prepare("
        SELECT op.*, cp.nombre_prueba, cp.codigo_prueba
        FROM orden_pruebas op
        JOIN ordenes_laboratorio ol ON op.id_orden = ol.id_orden
        JOIN catalogo_pruebas cp ON op.id_prueba = cp.id_prueba
        WHERE op.id_orden = ? AND ol.id_hospital = ?
    ");
    $stmt->execute([$id_orden, $id_hospital]);
    $pruebas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate patient age for reference values
    $edad = date_diff(date_create($orden['fecha_nacimiento']), date_create('today'))->y;
    $genero = $orden['genero'];

    // 3. Get all global result files for this order, separated by category
    $stmt_archivos = $conn->prepare("
        SELECT arl.* FROM archivos_resultados_laboratorio arl
        JOIN ordenes_laboratorio ol ON arl.id_orden = ol.id_orden
        WHERE arl.id_orden = ? AND ol.id_hospital = ?
        ORDER BY arl.id_archivo ASC
    ");
    $stmt_archivos->execute([$id_orden, $id_hospital]);
    $todos_archivos = $stmt_archivos->fetchAll(PDO::FETCH_ASSOC);

    $archivos_resultados = array_filter($todos_archivos, function ($a) {
        return $a['categoria'] === 'RESULTADO' || empty($a['categoria']);
    });
    $archivos_muestras = array_filter($todos_archivos, function ($a) {
        return $a['categoria'] === 'ORDEN_FISICA';
    });

    // Legacy support for single physical order if it exists but isn't in the new table
    $tiene_archivo_legacy = !empty($orden['archivo_resultados']);

    $page_title = "Procesar Orden #" . $orden['numero_orden'] . " - Centro Médico Herrera Saenz";

} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en procesar_orden: " . $e->getMessage());
    die("Error al cargar la orden. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Procesar Orden de Laboratorio - Centro Médico Herrera Saenz">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- logo -->
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- CSS Crítico (mismo que el dashboard) -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">

</head>

<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>

    <!-- Contenedor Principal -->
    <div class="dashboard-container">
        <!-- Header Superior -->
        <header class="dashboard-header">
            <div class="header-content">
                <!-- logo -->
                <div class="brand-container">
                    <img src="../../assets/img/cmhs.png" alt="Centro Médico Herrera Saenz" class="brand-logo" width="40"
                        height="40">
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

                    <!-- Botón para regresar a laboratory -->
                    <a href="index.php" class="back-btn">
                        <i class="bi bi-arrow-left"></i>
                        <span>Volver a Laboratorio</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido Principal -->
        <main class="main-content">
            <?php render_breadcrumbs([
                ['label' => 'Dashboard', 'url' => '../dashboard/index.php'],
                ['label' => 'Laboratorio', 'url' => 'index.php'],
                ['label' => 'Procesar Orden'],
            ]); ?>
            <!-- Tarjeta de información del paciente -->
            <div class="patient-header-card animate-in">
                <div>
                    <h2 class="mb-2"><?php echo htmlspecialchars($orden['nombre'] . ' ' . $orden['apellido']); ?></h2>
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
                    <p class="small text-muted mb-2">
                        <i class="bi bi-person-badge me-1"></i>
                        Dr. <?php echo htmlspecialchars($orden['doctor_nombre'] . ' ' . $orden['doctor_apellido']); ?>
                    </p>
                    <button type="button" class="btn btn-outline-primary btn-sm"
                        onclick="openOrderUploadModal(<?php echo $id_orden; ?>)">
                        <i class="bi bi-paperclip"></i> Adjuntar Orden Física
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm ms-1"
                        onclick="openResultsUploadModal(<?php echo $id_orden; ?>)">
                        <i class="bi bi-upload"></i> Subir Resultados
                    </button>

                    <!-- Display Physical Order Files -->
                    <?php if ($tiene_archivo_legacy || count($archivos_muestras) > 0): ?>
                            <div class="mt-2 d-flex flex-wrap gap-1 justify-content-end">
                                <?php if ($tiene_archivo_legacy): ?>
                                        <a href="<?php echo htmlspecialchars($orden['archivo_resultados']); ?>" target="_blank"
                                            class="btn btn-xs btn-info text-white">
                                            <i class="bi bi-eye"></i> Ver Orden (Principal)
                                        </a>
                                <?php endif; ?>
                                <?php foreach ($archivos_muestras as $idx => $am):
                                    $am_url = "api/get_result_file.php?id=" . $am['id_archivo'];
                                    ?>
                                        <a href="<?php echo htmlspecialchars($am_url); ?>" target="_blank"
                                            class="btn btn-xs btn-outline-info">
                                            <i class="bi bi-paperclip"></i> Orden #<?php echo $idx + 1; ?>
                                        </a>
                                <?php endforeach; ?>
                            </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formulario para ingresar resultados -->
            <form id="resultsForm" action="api/save_results.php" method="POST" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="id_orden" value="<?php echo $id_orden; ?>">

                <!-- Visualizador de Resultados (Global para la Orden) -->
                <div class="test-processing-section animate-in mb-4">
                    <div class="test-title-bar">
                        <h4 class="mb-0">
                            <i class="bi bi-file-earmark-medical text-primary me-2"></i>
                            Archivo de Resultados
                        </h4>
                    </div>
                    <div class="result-display-area p-4 text-center">
                        <?php if (count($archivos_resultados) > 0): ?>
                                <div class="row g-4 justify-content-center">
                                    <?php foreach ($archivos_resultados as $archivo):
                                        $file_url = "api/get_result_file.php?id=" . $archivo['id_archivo'];
                                        $mime_type = $archivo['tipo_contenido'];
                                        ?>
                                            <div class="col-md-6 col-lg-4" id="archivo-card-<?php echo $archivo['id_archivo']; ?>">
                                                <div class="card h-100 shadow-sm border position-relative">
                                                    <button type="button"
                                                        class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 rounded-circle shadow"
                                                        onclick="deleteResultFile(<?php echo $archivo['id_archivo']; ?>)"
                                                        title="Eliminar archivo" style="z-index: 10;">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <div class="card-body p-0 d-flex flex-column align-items-center justify-content-center bg-light"
                                                        style="min-height: 200px; overflow: hidden;">
                                                        <?php if (strpos($mime_type, 'image') !== false): ?>
                                                                <img src="<?php echo htmlspecialchars($file_url); ?>" class="img-fluid"
                                                                    loading="lazy" style="width: 100%; height: 200px; object-fit: cover;"
                                                                    alt="Resultado">
                                                        <?php elseif (strpos($mime_type, 'pdf') !== false): ?>
                                                                <div class="text-danger py-4"><i class="bi bi-file-earmark-pdf-fill"
                                                                        style="font-size: 3rem;"></i></div>
                                                                <p class="small text-truncate px-3 w-100 mb-2"
                                                                    title="<?php echo htmlspecialchars($archivo['nombre_archivo']); ?>">
                                                                    <?php echo htmlspecialchars($archivo['nombre_archivo']); ?>
                                                                </p>
                                                        <?php else: ?>
                                                                <div class="text-secondary py-4"><i class="bi bi-file-earmark-text-fill"
                                                                        style="font-size: 3rem;"></i></div>
                                                                <p class="small text-truncate px-3 w-100 mb-2"
                                                                    title="<?php echo htmlspecialchars($archivo['nombre_archivo']); ?>">
                                                                    <?php echo htmlspecialchars($archivo['nombre_archivo']); ?>
                                                                </p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="card-footer bg-white border-top p-2">
                                                        <a href="<?php echo htmlspecialchars($file_url); ?>" target="_blank"
                                                            class="btn btn-outline-primary btn-sm w-100">
                                                            <i class="bi bi-arrows-fullscreen me-1"></i> Abrir
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="mt-4 border-top pt-3">
                                    <button type="button" class="btn btn-outline-primary"
                                        onclick="openResultsUploadModal(<?php echo $id_orden; ?>)">
                                        <i class="bi bi-upload me-1"></i> Subir Más Resultados
                                    </button>
                                </div>
                        <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="bi bi-droplet"></i>
                                    </div>
                                    <h4 class="text-muted mb-2">Esperando resultados</h4>
                                    <p class="text-muted mb-3">Debe cargar el archivo de resultados (PDF o Imagen) para
                                        continuar</p>
                                    <button type="button" class="btn btn-outline-primary"
                                        onclick="openResultsUploadModal(<?php echo $id_orden; ?>)">
                                        <i class="bi bi-upload me-1"></i> Subir Resultados Ahora
                                    </button>
                                </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Lista de Pruebas -->
                <?php foreach ($pruebas as $prueba): ?>
                        <div class="test-processing-section animate-in delay-1"
                            data-id-orden-prueba="<?php echo $prueba['id_orden_prueba']; ?>">
                            <div class="test-title-bar">
                                <h4 class="mb-0">
                                    <i class="bi bi-virus text-primary me-2"></i>
                                    <?php echo htmlspecialchars($prueba['nombre_prueba']); ?>
                                </h4>
                            </div>

                            <!-- Parámetros de la Prueba -->
                            <div class="test-parameters mt-2">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th width="35%">Parámetro</th>
                                            <th width="20%">Resultado</th>
                                            <th width="15%">Unidad</th>
                                            <th width="25%">Valor Referencia</th>
                                            <th width="5%">Flag</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt_params = $conn->prepare("
                                        SELECT pp.*, rl.valor_resultado as valor_actual
                                        FROM parametros_pruebas pp
                                        LEFT JOIN resultados_laboratorio rl ON pp.id_parametro = rl.id_parametro 
                                            AND rl.id_orden_prueba = ?
                                        WHERE pp.id_prueba = ? 
                                        ORDER BY pp.orden_visualizacion ASC
                                    ");
                                        $stmt_params->execute([$prueba['id_orden_prueba'], $prueba['id_prueba']]);
                                        $parametros = $stmt_params->fetchAll(PDO::FETCH_ASSOC);

                                        foreach ($parametros as $param):
                                            // Determinar valores de referencia según paciente
                                            $min = null;
                                            $max = null;
                                            if ($edad <= 12) {
                                                $min = $param['valor_ref_pediatrico_min'];
                                                $max = $param['valor_ref_pediatrico_max'];
                                            } else if ($genero === 'Masculino') {
                                                $min = $param['valor_ref_hombre_min'];
                                                $max = $param['valor_ref_hombre_max'];
                                            } else {
                                                $min = $param['valor_ref_mujer_min'];
                                                $max = $param['valor_ref_mujer_max'];
                                            }
                                            $val_ref = ($min !== null && $max !== null) ? "$min - $max" : "N/D";
                                            ?>
                                                <tr>
                                                    <td class="fw-medium text-dark">
                                                        <?php echo htmlspecialchars($param['nombre_parametro']); ?>
                                                    </td>
                                                    <td>
                                                        <input type="text"
                                                            name="results[<?php echo $prueba['id_orden_prueba']; ?>][<?php echo $param['id_parametro']; ?>]"
                                                            class="form-control form-control-sm result-input"
                                                            value="<?php echo htmlspecialchars($param['valor_actual'] ?? ''); ?>"
                                                            data-min="<?php echo $min; ?>" data-max="<?php echo $max; ?>"
                                                            onchange="validateRange(this)">
                                                    </td>
                                                    <td class="text-muted small">
                                                        <?php echo htmlspecialchars($param['unidad_medida']); ?>
                                                    </td>
                                                    <td class="text-muted small"><?php echo $val_ref; ?></td>
                                                    <td class="flag-container"></td>
                                                </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                <?php endforeach; ?>

                <div class="sticky-bottom">
                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" class="action-btn">
                            <i class="bi bi-save"></i> Guardar Resultados
                        </button>
                        <button type="button" class="action-btn success" onclick="validateAndFinalize()">
                            <i class="bi bi-check-all"></i> Validar y Finalizar Orden
                        </button>
                    </div>
                </div>
            </form>

            <!-- Modal para carga de Orden Física -->
            <div class="modal fade" id="fileUploadModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius: var(--radius-lg);">
                        <div class="modal-header" style="border-bottom: 1px solid var(--color-border);">
                            <h5 class="modal-title">
                                <i class="bi bi-paperclip me-2"></i>
                                Adjuntar Orden Física (Muestra)
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="fileUploadForm" enctype="multipart/form-data">
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Seleccione archivo(s) (PDF o Imagen)</label>
                                    <input type="file" class="form-control" name="archivo_muestra[]"
                                        id="archivo_muestra" accept=".pdf,.jpg,.jpeg,.png" required multiple>
                                    <small class="text-muted">Formatos permitidos: PDF, JPG, PNG. Puede seleccionar
                                        varios archivos.</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Notas (Opcional)</label>
                                    <textarea class="form-control" name="notas" rows="2"
                                        placeholder="Agregar notas sobre la orden física..."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer" style="border-top: 1px solid var(--color-border);">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-1"></i>Confirmar y Cargar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Results Upload Modal -->
            <div class="modal fade" id="resultsUploadModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Subir Resultados de Laboratorio</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="resultsUploadForm" enctype="multipart/form-data">
                                <input type="hidden" name="id_orden" id="resultUploadOrderId">
                                <div class="mb-3">
                                    <label for="archivo_resultado" class="form-label">Archivo(s) de Resultados (PDF,
                                        JPG,
                                        PNG)</label>
                                    <input class="form-control" type="file" id="archivo_resultado"
                                        name="archivo_resultado[]" accept=".pdf,.jpg,.jpeg,.png" required multiple>
                                </div>
                                <div class="mb-3">
                                    <label for="notas_resultado" class="form-label">Notas Adicionales</label>
                                    <textarea class="form-control" id="notas_resultado" name="notas" rows="3"
                                        placeholder="Cualquier observación sobre el archivo..."></textarea>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-cloud-upload"></i> Subir Archivo
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript Optimizado -->
    <script>
        // Sistema de tema
        document.addEventListener('DOMContentLoaded', function () {
            'use strict';

            const CONFIG = {
                themeKey: 'dashboard-theme'
            };

            // ==========================================================================
            // REFERENCIAS A ELEMENTOS DOM
            // ==========================================================================
            const DOM = {
                html: document.documentElement,
                themeSwitch: document.getElementById('themeSwitch')
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
                    // 1. Verificar preferencia guardada
                    const savedTheme = localStorage.getItem(CONFIG.themeKey);
                    if (savedTheme) return savedTheme;

                    // 2. Verificar preferencia del sistema
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (prefersDark) return 'dark';

                    // 3. Tema por defecto (día)
                    return 'light';
                }

                applyTheme(theme) {
                    DOM.html.setAttribute('data-theme', theme);
                    localStorage.setItem(CONFIG.themeKey, theme);

                    // Actualizar meta tag para navegadores móviles
                    const metaTheme = document.querySelector('meta[name="theme-color"]');
                    if (metaTheme) {
                        metaTheme.setAttribute('content', theme === 'dark' ? '#0f172a' : '#ffffff');
                    }
                }

                toggleTheme() {
                    const newTheme = this.theme === 'light' ? 'dark' : 'light';
                    this.theme = newTheme;
                    this.applyTheme(newTheme);

                    // Animación sutil en el botón
                    if (DOM.themeSwitch) {
                        DOM.themeSwitch.style.transform = 'rotate(180deg)';
                        setTimeout(() => {
                            DOM.themeSwitch.style.transform = 'rotate(0)';
                        }, 300);
                    }
                }

                setupEventListeners() {
                    if (DOM.themeSwitch) {
                        DOM.themeSwitch.addEventListener('click', () => this.toggleTheme());
                    }

                    // Escuchar cambios en preferencias del sistema
                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                        if (!localStorage.getItem(CONFIG.themeKey)) {
                            this.theme = e.matches ? 'dark' : 'light';
                            this.applyTheme(this.theme);
                        }
                    });
                }
            }

            // ==========================================================================
            // FUNCIONES ESPECÍFICAS DE LA PÁGINA
            // ==========================================================================
            class LaboratoryFunctions {
                constructor() {
                    this.setupRangeValidation();
                    this.setupAnimations();
                }

                setupRangeValidation() {
                    // Inicializar flags si hay valores
                    document.querySelectorAll('.result-input').forEach(input => {
                        if (input.value) this.validateRange(input);
                    });
                }

                validateRange(input) {
                    const val = parseFloat(input.value);
                    const min = parseFloat(input.dataset.min);
                    const max = parseFloat(input.dataset.max);
                    const container = input.closest('tr').querySelector('.flag-container');

                    if (isNaN(val) || isNaN(min) || isNaN(max)) {
                        container.innerHTML = '';
                        return;
                    }

                    let flag = '';
                    if (val < min) {
                        flag = '<span class="flag-indicator flag-low" title="Bajo">L</span>';
                    } else if (val > max) {
                        flag = '<span class="flag-indicator flag-high" title="Alto">H</span>';
                    } else {
                        flag = '<span class="flag-indicator flag-normal" title="Normal">N</span>';
                    }

                    container.innerHTML = flag;
                }

                setupAnimations() {
                    // Animar elementos al cargar
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

                    // Observar elementos con clase de animación
                    document.querySelectorAll('.patient-header-card, .test-processing-section').forEach(el => {
                        observer.observe(el);
                    });
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN
            // ==========================================================================
            const themeManager = new ThemeManager();
            const labFunctions = new LaboratoryFunctions();

            // Exponer funciones globalmente
            window.laboratory = {
                theme: themeManager,
                functions: labFunctions
            };

            // Log de inicialización
            console.log('Laboratorio - Procesar Orden inicializado');
        });

        // Funciones globales para botones
        function openOrderUploadModal(id_orden) {
            window.currentOrderId = id_orden;
            const modal = new bootstrap.Modal(document.getElementById('fileUploadModal'));
            modal.show();
        }

        function openResultsUploadModal(id_orden) {
            document.getElementById('resultUploadOrderId').value = id_orden;
            const modal = new bootstrap.Modal(document.getElementById('resultsUploadModal'));
            modal.show();
        }

        function validateAndFinalize() {
            Swal.fire({
                title: '¿Validar y Finalizar?',
                text: 'Una vez validada, la orden no podrá ser modificada y los resultados estarán disponibles para el doctor.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, validar todo',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: 'var(--color-success)',
                background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `api/validate_order.php?id=<?php echo $id_orden; ?>&csrf_token=${window.CSRF_TOKEN}`;
                }
            });
        }

        // Handle file upload form submission
        document.getElementById('fileUploadForm')?.addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('id_orden', window.currentOrderId);
            // formData.append('id_orden_prueba', window.currentTestId); // Removed as we are uploading for order

            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Subiendo...';
            submitBtn.disabled = true;

            fetch('api/upload_sample_file.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Archivo Cargado',
                            text: 'El archivo se ha cargado correctamente',
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Error al cargar el archivo'
                        });
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error de conexión'
                    });
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
        });

        // Handle results upload form submission
        document.getElementById('resultsUploadForm')?.addEventListener('submit', function (e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Subiendo...';
            submitBtn.disabled = true;

            const formData = new FormData(this);

            fetch('api/upload_results.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Resultados Cargados',
                            text: 'El archivo de resultados se ha guardado correctamente',
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Error al cargar el archivo'
                        });
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error de conexión'
                    });
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
        });

        // Delete Result File
        function deleteResultFile(id_archivo) {
            Swal.fire({
                title: '¿Eliminar archivo?',
                text: "Esta acción no se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('api/delete_result_file.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id_archivo: id_archivo })
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Eliminado',
                                    text: 'El archivo se ha eliminado correctamente',
                                    showConfirmButton: false,
                                    timer: 1500
                                }).then(() => {
                                    document.getElementById('archivo-card-' + id_archivo).remove();
                                    // Optional: if no cards left, reload to show empty state
                                    if (document.querySelectorAll('[id^=archivo-card-]').length === 0) {
                                        location.reload();
                                    }
                                });
                            } else {
                                Swal.fire('Error', data.message || 'Error al eliminar', 'error');
                            }
                        })
                        .catch(err => Swal.fire('Error', 'Error de conexión', 'error'));
                }
            });
        }

        function validateRange(input) {
            window.laboratory.functions.validateRange(input);
        }

        // Manejo de envío del formulario (AJAX)
        document.getElementById('resultsForm')?.addEventListener('submit', function (e) {
            e.preventDefault();

            // Mostrar indicador de carga
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-2"></i>Guardando...';
            submitBtn.disabled = true;

            const formData = new FormData(this);

            fetch('api/save_results.php', {
                method: 'POST',
                body: formData
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: data.message || 'Resultados guardados correctamente',
                            timer: 2000,
                            showConfirmButton: false,
                            background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                            color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Error al guardar resultados',
                            background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                            color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                        });
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de Conexión',
                        text: 'Hubo un problema al comunicarse con el servidor.',
                        background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1e293b' : '#ffffff',
                        color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#e2e8f0' : '#1a1a1a'
                    });
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                });
        });

        // Estilos para spinner
        const style = document.createElement('style');
        style.textContent = `
        .spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    `;
        document.head.appendChild(style);
    </script>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>