<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');

verify_session();

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get all sales with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Get total count for pagination
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM ventas");
    $stmt->execute();
    $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Get sales data with pagination
    $stmt = $conn->prepare("
        SELECT id_venta, fecha_venta, nombre_cliente, tipo_pago, total, estado 
        FROM ventas 
        ORDER BY fecha_venta DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $page_title = "Ventas - Clínica";
    include_once '../../includes/header.php';
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!-- Inject Dashboard Custom Styles -->
<link rel="stylesheet" href="../../assets/css/dashboard-reengineered.css">


<style>
    /* Custom overrides for Sales Table */
    .table-container {
        border-radius: 16px;
        overflow: hidden;
    }
    
    .table thead th {
        font-size: 0.85rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--dash-dark);
        background-color: rgba(248, 249, 252, 0.8);
        border-bottom: 2px solid rgba(0, 0, 0, 0.05);
        padding: 1.25rem 1rem;
    }
    
    .table tbody td {
        padding: 1.25rem 1rem;
        font-size: 0.95rem;
    }
    
    .status-badge {
        font-weight: 600;
        letter-spacing: 0.02em;
    }
    
    .pagination-container .page-link {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 3px;
        border-radius: 50% !important;
        border: none;
        color: var(--dash-dark);
        font-weight: 600;
        background: rgba(255, 255, 255, 0.5);
    }
    
    .pagination-container .page-item.active .page-link {
        background: var(--dash-primary);
        color: white;
        box-shadow: 0 4px 10px rgba(58, 86, 183, 0.3);
    }
</style>

