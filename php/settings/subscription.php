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
    <style>
        :root {
            --primary: #0d6efd;
            --surface: #f8f9fa;
            --card: #ffffff;
            --border: #dee2e6;
            --text: #212529;
            --text-muted: #6c757d;
            --success: #198754;
            --warning: #ffc107;
            --danger: #dc3545;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--surface);
            color: var(--text);
        }

        .sub-header {
            background: linear-gradient(135deg, #0d6efd, #0099ff);
            color: white;
            padding: 2rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
        }

        .sub-header h2 {
            font-weight: 700;
            margin: 0;
        }

        .sub-header p {
            margin: 0;
            opacity: 0.85;
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .35rem .85rem;
            border-radius: 999px;
            font-weight: 600;
            font-size: .8rem;
        }

        .chip-activo {
            background: #d1fae5;
            color: #065f46;
        }

        .chip-inactivo {
            background: #fee2e2;
            color: #991b1b;
        }

        .chip-vencido {
            background: #fef3c7;
            color: #92400e;
        }

        .chip-prueba {
            background: #e0e7ff;
            color: #3730a3;
        }

        /* Tarjetas de módulos */
        .module-card {
            border: 2px solid var(--border);
            border-radius: .875rem;
            padding: 1.25rem;
            cursor: pointer;
            transition: all .2s;
            position: relative;
            background: var(--card);
            user-select: none;
        }

        .module-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 16px rgba(13, 110, 253, .12);
            transform: translateY(-2px);
        }

        .module-card.selected {
            border-color: var(--primary);
            background: #eff6ff;
        }

        .module-card.active-now {
            border-color: var(--success);
            background: #f0fdf4;
        }

        .module-card.core-module {
            opacity: .7;
            cursor: not-allowed;
            border-color: var(--success);
            background: #f0fdf4;
        }

        .module-icon {
            width: 44px;
            height: 44px;
            border-radius: .625rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            background: #e0e7ff;
            color: var(--primary);
            margin-bottom: .75rem;
        }

        .module-card.active-now .module-icon,
        .module-card.core-module .module-icon {
            background: #dcfce7;
            color: var(--success);
        }

        .module-card.selected .module-icon {
            background: var(--primary);
            color: white;
        }

        .module-name {
            font-weight: 600;
            font-size: .9rem;
            margin-bottom: .2rem;
        }

        .module-desc {
            font-size: .78rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .module-price {
            font-size: .8rem;
            font-weight: 700;
            color: var(--primary);
            margin-top: .5rem;
        }

        .module-check {
            position: absolute;
            top: .75rem;
            right: .75rem;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--border);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .module-card.selected .module-check,
        .module-card.active-now .module-check,
        .module-card.core-module .module-check {
            border-color: var(--success);
            background: var(--success);
        }

        .module-card.selected .module-check {
            border-color: var(--primary);
            background: var(--primary);
        }

        .module-check::after {
            content: '✓';
            color: white;
            font-size: .7rem;
            font-weight: 700;
            display: none;
        }

        .module-card.selected .module-check::after,
        .module-card.active-now .module-check::after,
        .module-card.core-module .module-check::after {
            display: block;
        }

        .total-bar {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: .75rem;
            padding: 1rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .total-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
    </style>
</head>

<body>
    <div class="container py-4" style="max-width: 960px;">

        <!-- Botón volver -->
        <a href="../dashboard/index.php" class="btn btn-outline-secondary btn-sm mb-3">
            <i class="bi bi-arrow-left me-1"></i> Volver al Panel
        </a>

        <!-- Encabezado -->
        <div class="sub-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h2><i class="bi bi-credit-card-2-front me-2"></i>Estado de Suscripción</h2>
                    <p><?php echo htmlspecialchars($h['nombre'] ?? 'Hospital'); ?></p>
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
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100" style="border-radius:1rem;">
                    <div class="card-body p-4">
                        <h5 class="fw-bold mb-3">Detalles del Plan</h5>
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="text-muted">Tipo</td>
                                <td class="fw-bold"><?php echo $h['tipo_suscripcion'] ?? '—'; ?></td>
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

                        <hr>
                        <h6 class="fw-bold mb-2">Módulos Activos</h6>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($h['modulos_activos'] as $mod): ?>
                                <span class="badge text-bg-success px-2 py-1"><?php echo ucfirst($mod); ?></span>
                            <?php endforeach; ?>
                        </div>

                        <hr>
                        <h6 class="fw-bold mb-2">Métodos de Pago</h6>
                        <ul class="list-unstyled small text-muted mb-0">
                            <li class="mb-1"><i class="bi bi-bank me-1"></i>Transferencia Bancaria (BI, Banrural, G&T)
                            </li>
                            <li class="mb-1"><i class="bi bi-credit-card me-1"></i>Tarjeta de Crédito/Débito</li>
                            <li><i class="bi bi-paypal me-1"></i>PayPal / Enlace de Pago</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Selector de módulos -->
            <div class="col-md-8">
                <div class="card border-0 shadow-sm" style="border-radius:1rem;">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <h5 class="fw-bold mb-0">Solicitar / Renovar Módulos</h5>
                            <div class="btn-group btn-group-sm" id="billingToggle" role="group">
                                <input type="radio" class="btn-check" name="billing" id="bilMes" value="mes" checked>
                                <label class="btn btn-outline-primary" for="bilMes">Mensual</label>
                                <input type="radio" class="btn-check" name="billing" id="bilAnual" value="anual">
                                <label class="btn btn-outline-primary" for="bilAnual">Anual <span
                                        class="badge bg-warning text-dark ms-1">-15%</span></label>
                                <input type="radio" class="btn-check" name="billing" id="bilVida" value="vida">
                                <label class="btn btn-outline-primary" for="bilVida">De por vida</label>
                            </div>
                        </div>
                        <p class="text-muted small mb-3">Selecciona los módulos que necesitas. Los que ya tienes activos
                            están marcados.</p>

                        <div class="row g-2" id="moduleGrid">
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

                        <div class="total-bar mt-3">
                            <div>
                                <div class="small text-muted">Total estimado</div>
                                <div class="total-amount" id="totalPrice">Q0/mes</div>
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

    <!-- Modal confirmación de solicitud -->
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius:1rem; border:0;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-send-check me-2 text-primary"></i>Confirmar
                        Solicitud</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Revisa los módulos seleccionados antes de enviar. El administrador
                        recibirá tu solicitud y la aprobará a la brevedad.</p>
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
                    <button class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
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