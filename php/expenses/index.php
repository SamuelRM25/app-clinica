<?php
// expenses/index.php - Módulo de Gastos del Centro Médico Herrera Saenz
// Extraído del módulo de Compras (pestaña Gastos) manteniendo la funcionalidad original
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
require_once '../../includes/module_guard.php';
require_once '../../includes/breadcrumbs.php';

check_module_access('purchases');

// Establecer zona horaria
date_default_timezone_set('America/Guatemala');
verify_session();

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener información del usuario
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_name = $_SESSION['nombre'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Administrador';
    $id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);

    // Verificar permisos (solo admin puede acceder a gastos)
    if ($user_type !== 'admin') {
        header("Location: ../dashboard/index.php");
        exit;
    }

    // Título de la página
    $page_title = "Gastos - Centro Médico Herrera Saenz";

} catch (Exception $e) {
    // Manejo de errores
    error_log("Error en módulo de gastos: " . $e->getMessage());
    die("Error al cargar el módulo de gastos. Por favor, contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description"
        content="Módulo de Gastos - Centro Médico Herrera Saenz - Gestión de gastos generales del hospital">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

    <!-- logo -->
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Bootstrap CSS (Required for Modals) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Seguridad y Protección de Código -->
    <script>window.ES_CREADOR = <?php echo isset($_SESSION['es_creador']) && $_SESSION['es_creador'] ? 'true' : 'false'; ?>;</script>
    <script src="../../assets/js/security.js"></script>

    <!-- CSS Crítico (incrustado para máxima velocidad) -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <link rel="stylesheet" href="../../assets/css/processing-overlay.css">

    <!-- Estilos para spinner -->
    <style>
        .spin {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>

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

                    <!-- Back Button -->
                    <a href="../dashboard/index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i>
                        Dashboard
                    </a>

                    <!-- Botón de cerrar sesión -->
                    <a href="../auth/logout.php" class="logout-btn">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Salir</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido Principal -->
        <main class="main-content">
            <?php render_breadcrumbs([
                ['label' => 'Dashboard', 'url' => '../dashboard/index.php'],
                ['label' => 'Gastos'],
            ]); ?>

            <!-- Sección: Gastos -->
            <section class="appointments-section animate-in">
                <div class="section-header">
                    <h3 class="section-title">
                        <i class="bi bi-wallet2 section-title-icon" style="color:var(--color-danger);"></i>
                        Gastos Generales del Hospital
                    </h3>
                    <div class="d-flex gap-2 flex-wrap">
                        <div class="search-box">
                            <i class="bi bi-calendar-range search-icon"></i>
                            <input type="month" id="gastosMonth" class="form-control form-control-sm"
                                value="<?php echo date('Y-m'); ?>"
                                onchange="loadGastos()"
                                style="padding-left:2.25rem;max-width:200px;">
                        </div>
                        <button class="action-btn" onclick="showNewGastoModal()">
                            <i class="bi bi-plus-lg me-2"></i>Nuevo Gasto
                        </button>
                        <button class="action-btn action-btn-outline" onclick="showDeletedGastos()">
                            <i class="bi bi-archive me-2"></i>Ver Eliminados
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="appointments-table" id="gastosTable">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Descripción</th>
                                <th>Categoría</th>
                                <th>Cant.</th>
                                <th class="text-end">Subtotal</th>
                                <th class="text-end">Total</th>
                                <th>Registrado por</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr id="gastosLoadingRow">
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-arrow-clockwise spin me-2"></i>Cargando gastos...
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="5" class="text-end fw-bold">Total Gastos:</td>
                                <td class="fw-bold text-danger" id="gastosTotalFooter">Q0.00</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </main>
    </div>

    <!-- Modal para nuevo gasto -->
    <div class="custom-modal-overlay" id="newGastoModal">
        <div class="custom-modal" style="max-width:600px;">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title">
                    <i class="bi bi-wallet2 text-danger me-2"></i>
                    Registrar Nuevo Gasto
                </h5>
                <button type="button" class="custom-modal-close"
                    onclick="document.getElementById('newGastoModal').classList.remove('active')">&times;</button>
            </div>
            <div class="custom-modal-body">
                <form id="gastoForm">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" id="gasto_descripcion" rows="2"
                                placeholder="Ej. Insumos para baños, materiales de limpieza, etc." required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Categoría</label>
                            <select class="form-select" id="gasto_categoria" required onchange="onCategoriaChange()">
                                <option value="Gasto General">Gasto General</option>
                                <option value="Consulta Médica">Consulta Médica</option>
                                <option value="Pago Comisiones Médicos">Pago Comisiones Médicos</option>
                                <option value="Otra">Otra (especificar)</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="gasto_categoria_otra_wrap" style="display:none">
                            <label class="form-label">Especificar categoría</label>
                            <input type="text" class="form-control" id="gasto_categoria_otra" maxlength="100" placeholder="Ej: Servicios básicos">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="gasto_fecha"
                                value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cantidad</label>
                            <input type="number" class="form-control" id="gasto_cantidad" min="1" value="1"
                                onchange="calcularGastoTotal()" onkeyup="calcularGastoTotal()">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Subtotal (Q)</label>
                            <input type="number" class="form-control" id="gasto_subtotal" min="0" step="0.01"
                                onchange="calcularGastoTotal()" onkeyup="calcularGastoTotal()">
                        </div>
                        <div class="col-md-4 offset-md-8">
                            <label class="form-label fw-bold">Total (Q)</label>
                            <input type="number" class="form-control" id="gasto_total" min="0" step="0.01" readonly
                                style="font-weight:700;background:var(--color-surface);">
                        </div>
                    </div>
                </form>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="action-btn secondary"
                    onclick="document.getElementById('newGastoModal').classList.remove('active')">Cancelar</button>
                <button type="button" class="action-btn primary" id="saveGastoBtn" onclick="saveGasto()">
                    <i class="bi bi-check-lg me-2"></i>Guardar Gasto
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de gastos eliminados -->
    <div class="custom-modal-overlay" id="deletedGastosModal">
        <div class="custom-modal modal-lg">
            <div class="custom-modal-header">
                <h5 class="custom-modal-title">
                    <i class="bi bi-archive text-secondary me-2"></i>
                    Gastos Eliminados
                </h5>
                <button type="button" class="custom-modal-close"
                    onclick="this.closest('.custom-modal-overlay').classList.remove('active')">&times;</button>
            </div>
            <div class="custom-modal-body">
                <p class="text-muted mb-3">Estos gastos se eliminarán definitivamente al final del mes.</p>
                <div class="table-responsive">
                    <table class="appointments-table" id="deletedGastosTable">
                        <thead>
                            <tr>
                                <th>Fecha eliminación</th>
                                <th>Descripción</th>
                                <th>Categoría</th>
                                <th>Cant.</th>
                                <th class="text-end">Total</th>
                                <th>Eliminado por</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-arrow-clockwise spin me-2"></i>Cargando...
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="text-end fw-bold">Total Eliminados:</td>
                                <td class="fw-bold text-danger" id="deletedGastosTotalFooter">Q0.00</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="action-btn secondary"
                    onclick="document.getElementById('deletedGastosModal').classList.remove('active')">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // ==========================================================================
        // CONTROL DE TEMA
        // ==========================================================================
        document.addEventListener('DOMContentLoaded', () => {
            const themeSwitch = document.getElementById('themeSwitch');
            const html = document.documentElement;
            const savedTheme = localStorage.getItem('app-theme') || 'light';
            html.setAttribute('data-theme', savedTheme);
            if (themeSwitch) {
                themeSwitch.addEventListener('click', () => {
                    const current = html.getAttribute('data-theme');
                    const next = current === 'light' ? 'dark' : 'light';
                    html.setAttribute('data-theme', next);
                    localStorage.setItem('app-theme', next);
                    if (themeSwitch.style) {
                        themeSwitch.style.transform = 'rotate(180deg)';
                        setTimeout(() => { themeSwitch.style.transform = 'rotate(0)'; }, 200);
                    }
                });
            }
        });

        // ==========================================================================
        // FUNCIONES DE GASTOS
        // ==========================================================================

        window.loadGastos = function () {
            const monthEl = document.getElementById('gastosMonth');
            const month = monthEl ? monthEl.value : '<?php echo date('Y-m'); ?>';
            const start = month + '-01';
            const parts = month.split('-');
            const lastDay = new Date(parseInt(parts[0]), parseInt(parts[1]), 0).getDate();
            const end = month + '-' + String(lastDay).padStart(2, '0');

            const tbody = document.querySelector('#gastosTable tbody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-arrow-clockwise spin me-2"></i>Cargando gastos...</td></tr>';

            fetch('../purchases/get_gastos.php?fecha_inicio=' + encodeURIComponent(start) + '&fecha_fin=' + encodeURIComponent(end))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">' + (data.message || 'Error al cargar') + '</td></tr>';
                        return;
                    }
                    renderGastosTable(data.rows || []);
                })
                .catch(function(err) {
                    console.error('Error loading gastos:', err);
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-danger py-4">Error: ' + (err.message || 'Error de conexión') + '</td></tr>';
                });
        };

        window.escapeHtml = function (text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        };

        function renderGastosTable(rows) {
            const tbody = document.querySelector('#gastosTable tbody');
            const footer = document.getElementById('gastosTotalFooter');
            if (!tbody) return;

            if (rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>No hay gastos registrados en este mes</td></tr>';
                if (footer) footer.textContent = 'Q0.00';
                return;
            }

            let html = '';
            let totalGeneral = 0;
            rows.forEach(function(g) {
                totalGeneral += g.total;
                const categoriaLabel = g.categoria === 'Otra' && g.categoria_otra
                    ? 'Otra: ' + escapeHtml(g.categoria_otra)
                    : escapeHtml(g.categoria || 'Gasto General');
                html += '<tr>' +
                    '<td>' + g.fecha + '</td>' +
                    '<td>' + escapeHtml(g.descripcion) + '</td>' +
                    '<td><span class="badge bg-secondary">' + categoriaLabel + '</span></td>' +
                    '<td class="text-center">' + g.cantidad + '</td>' +
                    '<td class="text-end">Q' + Number(g.subtotal).toFixed(2) + '</td>' +
                    '<td class="text-end fw-bold text-danger">Q' + Number(g.total).toFixed(2) + '</td>' +
                    '<td>' + escapeHtml(g.registrado_por || '—') + '</td>' +
                    '<td class="text-center">' +
                    '<button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="deleteGasto(' + g.id + ')" title="Eliminar gasto">' +
                    '<i class="bi bi-trash"></i></button></td>' +
                    '</tr>';
            });
            tbody.innerHTML = html;
            if (footer) footer.textContent = 'Q' + totalGeneral.toFixed(2);
        }

        window.showNewGastoModal = function () {
            document.getElementById('gasto_descripcion').value = '';
            document.getElementById('gasto_categoria').value = 'Gasto General';
            document.getElementById('gasto_categoria_otra').value = '';
            document.getElementById('gasto_categoria_otra_wrap').style.display = 'none';
            document.getElementById('gasto_fecha').value = '<?php echo date('Y-m-d'); ?>';
            document.getElementById('gasto_cantidad').value = '1';
            document.getElementById('gasto_subtotal').value = '';
            document.getElementById('gasto_total').value = '0.00';
            document.getElementById('newGastoModal').classList.add('active');
            document.getElementById('gasto_descripcion').focus();
        };

        window.onCategoriaChange = function () {
            const sel = document.getElementById('gasto_categoria');
            const wrap = document.getElementById('gasto_categoria_otra_wrap');
            const otraInput = document.getElementById('gasto_categoria_otra');
            if (sel.value === 'Otra') {
                wrap.style.display = '';
                otraInput.required = true;
            } else {
                wrap.style.display = 'none';
                otraInput.required = false;
                otraInput.value = '';
            }
        };

        window.calcularGastoTotal = function () {
            const cant = parseFloat(document.getElementById('gasto_cantidad').value) || 0;
            const sub = parseFloat(document.getElementById('gasto_subtotal').value) || 0;
            document.getElementById('gasto_total').value = (cant * sub).toFixed(2);
        };

        window.saveGasto = function () {
            const descripcion = document.getElementById('gasto_descripcion').value.trim();
            const categoria = document.getElementById('gasto_categoria').value;
            const categoriaOtra = document.getElementById('gasto_categoria_otra').value.trim();
            const fecha = document.getElementById('gasto_fecha').value;
            const cantidad = parseInt(document.getElementById('gasto_cantidad').value) || 1;
            const subtotal = parseFloat(document.getElementById('gasto_subtotal').value) || 0;
            const total = parseFloat(document.getElementById('gasto_total').value) || (cantidad * subtotal);

            if (!descripcion) {
                Swal.fire({ title: 'Descripción requerida', text: 'Por favor ingrese una descripción del gasto', icon: 'warning', confirmButtonText: 'Entendido' });
                return;
            }
            if (subtotal <= 0) {
                Swal.fire({ title: 'Subtotal inválido', text: 'El subtotal debe ser mayor a 0', icon: 'warning', confirmButtonText: 'Entendido' });
                return;
            }
            if (categoria === 'Otra' && !categoriaOtra) {
                Swal.fire({ title: 'Categoría requerida', text: 'Debe especificar el nombre de la categoría personalizada', icon: 'warning', confirmButtonText: 'Entendido' });
                return;
            }

            const btn = document.getElementById('saveGastoBtn');
            if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-2"></i>Guardando...'; }

            const payload = {
                descripcion: descripcion,
                categoria: categoria,
                categoria_otra: categoria === 'Otra' ? categoriaOtra : '',
                cantidad: cantidad,
                subtotal: subtotal,
                total: total,
                fecha: fecha
            };

            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = csrfMeta ? csrfMeta.content : '';
            fetch('../purchases/save_gasto.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify(payload)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    document.getElementById('newGastoModal').classList.remove('active');
                    Swal.fire({ title: 'Gasto Registrado', text: 'El gasto se ha registrado correctamente', icon: 'success', timer: 1500, showConfirmButton: false });
                    loadGastos();
                } else {
                    Swal.fire({ title: 'Error', text: data.message || 'Error al guardar el gasto', icon: 'error', confirmButtonText: 'Entendido' });
                }
            })
            .catch(function(err) {
                console.error('Error:', err);
                Swal.fire({ title: 'Error de conexión', text: 'Ocurrió un error al procesar la solicitud', icon: 'error', confirmButtonText: 'Entendido' });
            })
            .finally(function() {
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-2"></i>Guardar Gasto'; }
            });
        };

        window.deleteGasto = function (id) {
            Swal.fire({
                title: 'Eliminar gasto',
                html: '<p class="text-muted mb-3">Esta acción moverá el gasto a la papelera. Los gastos eliminados se borran definitivamente al final de cada mes.</p>',
                input: 'textarea',
                inputLabel: 'Motivo de eliminación',
                inputPlaceholder: 'Describa por qué elimina este gasto...',
                inputAttributes: { required: true },
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: '<i class="bi bi-trash me-1"></i>Eliminar',
                cancelButtonText: 'Cancelar',
                preConfirm: function(motivo) {
                    if (!motivo || !motivo.trim()) {
                        Swal.showValidationMessage('Debe ingresar un motivo');
                        return false;
                    }
                    return motivo.trim();
                }
            }).then(function(result) {
                if (!result.isConfirmed || !result.value) return;
                const motivo = result.value;
                const csrfMeta2 = document.querySelector('meta[name="csrf-token"]');
                const csrfToken2 = csrfMeta2 ? csrfMeta2.content : '';
                fetch('../purchases/delete_gasto.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken2 },
                    body: JSON.stringify({ id: id, motivo: motivo })
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        Swal.fire({ title: 'Eliminado', text: 'El gasto se movió a la papelera', icon: 'success', timer: 1500, showConfirmButton: false });
                        loadGastos();
                    } else {
                        Swal.fire({ title: 'Error', text: data.message || 'Error al eliminar', icon: 'error', confirmButtonText: 'Entendido' });
                    }
                })
                .catch(function(err) {
                    console.error('Error:', err);
                    Swal.fire({ title: 'Error de conexión', icon: 'error', confirmButtonText: 'Entendido' });
                });
            });
        };

        // ==========================================================================
        // FUNCIONES DE GASTOS ELIMINADOS
        // ==========================================================================

        window.showDeletedGastos = function () {
            document.getElementById('deletedGastosModal').classList.add('active');
            loadDeletedGastos();
        };

        window.loadDeletedGastos = function () {
            const tbody = document.querySelector('#deletedGastosTable tbody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-arrow-clockwise spin me-2"></i>Cargando...</td></tr>';

            const now = new Date();
            const start = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-01';
            const end = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate()).padStart(2, '0');

            fetch('../purchases/get_gastos_eliminados.php?fecha_inicio=' + encodeURIComponent(start) + '&fecha_fin=' + encodeURIComponent(end))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">' + (data.message || 'Error al cargar') + '</td></tr>';
                        return;
                    }
                    renderDeletedGastosTable(data.rows || []);
                })
                .catch(function(err) {
                    console.error('Error loading deleted gastos:', err);
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-4">Error: ' + (err.message || 'Error de conexión') + '</td></tr>';
                });
        };

        function renderDeletedGastosTable(rows) {
            const tbody = document.querySelector('#deletedGastosTable tbody');
            const footer = document.getElementById('deletedGastosTotalFooter');
            if (!tbody) return;

            if (rows.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-check-circle me-2 text-success"></i>No hay gastos eliminados este mes</td></tr>';
                if (footer) footer.textContent = 'Q0.00';
                return;
            }

            let html = '';
            let totalGeneral = 0;
            rows.forEach(function(g) {
                totalGeneral += g.total;
                const categoriaLabel = g.categoria === 'Otra' && g.categoria_otra
                    ? 'Otra: ' + escapeHtml(g.categoria_otra)
                    : escapeHtml(g.categoria || 'Gasto General');
                html += '<tr>' +
                    '<td>' + g.fecha_eliminacion + '</td>' +
                    '<td>' + escapeHtml(g.descripcion) + '</td>' +
                    '<td><span class="badge bg-secondary">' + categoriaLabel + '</span></td>' +
                    '<td class="text-center">' + g.cantidad + '</td>' +
                    '<td class="text-end fw-bold text-danger">Q' + Number(g.total).toFixed(2) + '</td>' +
                    '<td>' + escapeHtml(g.eliminado_por_nombre || '—') + '</td>' +
                    '<td><span class="text-muted fst-italic small">' + escapeHtml(g.motivo_eliminacion) + '</span></td>' +
                    '</tr>';
            });
            tbody.innerHTML = html;
            if (footer) footer.textContent = 'Q' + totalGeneral.toFixed(2);
        }

        // ==========================================================================
        // INICIALIZACIÓN
        // ==========================================================================
        document.addEventListener('DOMContentLoaded', () => {
            loadGastos();
            console.log('Módulo de Gastos CMS v1.0 inicializado correctamente');
        });
    </script>
    <script src="../../assets/js/processing-overlay.js"></script>
</body>

</html>
