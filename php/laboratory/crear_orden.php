<?php
// laboratory/crear_orden.php - Create a new clinical laboratory order
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/breadcrumbs.php';
require_once '../../includes/module_guard.php';

$id_hospital = hospital_id();
$embedded_mode = isset($_GET['embedded']) && $_GET['embedded'] == '1';

verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Fetch all available tests grouped by category for selection
    $stmt = $conn->prepare("SELECT id_prueba, codigo_prueba, nombre_prueba, categoria, notas, precio, tiempo_procesamiento_horas, muestra_requerida FROM catalogo_pruebas WHERE id_hospital = ? ORDER BY categoria, nombre_prueba");
    $stmt->execute([$id_hospital]);
    $catalogo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pruebas_por_categoria = [];
    foreach ($catalogo as $prueba) {
        $pruebas_por_categoria[$prueba['categoria'] ?? 'Sin Categoría'][] = $prueba;
    }

    // Obtener doctores para el selector
    $stmt = $conn->prepare("SELECT idUsuario, nombre, apellido FROM usuarios WHERE tipoUsuario = 'doc' AND id_hospital = ? ORDER BY apellido");
    $stmt->execute([$id_hospital]);
    $doctors = $stmt->fetchAll();

    // Pre-seleccionar paciente si viene en URL
    $preselected_patient = null;
    if (isset($_GET['id_paciente'])) {
        $stmt = $conn->prepare("SELECT id_paciente, nombre, apellido FROM pacientes WHERE id_paciente = ? AND id_hospital = ?");
        $stmt->execute([$_GET['id_paciente'], $id_hospital]);
        $preselected_patient = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Obtener todos los pacientes para el buscador (si no hay preseleccionado)
    $stmt = $conn->prepare("SELECT id_paciente, nombre, apellido FROM pacientes WHERE id_hospital = ? ORDER BY nombre, apellido");
    $stmt->execute([$id_hospital]);
    $all_patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $page_title = "Nueva Orden de Laboratorio";
} catch (Exception $e) {
    error_log('Error en laboratory/crear_orden.php: ' . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Crear Orden de Laboratorio - Centro Médico Herrera Saenz">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- logo -->
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">

    <!-- Google Fonts - Inter -->
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <!-- CSS Crítico (mismo que index.php) -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
                            <?php echo strtoupper(substr($_SESSION['nombre'], 0, 1)); ?>
                        </div>
                        <div class="header-details">
                            <span class="header-name"><?php echo htmlspecialchars($_SESSION['nombre']); ?></span>
                            <span class="header-role">Crear Orden de Laboratorio</span>
                        </div>
                    </div>

                    <!-- Botón de volver -->
                    <a href="index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i>
                        Volver a Laboratorios
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido Principal -->
        <main class="main-content">
            <?php render_breadcrumbs([
                ['label' => 'Dashboard', 'url' => '../dashboard/index.php'],
                ['label' => 'Laboratorio', 'url' => 'index.php'],
                ['label' => 'Nueva Orden'],
            ]); ?>
            <!-- Banner de bienvenida -->
            <div class="welcome-banner animate-in">
                <h1>Nueva Orden de Laboratorio</h1>
                <p>Complete la información para generar una nueva solicitud de pruebas</p>
            </div>

            <!-- Formulario de orden -->
            <form id="orderForm" action="api/create_order.php" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="is_embedded" id="is_embedded" value="<?php echo $embedded_mode ? '1' : '0'; ?>">
                <div class="order-form-container">
                    <!-- Panel izquierdo: Información y selección de pruebas -->
                    <div>
                        <!-- Información del paciente -->
                        <div class="patient-info-card animate-in delay-1">
                            <h3 class="section-title mb-4">
                                <i class="bi bi-person-badge section-title-icon"></i>
                                Información del Paciente
                            </h3>

                            <div class="row">
                                <div class="col-md-7">
                                    <div class="form-group">
                                        <label class="form-label">Paciente *</label>
                                        <input class="form-control" list="patientDatalist" id="patient_input"
                                            placeholder="Buscar paciente (Nombre, Apellido)..." required
                                            autocomplete="off"
                                            value="<?php echo $preselected_patient ? htmlspecialchars($preselected_patient['nombre'] . ' ' . $preselected_patient['apellido']) : ''; ?>">
                                        <datalist id="patientDatalist">
                                            <?php foreach ($all_patients as $p): ?>
                                                    <option data-id="<?php echo $p['id_paciente']; ?>"
                                                        value="<?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido']); ?>">
                                                <?php endforeach; ?>
                                        </datalist>
                                        <input type="hidden" name="id_paciente" id="id_paciente"
                                            value="<?php echo $preselected_patient ? $preselected_patient['id_paciente'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-5">
                                    <div class="form-group">
                                        <label class="form-label">Doctor Solicitante</label>
                                        <select id="id_doctor" name="id_doctor" class="form-control">
                                            <option value="">Seleccionar doctor...</option>
                                            <?php foreach ($doctors as $doc): ?>
                                                    <option value="<?php echo $doc['idUsuario']; ?>">
                                                        Dr.
                                                        <?php echo htmlspecialchars($doc['nombre'] . ' ' . $doc['apellido']); ?>
                                                    </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label class="form-label">Prioridad</label>
                                        <select name="prioridad" class="form-control">
                                            <option value="Normal">Normal</option>
                                            <option value="Urgente">Urgente</option>
                                            <option value="Emergencia">Emergencia</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <div class="form-group">
                                        <label class="form-label">Instrucciones Especiales</label>
                                        <input type="text" name="instrucciones" class="form-control"
                                            placeholder="Ej: Ayuno de 8 horas, muestra en ayunas, etc.">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Selección de pruebas -->
                        <div class="tests-selection-card animate-in delay-2">
                            <div class="section-header mb-4">
                                <h3 class="section-title">
                                    <i class="bi bi-clipboard-check section-title-icon"></i>
                                    Selección de Pruebas
                                </h3>
                                <div class="text-muted">
                                    <?php echo count($catalogo); ?> pruebas disponibles
                                </div>
                            </div>

                            <?php if (count($catalogo) > 0): ?>
                                <!-- Barra de búsqueda -->
                                <div class="tests-search-bar">
                                    <i class="bi bi-search search-icon"></i>
                                    <input type="text" class="tests-search-input" id="labTestSearch"
                                        placeholder="Buscar pruebas por nombre..." autocomplete="off">
                                </div>

                                <!-- Filtros rápidos por categoría -->
                                <div class="category-filter-pills" id="categoryFilterPills">
                                    <button class="category-pill active" data-category="all">Todas</button>
                                    <?php foreach ($pruebas_por_categoria as $categoria => $pruebas): ?>
                                        <button class="category-pill" data-category="<?php echo htmlspecialchars($categoria); ?>">
                                            <?php echo htmlspecialchars($categoria); ?>
                                            <span class="badge"><?php echo count($pruebas); ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>

                                <div class="tests-search-status" id="testsSearchStatus">
                                    Mostrando <strong><?php echo count($catalogo); ?></strong> pruebas
                                </div>

                                <!-- Acordeón de categorías -->
                                <div class="category-accordion" id="categoryAccordion">
                                    <?php 
                                    $catIndex = 0;
                                    $categoryIcons = [
                                        'Hematología' => 'bi-droplet',
                                        'Química Sanguínea' => 'bi-flask',
                                        'Urianálisis' => 'bi-cup-straw',
                                        'Inmunología' => 'bi-shield-check',
                                        'Microbiología' => 'bi-bug',
                                        'Serología' => 'bi-heart-pulse',
                                    ];
                                    foreach ($pruebas_por_categoria as $categoria => $pruebas): 
                                        $icon = $categoryIcons[$categoria] ?? 'bi-folder2';
                                        $catIndex++;
                                    ?>
                                    <div class="category-accordion-item expanded" data-category="<?php echo htmlspecialchars($categoria); ?>">
                                        <div class="category-accordion-header" onclick="toggleCategory(this)">
                                            <div class="category-header-left">
                                                <div class="category-icon"><i class="bi <?php echo $icon; ?>"></i></div>
                                                <span class="category-name"><?php echo htmlspecialchars($categoria); ?></span>
                                                <span class="category-count"><?php echo count($pruebas); ?></span>
                                            </div>
                                            <div class="category-header-right">
                                                <span class="category-select-all" onclick="event.stopPropagation(); toggleCategorySelectAll(this, '<?php echo htmlspecialchars($categoria); ?>')">
                                                    <i class="bi bi-check-all"></i> Todo
                                                </span>
                                                <i class="bi bi-chevron-down category-chevron"></i>
                                            </div>
                                        </div>
                                        <div class="category-accordion-body">
                                            <div class="category-accordion-body-inner">
                                                <div class="tests-grid">
                                                    <?php foreach ($pruebas as $prueba): ?>
                                                    <div class="test-card"
                                                        onclick="toggleTest(this, <?php echo htmlspecialchars(json_encode($prueba)); ?>)"
                                                        data-id="<?php echo $prueba['id_prueba']; ?>"
                                                        data-category="<?php echo htmlspecialchars($categoria); ?>">
                                                        <input type="checkbox" name="pruebas[]"
                                                            value="<?php echo $prueba['id_prueba']; ?>" class="d-none">
                                                        <div class="test-checkbox"><i class="bi bi-check"></i></div>
                                                        <div class="test-info">
                                                            <div class="test-name"><?php echo htmlspecialchars($prueba['nombre_prueba']); ?></div>
                                                            <div class="test-meta">
                                                                <span class="test-price">Q<?php echo number_format($prueba['precio'] ?? 0, 2); ?></span>
                                                                <span class="test-time"><i class="bi bi-clock"></i> <?php echo $prueba['tiempo_procesamiento_horas']; ?>h</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                    <div class="empty-state text-center py-5">
                                        <i class="bi bi-clipboard-x empty-icon"></i>
                                        <h4 class="text-muted mb-2">No hay pruebas disponibles</h4>
                                        <p class="text-muted">Configure primero el catálogo de pruebas</p>
                                        <a href="catalogo_pruebas.php" class="action-btn secondary">
                                            <i class="bi bi-gear"></i>
                                            Ir al catálogo
                                        </a>
                                    </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Panel derecho: Resumen -->
                    <div class="order-summary-card animate-in delay-3">
                        <div class="order-summary-header">
                            <h3><i class="bi bi-receipt section-title-icon"></i> Resumen de la Orden</h3>
                            <span class="selected-badge" id="selectedBadge">0</span>
                        </div>

                        <div class="selected-tests-list" id="selectedTestsList">
                            <div class="empty-state text-center py-4">
                                <i class="bi bi-cart empty-icon"></i>
                                <p class="text-muted mb-0">No hay pruebas seleccionadas</p>
                                <small class="text-muted">Haga clic en las pruebas para agregarlas</small>
                            </div>
                        </div>

                        <div class="order-total-section">
                            <span class="order-total-label">Total:</span>
                            <span id="orderTotal" class="order-total-amount">Q0.00</span>
                        </div>

                        <div class="form-group mt-3">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="2"
                                placeholder="Observaciones adicionales..."></textarea>
                        </div>

                        <button type="submit" class="order-submit-btn mt-3">
                            <i class="bi bi-file-earmark-check"></i>
                            Generar Orden de Laboratorio
                        </button>

                        <div class="text-center mt-2">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i>
                                La orden será creada en estado "Pendiente"
                            </small>
                        </div>
                    </div>
                        </div>

                        <div class="order-total">
                            <span>Total:</span>
                            <span id="orderTotal" class="order-total-amount">Q0.00</span>
                        </div>

                        <div class="form-group mt-4">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="3"
                                placeholder="Observaciones adicionales..."></textarea>
                        </div>

                        <button type="submit" class="action-btn w-100 mt-4 py-3">
                            <i class="bi bi-file-earmark-check"></i>
                            Generar Orden de Laboratorio
                        </button>

                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i>
                                La orden será creada en estado "Pendiente"
                            </small>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- JavaScript Optimizado (mismo que index.php) -->
    <script>
        // Dashboard de Laboratorio Reingenierizado

        (function () {
            'use strict';

            // ==========================================================================
            // CONFIGURACIÓN Y CONSTANTES
            // ========================================================================== */
            const CONFIG = {
                themeKey: 'dashboard-theme',
                transitionDuration: 300,
                animationDelay: 100
            };

            // ==========================================================================
            // REFERENCIAS A ELEMENTOS DOM
            // ========================================================================== */
            const DOM = {
                html: document.documentElement,
                body: document.body,
                themeSwitch: document.getElementById('themeSwitch')
            };

            // ==========================================================================
            // MANEJO DE TEMA (DÍA/NOCHE)
            // ========================================================================== */
            class ThemeManager {
                constructor() {
                    this.theme = this.getInitialTheme();
                    this.applyTheme(this.theme);
                    this.setupEventListeners();
                }

                getInitialTheme() {
                    const savedTheme = localStorage.getItem(CONFIG.themeKey);
                    if (savedTheme) return savedTheme;

                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (prefersDark) return 'dark';

                    return 'light';
                }

                applyTheme(theme) {
                    DOM.html.setAttribute('data-theme', theme);
                    localStorage.setItem(CONFIG.themeKey, theme);

                    const metaTheme = document.querySelector('meta[name="theme-color"]');
                    if (metaTheme) {
                        metaTheme.setAttribute('content', theme === 'dark' ? '#0f172a' : '#ffffff');
                    }
                }

                toggleTheme() {
                    const newTheme = this.theme === 'light' ? 'dark' : 'light';
                    this.theme = newTheme;
                    this.applyTheme(newTheme);

                    if (DOM.themeSwitch) {
                        DOM.themeSwitch.style.transform = 'rotate(180deg)';
                        setTimeout(() => {
                            DOM.themeSwitch.style.transform = 'rotate(0)';
                        }, CONFIG.transitionDuration);
                    }
                }

                setupEventListeners() {
                    if (DOM.themeSwitch) {
                        DOM.themeSwitch.addEventListener('click', () => this.toggleTheme());
                    }

                    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                        if (!localStorage.getItem(CONFIG.themeKey)) {
                            this.theme = e.matches ? 'dark' : 'light';
                            this.applyTheme(this.theme);
                        }
                    });
                }
            }

            // ==========================================================================
            // COMPONENTES DINÁMICOS
            // ========================================================================== */
            class DynamicComponents {
                constructor() {
                    this.setupAnimations();
                    this.setupSelect2();
                    this.setupFormValidation();
                }

                setupAnimations() {
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

                    document.querySelectorAll('.patient-info-card, .tests-selection-card, .order-summary-card').forEach(el => {
                        observer.observe(el);
                    });
                }

                setupSelect2() {
                    // Logic for patient search with Datalist
                    const patientInput = document.getElementById('patient_input');
                    const patientHidden = document.getElementById('id_paciente');
                    const datalist = document.getElementById('patientDatalist');

                    if (patientInput && patientHidden && datalist) {
                        patientInput.addEventListener('input', function () {
                            const val = this.value;
                            const options = datalist.options;
                            let found = false;

                            for (let i = 0; i < options.length; i++) {
                                if (options[i].value === val) {
                                    patientHidden.value = options[i].getAttribute('data-id');
                                    found = true;
                                    break;
                                }
                            }

                            if (!found) {
                                patientHidden.value = '';
                            }
                        });

                        // Si ya hay un valor (precargado), asegurar que el hidden tenga el ID
                        if (patientInput.value && !patientHidden.value) {
                            const val = patientInput.value;
                            const options = datalist.options;
                            for (let i = 0; i < options.length; i++) {
                                if (options[i].value === val) {
                                    patientHidden.value = options[i].getAttribute('data-id');
                                    break;
                                }
                            }
                        }
                    }

                    // Configurar Select2 solo para doctor
                    if ($('#id_doctor').length) {
                        $('#id_doctor').select2({
                            theme: 'default',
                            placeholder: 'Seleccionar doctor...',
                            allowClear: true
                        });
                    }
                }

                setupFormValidation() {
                    // Validación centralizada en handler de SweetAlert2
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ========================================================================== */
            document.addEventListener('DOMContentLoaded', () => {
                const themeManager = new ThemeManager();
                const dynamicComponents = new DynamicComponents();

                window.orderDashboard = {
                    theme: themeManager,
                    components: dynamicComponents,
                    selectedTests: []
                };

                console.log('Crear Orden inicializado');

                // Filtro de búsqueda de pruebas
                const searchInput = document.getElementById('labTestSearch');
                if (searchInput) {
                    searchInput.addEventListener('input', function () {
                        const term = this.value.toLowerCase();
                        const items = document.querySelectorAll('.category-accordion-item');
                        let visibleTests = 0;

                        items.forEach(item => {
                            const cards = item.querySelectorAll('.test-card');
                            let someVisible = false;
                            cards.forEach(card => {
                                const name = card.querySelector('.test-name').textContent.toLowerCase();
                                if (name.includes(term)) {
                                    card.style.display = '';
                                    someVisible = true;
                                    visibleTests++;
                                } else {
                                    card.style.display = 'none';
                                }
                            });

                            if (term === '') {
                                item.style.display = '';
                            } else if (someVisible) {
                                item.style.display = '';
                                if (!item.classList.contains('expanded')) {
                                    item.classList.add('expanded');
                                }
                            } else {
                                item.style.display = 'none';
                            }
                        });

                        const statusEl = document.getElementById('testsSearchStatus');
                        if (statusEl) {
                            if (term === '') {
                                statusEl.innerHTML = 'Mostrando <strong>' + document.querySelectorAll('.test-card').length + '</strong> pruebas';
                            } else {
                                statusEl.innerHTML = 'Se encontraron <strong>' + visibleTests + '</strong> pruebas';
                            }
                        }
                    });
                }

                // Filtros por categoría (píldoras)
                const filterPills = document.querySelectorAll('.category-pill');
                filterPills.forEach(pill => {
                    pill.addEventListener('click', function () {
                        filterPills.forEach(p => p.classList.remove('active'));
                        this.classList.add('active');

                        const category = this.dataset.category;
                        const items = document.querySelectorAll('.category-accordion-item');

                        items.forEach(item => {
                            if (category === 'all') {
                                item.style.display = '';
                            } else {
                                const cat = item.dataset.category;
                                item.style.display = cat === category ? '' : 'none';
                            }
                        });
                    });
                });
            });

            // ==========================================================================
            // FUNCIONES AUXILIARES
            // ========================================================================== */
            function calcularEdad(fechaNacimiento) {
                const hoy = new Date();
                const nacimiento = new Date(fechaNacimiento);
                let edad = hoy.getFullYear() - nacimiento.getFullYear();
                const mes = hoy.getMonth() - nacimiento.getMonth();

                if (mes < 0 || (mes === 0 && hoy.getDate() < nacimiento.getDate())) {
                    edad--;
                }
                return edad;
            }

            function showError(mensaje) {
                // Crear notificación de error
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert-error';
                errorDiv.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--color-danger);
                color: white;
                padding: var(--space-md) var(--space-lg);
                border-radius: var(--radius-md);
                z-index: 9999;
                box-shadow: var(--shadow-lg);
                animation: slideIn 0.3s ease;
            `;
                errorDiv.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>${mensaje}</span>
                </div>
            `;

                document.body.appendChild(errorDiv);

                setTimeout(() => {
                    errorDiv.style.animation = 'slideOut 0.3s ease';
                    setTimeout(() => errorDiv.remove(), 300);
                }, 3000);

                // Agregar animaciones CSS si no existen
                if (!document.querySelector('#error-animations')) {
                    const style = document.createElement('style');
                    style.id = 'error-animations';
                    style.textContent = `
                    @keyframes slideIn {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                    @keyframes slideOut {
                        from { transform: translateX(0); opacity: 1; }
                        to { transform: translateX(100%); opacity: 0; }
                    }
                `;
                    document.head.appendChild(style);
                }
            }

        })();

        // ==========================================================================
        // FUNCIONES ESPECÍFICAS PARA CREAR ORDEN
        // ========================================================================== */

        function toggleCategory(headerEl) {
            const item = headerEl.closest('.category-accordion-item');
            if (item) {
                item.classList.toggle('expanded');
            }
        }

        function toggleCategorySelectAll(el, category) {
            const items = document.querySelectorAll('.category-accordion-item');
            let targetItem = null;
            items.forEach(item => {
                if (item.dataset.category === category) {
                    targetItem = item;
                }
            });

            if (!targetItem) return;

            const cards = targetItem.querySelectorAll('.test-card');
            const allSelected = Array.from(cards).every(c => c.classList.contains('selected'));

            cards.forEach(card => {
                const testId = card.dataset.id;
                const testData = selectedTests.find(t => t.id_prueba == testId);

                if (allSelected) {
                    if (testData) {
                        const checkbox = card.querySelector('input[type="checkbox"]');
                        card.classList.remove('selected');
                        checkbox.checked = false;
                        const idx = selectedTests.findIndex(t => t.id_prueba == testId);
                        if (idx !== -1) selectedTests.splice(idx, 1);
                    }
                } else {
                    if (!testData) {
                        const checkbox = card.querySelector('input[type="checkbox"]');
                        card.classList.add('selected');
                        checkbox.checked = true;
                        // Reconstruct testData from the card's onclick attribute
                        const onclickAttr = card.getAttribute('onclick');
                        // Use a simpler approach: parse from the category's PHP data
                        const allTests = <?php echo json_encode($catalogo); ?>;
                        const found = allTests.find(t => t.id_prueba == testId);
                        if (found) {
                            selectedTests.push({
                                ...found,
                                precio: parseFloat(found.precio || found.price || 0)
                            });
                        }
                    }
                }
            });

            updateOrderSummary();
        }

        let selectedTests = [];

        function toggleTest(card, testData) {
            const checkbox = card.querySelector('input[type="checkbox"]');
            const index = selectedTests.findIndex(t => t.id_prueba === testData.id_prueba);

            if (index === -1) {
                selectedTests.push({
                    ...testData,
                    precio: parseFloat(testData.precio || testData.price || 0)
                });
                card.classList.add('selected');
                checkbox.checked = true;
            } else {
                selectedTests.splice(index, 1);
                card.classList.remove('selected');
                checkbox.checked = false;
            }

            updateOrderSummary();
        }

        function updateOrderSummary() {
            const listContainer = document.getElementById('selectedTestsList');
            const totalElement = document.getElementById('orderTotal');
            const badge = document.getElementById('selectedBadge');

            if (badge) {
                badge.textContent = selectedTests.length;
            }

            if (selectedTests.length === 0) {
                listContainer.innerHTML = `
                <div class="empty-state text-center py-4">
                    <i class="bi bi-cart empty-icon"></i>
                    <p class="text-muted mb-0">No hay pruebas seleccionadas</p>
                    <small class="text-muted">Haga clic en las pruebas para agregarlas</small>
                </div>
            `;
                totalElement.textContent = 'Q0.00';
                return;
            }

            let total = 0;
            let html = '';

            selectedTests.forEach((test) => {
                total += test.precio;
                html += `
                <div class="selected-test-item">
                    <span class="test-item-name" title="${test.nombre_prueba}">${test.nombre_prueba}</span>
                    <span class="test-item-price">Q${test.precio.toFixed(2)}</span>
                    <i class="bi bi-x-circle test-item-remove" 
                       onclick="removeTest(${test.id_prueba})"
                       title="Remover prueba"></i>
                </div>
            `;
            });

            listContainer.innerHTML = html;
            totalElement.textContent = `Q${total.toFixed(2)}`;
        }

        function removeTest(testId) {
            const card = document.querySelector(`.test-card[data-id="${testId}"]`);
            if (card) {
                const testData = selectedTests.find(t => t.id_prueba == testId);
                if (testData) {
                    toggleTest(card, testData);
                }
            }
        }

        // SweetAlert2 para confirmación
        const isEmbedded = document.getElementById('is_embedded').value === '1';

        document.getElementById('orderForm')?.addEventListener('submit', function (e) {
            e.preventDefault();

            const submitBtn = this.querySelector('button[type=submit]');

            if (selectedTests.length === 0) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Debe seleccionar al menos una prueba',
                    confirmButtonColor: '#0d6efd'
                });
                return;
            }

            const paciente = $('#id_paciente').val();
            if (!paciente) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Debe seleccionar un paciente',
                    confirmButtonColor: '#0d6efd'
                });
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-2"></i> Generando...';

            Swal.fire({
                title: '¿Confirmar Orden?',
                html: `
                <div class="text-start">
                    <p>Se crear una orden con <strong>${selectedTests.length} pruebas</strong></p>
                    <p class="mb-0">Total: <strong class="text-success">${document.getElementById('orderTotal').textContent}</strong></p>
                </div>
            `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, generar orden',
                confirmButtonColor: '#0d6efd',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Generando orden...',
                        text: 'Por favor espere',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    const formData = new FormData(this);
                    fetch('api/create_order.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Orden Generada!',
                                text: 'Número de orden: ' + data.order_number,
                                confirmButtonColor: '#0d6efd'
                            }).then(() => {
                                if (data.redirect) {
                                    window.location.href = data.redirect;
                                } else if (window.parent && window.parent.closeModal) {
                                    window.parent.closeModal('labOrderModal');
                                }
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Error al generar la orden',
                                confirmButtonColor: '#0d6efd'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error de conexión',
                            confirmButtonColor: '#0d6efd'
                        });
                    })
                    .finally(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-file-earmark-check"></i> Generar Orden de Laboratorio';
                    });
                } else {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-file-earmark-check"></i> Generar Orden de Laboratorio';
                }
            });
        });

        // Cargar SweetAlert2 dinámicamente si es necesario
        function loadSweetAlert() {
            return new Promise((resolve) => {
                if (typeof Swal !== 'undefined' && typeof Swal.fire === 'function') {
                    resolve();
                    return;
                }

                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                script.onload = resolve;
                document.head.appendChild(script);
            });
        }

        // Estilos adicionales
        const additionalStyles = document.createElement('style');
        additionalStyles.textContent = `
        .alert-error {
            background: var(--color-danger);
            color: white;
            padding: var(--space-md) var(--space-lg);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-md);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .swal2-popup {
            font-family: var(--font-family) !important;
        }
        
        .swal2-confirm {
            background-color: var(--color-primary) !important;
        }
    `;
        document.head.appendChild(additionalStyles);

        // Cargar SweetAlert2 al iniciar
        document.addEventListener('DOMContentLoaded', loadSweetAlert);
    </script>
</body>

</html>