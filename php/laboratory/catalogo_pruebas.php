<?php
// laboratory/catalogo_pruebas.php - Management of Clinical Tests
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);

verify_session();

// Only admins can manage the catalog
if ($_SESSION['tipoUsuario'] !== 'admin') {
    header("Location: index.php");
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Fetch all tests with their parameter count
    $stmt = $conn->prepare("
        SELECT cp.*, COUNT(pp.id_parametro) as num_parametros
        FROM catalogo_pruebas cp
        LEFT JOIN parametros_pruebas pp ON cp.id_prueba = pp.id_prueba
        WHERE cp.id_hospital = ?
        GROUP BY cp.id_prueba
        ORDER BY cp.categoria, cp.nombre_prueba
    ");
    $stmt->execute([$id_hospital]);
    $catalogo = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by category for the UI
    $pruebas_por_categoria = [];
    foreach ($catalogo as $prueba) {
        $pruebas_por_categoria[$prueba['categoria'] ?? 'Sin Categoría'][] = $prueba;
    }

    // Estadísticas para la página
    $total_pruebas = count($catalogo);
    $total_categorias = count($pruebas_por_categoria);

    $page_title = "catálogo de Pruebas - Laboratorio";
} catch (Exception $e) {
    error_log('Error en laboratory/catalogo_pruebas.php: ' . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="catálogo de Pruebas de Laboratorio - Centro Médico Herrera Saenz">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- logo -->
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">

    <!-- Google Fonts - Inter -->
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- CSS Crítico (mismo que index.php) -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Bootstrap CSS/JS (Required for Modal) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
                            <span class="header-role">Administrador de Laboratorio</span>
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
            <!-- Banner de bienvenida -->
            <div class="welcome-banner animate-in">
                <h1>catálogo de Pruebas</h1>
                <p>Administre las pruebas disponibles en el laboratorio</p>
            </div>

            <!-- Estadísticas del catálogo -->
            <div class="stats-grid">
                <div class="stat-card animate-in delay-1">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Total de Pruebas</div>
                            <div class="stat-value"><?php echo $total_pruebas; ?></div>
                        </div>
                        <div class="stat-icon primary">
                            <i class="bi bi-clipboard-data"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="bi bi-box-seam"></i>
                        <span>Disponibles en sistema</span>
                    </div>
                </div>

                <div class="stat-card animate-in delay-2">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Categorías</div>
                            <div class="stat-value"><?php echo $total_categorias; ?></div>
                        </div>
                        <div class="stat-icon info">
                            <i class="bi bi-tags"></i>
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="bi bi-diagram-3"></i>
                        <span>Grupos organizados</span>
                    </div>
                </div>

                <div class="stat-card animate-in delay-3">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">Con Parámetros</div>
                            <div class="stat-value">
                                <?php
                                $con_parametros = array_filter($catalogo, function ($p) {
                                    return $p['num_parametros'] > 0;
                                });
                                echo count($con_parametros);
                                ?>
                            </div>
                        </div>
                        <div class="stat-icon success">
                            <i class="bi bi-list-check"></i>
                        </div>
                    </div>
                    <div class="stat-change positive">
                        <i class="bi bi-check-circle"></i>
                        <span>Configuradas completamente</span>
                    </div>
                </div>
            </div>

            <!-- Sección principal del catálogo -->
            <section class="catalog-section animate-in delay-2">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-clipboard-plus section-title-icon"></i>
                        Todas las Pruebas
                    </h3>
                    <button class="action-btn" onclick="openTestModal()">
                        <i class="bi bi-plus-lg"></i>
                        Agregar Nueva Prueba
                    </button>
                </div>

                <?php if (count($catalogo) > 0): ?>
                        <?php foreach ($pruebas_por_categoria as $categoria => $pruebas): ?>
                                <div class="category-header animate-in">
                                    <i class="bi bi-folder2-open"></i>
                                    <?php echo htmlspecialchars($categoria); ?>
                                    <span class="badge bg-primary ms-2"><?php echo count($pruebas); ?></span>
                                </div>

                                <div class="tests-grid">
                                    <?php foreach ($pruebas as $prueba): ?>
                                            <div class="test-card animate-in">
                                                <div class="test-header">
                                                    <div class="test-code"><?php echo htmlspecialchars($prueba['codigo_prueba']); ?></div>
                                                    <div class="test-name"><?php echo htmlspecialchars($prueba['nombre_prueba']); ?></div>
                                                </div>

                                                <div class="test-details">
                                                    <div class="test-detail">
                                                        <i class="bi bi-droplet"></i>
                                                        <span><?php echo htmlspecialchars($prueba['muestra_requerida'] ?: 'No especificada'); ?></span>
                                                    </div>
                                                    <div class="test-detail">
                                                        <i class="bi bi-clock"></i>
                                                        <span><?php echo $prueba['tiempo_procesamiento_horas']; ?> horas de procesamiento</span>
                                                    </div>
                                                    <div class="test-detail">
                                                        <i class="bi bi-info-circle"></i>
                                                        <span><?php echo htmlspecialchars($prueba['descripcion'] ?? 'Sin descripción'); ?></span>
                                                    </div>
                                                </div>

                                                <div class="test-footer">
                                                    <div>
                                                        <div class="test-price">Q<?php echo number_format($prueba['precio'] ?? 0, 2); ?></div>
                                                        <div class="test-params">
                                                            <?php echo $prueba['num_parametros']; ?> parámetros
                                                        </div>
                                                    </div>
                                                    <div class="test-actions">
                                                        <button class="btn-icon manage" title="Gestionar parámetros"
                                                            onclick="manageParameters(<?php echo $prueba['id_prueba']; ?>)">
                                                            <i class="bi bi-list-check"></i>
                                                        </button>
                                                        <button class="btn-icon edit" title="Editar prueba"
                                                            onclick="editTest(<?php echo htmlspecialchars(json_encode($prueba)); ?>)">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                    <?php endforeach; ?>
                                </div>
                        <?php endforeach; ?>
                <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="bi bi-clipboard-x"></i>
                            </div>
                            <h4 class="text-muted mb-2">No hay pruebas registradas</h4>
                            <p class="text-muted mb-3">Comience agregando la primera prueba al catálogo</p>
                            <button class="action-btn" onclick="openTestModal()">
                                <i class="bi bi-plus-lg"></i>
                                Agregar Primera Prueba
                            </button>
                        </div>
                <?php endif; ?>
            </section>
        </main>

        <!-- Modal para Nueva/Editar Prueba -->
        <div class="modal fade" id="testModal" tabindex="-1" aria-labelledby="testModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="testModalLabel">Nueva Prueba de Laboratorio</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="testForm">
                            <input type="hidden" id="id_prueba" name="id_prueba">

                            <div class="mb-3">
                                <label for="nombre_prueba" class="form-label">
                                    <i class="bi bi-clipboard-pulse"></i> Nombre de la Prueba *
                                </label>
                                <input type="text" class="form-control" id="nombre_prueba" name="nombre" required
                                    placeholder="Ej: Hemograma Completo">
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="codigo_prueba" class="form-label">
                                        <i class="bi bi-upc-scan"></i> Código *
                                    </label>
                                    <input type="text" class="form-control" id="codigo_prueba" name="codigo" required
                                        placeholder="HEM-01">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="categoria" class="form-label">
                                        <i class="bi bi-folder"></i> Categoría
                                    </label>
                                    <input type="text" class="form-control" id="categoria" name="categoria"
                                        placeholder="Hematología">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="descripcion" class="form-label">
                                    <i class="bi bi-text-paragraph"></i> Descripción
                                </label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="2"
                                    placeholder="Descripción de la prueba..."></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="precio" class="form-label">
                                        <i class="bi bi-currency-dollar"></i> Precio (Q)
                                    </label>
                                    <input type="number" step="0.01" class="form-control" id="precio" name="precio"
                                        placeholder="0.00" value="0">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="muestra_requerida" class="form-label">
                                        <i class="bi bi-droplet"></i> Muestra Requerida
                                    </label>
                                    <input type="text" class="form-control" id="muestra_requerida"
                                        name="muestra_requerida" placeholder="Sangre, Orina, etc.">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="tiempo_procesamiento_horas" class="form-label">
                                        <i class="bi bi-clock"></i> Tiempo (Hrs)
                                    </label>
                                    <input type="number" class="form-control" id="tiempo_procesamiento_horas"
                                        name="tiempo_procesamiento_horas" placeholder="24" value="24">
                                </div>
                            </div>

                            <div class="alert alert-info d-flex align-items-center" role="alert">
                                <i class="bi bi-info-circle me-2"></i>
                                <div>Los campos marcados con * son obligatorios</div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-primary" onclick="saveTest()">
                            <i class="bi bi-check-circle"></i> Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Optimizado (mismo que index.php) -->
    <script>
        // Dashboard de Laboratorio Reingenierizado

        (function () {
            'use strict';

            // ==========================================================================
            // CONFIGURACIÓN Y CONSTANTES
            // ==========================================================================
            const CONFIG = {
                themeKey: 'dashboard-theme',

                transitionDuration: 300,
                animationDelay: 100
            };

            // ==========================================================================
            // REFERENCIAS A ELEMENTOS DOM
            // ==========================================================================
            const DOM = {
                html: document.documentElement,
                body: document.body,
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
            // ==========================================================================
            class DynamicComponents {
                constructor() {
                    this.setupAnimations();
                    this.setupCardInteractions();
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

                    document.querySelectorAll('.test-card, .stat-card, .catalog-section').forEach(el => {
                        observer.observe(el);
                    });
                }

                setupCardInteractions() {
                    // Agregar efecto hover a las tarjetas de prueba
                    const testCards = document.querySelectorAll('.test-card');
                    testCards.forEach(card => {
                        card.addEventListener('mouseenter', () => {
                            card.style.transform = 'translateY(-4px)';
                        });
                        card.addEventListener('mouseleave', () => {
                            card.style.transform = 'translateY(0)';
                        });
                    });
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                const themeManager = new ThemeManager();
                const dynamicComponents = new DynamicComponents();

                window.catalogDashboard = {
                    theme: themeManager,
                    components: dynamicComponents
                };

                console.log('catálogo de Pruebas inicializado');
            });

            // ==========================================================================
            // POLYFILLS PARA NAVEGADORES ANTIGUOS
            // ==========================================================================
            if (!NodeList.prototype.forEach) {
                NodeList.prototype.forEach = Array.prototype.forEach;
            }

        })();

        // ==========================================================================
        // FUNCIONES ESPECÍFICAS DEL catálogo
        // ==========================================================================

        function openTestModal(data = null) {
            const modal = new bootstrap.Modal(document.getElementById('testModal'));
            const form = document.getElementById('testForm');
            const modalTitle = document.getElementById('testModalLabel');

            // Reset form
            form.reset();

            if (data) {
                // Edit mode
                modalTitle.textContent = 'Editar Prueba';
                document.getElementById('id_prueba').value = data.id_prueba || '';
                document.getElementById('nombre_prueba').value = data.nombre_prueba || '';
                document.getElementById('codigo_prueba').value = data.codigo_prueba || '';
                document.getElementById('categoria').value = data.categoria || '';
                document.getElementById('descripcion').value = data.notas || '';
                document.getElementById('precio').value = data.precio || '0';
                document.getElementById('muestra_requerida').value = data.muestra_requerida || '';
                document.getElementById('tiempo_procesamiento_horas').value = data.tiempo_procesamiento_horas || '24';
            } else {
                // Create mode
                modalTitle.textContent = 'Nueva Prueba de Laboratorio';
            }

            modal.show();
        }

        function saveTest() {
            const form = document.getElementById('testForm');

            // Validate required fields
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);

            console.log('Saving test...');
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }

            fetch('api/save_test.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal
                        const modal = bootstrap.Modal.getInstance(document.getElementById('testModal'));
                        modal.hide();

                        // Show success message
                        alert('✓ ' + data.message);

                        // Reload page
                        location.reload();
                    } else {
                        alert('✗ Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('✗ Error de conexión: ' + error.message);
                });
        }

        function editTest(data) {
            openTestModal(data);
        }

        function manageParameters(id) {
            window.location.href = `parametros_prueba.php?id=${id}`;
        }

        function loadSweetAlert() {
            return new Promise((resolve) => {
                if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
                    resolve();
                    return;
                }

                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
                script.onload = resolve;
                document.head.appendChild(script);
            });
        }

        // Efectos de carga para formularios
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function (e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Procesando...';
                    submitBtn.disabled = true;

                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 3000);
                }
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
        
        
        /* Efectos adicionales para catálogo */
        .test-card {
            cursor: pointer;
        }
        
        .test-card:hover {
            border-color: var(--color-primary);
        }
        
        /* ==========================================================================
           ESTILOS PERSONALIZADOS PARA MODALES SWEETALERT2
           ========================================================================== */
        
        /* Contenedor del modal */
        .custom-modal-popup {
            font-family: var(--font-family) !important;
            border-radius: var(--radius-lg) !important;
            padding: 0 !important;
        }
        
        .custom-modal-title {
            font-size: var(--font-size-2xl) !important;
            font-weight: 700 !important;
            color: var(--color-text) !important;
            padding: var(--space-xl) var(--space-xl) var(--space-md) !important;
            border-bottom: 2px solid var(--color-border) !important;
            margin: 0 !important;
        }
        
        .custom-modal-content {
            padding: var(--space-lg) var(--space-xl) !important;
        }
        
        /* Formulario del modal */
        .modal-test-form {
            text-align: left;
        }
        
        .form-section {
            margin-bottom: var(--space-lg);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: var(--space-md);
        }
        
        .form-group,
        .form-group-full {
            display: flex;
            flex-direction: column;
        }
        
        .modal-label {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            font-weight: 600;
            font-size: var(--font-size-sm);
            color: var(--color-text);
            margin-bottom: var(--space-xs);
        }
        
        .modal-label i {
            color: var(--color-primary);
            font-size: var(--font-size-base);
        }
        
        .modal-input,
        .modal-textarea {
            width: 100%;
            padding: var(--space-sm) var(--space-md);
            border: 2px solid var(--color-border);
            border-radius: var(--radius-md);
            background: var(--color-surface);
            color: var(--color-text);
            font-family: var(--font-family);
            font-size: var(--font-size-base);
            transition: all var(--transition-base);
        }
        
        .modal-input:focus,
        .modal-textarea:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.1);
            background: var(--color-card);
        }
        
        .modal-input::placeholder,
        .modal-textarea::placeholder {
            color: var(--color-text-secondary);
            opacity: 0.6;
        }
        
        .modal-textarea {
            resize: vertical;
            min-height: 60px;
        }
        
        /* Nota informativa */
        .form-note {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space-md);
            background: rgba(var(--color-info-rgb), 0.1);
            border-left: 3px solid var(--color-info);
            border-radius: var(--radius-sm);
            font-size: var(--font-size-sm);
            color: var(--color-text-secondary);
            margin-top: var(--space-md);
        }
        
        .form-note i {
            color: var(--color-info);
            font-size: var(--font-size-lg);
        }
        
        /* Botones del modal */
        .custom-modal-confirm,
        .custom-modal-cancel {
            padding: var(--space-sm) var(--space-xl) !important;
            border-radius: var(--radius-md) !important;
            font-weight: 600 !important;
            font-size: var(--font-size-base) !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: var(--space-xs) !important;
            transition: all var(--transition-base) !important;
            border: none !important;
        }
        
        .custom-modal-confirm {
            background: var(--color-primary) !important;
            color: white !important;
        }
        
        .custom-modal-confirm:hover {
            background: var(--color-primary) !important;
            opacity: 0.9 !important;
            transform: translateY(-2px) !important;
            box-shadow: var(--shadow-md) !important;
        }
        
        .custom-modal-cancel {
            background: var(--color-secondary) !important;
            color: white !important;
        }
        
        .custom-modal-cancel:hover {
            background: var(--color-secondary) !important;
            opacity: 0.9 !important;
        }
        
        /* Mensaje de validación */
        .swal2-validation-message {
            background: rgba(var(--color-danger-rgb), 0.1) !important;
            border-left: 3px solid var(--color-danger) !important;
            color: var(--color-danger) !important;
            font-weight: 500 !important;
            display: flex !important;
            align-items: center !important;
            gap: var(--space-sm) !important;
        }
        
        /* Responsive para modales */
        @media (max-width: 767px) {
            .custom-modal-popup {
                width: 95% !important;
                margin: var(--space-md) !important;
            }
            
            .custom-modal-title {
                font-size: var(--font-size-xl) !important;
                padding: var(--space-lg) var(--space-md) var(--space-sm) !important;
            }
            
            .custom-modal-content {
                padding: var(--space-md) !important;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .custom-modal-confirm,
            .custom-modal-cancel {
                width: 100% !important;
                justify-content: center !important;
            }
        }
        
        /* Tema oscuro para modales */
        [data-theme="dark"] .swal2-popup {
            background: var(--color-card) !important;
            color: var(--color-text) !important;
        }
        
        [data-theme="dark"] .modal-input,
        [data-theme="dark"] .modal-textarea {
            background: var(--color-surface) !important;
            color: var(--color-text) !important;
            border-color: var(--color-border) !important;
        }
        
        [data-theme="dark"] .modal-input:focus,
        [data-theme="dark"] .modal-textarea:focus {
            background: var(--color-bg) !important;
        }
    `;
        document.head.appendChild(style);
    </script>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>