<div class="dashboard-wrapper sidebar-collapsed">
    <!-- Mobile Overlay -->
    <div class="dashboard-mobile-overlay"></div>

    <!-- Desktop Sidebar Toggle -->
    <button class="btn btn-white shadow-sm border rounded-circle position-fixed d-none d-md-flex align-items-center justify-content-center" id="desktopSidebarToggle" title="Mostrar Menú" style="top: 20px; left: 20px; width: 45px; height: 45px; z-index: 1040; transition: all 0.3s ease;">
        <i class="bi bi-list text-primary fs-4"></i>
    </button>

    <!-- Sidebar Reengineered -->
    <div class="sidebar-glass p-3 d-flex flex-column">
        <div class="brand-section d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center text-decoration-none">
                <img src="../../assets/img/siloe.png" alt="Logo" style="height: 40px; margin-right: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">
            </div>
            <button class="btn btn-sm btn-light text-primary rounded-circle shadow-sm d-none d-md-flex align-items-center justify-content-center" id="sidebarCloseBtn" style="width: 32px; height: 32px;">
                <i class="bi bi-chevron-left"></i>
            </button>
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
            <li><a href="../purchases/index.php" class="nav-link"><i class="bi bi-cart-plus"></i> Compras</a></li>
            <li><a href="../sales/index.php" class="nav-link active"><i class="bi bi-receipt"></i> Ventas</a></li>
            <li><a href="../reports/index.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reportes</a></li>
            <?php endif; ?>
            <?php if ($rol === 'admin' || $rol === 'user'): ?>
            <li><a href="../billing/index.php" class="nav-link"><i class="bi bi-cash-coin"></i> Cobros</a></li>
            <?php endif; ?>
        </ul>
        
        <div class="mt-auto">
            <div class="dropdown p-2">
                <div class="d-flex align-items-center text-dark">
                    <div class="avatar-circle me-2 bg-primary text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px;">
                        <?php echo strtoupper(substr($_SESSION['nombre'], 0, 1)); ?>
                    </div>
                    <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Reengineered -->
    <div class="main-content-glass">
        <div class="container-xxl"> <!-- Changed to container-xxl for better containment on large screens -->
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h2 class="fw-bold text-dark mb-1">Registro de Ventas</h2>
                    <p class="text-muted text-sm mb-0">Gestión de historial y reportes de transacciones</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-glass-success shadow-sm" data-bs-toggle="modal" data-bs-target="#shiftReportModal">
                        <i class="bi bi-file-earmark-bar-graph me-2"></i>Reporte por Jornada
                    </button>
                    <a href="../dispensary/index.php" class="btn btn-primary rounded-pill px-4 shadow-sm">
                        <i class="bi bi-plus-lg me-2"></i>Nueva Venta
                    </a>
                </div>
            </div>
            
            <!-- Stats Summary (New Addition for better visuals) -->
            <div class="row g-4 mb-4 animate__animated animate__fadeInUp">
                <div class="col-md-4">
                    <div class="card-glass p-3 mb-0">
                        <div class="d-flex align-items-center">
                            <div class="stats-card-icon bg-gradient-primary-soft me-3">
                                <i class="bi bi-cart-check"></i>
                            </div>
                            <div>
                                <p class="text-muted text-xs mb-0 uppercase-label">Ventas Totales</p>
                                <h4 class="fw-bold mb-0"><?php echo $total_records; ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sales Table Card -->
            <div class="card-glass shadow-sm">
                <div class="p-0"> <!-- Removed padding for full-bleed table effect -->
                    <div class="table-responsive table-container">
                        <table class="table table-glass table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Fecha & Hora</th>
                                    <th>Cliente</th>
                                    <th>Tipo de Pago</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th class="text-end pe-4">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($ventas) > 0): ?>
                                    <?php foreach ($ventas as $venta): ?>
                                        <tr class="transition-hover">
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="icon-square bg-light text-primary me-3 rounded-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                        <i class="bi bi-receipt"></i>
                                                    </div>
                                                    <div>
                                                        <div class="fw-medium text-dark"><?php echo date('d/m/Y', strtotime($venta['fecha_venta'])); ?></div>
                                                        <div class="text-xs text-muted"><?php echo date('h:i A', strtotime($venta['fecha_venta'])); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="fw-semibold text-dark"><?php echo htmlspecialchars($venta['nombre_cliente']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
                                                    <?php echo htmlspecialchars($venta['tipo_pago']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="fw-bold text-primary fs-6">Q<?php echo number_format($venta['total'], 2); ?></span>
                                            </td>
                                            <td>
                                                <?php 
                                                $statusClass = match($venta['estado']) {
                                                    'Pagado' => 'bg-success text-success',
                                                    'Pendiente' => 'bg-warning text-warning',
                                                    'Cancelado' => 'bg-danger text-danger',
                                                    default => 'bg-secondary text-secondary'
                                                };
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?> bg-opacity-10 border border-opacity-25 rounded-pill px-3 py-2 status-badge">
                                                    <?php echo htmlspecialchars($venta['estado']); ?>
                                                </span>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="d-flex justify-content-end gap-2">
                                                    <button type="button" class="btn btn-sm btn-light text-primary view-details rounded-circle shadow-sm" 
                                                            data-id="<?php echo $venta['id_venta']; ?>" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#viewDetailsModal"
                                                            title="Ver Detalles"
                                                            style="width: 35px; height: 35px;">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <a href="../dispensary/print_receipt.php?id=<?php echo $venta['id_venta']; ?>" target="_blank" 
                                                       class="btn btn-sm btn-light text-dark rounded-circle shadow-sm"
                                                       title="Imprimir Comprobante"
                                                       style="width: 35px; height: 35px; display: inline-flex; align-items: center; justify-content: center;">
                                                        <i class="bi bi-printer"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5">
                                            <div class="d-flex flex-column align-items-center text-muted opacity-50">
                                                <i class="bi bi-receipt fs-1 mb-3"></i>
                                                <h5>No hay ventas registradas</h5>
                                                <p class="text-sm">Las nuevas ventas aparecerán aquí.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Smart Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center py-4 pagination-container">
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-glass mb-0">
                                <!-- Previous -->
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php
                                $range = 2; // How many pages to show around current page
                                $initial_num = $page - $range;
                                $condition_limit_num = ($page + $range) + 1;

                                // Always show first page
                                if ($total_pages > 1) {
                                    echo '<li class="page-item ' . ($page == 1 ? 'active' : '') . '"><a class="page-link" href="?page=1">1</a></li>';
                                }

                                // Ellipsis before
                                if ($initial_num > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link border-0 bg-transparent">...</span></li>';
                                }

                                for ($i = 2; $i < $total_pages; $i++) {
                                    if ($i >= $initial_num && $i < $condition_limit_num) {
                                        echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . '">' . $i . '</a></li>';
                                    }
                                }

                                // Ellipsis after
                                if ($condition_limit_num < $total_pages) {
                                    echo '<li class="page-item disabled"><span class="page-link border-0 bg-transparent">...</span></li>';
                                }

                                // Always show last page if not same as first
                                if ($total_pages > 1 && $total_pages != 1) {
                                    echo '<li class="page-item ' . ($page == $total_pages ? 'active' : '') . '"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                                }
                                ?>
                                
                                <!-- Next -->
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-light border-0 pb-0">
                <h5 class="modal-title fw-bold text-dark">Detalles de Venta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="card bg-light border-0 rounded-3 mb-4 p-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <p class="text-muted text-xs uppercase-label mb-1">Cliente</p>
                            <h6 class="fw-bold text-dark mb-0" id="modal-cliente">---</h6>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p class="text-muted text-xs uppercase-label mb-1">Fecha</p>
                            <h6 class="fw-bold text-dark mb-0" id="modal-fecha">---</h6>
                        </div>
                        <div class="col-md-6">
                            <p class="text-muted text-xs uppercase-label mb-1">Tipo de Pago</p>
                            <h6 class="fw-bold text-dark mb-0" id="modal-tipo-pago">---</h6>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p class="text-muted text-xs uppercase-label mb-1">Estado</p>
                            <h6 class="fw-bold text-dark mb-0" id="modal-estado">---</h6>
                        </div>
                    </div>
                </div>
                
                <h6 class="fw-bold mb-3">Productos Adquiridos</h6>
                <div class="table-responsive rounded-3 border">
                    <table class="table table-sm mb-0" id="modal-items">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3 py-2">Medicamento</th>
                                <th class="py-2">Presentación</th>
                                <th class="text-center py-2">Cant.</th>
                                <th class="text-end py-2">Precio Unit.</th>
                                <th class="text-end pe-3 py-2">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Items will be loaded dynamically -->
                        </tbody>
                        <tfoot class="bg-light border-top">
                            <tr>
                                <th colspan="4" class="text-end py-3">Total:</th>
                                <th class="text-end pe-3 py-3 text-primary fw-bold fs-5" id="modal-total"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
                <a href="#" class="btn btn-primary rounded-pill px-4" id="modal-print-btn" target="_blank">
                    <i class="bi bi-printer me-2"></i>Imprimir
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Shift Report Modal -->
<div class="modal fade" id="shiftReportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-success bg-opacity-10 border-0">
                <h5 class="modal-title fw-bold text-success"><i class="bi bi-file-earmark-bar-graph me-2"></i>Reporte por Jornada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label for="shiftDate" class="form-label fw-bold">Seleccionar Fecha de Inicio</label>
                    <input type="date" class="form-control form-control-lg rounded-3 border-light bg-light" id="shiftDate" value="<?php echo date('Y-m-d'); ?>">
                    <div class="form-text mt-2 text-muted">
                        <i class="bi bi-info-circle me-1"></i> La jornada comprende de <strong>8:00 AM</strong> de la fecha seleccionada a <strong>8:00 AM</strong> del día siguiente.
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success rounded-pill px-4 shadow-lg" id="generateShiftReport">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Generar Reporte
                </button>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>

<script src="../../assets/js/dashboard-reengineered.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // View details modal
    const viewDetailsButtons = document.querySelectorAll('.view-details');
    
    viewDetailsButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            // Fetch sale details
            fetch(`get_sale_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Populate modal with sale details
                        document.getElementById('modal-cliente').textContent = data.venta.nombre_cliente;
                        // Format date nicely
                        const date = new Date(data.venta.fecha_venta);
                        document.getElementById('modal-fecha').textContent = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                        
                        document.getElementById('modal-tipo-pago').textContent = data.venta.tipo_pago;
                        document.getElementById('modal-estado').textContent = data.venta.estado;
                        document.getElementById('modal-total').textContent = 'Q' + parseFloat(data.venta.total).toFixed(2);
                        
                        // Set print button URL
                        document.getElementById('modal-print-btn').href = `../dispensary/print_receipt.php?id=${id}`;
                        
                        // Clear previous items
                        const itemsTable = document.getElementById('modal-items').querySelector('tbody');
                        itemsTable.innerHTML = '';
                        
                        // Add new items
                        data.items.forEach(item => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td class="ps-3 py-2">${item.nombre_medicamento || 'Medicamento'}</td>
                                <td class="py-2">${item.presentacion || '-'}</td>
                                <td class="text-center py-2">${item.cantidad}</td>
                                <td class="text-end py-2">Q${parseFloat(item.precio_unitario).toFixed(2)}</td>
                                <td class="text-end pe-3 py-2 fw-bold">Q${parseFloat(item.subtotal).toFixed(2)}</td>
                            `;
                            itemsTable.appendChild(row);
                        });
                    }
                })
                .catch(error => console.error('Error:', error));
        });
    });
    
    // Generate Shift Report
    const generateReportBtn = document.getElementById('generateShiftReport');
    if(generateReportBtn) {
        generateReportBtn.addEventListener('click', function() {
            const date = document.getElementById('shiftDate').value;
            if(date) {
                // Open report in new tab
                window.open(`generate_shift_report.php?date=${date}`, '_blank');
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('shiftReportModal'));
                modal.hide();
            }
        });
    }
});
</script>