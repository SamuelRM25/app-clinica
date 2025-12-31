<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');

verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $page_title = "Compras - Clínica";
    include_once '../../includes/header.php';
    
    // Check if new tables exist, if not, fallback or show error (or handle gracefully)
    // For this reengineering, we assume tables exist.
    
} catch (Exception $e) {
    die("Error: No se pudo conectar a la base de datos");
}
?>

<!-- Inject Dashboard Custom Styles (Same as Inventory) -->
<link rel="stylesheet" href="../../assets/css/dashboard-reengineered.css">

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* Reuse styles from inventory/index.php */
    .glass-panel {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(12px);
        border-radius: 16px;
        border: 1px solid rgba(255, 255, 255, 0.4);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }
    
    .table-glass {
        --bs-table-bg: transparent;
        --bs-table-hover-bg: rgba(255, 255, 255, 0.3);
    }
    
    .form-glass {
        background: rgba(255, 255, 255, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.5);
    }
    
    .btn-glass {
        background: rgba(255, 255, 255, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.5);
        transition: all 0.3s;
    }
    .btn-glass:hover {
        background: rgba(255, 255, 255, 0.8);
        transform: translateY(-2px);
    }
</style>

<!-- Mobile Overlay -->
    <div class="dashboard-mobile-overlay"></div>

    <!-- Desktop Sidebar Toggle -->
    <button class="btn btn-white shadow-sm border rounded-circle position-fixed d-none d-md-flex align-items-center justify-content-center" id="desktopSidebarToggle" title="Mostrar Menú" style="top: 20px; left: 20px; width: 45px; height: 45px; z-index: 1040; transition: all 0.3s ease;">
        <i class="bi bi-list text-primary fs-4"></i>
    </button>

