<?php
/**
 * subscription.php — Estado de Suscripción y Solicitud de Módulos
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

require_once '../../includes/multitenant.php';

$db = new Database();
$conn = $db->getConnection();
$h = get_hospital_config($conn, $_SESSION['id_hospital'] ?? 1);

$page_title = "Suscripción";

// Módulos disponibles con metadata
$available_modules = [
    'core' => ['label' => 'Core / Consulta Externa', 'icon' => 'bi-house-heart', 'precio_mes' => 0, 'precio_anual' => 0, 'desc' => 'Pacientes, Citas, Historial clínico. Base obligatoria.'],
    'pharmacy' => ['label' => 'Farmacia / Punto de Venta', 'icon' => 'bi-capsule', 'precio_mes' => 199, 'precio_anual' => 1990, 'desc' => 'Despacho de medicamentos y punto de venta integrado.'],
    'hospitalization' => ['label' => 'Hospitalización', 'icon' => 'bi-hospital', 'precio_mes' => 299, 'precio_anual' => 2990, 'desc' => 'Gestión de camas, habitaciones, evoluciones y signos vitales.'],
    'laboratory' => ['label' => 'Laboratorio Clínico', 'icon' => 'bi-flask', 'precio_mes' => 249, 'precio_anual' => 2490, 'desc' => 'Órdenes, resultados, reactivos y control de calidad.'],
    'inventory' => ['label' => 'Inventario', 'icon' => 'bi-box-seam', 'precio_mes' => 149, 'precio_anual' => 1490, 'desc' => 'Control de stock de medicamentos e insumos.'],
    'imaging' => ['label' => 'Imagenología / Procedimientos', 'icon' => 'bi-activity', 'precio_mes' => 199, 'precio_anual' => 1990, 'desc' => 'Rayos X, Ultrasonidos, EKG y Procedimientos Menores.'],
    'purchases' => ['label' => 'Compras y Proveedores', 'icon' => 'bi-cart-plus', 'precio_mes' => 149, 'precio_anual' => 1490, 'desc' => 'Gestión de compras, pagos y cuentas por pagar.'],
    'sales' => ['label' => 'Ventas y Facturación', 'icon' => 'bi-receipt', 'precio_mes' => 149, 'precio_anual' => 1490, 'desc' => 'Registro de ventas con detalle por ítem.'],
    'finances' => ['label' => 'Cuentas Hospitalarias', 'icon' => 'bi-cash-stack', 'precio_mes' => 199, 'precio_anual' => 1990, 'desc' => 'Facturación compleja, abonos y saldos de hospitalización.'],
    'reports' => ['label' => 'Reportes Estadísticos', 'icon' => 'bi-graph-up', 'precio_mes' => 99, 'precio_anual' => 990, 'desc' => 'Reportes financieros, de pacientes y de desempeño.'],
];
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> — <?php echo htmlspecialchars($h['nombre'] ?? ''); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../../assets/css/global_dashb<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>

    <div class="dashboard-container">
        <!-- Header Superior -->
        <header class="dashboard-header">
            <div class="header-content">
                <!-- Logo -->
                <div class="brand-container">
                    <img src="../../assets/img/Logo.png" alt="Centro Médico RS" class="brand-logo">
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

                    <!-- Back Button -->
                    <a href="../dashboard/index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i>
                        Dashboard
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenido Principal -->
        <main class="main-content">
            <div class="container-fluid px-0">
                <!-- Encabezado -->
                <div class="stat-card mb-4 animate-in">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <h2 class="fw-bold mb-1"><i class="bi bi-credit-card-2-front me-2 text-primary"></i>Estado de Suscripción</h2>
                            <p class="text-muted mb-0"><?php echo htmlspecialchars($h['nombre'] ?? 'Hospital'); ?></p>
                        </div>
                        <?php
                        $chip_class = match ($h['estado_suscripcion'] ?? '') {
                            'Activo' => 'chip-activo',
                            'Vencido' => 'chip-vencido',
                            'Inactivo' => 'chip-inactivo',
                            default => 'chip-prueba'
                        };
                        ?>
                        <span class="status-chip <?php echo $chip_class; ?>" style="font-size:.95rem; padding:.5rem 1.2rem;">
                            <i class="bi bi-circle-fill" style="font-size:.5rem;"></i>
                            <?php echo strtoupper($h['estado_suscripcion'] ?? 'PRUEBA'); ?>
                        </span>
                    </div>
                </div>

                <div class="row g-4">
                    <!-- Info de suscripción -->
                    <div class="col-md-4 animate-in" style="animation-delay: 0.1s;">
                        <div class="card border-0 shadow-sm h-100" style="border-radius:1rem; background: var(--color-card); border: 1px solid var(--color-border) !important;">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-3" style="color: var(--color-text);">Detalles del Plan</h5>
                                <table class="table table-borderless table-sm text-start">
                                    <tr>
                                        <td class="text-muted">Tipo</td>
                                        <td class="fw-bold" style="color: var(--color-text);"><?php echo $h['tipo_suscripcion'] ?? '—'; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Estado</td>
                                        <td><span
                                                class="status-chip <?php echo $chip_class; ?>"><?php echo $h['estado_suscripcion'] ?? '—'; ?></span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Vencimiento</td>
                                        <td class="fw-bold text-primary">
                                            <?php if (($h['tipo_suscripcion'] ?? '') === 'De por vida'): ?>
                                                <span class="text-success">♾ De por vida</span>
                                            <?php else: ?>
                                                <?php echo $h['fecha_vencimiento'] ?? '—'; ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>

                                <hr style="border-color: var(--color-border);">
                                <h6 class="fw-bold mb-2" style="color: var(--color-text);">Módulos Activos</h6>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php foreach ($h['modulos_activos'] as $mod): ?>
                                        <span class="badge badge-success px-2 py-1"><?php echo ucfirst($mod); ?></span>
                                    <?php endforeach; ?>
                                </div>

                                <hr style="border-color: var(--color-border);">
                                <h6 class="fw-bold mb-2" style="color: var(--color-text);">Métodos de Pago</h6>
                                <ul class="list-unstyled small text-muted mb-0 text-start">
                                    <li class="mb-1"><i class="bi bi-bank me-1 text-primary"></i>Transferencia Bancaria (BI, Banrural, G&T)</li>
                                    <li class="mb-1"><i class="bi bi-credit-card me-1 text-primary"></i>Tarjeta de Crédito/Débito</li>
                                    <li><i class="bi bi-paypal me-1 text-primary"></i>PayPal / Enlace de Pago</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Selector de módulos -->
                    <div class="col-md-8 animate-in" style="animation-delay: 0.2s;">
                        <div class="card border-0 shadow-sm" style="border-radius:1rem; background: var(--color-card); border: 1px solid var(--color-border) !important;">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                    <h5 class="fw-bold mb-0" style="color: var(--color-text);">Solicitar / Renovar Módulos</h5>
                                    <div class="btn-group btn-group-sm" id="billingToggle" role="group">
                                        <input type="radio" class="btn-check" name="billing" id="bilMes" value="mes" checked>
                                        <label class="btn btn-outline-primary" for="bilMes">Mensual</label>
                                        <input type="radio" class="btn-check" name="billing" id="bilAnual" value="anual">
                                        <label class="btn btn-outline-primary" for="bilAnual">Anual <span
                                                class="badge badge-warning ms-1">-15%</span></label>
                                        <input type="radio" class="btn-check" name="billing" id="bilVida" value="vida">
                                        <label class="btn btn-outline-primary" for="bilVida">De por vida</label>
                                    </div>
                                </div>
                                <p class="text-muted small mb-3">Selecciona los módulos que necesitas. Los que ya tienes activos están marcados.</p>

                                <div class="row g-3" id="moduleGrid">
                                    <?php foreach ($available_modules as $key => $mod):
                                        $is_core = ($key === 'core');
                                        $is_active = in_array($key, $h['modulos_activos']);
                                        $class = $is_core ? 'core-module' : ($is_active ? 'active-now selected' : '');
                                        ?>
                                        <div class="col-sm-6">
                                            <div class="module-card <?php echo $class; ?>" data-module="<?php echo $key; ?>"
                                                data-price-mes="<?php echo $mod['precio_mes']; ?>"
                                                data-price-anual="<?php echo $mod['precio_anual']; ?>" onclick="toggleModule(this)"
                                                <?php if ($is_core)
                                                    echo 'onclick="return false;"'; ?>>
                                                <div class="module-check"></div>
                                                <div class="module-icon"><i class="bi <?php echo $mod['icon']; ?>"></i></div>
                                                <div class="module-name"><?php echo $mod['label']; ?></div>
                                                <div class="module-desc"><?php echo $mod['desc']; ?></div>
                                                <div class="module-price" id="price-<?php echo $key; ?>">
                                                    <?php if ($mod['precio_mes'] === 0): ?>
                                                        <span class="text-success">Incluido</span>
                                                    <?php else: ?>
                                                        Q<?php echo $mod['precio_mes']; ?>/mes
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="total-bar mt-4">
                                    <div>
                                        <div class="small text-muted">Total estimado</div>
                                        <div class="total-amount" id="totalPrice" style="color: var(--color-primary);">Q0/mes</div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-outline-secondary btn-sm"
                                            onclick="resetSelection()">Restablecer</button>
                                        <button class="btn btn-primary px-4" onclick="openRequestModal()">
                                            <i class="bi bi-send me-1"></i>Enviar Solicitud
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal confirmación de solicitud -->
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:1rem; border:0; background: var(--color-card); border: 1px solid var(--color-border) !important;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" style="color: var(--color-text);"><i class="bi bi-send-check me-2 text-primary"></i>Confirmar Solicitud</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="filter: var(--theme-close-btn-filter, none);"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Revisa los módulos seleccionados antes de enviar. El administrador recibirá tu solicitud y la aprobará a la brevedad.</p>
                    <div id="requestSummary" class="mb-3"></div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tipo de suscripción</label>
                        <input type="text" id="reqTipo" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Total estimado</label>
                        <input type="text" id="reqTotal" class="form-control fw-bold text-primary" readonly>
                    </div>
                    <div>
                        <label class="form-label small fw-semibold">Mensaje adicional (opcional)</label>
                        <textarea id="reqMensaje" class="form-control" rows="2"
                            placeholder="Cualquier consulta o aclaración..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button class="btn btn-light" data-bs-dismiss="modal" style="background: var(--color-surface); color: var(--color-text); border: 1px solid var(--color-border);">Cancelar</button>
                    <button class="btn btn-primary px-4" onclick="submitRequest()">
                        <i class="bi bi-check2-circle me-1"></i>Confirmar y Enviar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const modulePrices = <?php
        $prices = [];
        foreach ($available_modules as $k => $m) {
            $prices[$k] = ['mes' => $m['precio_mes'], 'anual' => $m['precio_anual']];
        }
        echo json_encode($prices);
        ?>;

        const activeModules = <?php echo json_encode($h['modulos_activos']); ?>;
        let currentBilling = 'mes';

        // Escuchar cambio de período de facturación
        document.querySelectorAll('input[name="billing"]').forEach(r => {
            r.addEventListener('change', () => {
                currentBilling = r.value;
                updateAllPrices();
                updateTotal();
            });
        });

        function toggleModule(card) {
            if (card.classList.contains('core-module')) return;
            card.classList.toggle('selected');
            updateTotal();
        }

        function updateAllPrices() {
            document.querySelectorAll('.module-card').forEach(card => {
                const key = card.dataset.module;
                const priceEl = document.getElementById('price-' + key);
                if (!priceEl) return;
                const p = modulePrices[key];
                if (p.mes === 0) { priceEl.innerHTML = '<span class="text-success">Incluido</span>'; return; }
                if (currentBilling === 'mes') priceEl.textContent = `Q${p.mes}/mes`;
                if (currentBilling === 'anual') priceEl.textContent = `Q${Math.round(p.anual)}/año`;
                if (currentBilling === 'vida') priceEl.textContent = `Q${Math.round(p.anual * 5)} única vez`;
            });
        }

        function updateTotal() {
            let total = 0;
            document.querySelectorAll('.module-card.selected').forEach(card => {
                const key = card.dataset.module;
                const p = modulePrices[key];
                if (currentBilling === 'mes') total += p.mes;
                if (currentBilling === 'anual') total += p.anual;
                if (currentBilling === 'vida') total += p.anual * 5;
            });
            const label = currentBilling === 'mes' ? '/mes' : currentBilling === 'anual' ? '/año' : ' única vez';
            document.getElementById('totalPrice').textContent = `Q${total}${label}`;
        }

        function resetSelection() {
            document.querySelectorAll('.module-card').forEach(card => {
                if (card.classList.contains('core-module')) return;
                card.classList.remove('selected');
                if (activeModules.includes(card.dataset.module)) {
                    card.classList.add('active-now', 'selected');
                }
            });
            updateTotal();
        }

        function openRequestModal() {
            const selected = getSelected();
            if (selected.length === 0) {
                Swal.fire('Atención', 'Selecciona al menos un módulo.', 'warning');
                return;
            }

            const tipoMap = { mes: 'Mensual', anual: 'Anual', vida: 'De por vida' };
            document.getElementById('reqTipo').value = tipoMap[currentBilling];
            document.getElementById('reqTotal').value = document.getElementById('totalPrice').textContent;

            const summary = selected.map(m => `<span class="badge text-bg-primary me-1 mb-1">${m}</span>`).join('');
            document.getElementById('requestSummary').innerHTML = `<div class="d-flex flex-wrap gap-1">${summary}</div>`;

            new bootstrap.Modal(document.getElementById('requestModal')).show();
        }

        function getSelected() {
            const arr = [];
            document.querySelectorAll('.module-card.selected').forEach(c => arr.push(c.dataset.module));
            return arr;
        }

        async function submitRequest() {
            const tipoMap = { mes: 'Mensual', anual: 'Anual', vida: 'De por vida' };
            const fd = new FormData();
            fd.append('modulos', JSON.stringify(getSelected()));
            fd.append('tipo_suscripcion', tipoMap[currentBilling]);
            fd.append('mensaje', document.getElementById('reqMensaje').value);

            try {
                const r = await fetch('api/request_modules.php', { method: 'POST', body: fd });
                const js = await r.json();
                bootstrap.Modal.getInstance(document.getElementById('requestModal')).hide();
                Swal.fire(js.status === 'success' ? '¡Solicitud Enviada!' : 'Error',
                    js.message,
                    js.status === 'success' ? 'success' : 'error');
            } catch (e) {
                Swal.fire('Error', 'No se pudo enviar la solicitud.', 'error');
            }
        }

        // Inicializar total
        updateTotal();
    </script>
</body>

</html>