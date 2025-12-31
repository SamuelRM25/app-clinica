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
    
    // Get inventory statistics
    $stats_query = "SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN cantidad_med = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(CASE WHEN cantidad_med > 0 AND cantidad_med <= 10 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN DATEDIFF(fecha_vencimiento, NOW()) <= 30 AND fecha_vencimiento >= NOW() THEN 1 ELSE 0 END) as expiring_soon,
        SUM(CASE WHEN fecha_vencimiento < NOW() THEN 1 ELSE 0 END) as expired
    FROM inventario";
    $stats_stmt = $conn->query($stats_query);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    $page_title = "Inventario - Clínica";
    include_once '../../includes/header.php';
} catch (Exception $e) {
    die("Error: No se pudo conectar a la base de datos");
}
?>

<!-- Inject Dashboard Custom Styles -->
<link rel="stylesheet" href="../../assets/css/dashboard-reengineered.css">

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(12px);
        border-radius: 16px;
        padding: 1.25rem;
        border: 1px solid rgba(255, 255, 255, 0.4);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .stat-value {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }

    .stat-label {
        font-size: 0.875rem;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .inventory-table-container {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(12px);
        border-radius: 16px;
        padding: 1rem;
        border: 1px solid rgba(255, 255, 255, 0.4);
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }

    .inventory-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    .inventory-table thead th {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 1rem;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.2px;
        border: none;
    }

    .inventory-table thead th:first-child {
        border-top-left-radius: 12px;
    }

    .inventory-table thead th:last-child {
        border-top-right-radius: 12px;
    }

    .inventory-table tbody tr {
        transition: all 0.3s ease;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    }

    .inventory-table tbody tr:hover {
        background: rgba(102, 126, 234, 0.05);
        transform: scale(1.01);
    }

    .inventory-table tbody td {
        padding: 0.75rem;
        vertical-align: middle;
        font-size: 0.9rem;
    }

    .stock-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.375rem 0.75rem;
        border-radius: 50px;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .stock-badge.out {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
    }

    .stock-badge.low {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
    }

    .stock-badge.adequate {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
    }

    .expiry-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 50px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .expiry-badge.expired {
        background: rgba(239, 68, 68, 0.1);
        color: #dc2626;
    }

    .expiry-badge.expiring {
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
    }

    .expiry-badge.valid {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
    }

    .filter-pills {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-bottom: 1.5rem;
    }

    .filter-pill {
        padding: 0.5rem 1rem;
        border-radius: 50px;
        border: 2px solid transparent;
        background: rgba(255, 255, 255, 0.5);
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 0.875rem;
        font-weight: 600;
    }

    .filter-pill:hover {
        background: rgba(102, 126, 234, 0.1);
        border-color: #667eea;
    }

    .filter-pill.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: #667eea;
    }

    .action-btn {
        padding: 0.5rem;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 1rem;
    }

    .action-btn:hover {
        transform: scale(1.1);
    }

    .btn-edit {
        background: rgba(59, 130, 246, 0.1);
        color: #3b82f6;
    }

    .btn-delete {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
    }

    .search-box {
        position: relative;
        margin-bottom: 1.5rem;
    }

    .search-box input {
        width: 100%;
        padding: 1rem 1rem 1rem 3rem;
        border-radius: 12px;
        border: 2px solid rgba(0, 0, 0, 0.1);
        background: white;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .search-box input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .search-box i {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #6b7280;
        font-size: 1.25rem;
    }

    @media (max-width: 768px) {
        .stats-grid {
            grid-template-columns: 1fr;
        }
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
                <img src="../../assets/img/siloe.png" alt="Logo" style="height: 40px; margin-right: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));"/>
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
            <li><a href="../inventory/index.php" class="nav-link active"><i class="bi bi-box-seam"></i> Inventario</a></li>
            <?php endif; ?>
            <?php if ($rol === 'admin'): ?>
            <li><a href="../purchases/index.php" class="nav-link"><i class="bi bi-cart-plus"></i> Compras</a></li>
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
        <div class="container-fluid p-2 p-md-3">
            <?php if (isset($_SESSION['inventory_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['inventory_status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['inventory_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php 
                unset($_SESSION['inventory_message']);
                unset($_SESSION['inventory_status']);
                ?>
            <?php endif; ?>

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold mb-1">
                        <i class="bi bi-box-seam me-2 text-primary"></i>
                        Gestión de Inventario
                    </h2>
                    <p class="text-muted mb-0">Control y administración de medicamentos e insumos</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="generate_report.php?format=pdf" class="btn btn-outline-success">
                        <i class="bi bi-file-pdf me-2"></i>Reporte PDF
                    </a>
                    <a href="generate_report.php?format=csv" class="btn btn-outline-primary">
                        <i class="bi bi-file-earmark-spreadsheet me-2"></i>Exportar CSV
                    </a>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMedicineModal">
                        <i class="bi bi-plus-circle me-2"></i>Agregar Medicamento
                    </button>
                </div>
            </div>

            <!-- Statistics Dashboard -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <div class="stat-value" style="color: #3b82f6;"><?php echo $stats['total_items']; ?></div>
                    <div class="stat-label">Total Items</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;">
                        <i class="bi bi-exclamation-triangle"></i>
                    </div>
                    <div class="stat-value" style="color: #ef4444;"><?php echo $stats['out_of_stock']; ?></div>
                    <div class="stat-label">Agotados</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                        <i class="bi bi-exclamation-circle"></i>
                    </div>
                    <div class="stat-value" style="color: #f59e0b;"><?php echo $stats['low_stock']; ?></div>
                    <div class="stat-label">Stock Bajo</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stat-value" style="color: #f59e0b;"><?php echo $stats['expiring_soon']; ?></div>
                    <div class="stat-label">Por Vencer (30 días)</div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="animate__animated animate__fadeInUp animate__delay-1s">
                <div class="search-box">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchInput" placeholder="Buscar por nombre, molécula o casa farmacéutica...">
                </div>

                <div class="filter-pills">
                    <button class="filter-pill active" data-filter="all">
                        <i class="bi bi-grid me-1"></i>Todos
                    </button>
                    <button class="filter-pill" data-filter="in-stock">
                        <i class="bi bi-check-circle me-1"></i>En Stock
                    </button>
                    <button class="filter-pill" data-filter="low-stock">
                        <i class="bi bi-exclamation-circle me-1"></i>Stock Bajo
                    </button>
                    <button class="filter-pill" data-filter="out-of-stock">
                        <i class="bi bi-x-circle me-1"></i>Agotados
                    </button>
                    <button class="filter-pill" data-filter="expiring">
                        <i class="bi bi-clock-history me-1"></i>Por Vencer
                    </button>
                    <button class="filter-pill" data-filter="expired">
                        <i class="bi bi-calendar-x me-1"></i>Vencidos
                    </button>
                    <button class="filter-pill" data-filter="pending">
                        <i class="bi bi-box-arrow-in-down me-1"></i>Pendientes de Recibir
                    </button>
                </div>
            </div>

            <!-- Inventory Table -->
            <div class="inventory-table-container">
                <div class="table-responsive">
                    <table class="inventory-table" id="inventoryTable">
                        <thead>
                            <tr>
                                <th>Medicamento</th>
                                <th>Molécula</th>
                                <th>Presentación</th>
                                <th>Casa Farmacéutica</th>
                                <th>Stock</th>
                                <th>Adquisición</th>
                                <th>Vencimiento</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch inventory with optional new columns
                            $stmt = $conn->query("SELECT * FROM inventario ORDER BY fecha_vencimiento ASC");
                            while ($row = $stmt->fetch()) {
                                $estado = $row['estado'] ?? 'Disponible';
                                
                                // Calculate expiration status
                                $is_expired = false;
                                $days_until_expiry = 0;
                                $expiry_text = 'N/A';
                                $expiry_status = 'valid';
                                
                                if ($row['fecha_vencimiento']) {
                                    $expiry_date = new DateTime($row['fecha_vencimiento']);
                                    $today = new DateTime();
                                    $days_until_expiry = $today->diff($expiry_date)->days;
                                    $is_expired = $expiry_date < $today;
                                    
                                    if ($is_expired) {
                                        $expiry_status = 'expired';
                                        $expiry_text = 'Vencido';
                                    } elseif ($days_until_expiry <= 30) {
                                        $expiry_status = 'expiring';
                                        $expiry_text = $days_until_expiry . ' días';
                                    } else {
                                        $expiry_text = 'Válido';
                                    }
                                } else {
                                    if ($estado === 'Pendiente') {
                                        $expiry_text = 'Por definir';
                                        $expiry_status = 'pending';
                                    }
                                }
                                
                                // Determine stock status
                                $stock_status = 'adequate';
                                $stock_icon = '🟢';
                                
                                if ($estado === 'Pendiente') {
                                    $stock_status = 'pending';
                                    $stock_icon = '⏳';
                                } elseif ($row['cantidad_med'] == 0) {
                                    $stock_status = 'out';
                                    $stock_icon = '🔴';
                                } elseif ($row['cantidad_med'] <= 10) {
                                    $stock_status = 'low';
                                    $stock_icon = '🟡';
                                }
                                
                                // Data attributes for filtering
                                $data_attrs = "data-stock='{$stock_status}' data-expiry='{$expiry_status}'";
                            ?>
                            <tr <?php echo $data_attrs; ?>>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['nom_medicamento']); ?></strong>
                                    <?php if ($estado === 'Pendiente'): ?>
                                        <span class="badge bg-warning text-dark ms-2">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['mol_medicamento']); ?></td>
                                <td><?php echo htmlspecialchars($row['presentacion_med']); ?></td>
                                <td><?php echo htmlspecialchars($row['casa_farmaceutica']); ?></td>
                                <td>
                                    <span class="stock-badge <?php echo $stock_status; ?>">
                                        <?php echo $stock_icon; ?> <?php echo $row['cantidad_med']; ?> unidades
                                    </span>
                                </td>
                                <td><?php echo $row['fecha_adquisicion'] ? date('d/m/Y', strtotime($row['fecha_adquisicion'])) : 'N/A'; ?></td>
                                <td>
                                    <?php if ($row['fecha_vencimiento']): ?>
                                        <div><?php echo date('d/m/Y', strtotime($row['fecha_vencimiento'])); ?></div>
                                        <span class="expiry-badge <?php echo $expiry_status; ?>"><?php echo $expiry_text; ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <?php if ($estado === 'Pendiente'): ?>
                                            <button type="button" class="btn btn-sm btn-success shadow-sm" 
                                                    onclick="openReceiveModal(<?php echo $row['id_inventario']; ?>, '<?php echo htmlspecialchars($row['nom_medicamento']); ?>')"
                                                    title="Recibir Producto">
                                                <i class="bi bi-box-seam me-1"></i> Recibir
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="action-btn btn-edit edit-btn" 
                                                    data-id="<?php echo $row['id_inventario']; ?>"
                                                    data-bs-toggle="modal" data-bs-target="#editMedicineModal"
                                                    title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="action-btn btn-delete delete-btn"
                                                    data-id="<?php echo $row['id_inventario']; ?>"
                                                    title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Medicine Modal -->
<div class="modal fade" id="addMedicineModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 16px 16px 0 0;">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Agregar Medicamento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="addMedicineForm" action="save_medicine.php" method="POST">
                <div class="modal-body" style="padding: 2rem;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nom_medicamento" class="form-label fw-bold">Nombre del Medicamento</label>
                            <input type="text" class="form-control" id="nom_medicamento" name="nom_medicamento" required>
                        </div>
                        <div class="col-md-6">
                            <label for="mol_medicamento" class="form-label fw-bold">Molécula</label>
                            <input type="text" class="form-control" id="mol_medicamento" name="mol_medicamento" required>
                        </div>
                        <div class="col-md-6">
                            <label for="presentacion_med" class="form-label fw-bold">Presentación</label>
                            <input type="text" class="form-control" id="presentacion_med" name="presentacion_med" required>
                        </div>
                        <div class="col-md-6">
                            <label for="casa_farmaceutica" class="form-label fw-bold">Casa Farmacéutica</label>
                            <input type="text" class="form-control" id="casa_farmaceutica" name="casa_farmaceutica" required>
                        </div>
                        <div class="col-md-4">
                            <label for="cantidad_med" class="form-label fw-bold">Cantidad</label>
                            <input type="number" class="form-control" id="cantidad_med" name="cantidad_med" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label for="fecha_adquisicion" class="form-label fw-bold">Fecha de Adquisición</label>
                            <input type="date" class="form-control" id="fecha_adquisicion" name="fecha_adquisicion" required>
                        </div>
                        <div class="col-md-4">
                            <label for="fecha_vencimiento" class="form-label fw-bold">Fecha de Vencimiento</label>
                            <input type="date" class="form-control" id="fecha_vencimiento" name="fecha_vencimiento" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(0,0,0,0.1); padding: 1rem 2rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Medicine Modal -->
<div class="modal fade" id="editMedicineModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 16px 16px 0 0;">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Editar Medicamento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="editMedicineForm" action="update_medicine.php" method="POST">
                <input type="hidden" name="id_inventario" id="edit_id_inventario">
                <div class="modal-body" style="padding: 2rem;">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="edit_nom_medicamento" class="form-label fw-bold">Nombre del Medicamento</label>
                            <input type="text" class="form-control" id="edit_nom_medicamento" name="nom_medicamento" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_mol_medicamento" class="form-label fw-bold">Molécula</label>
                            <input type="text" class="form-control" id="edit_mol_medicamento" name="mol_medicamento" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_presentacion_med" class="form-label fw-bold">Presentación</label>
                            <input type="text" class="form-control" id="edit_presentacion_med" name="presentacion_med" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_casa_farmaceutica" class="form-label fw-bold">Casa Farmacéutica</label>
                            <input type="text" class="form-control" id="edit_casa_farmaceutica" name="casa_farmaceutica" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_cantidad_med" class="form-label fw-bold">Cantidad</label>
                            <input type="number" class="form-control" id="edit_cantidad_med" name="cantidad_med" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_fecha_adquisicion" class="form-label fw-bold">Fecha de Adquisición</label>
                            <input type="date" class="form-control" id="edit_fecha_adquisicion" name="fecha_adquisicion" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_fecha_vencimiento" class="form-label fw-bold">Fecha de Vencimiento</label>
                            <input type="date" class="form-control" id="edit_fecha_vencimiento" name="fecha_vencimiento" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(0,0,0,0.1); padding: 1rem 2rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i>Actualizar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Receive Medicine Modal -->
<div class="modal fade" id="receiveMedicineModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <div class="modal-header" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border-radius: 16px 16px 0 0;">
                <h5 class="modal-title"><i class="bi bi-box-check me-2"></i>Recibir Medicamento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="receiveMedicineForm">
                <input type="hidden" name="id_inventario" id="receive_id_inventario">
                <div class="modal-body" style="padding: 2rem;">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Medicamento</label>
                        <input type="text" class="form-control" id="receive_nom_medicamento" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="receive_fecha_vencimiento" class="form-label fw-bold">Fecha de Vencimiento</label>
                        <input type="date" class="form-control" id="receive_fecha_vencimiento" name="fecha_vencimiento" required>
                    </div>
                    <div class="mb-3">
                        <label for="receive_documento" class="form-label fw-bold">No. Factura / Nota de Envío</label>
                        <input type="text" class="form-control" id="receive_documento" name="documento_referencia" placeholder="Opcional: Actualizar documento">
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid rgba(0,0,0,0.1); padding: 1rem 2rem;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="submitReceive()">
                        <i class="bi bi-check-circle me-2"></i>Confirmar Recepción
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Existing script logic needs to be preserved or merged?
    // The existing file doesn't seem to have much script at the bottom, likely in footer or header?
    // Wait, the file I read didn't show the script section at the bottom. 
    // I should check if there is existing script.
    
    function openReceiveModal(id, name) {
        document.getElementById('receive_id_inventario').value = id;
        document.getElementById('receive_nom_medicamento').value = name;
        // Default expiry to today + 1 year maybe?
        const d = new Date();
        d.setFullYear(d.getFullYear() + 1);
        document.getElementById('receive_fecha_vencimiento').valueAsDate = d;
        
        var modal = new bootstrap.Modal(document.getElementById('receiveMedicineModal'));
        modal.show();
    }
    
    function submitReceive() {
        const id = document.getElementById('receive_id_inventario').value;
        const expiry = document.getElementById('receive_fecha_vencimiento').value;
        const doc = document.getElementById('receive_documento').value;
        
        if (!expiry) {
            Swal.fire('Error', 'Debe ingresar la fecha de vencimiento', 'warning');
            return;
        }
        
        const payload = {
            id_inventario: id,
            fecha_vencimiento: expiry,
            documento_referencia: doc
        };
        
        fetch('receive_item.php', {
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
                    title: 'Recibido',
                    text: 'El medicamento ha sido marcado como disponible',
                    icon: 'success'
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', data.message || 'Error al recibir', 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'Error de conexión', 'error');
        });
    }
    
    // Filter logic
    document.querySelectorAll('.filter-pill').forEach(pill => {
        pill.addEventListener('click', function() {
            // Remove active class from all
            document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
            // Add to current
            this.classList.add('active');
            
            const filter = this.dataset.filter;
            const rows = document.querySelectorAll('#inventoryTable tbody tr');
            
            rows.forEach(row => {
                const stock = row.dataset.stock;
                const expiry = row.dataset.expiry;
                
                let show = false;
                
                if (filter === 'all') show = true;
                else if (filter === 'in-stock' && stock === 'adequate') show = true;
                else if (filter === 'low-stock' && stock === 'low') show = true;
                else if (filter === 'out-of-stock' && stock === 'out') show = true;
                else if (filter === 'expiring' && expiry === 'expiring') show = true;
                else if (filter === 'expired' && expiry === 'expired') show = true;
                else if (filter === 'pending' && stock === 'pending') show = true;
                
                row.style.display = show ? '' : 'none';
            });
        });
    });
    
    // Search logic
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const term = this.value.toLowerCase();
        const rows = document.querySelectorAll('#inventoryTable tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });
</script>

<?php include_once '../../includes/footer.php'; ?>

<script src="../../assets/js/dashboard-reengineered.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality with debouncing
    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    const tableRows = document.querySelectorAll('#inventoryTable tbody tr');
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const searchText = this.value.toLowerCase();
            
            tableRows.forEach(row => {
                const medicineName = row.cells[0].textContent.toLowerCase();
                const molecule = row.cells[1].textContent.toLowerCase();
                const pharma = row.cells[3].textContent.toLowerCase();
                
                if (medicineName.includes(searchText) || molecule.includes(searchText) || pharma.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }, 300);
    });

    // Filter functionality
    const filterPills = document.querySelectorAll('.filter-pill');
    
    filterPills.forEach(pill => {
        pill.addEventListener('click', function() {
            // Remove active class from all pills
            filterPills.forEach(p => p.classList.remove('active'));
            // Add active class to clicked pill
            this.classList.add('active');
            
            const filter = this.getAttribute('data-filter');
            
            tableRows.forEach(row => {
                const stockStatus = row.getAttribute('data-stock');
                const expiryStatus = row.getAttribute('data-expiry');
                let show = false;
                
                switch(filter) {
                    case 'all':
                        show = true;
                        break;
                    case 'in-stock':
                        show = stockStatus !== 'out';
                        break;
                    case 'low-stock':
                        show = stockStatus === 'low';
                        break;
                    case 'out-of-stock':
                        show = stockStatus === 'out';
                        break;
                    case 'expiring':
                        show = expiryStatus === 'expiring';
                        break;
                    case 'expired':
                        show = expiryStatus === 'expired';
                        break;
                }
                
                row.style.display = show ? '' : 'none';
            });
        });
    });

    // Edit button functionality
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            fetch('get_medicine.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_id_inventario').value = data.id_inventario;
                    document.getElementById('edit_nom_medicamento').value = data.nom_medicamento;
                    document.getElementById('edit_mol_medicamento').value = data.mol_medicamento;
                    document.getElementById('edit_presentacion_med').value = data.presentacion_med;
                    document.getElementById('edit_casa_farmaceutica').value = data.casa_farmaceutica;
                    document.getElementById('edit_cantidad_med').value = data.cantidad_med;
                    document.getElementById('edit_fecha_adquisicion').value = data.fecha_adquisicion;
                    document.getElementById('edit_fecha_vencimiento').value = data.fecha_vencimiento;
                })
                .catch(error => {
                    Swal.fire('Error', 'No se pudo cargar la información del medicamento', 'error');
                });
        });
    });

    // Delete button functionality
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Esta acción no se puede revertir",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                background: 'rgba(255, 255, 255, 0.95)',
                backdrop: 'rgba(0,0,0,0.4)'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'delete_medicine.php?id=' + id;
                }
            });
        });
    });
    
    // Set today's date as default for acquisition date
    document.getElementById('fecha_adquisicion').valueAsDate = new Date();
    
    // Add animation to table rows on load
    const rows = document.querySelectorAll('#inventoryTable tbody tr');
    rows.forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'translateY(20px)';
        setTimeout(() => {
            row.style.transition = 'all 0.5s ease';
            row.style.opacity = '1';
            row.style.transform = 'translateY(0)';
        }, index * 50);
    });
});
</script>