<div class="dashboard-wrapper sidebar-collapsed">
    <!-- Sidebar (Same as Inventory) -->
    <div class="sidebar-glass p-3 d-flex flex-column">
        <div class="brand-section">
            <div class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-decoration-none">
                <img src="../../assets/img/siloe.png" alt="Logo" style="height: 40px; margin-right: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">
            </div>
        </div>
        
        <ul class="nav nav-pills flex-column mb-auto">
            <?php $rol = $_SESSION['tipoUsuario'] ?? $_SESSION['rol'] ?? ''; ?>
            <?php if ($rol === 'admin' || $rol === 'doc' || $rol === 'user'): ?>
            <li class="nav-item"><a href="../dashboard/index.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="../patients/index.php" class="nav-link"><i class="bi bi-people"></i> Pacientes</a></li>
            <?php endif; ?>
            <?php if ($rol === 'admin' || $rol === 'user'): ?>
            <li><a href="../appointments/index.php" class="nav-link"><i class="bi bi-calendar"></i> Citas</a></li>
            <li><a href="../minor_procedures/index.php" class="nav-link"><i class="bi bi-bandaid"></i> Proc. Menores</a></li>
            <li><a href="../examinations/index.php" class="nav-link"><i class="bi bi-file-earmark-medical"></i> Exámenes</a></li>
            <li><a href="../dispensary/index.php" class="nav-link"><i class="bi bi-cart4"></i> Dispensario</a></li>
            <li><a href="../inventory/index.php" class="nav-link"><i class="bi bi-box-seam"></i> Inventario</a></li>
            <?php endif; ?>
            <?php if ($rol === 'admin'): ?>
            <li><a href="../purchases/index.php" class="nav-link active"><i class="bi bi-cart-plus"></i> Compras</a></li>
            <li><a href="../sales/index.php" class="nav-link"><i class="bi bi-receipt"></i> Ventas</a></li>
            <li><a href="../reports/index.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reportes</a></li>
            <?php endif; ?>
            <?php if ($rol === 'admin' || $rol === 'user'): ?>
            <li><a href="../billing/index.php" class="nav-link"><i class="bi bi-cash-coin"></i> Cobros</a></li>
            <?php endif; ?>
        </ul>
        
        <div class="mt-auto">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle p-2 rounded hover-effect" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false" style="color: var(--text-color);">
                    <div class="avatar-circle me-2 bg-primary text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px;">
                        <?php echo strtoupper(substr($_SESSION['nombre'] ?? 'U', 0, 1)); ?>
                    </div>
                    <strong><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></strong>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="dropdownUser1">
                    <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content-glass">
        <div class="container-fluid p-4">
            
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">
                        <i class="bi bi-cart-plus me-2 text-primary"></i>
                        Gestión de Compras
                    </h2>
                    <p class="text-muted mb-0">Registro y control de compras de medicamentos e insumos</p>
                </div>
                <button type="button" class="btn btn-primary shadow-sm" onclick="showNewPurchaseModal()">
                    <i class="bi bi-plus-lg me-2"></i>Nueva Compra
                </button>
            </div>

            <!-- Tabs Navigation -->
            <ul class="nav nav-pills mb-4" id="pills-tab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active rounded-pill px-4 me-2" id="pills-new-tab" data-bs-toggle="pill" data-bs-target="#pills-new" type="button" role="tab" aria-controls="pills-new" aria-selected="true">
                        <i class="bi bi-cart-check me-2"></i>Nuevas Compras
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link rounded-pill px-4" id="pills-old-tab" data-bs-toggle="pill" data-bs-target="#pills-old" type="button" role="tab" aria-controls="pills-old" aria-selected="false">
                        <i class="bi bi-archive me-2"></i>Compras Antiguas
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="pills-tabContent">
                <!-- Nuevas Compras Tab -->
                <div class="tab-pane fade show active" id="pills-new" role="tabpanel" aria-labelledby="pills-new-tab">
                    <!-- Recent Purchases Table -->
                    <div class="glass-panel p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold m-0">Historial de Compras Recientes</h5>
                            <div class="input-group" style="width: 300px;">
                                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control bg-transparent border-start-0" id="searchNew" placeholder="Buscar compra...">
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="tableNew">
                                <thead class="table-light bg-transparent">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Documento</th>
                                        <th>Proveedor</th>
                                        <th>Total</th>
                                        <th>Pagado</th>
                                        <th>Saldo / Pagar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        $stmt = $conn->query("SELECT * FROM purchase_headers ORDER BY purchase_date DESC LIMIT 50");
                                        while ($row = $stmt->fetch()) {
                                            $paid = $row['paid_amount'] ?? 0;
                                            $balance = $row['total_amount'] - $paid;
                                            ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($row['purchase_date'])); ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge bg-light text-dark border me-2">
                                                            <?php echo htmlspecialchars($row['document_type']); ?>
                                                            <?php echo $row['document_number'] ? '#'.$row['document_number'] : ''; ?>
                                                        </span>
                                                        <button class="btn btn-sm btn-link text-primary p-0" onclick="viewPurchaseDetails(<?php echo $row['id']; ?>)" title="Ver Detalles">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['provider_name']); ?></td>
                                                <td class="fw-bold">Q<?php echo number_format($row['total_amount'], 2); ?></td>
                                                <td class="text-success">Q<?php echo number_format($paid, 2); ?></td>
                                                <td>
                                                    <button class="btn btn-sm <?php echo $balance > 0 ? 'btn-outline-danger' : 'btn-outline-success'; ?> fw-bold w-100" onclick="openPaymentModal(<?php echo $row['id']; ?>)" title="Click para abonar">
                                                        Q<?php echo number_format($balance, 2); ?>
                                                        <i class="bi bi-cash-coin ms-1"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php
                                        }
                                    } catch (PDOException $e) {
                                        echo "<tr><td colspan='6' class='text-center text-muted'>No hay compras registradas en el nuevo sistema.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Compras Antiguas Tab -->
                <div class="tab-pane fade" id="pills-old" role="tabpanel" aria-labelledby="pills-old-tab">
                    <div class="glass-panel p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="fw-bold m-0">Historial de Compras Antiguas</h5>
                                <small class="text-muted">Registros anteriores a la actualización del sistema</small>
                            </div>
                            <div class="input-group" style="width: 300px;">
                                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control bg-transparent border-start-0" id="searchOld" placeholder="Buscar por producto...">
                            </div>
                        </div>
                        
                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-hover align-middle" id="tableOld">
                                <thead class="table-light bg-transparent sticky-top">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Producto</th>
                                        <th>Presentación</th>
                                        <th>Casa Farm.</th>
                                        <th>Cant.</th>
                                        <th>Precio U.</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        $stmtOld = $conn->query("SELECT * FROM compras ORDER BY fecha_compra DESC LIMIT 100");
                                        while ($row = $stmtOld->fetch()) {
                                            $statusClass = 'secondary';
                                            if ($row['estado_compra'] == 'Completo') $statusClass = 'success';
                                            if ($row['estado_compra'] == 'Pendiente') $statusClass = 'warning';
                                            if ($row['estado_compra'] == 'Abonado') $statusClass = 'info';
                                            ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($row['fecha_compra'])); ?></td>
                                                <td class="fw-bold"><?php echo htmlspecialchars($row['nombre_compra']); ?></td>
                                                <td><?php echo htmlspecialchars($row['presentacion_compra']); ?></td>
                                                <td><?php echo htmlspecialchars($row['casa_compra']); ?></td>
                                                <td><?php echo $row['cantidad_compra']; ?></td>
                                                <td>Q<?php echo number_format($row['precio_unidad'], 2); ?></td>
                                                <td class="fw-bold text-primary">Q<?php echo number_format($row['total_compra'], 2); ?></td>
                                                <td><span class="badge bg-<?php echo $statusClass; ?>"><?php echo $row['estado_compra']; ?></span></td>
                                            </tr>
                                            <?php
                                        }
                                    } catch (PDOException $e) {
                                        echo "<tr><td colspan='8' class='text-center text-muted'>No se encontraron registros antiguos.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>

<!-- New Purchase Modal (Large) -->
<div class="modal fade" id="newPurchaseModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content glass-panel border-0">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-bag-plus me-2"></i>Registrar Nueva Compra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="purchaseForm">
                    <!-- Header Info -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Fecha de Compra</label>
                            <input type="date" class="form-control" name="purchase_date" id="purchase_date" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Tipo de Documento</label>
                            <select class="form-select" name="document_type" id="document_type" required>
                                <option value="Factura">Factura</option>
                                <option value="Nota de Envío">Nota de Envío</option>
                                <option value="Consumidor Final">Consumidor Final</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">No. Documento</label>
                            <input type="text" class="form-control" name="document_number" id="document_number" placeholder="Ej. A-12345">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Casa Farmacéutica / Proveedor</label>
                            <input type="text" class="form-control" name="provider_name" id="provider_name" placeholder="Nombre de la casa farmacéutica">
                        </div>
                    </div>
                    
                    <hr class="opacity-25">
                    
                    <!-- Add Item Section -->
                    <h6 class="fw-bold mb-3">Agregar Productos</h6>
                    <div class="card bg-light border-0 mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small">Producto/Medicamento</label>
                                    <input type="text" class="form-control form-control-sm" id="item_name" placeholder="Nombre">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Presentación</label>
                                    <input type="text" class="form-control form-control-sm" id="item_presentation" placeholder="Ej. Tableta">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Molécula</label>
                                    <input type="text" class="form-control form-control-sm" id="item_molecule" placeholder="Componente">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label small">Cant.</label>
                                    <input type="number" class="form-control form-control-sm" id="item_qty" min="1" value="1">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Costo (Q)</label>
                                    <input type="number" class="form-control form-control-sm" id="item_cost" min="0" step="0.01">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small">Precio Venta (Q)</label>
                                    <input type="number" class="form-control form-control-sm" id="item_sale_price" min="0" step="0.01">
                                </div>
                                <div class="col-md-12 d-flex justify-content-end mt-3">
                                    <button type="button" class="btn btn-primary btn-sm px-4" onclick="addItem()">
                                        <i class="bi bi-plus-lg me-2"></i>Agregar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Items List -->
                    <div class="table-responsive mb-3" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-bordered" id="itemsTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>Producto</th>
                                    <th>Presentación</th>
                                    <th>Cant.</th>
                                    <th>Costo U.</th>
                                    <th>Precio Venta</th>
                                    <th>Subtotal</th>
                                    <th style="width: 50px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Items will be added here -->
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="4" class="text-end fw-bold">Total Compra:</td>
                                    <td class="fw-bold text-primary">Q<span id="totalAmount">0.00</span></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="savePurchase()">
                    <i class="bi bi-check-lg me-2"></i>Guardar Compra
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content glass-panel border-0">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-coin me-2"></i>Gestionar Pagos / Abonos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="paymentHeaderInfo" class="alert alert-info d-flex justify-content-between align-items-center mb-4">
                    <!-- Loaded dynamically -->
                    <span>Cargando info...</span>
                </div>

                <div class="row">
                    <div class="col-md-5 border-end">
                        <h6 class="fw-bold mb-3">Registrar Nuevo Abono</h6>
                        <form id="paymentForm">
                            <input type="hidden" id="pay_purchase_id" name="purchase_id">
                            
                            <div class="mb-3">
                                <label class="form-label small">Fecha</label>
                                <input type="date" class="form-control" name="payment_date" id="pay_date" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small">Monto (Q)</label>
                                <input type="number" class="form-control" name="amount" id="pay_amount" step="0.01" min="0.01" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small">Método de Pago</label>
                                <select class="form-select" name="payment_method" id="pay_method">
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Transferencia">Transferencia</option>
                                    <option value="Depósito">Depósito</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label small">Notas</label>
                                <textarea class="form-control" name="notes" id="pay_notes" rows="2"></textarea>
                            </div>
                            
                            <button type="button" class="btn btn-success w-100" onclick="submitPayment()">
                                <i class="bi bi-check-circle me-2"></i>Registrar Pago
                            </button>
                        </form>
                    </div>
                    
                    <div class="col-md-7">
                        <h6 class="fw-bold mb-3">Historial de Pagos</h6>
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-hover" id="paymentsHistoryTable">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Método</th>
                                        <th>Monto</th>
                                        <th>Notas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Loaded dynamically -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Purchase Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content glass-panel border-0">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title fw-bold">Detalles de Compra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailsModalBody">
                <div class="text-center"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialize date to today
    document.getElementById('purchase_date').valueAsDate = new Date();
    
    let purchaseItems = [];
    
    function showNewPurchaseModal() {
        // Reset form
        document.getElementById('purchaseForm').reset();
        document.getElementById('purchase_date').valueAsDate = new Date();
        purchaseItems = [];
        renderItems();
        var modal = new bootstrap.Modal(document.getElementById('newPurchaseModal'));
        modal.show();
    }

    function viewPurchaseDetails(id) {
        var modal = new bootstrap.Modal(document.getElementById('detailsModal'));
        modal.show();
        
        fetch('get_purchase_details.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const h = data.header;
                let html = `
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Proveedor:</strong> ${h.provider_name}</p>
                            <p class="mb-1"><strong>Documento:</strong> ${h.document_type} ${h.document_number}</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <p class="mb-1"><strong>Fecha:</strong> ${h.purchase_date}</p>
                            <p class="mb-1"><strong>Total:</strong> Q${parseFloat(h.total_amount).toFixed(2)}</p>
                            <span class="badge bg-${h.status === 'Completado' ? 'success' : 'warning'}">${h.status}</span>
                        </div>
                    </div>
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th>Pres.</th>
                                <th>Cant.</th>
                                <th>Costo</th>
                                <th>Precio Venta</th>
                                <th>Subtotal</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                
                data.items.forEach(item => {
                    html += `
                        <tr>
                            <td>${item.product_name}</td>
                            <td>${item.presentation}</td>
                            <td>${item.quantity}</td>
                            <td>Q${parseFloat(item.unit_cost).toFixed(2)}</td>
                            <td>Q${parseFloat(item.sale_price || 0).toFixed(2)}</td>
                            <td>Q${parseFloat(item.subtotal).toFixed(2)}</td>
                            <td>${item.status}</td>
                        </tr>
                    `;
                });
                
                html += `</tbody></table>`;
                document.getElementById('detailsModalBody').innerHTML = html;
            } else {
                document.getElementById('detailsModalBody').innerHTML = '<p class="text-danger">Error al cargar detalles</p>';
            }
        })
        .catch(err => {
            document.getElementById('detailsModalBody').innerHTML = '<p class="text-danger">Error de conexión</p>';
        });
    }
    
    function addItem() {
        const name = document.getElementById('item_name').value;
        const qty = parseFloat(document.getElementById('item_qty').value);
        const cost = parseFloat(document.getElementById('item_cost').value);
        const salePrice = parseFloat(document.getElementById('item_sale_price').value);
        
        if (!name || !qty || isNaN(cost) || isNaN(salePrice)) {
            Swal.fire('Error', 'Por favor complete todos los campos del producto', 'warning');
            return;
        }
        
        const item = {
            id: Date.now(), // Temp ID
            name: name,
            presentation: document.getElementById('item_presentation').value,
            molecule: document.getElementById('item_molecule').value,
            // pharma removed, using provider_name from header
            qty: qty,
            cost: cost,
            sale_price: salePrice,
            subtotal: qty * cost
        };
        
        purchaseItems.push(item);
        renderItems();
        
        // Clear inputs
        document.getElementById('item_name').value = '';
        document.getElementById('item_presentation').value = '';
        document.getElementById('item_molecule').value = '';
        document.getElementById('item_qty').value = '1';
        document.getElementById('item_cost').value = '';
        document.getElementById('item_sale_price').value = '';
        document.getElementById('item_name').focus();
    }
    
    function removeItem(id) {
        purchaseItems = purchaseItems.filter(i => i.id !== id);
        renderItems();
    }
    
    function renderItems() {
        const tbody = document.querySelector('#itemsTable tbody');
        tbody.innerHTML = '';
        
        let total = 0;
        
        purchaseItems.forEach(item => {
            total += item.subtotal;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <div class="fw-bold">${item.name}</div>
                    <small class="text-muted">${item.molecule}</small>
                </td>
                <td>${item.presentation}</td>
                <td class="text-center">${item.qty}</td>
                <td class="text-end">Q${item.cost.toFixed(2)}</td>
                <td class="text-end">Q${item.sale_price.toFixed(2)}</td>
                <td class="text-end">Q${item.subtotal.toFixed(2)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeItem(${item.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
        
        document.getElementById('totalAmount').textContent = total.toFixed(2);
    }
    
    function savePurchase() {
        if (purchaseItems.length === 0) {
            Swal.fire('Error', 'Debe agregar al menos un producto a la compra', 'warning');
            return;
        }
        
        const header = {
            purchase_date: document.getElementById('purchase_date').value,
            document_type: document.getElementById('document_type').value,
            document_number: document.getElementById('document_number').value,
            provider_name: document.getElementById('provider_name').value,
            total_amount: parseFloat(document.getElementById('totalAmount').textContent)
        };
        
        const payload = {
            header: header,
            items: purchaseItems
        };
        
        fetch('save_purchase.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Éxito',
                    text: 'Compra registrada correctamente. Los productos se han agregado al inventario como pendientes.',
                    icon: 'success'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', data.message || 'Error al guardar la compra', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'Ocurrió un error al procesar la solicitud', 'error');
        });
    }

    // Search functionality
    document.getElementById('searchNew').addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const rows = document.querySelectorAll('#tableNew tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchText) ? '' : 'none';
        });
    });

    document.getElementById('searchOld').addEventListener('keyup', function() {
        const searchText = this.value.toLowerCase();
        const rows = document.querySelectorAll('#tableOld tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(searchText) ? '' : 'none';
        });
    });

    // --- Payment Functions ---

    function openPaymentModal(id) {
        document.getElementById('pay_purchase_id').value = id;
        document.getElementById('pay_date').valueAsDate = new Date();
        document.getElementById('pay_amount').value = '';
        document.getElementById('pay_notes').value = '';
        
        loadPayments(id);
        
        var modal = new bootstrap.Modal(document.getElementById('paymentModal'));
        modal.show();
    }

    function loadPayments(id) {
        fetch('get_payments.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const h = data.header;
                const total = parseFloat(h.total_amount);
                const paid = parseFloat(h.paid_amount || 0);
                const balance = total - paid;
                
                // Update Header Info
                const infoHtml = `
                    <div>
                        <strong>${h.document_type} ${h.document_number || ''}</strong><br>
                        <small class="text-muted">Total: Q${total.toFixed(2)}</small>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-success mb-1">Pagado: Q${paid.toFixed(2)}</div><br>
                        <div class="badge bg-danger">Saldo: Q${balance.toFixed(2)}</div>
                    </div>
                `;
                document.getElementById('paymentHeaderInfo').innerHTML = infoHtml;
                
                // Auto-fill amount with balance if not set
                if (!document.getElementById('pay_amount').value) {
                    document.getElementById('pay_amount').value = balance.toFixed(2);
                }

                // Update History Table
                const tbody = document.querySelector('#paymentsHistoryTable tbody');
                tbody.innerHTML = '';
                
                if (data.payments.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No hay pagos registrados</td></tr>';
                } else {
                    data.payments.forEach(p => {
                        tbody.innerHTML += `
                            <tr>
                                <td>${p.payment_date}</td>
                                <td>${p.payment_method}</td>
                                <td class="fw-bold text-success">Q${parseFloat(p.amount).toFixed(2)}</td>
                                <td><small>${p.notes || '-'}</small></td>
                            </tr>
                        `;
                    });
                }
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(err => console.error(err));
    }

    function submitPayment() {
        const form = document.getElementById('paymentForm');
        const formData = new FormData(form);
        
        fetch('save_payment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Éxito',
                    text: 'Abono registrado correctamente',
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    // Refresh payments list and update background table row if possible, 
                    // or just reload page to keep it simple and consistent
                    location.reload(); 
                    // Alternatively: loadPayments(formData.get('purchase_id'));
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'Error al procesar el pago', 'error');
        });
    }
</script>

<script src="../../assets/js/dashboard-reengineered.js"></script>
<?php include_once '../../includes/footer.php'; ?>
