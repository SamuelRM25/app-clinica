<?php
// laboratory/parametros_prueba.php - Configure parameters for a specific test
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

verify_session();

if ($_SESSION['tipoUsuario'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$id_prueba = $_GET['id'] ?? null;
if (!$id_prueba) {
    header("Location: catalogo_pruebas.php");
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get test details
    $stmt = $conn->prepare("SELECT * FROM catalogo_pruebas WHERE id_prueba = ? AND id_hospital = ?");
    $stmt->execute([$id_prueba, $id_hospital]);
    $prueba = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prueba) {
        header("Location: catalogo_pruebas.php");
        exit;
    }

    // Get current parameters
    $stmt = $conn->prepare("SELECT * FROM parametros_pruebas WHERE id_prueba = ? AND id_hospital = ? ORDER BY orden_visualizacion, id_parametro");
    $stmt->execute([$id_prueba, $id_hospital]);
    $parametros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count parameters
    $total_parametros = count($parametros);
    $con_valores = array_filter($parametros, function ($p) {
        return !empty($p['valor_ref_hombre_min']) || !empty($p['valor_ref_mujer_min']) || !empty($p['valor_ref_pediatrico_min']);
    });

    $page_title = "Parámetros: " . $prueba['nombre_prueba'];
} catch (Exception $e) {
    error_log('Error en laboratory/parametros_prueba.php: ' . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Configuración de Parámetros de Prueba - Laboratorio">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter -->
<!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- SortableJS para arrastrar y soltar -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

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
                <!-- Logo -->
                <div class="brand-container">
                    <img src="../../assets/img/Logo.png" alt="Centro Médico RS" class="brand-logo" width="40" height="40">
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
                    <a href="catalogo_pruebas.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i>
                        Volver al Catálogo
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido Principal -->
        <main class="main-content">
            <!-- Información de la prueba -->
            <div class="test-info-banner animate-in">
                <div class="test-header">
                    <div>
                        <h1 class="test-title">
                            <i class="bi bi-list-check"></i>
                            <?php echo htmlspecialchars($prueba['nombre_prueba']); ?>
                        </h1>
                        <div class="test-code"><?php echo htmlspecialchars($prueba['codigo_prueba']); ?></div>
                        <p class="text-muted mt-2">
                            <?php echo htmlspecialchars($prueba['descripcion'] ?? 'Sin descripción'); ?>
                        </p>
                    </div>
                    <div class="test-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $total_parametros; ?></div>
                            <div class="stat-label">Parámetros</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo count($con_valores); ?></div>
                            <div class="stat-label">Con Valores</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">
                                <?php echo htmlspecialchars($prueba['muestra_requerida'] ?: 'N/A'); ?>
                            </div>
                            <div class="stat-label">Muestra</div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            Arrastra y suelta para reordenar los parámetros
                        </small>
                    </div>
                    <div>
                        <small class="text-success">
                            <i class="bi bi-clock"></i>
                            Tiempo estimado: <?php echo $prueba['tiempo_procesamiento_horas']; ?> horas
                        </small>
                    </div>
                </div>
            </div>

            <!-- Formulario de parámetros -->
            <div class="params-form-container animate-in delay-1">
                <div class="section-header mb-4">
                    <h3 class="section-title">
                        <i class="bi bi-sliders section-title-icon"></i>
                        Configuración de Parámetros
                    </h3>
                    <button type="button" class="action-btn" onclick="addParamRow()">
                        <i class="bi bi-plus-lg"></i>
                        Agregar Parámetro
                    </button>
                </div>

                <form id="paramsForm" action="api/save_parameters.php" method="POST">
                    <input type="hidden" name="id_prueba" value="<?php echo $id_prueba; ?>">
                    <input type="hidden" name="param_order" id="paramOrder" value="">

                    <div id="paramsContainer" class="mb-4">
                        <?php if (count($parametros) > 0): ?>
                                <?php foreach ($parametros as $idx => $param): ?>
                                        <div class="param-row animate-in delay-<?php echo min($idx + 1, 4); ?>"
                                            data-id="<?php echo $param['id_parametro']; ?>">
                                            <div class="d-flex align-items-start gap-3">
                                                <div class="drag-handle flex-shrink-0" title="Arrastrar para reordenar">
                                                    <i class="bi bi-grip-vertical"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div class="row">
                                                        <div class="col-md-5 mb-3">
                                                            <label class="form-label">Nombre del Parámetro *</label>
                                                            <input type="text" name="params[<?php echo $idx; ?>][nombre]"
                                                                class="form-control"
                                                                value="<?php echo htmlspecialchars($param['nombre_parametro']); ?>"
                                                                required placeholder="Ej: Glucosa, Hemoglobina, etc.">
                                                        </div>
                                                        <div class="col-md-3 mb-3">
                                                            <label class="form-label">Unidad de Medida</label>
                                                            <input type="text" name="params[<?php echo $idx; ?>][unidad]"
                                                                class="form-control"
                                                                value="<?php echo htmlspecialchars($param['unidad_medida']); ?>"
                                                                placeholder="mg/dL, %, g/dL...">
                                                        </div>
                                                        <div class="col-md-3 mb-3">
                                                            <label class="form-label">Tipo de Dato</label>
                                                            <select name="params[<?php echo $idx; ?>][tipo]" class="form-select">
                                                                <option value="Numérico" <?php echo $param['tipo_dato'] === 'Numérico' ? 'selected' : ''; ?>>Numérico</option>
                                                                <option value="Texto" <?php echo $param['tipo_dato'] === 'Texto' ? 'selected' : ''; ?>>Texto</option>
                                                                <option value="Selección" <?php echo $param['tipo_dato'] === 'Selección' ? 'selected' : ''; ?>>Selección</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-1 mb-3 d-flex align-items-end">
                                                            <button type="button" class="btn-icon remove" onclick="removeParam(this)"
                                                                title="Eliminar parámetro">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <input type="hidden" name="params[<?php echo $idx; ?>][id_parametro]"
                                                        value="<?php echo $param['id_parametro']; ?>">

                                                    <div class="ref-values-grid">
                                                        <div class="ref-group">
                                                            <div class="ref-title hombre">
                                                                <i class="bi bi-gender-male"></i>
                                                                Hombres
                                                            </div>
                                                            <div class="input-range">
                                                                <input type="number" step="0.0001"
                                                                    name="params[<?php echo $idx; ?>][h_min]"
                                                                    class="form-control form-control-sm"
                                                                    value="<?php echo $param['valor_ref_hombre_min']; ?>"
                                                                    placeholder="Mínimo">
                                                                <span class="range-separator">-</span>
                                                                <input type="number" step="0.0001"
                                                                    name="params[<?php echo $idx; ?>][h_max]"
                                                                    class="form-control form-control-sm"
                                                                    value="<?php echo $param['valor_ref_hombre_max']; ?>"
                                                                    placeholder="Máximo">
                                                            </div>
                                                        </div>
                                                        <div class="ref-group">
                                                            <div class="ref-title mujer">
                                                                <i class="bi bi-gender-female"></i>
                                                                Mujeres
                                                            </div>
                                                            <div class="input-range">
                                                                <input type="number" step="0.0001"
                                                                    name="params[<?php echo $idx; ?>][m_min]"
                                                                    class="form-control form-control-sm"
                                                                    value="<?php echo $param['valor_ref_mujer_min']; ?>"
                                                                    placeholder="Mínimo">
                                                                <span class="range-separator">-</span>
                                                                <input type="number" step="0.0001"
                                                                    name="params[<?php echo $idx; ?>][m_max]"
                                                                    class="form-control form-control-sm"
                                                                    value="<?php echo $param['valor_ref_mujer_max']; ?>"
                                                                    placeholder="Máximo">
                                                            </div>
                                                        </div>
                                                        <div class="ref-group">
                                                            <div class="ref-title pediatria">
                                                                <i class="bi bi-emoji-smile"></i>
                                                                Pediatría
                                                            </div>
                                                            <div class="input-range">
                                                                <input type="number" step="0.0001"
                                                                    name="params[<?php echo $idx; ?>][p_min]"
                                                                    class="form-control form-control-sm"
                                                                    value="<?php echo $param['valor_ref_pediatrico_min']; ?>"
                                                                    placeholder="Mínimo">
                                                                <span class="range-separator">-</span>
                                                                <input type="number" step="0.0001"
                                                                    name="params[<?php echo $idx; ?>][p_max]"
                                                                    class="form-control form-control-sm"
                                                                    value="<?php echo $param['valor_ref_pediatrico_max']; ?>"
                                                                    placeholder="Máximo">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                <?php endforeach; ?>
                        <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="bi bi-list-check"></i>
                                    </div>
                                    <h4 class="text-muted mb-2">No hay parámetros configurados</h4>
                                    <p class="text-muted mb-3">Comience agregando el primer parámetro para esta prueba</p>
                                    <button type="button" class="action-btn" onclick="addParamRow()">
                                        <i class="bi bi-plus-lg"></i>
                                        Agregar Primer Parámetro
                                    </button>
                                </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-4 border-top">
                        <div>
                            <small class="text-muted">
                                <i class="bi bi-lightbulb"></i>
                                Los parámetros se mostrarán en el orden que los organice aquí
                            </small>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="action-btn secondary" onclick="window.history.back()">
                                <i class="bi bi-x-circle"></i>
                                Cancelar
                            </button>
                            <button type="submit" class="action-btn">
                                <i class="bi bi-save"></i>
                                Guardar Cambios
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <!-- JavaScript Optimizado -->
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
                themeSwitch: document.getElementById('themeSwitch'),
                paramsContainer: document.getElementById('paramsContainer'),
                paramsForm: document.getElementById('paramsForm'),
                paramOrder: document.getElementById('paramOrder')
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
            // GESTIÓN DE PARÁMETROS
            // ==========================================================================
            class ParametersManager {
                constructor() {
                    this.paramCounter = <?php echo count($parametros); ?>;
                    this.sortable = null;
                    this.initSortable();
                    this.setupFormSubmission();
                    this.initGlobalFunctions();
                }

                initSortable() {
                    if (DOM.paramsContainer && Sortable) {
                        this.sortable = Sortable.create(DOM.paramsContainer, {
                            animation: 150,
                            handle: '.drag-handle',
                            ghostClass: 'dragging',
                            onEnd: (evt) => {
                                this.updateParamOrder();
                            }
                        });
                    }
                }

                // Exponer funciones globales
                initGlobalFunctions() {
                    window.parametersManager = this;
                    window.addParamRow = () => this.addParamRow();
                    window.removeParam = (btn) => {
                        // Lógica ya manejada en HTML
                    };
                }

                updateParamOrder() {
                    if (!DOM.paramOrder) return;

                    const order = [];
                    const paramRows = DOM.paramsContainer.querySelectorAll('.param-row');

                    paramRows.forEach((row, index) => {
                        const paramId = row.getAttribute('data-id');
                        if (paramId) {
                            order.push(paramId);
                        }
                    });

                    DOM.paramOrder.value = order.join(',');
                }

                addParamRow() {
                    if (!DOM.paramsContainer) return;

                    // Remover estado vacío si existe
                    const emptyState = DOM.paramsContainer.querySelector('.empty-state');
                    if (emptyState) {
                        emptyState.remove();
                    }

                    const row = document.createElement('div');
                    row.className = 'param-row animate-in';
                    row.setAttribute('data-id', 'new_' + Date.now());

                    const currentIndex = this.paramCounter;
                    row.innerHTML = `
                    <div class="d-flex align-items-start gap-3">
                        <div class="drag-handle flex-shrink-0" title="Arrastrar para reordenar">
                            <i class="bi bi-grip-vertical"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="row">
                                <div class="col-md-5 mb-3">
                                    <label class="form-label">Nombre del Parámetro *</label>
                                    <input type="text" name="params[${currentIndex}][nombre]" 
                                           class="form-control" 
                                           required
                                           placeholder="Ej: Glucosa, Hemoglobina, etc.">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Unidad de Medida</label>
                                    <input type="text" name="params[${currentIndex}][unidad]" 
                                           class="form-control" 
                                           placeholder="mg/dL, %, g/dL...">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Tipo de Dato</label>
                                    <select name="params[${currentIndex}][tipo]" class="form-select">
                                        <option value="Numérico">Numérico</option>
                                        <option value="Texto">Texto</option>
                                        <option value="Selección">Selección</option>
                                    </select>
                                </div>
                                <div class="col-md-1 mb-3 d-flex align-items-end">
                                    <button type="button" class="btn-icon remove" 
                                            onclick="parametersManager.removeParam(this)" 
                                            title="Eliminar parámetro">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <input type="hidden" name="params[${currentIndex}][id_parametro]" value="">
                            
                            <div class="ref-values-grid">
                                <div class="ref-group">
                                    <div class="ref-title hombre">
                                        <i class="bi bi-gender-male"></i>
                                        Hombres
                                    </div>
                                    <div class="input-range">
                                        <input type="number" step="0.0001" name="params[${currentIndex}][h_min]" 
                                               class="form-control form-control-sm" 
                                               placeholder="Mínimo">
                                        <span class="range-separator">-</span>
                                        <input type="number" step="0.0001" name="params[${currentIndex}][h_max]" 
                                               class="form-control form-control-sm" 
                                               placeholder="Máximo">
                                    </div>
                                </div>
                                <div class="ref-group">
                                    <div class="ref-title mujer">
                                        <i class="bi bi-gender-female"></i>
                                        Mujeres
                                    </div>
                                    <div class="input-range">
                                        <input type="number" step="0.0001" name="params[${currentIndex}][m_min]" 
                                               class="form-control form-control-sm" 
                                               placeholder="Mínimo">
                                        <span class="range-separator">-</span>
                                        <input type="number" step="0.0001" name="params[${currentIndex}][m_max]" 
                                               class="form-control form-control-sm" 
                                               placeholder="Máximo">
                                    </div>
                                </div>
                                <div class="ref-group">
                                    <div class="ref-title pediatria">
                                        <i class="bi bi-emoji-smile"></i>
                                        Pediatría
                                    </div>
                                    <div class="input-range">
                                        <input type="number" step="0.0001" name="params[${currentIndex}][p_min]" 
                                               class="form-control form-control-sm" 
                                               placeholder="Mínimo">
                                        <span class="range-separator">-</span>
                                        <input type="number" step="0.0001" name="params[${currentIndex}][p_max]" 
                                               class="form-control form-control-sm" 
                                               placeholder="Máximo">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                    DOM.paramsContainer.appendChild(row);
                    this.paramCounter++;

                    // Aplicar animación
                    setTimeout(() => {
                        row.style.animation = 'fadeInUp 0.6s ease-out forwards';
                    }, 10);

                    // Actualizar orden
                    this.updateParamOrder();
                }

                removeParam(button) {
                    const row = button.closest('.param-row');
                    if (!row) return;

                    // Mostrar confirmación solo para parámetros existentes (no nuevos)
                    const paramId = row.getAttribute('data-id');
                    const isNew = paramId.startsWith('new_');

                    if (!isNew) {
                        this.showDeleteConfirmation(row);
                    } else {
                        row.remove();
                        this.checkEmptyState();
                        this.updateParamOrder();
                    }
                }

                showDeleteConfirmation(row) {
                    loadSweetAlert().then(() => {
                        Swal.fire({
                            title: '¿Eliminar parámetro?',
                            text: 'Esta acción no se puede deshacer',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Sí, eliminar',
                            confirmButtonColor: '#dc3545',
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                row.remove();
                                this.checkEmptyState();
                                this.updateParamOrder();

                                Swal.fire({
                                    icon: 'success',
                                    title: 'Eliminado',
                                    text: 'Parámetro eliminado correctamente',
                                    timer: 1500,
                                    showConfirmButton: false
                                });
                            }
                        });
                    });
                }

                // Exponer funciones globales para los botones onclick
                initGlobalFunctions() {
                    window.parametersManager = this;
                    window.addParamRow = () => this.addParamRow();
                    window.removeParam = (btn) => {
                        // Lógica para remover (implementada en el HTML como parametersManager.removeParam, pero por si acaso)
                    };
                }

                checkEmptyState() {
                    if (!DOM.paramsContainer) return;

                    const paramRows = DOM.paramsContainer.querySelectorAll('.param-row');
                    if (paramRows.length === 0) {
                        const emptyState = document.createElement('div');
                        emptyState.className = 'empty-state';
                        emptyState.innerHTML = `
                        <div class="empty-icon">
                            <i class="bi bi-list-check"></i>
                        </div>
                        <h4 class="text-muted mb-2">No hay parámetros configurados</h4>
                        <p class="text-muted mb-3">Comience agregando el primer parámetro para esta prueba</p>
                        <button type="button" class="action-btn" onclick="parametersManager.addParamRow()">
                            <i class="bi bi-plus-lg"></i>
                            Agregar Primer Parámetro
                        </button>
                    `;
                        DOM.paramsContainer.appendChild(emptyState);
                    }
                }

                setupFormSubmission() {
                    if (!DOM.paramsForm) return;

                    DOM.paramsForm.addEventListener('submit', (e) => {
                        e.preventDefault();
                        this.submitForm();
                    });
                }

                submitForm() {
                    // Actualizar orden antes de enviar
                    this.updateParamOrder();

                    // Validar que al menos haya un parámetro
                    const paramRows = DOM.paramsContainer.querySelectorAll('.param-row');
                    if (paramRows.length === 0) {
                        this.showError('Debe agregar al menos un parámetro');
                        return;
                    }

                    // Validar nombres de parámetros
                    const paramNames = new Set();
                    const inputs = DOM.paramsForm.querySelectorAll('input[name$="[nombre]"]');
                    let hasEmptyName = false;

                    inputs.forEach(input => {
                        const value = input.value.trim();
                        if (!value) {
                            hasEmptyName = true;
                            input.style.borderColor = 'var(--color-danger)';
                        } else {
                            input.style.borderColor = '';
                            if (paramNames.has(value.toLowerCase())) {
                                this.showError(`El nombre "${value}" está duplicado. Los nombres deben ser únicos.`);
                                input.style.borderColor = 'var(--color-danger)';
                                throw new Error('Duplicate name');
                            }
                            paramNames.add(value.toLowerCase());
                        }
                    });

                    if (hasEmptyName) {
                        this.showError('Todos los parámetros deben tener un nombre');
                        return;
                    }

                    // Mostrar carga
                    loadSweetAlert().then(() => {
                        Swal.fire({
                            title: 'Guardando...',
                            text: 'Por favor espere mientras se guardan los cambios',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        const formData = new FormData(DOM.paramsForm);

                        fetch(DOM.paramsForm.action, {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: '¡Guardado!',
                                        text: data.message,
                                        timer: 2000,
                                        showConfirmButton: false
                                    }).then(() => {
                                        window.location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: data.message || 'Error al guardar los parámetros'
                                    });
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error de conexión',
                                    text: 'No se pudo conectar con el servidor'
                                });
                            });
                    });
                }

                showError(message) {
                    loadSweetAlert().then(() => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de validación',
                            text: message,
                            confirmButtonColor: '#0d6efd'
                        });
                    });
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                const themeManager = new ThemeManager();
                const parametersManager = new ParametersManager();

                window.parametersManager = parametersManager;

                console.log('Gestión de Parámetros inicializada');
                console.log('Prueba: <?php echo htmlspecialchars($prueba["nombre_prueba"]); ?>');
                console.log('Parámetros: <?php echo $total_parametros; ?>');
            });

            // ==========================================================================
            // FUNCIONES AUXILIARES
            // ==========================================================================
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

            // ==========================================================================
            // POLYFILLS PARA NAVEGADORES ANTIGUOS
            // ==========================================================================
            if (!NodeList.prototype.forEach) {
                NodeList.prototype.forEach = Array.prototype.forEach;
            }

        })();

        // Estilos adicionales
        const additionalStyles = document.createElement('style');
        additionalStyles.textContent = `
        .swal2-popup {
            font-family: var(--font-family) !important;
        }
        
        .swal2-confirm {
            background-color: var(--color-primary) !important;
        }
        
        .swal2-cancel {
            background-color: var(--color-surface) !important;
            color: var(--color-text) !important;
            border: 1px solid var(--color-border) !important;
        }
        
        /* Efectos de arrastre */
        .sortable-ghost {
            opacity: 0.4;
            background: rgba(var(--color-primary-rgb), 0.1);
        }
        
        .sortable-chosen {
            background: rgba(var(--color-primary-rgb), 0.05);
            box-shadow: var(--shadow-md);
        }
        
        /* Animación para nuevos parámetros */
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(30px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .param-row:last-child {
            animation: slideInRight 0.4s ease-out;
        }
    `;
        document.head.appendChild(additionalStyles);
    </script>
</body>

</html>