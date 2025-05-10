<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Mexico_City'); // Ajusta esto a tu zona horaria local

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

<div class="d-flex">
    <?php include_once '../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center">
                    <a href="../dashboard/index.php" class="btn btn-outline-secondary me-3">
                        <i class="bi bi-arrow-left"></i> Regresar
                    </a>
                    <h2>Registro de Ventas</h2>
                </div>
                <a href="../dispensary/index.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Nueva Venta
                </a>
            </div>
            
            <!-- Sales Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Tipo de Pago</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($ventas) > 0): ?>
                                    <?php foreach ($ventas as $venta): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($venta['fecha_venta'])); ?></td>
                                            <td><?php echo htmlspecialchars($venta['nombre_cliente']); ?></td>
                                            <td><?php echo htmlspecialchars($venta['tipo_pago']); ?></td>
                                            <td>$<?php echo number_format($venta['total'], 2); ?></td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo match($venta['estado']) {
                                                        'Pagado' => 'bg-success',
                                                        'Pendiente' => 'bg-warning',
                                                        'Cancelado' => 'bg-danger',
                                                        default => 'bg-secondary'
                                                    };
                                                ?>">
                                                    <?php echo htmlspecialchars($venta['estado']); ?>
                                                </span>
                                            </td>
                                            <!-- In the table where you display sales -->
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info view-details" data-bs-toggle="modal" data-bs-target="#viewDetailsModal" data-id="<?php echo $venta['id_venta']; ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <a href="../dispensary/print_receipt.php?id=<?php echo $venta['id_venta']; ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No hay ventas registradas</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles de Venta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Cliente:</strong> <span id="modal-cliente"></span></p>
                        <p><strong>Fecha:</strong> <span id="modal-fecha"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Tipo de Pago:</strong> <span id="modal-tipo-pago"></span></p>
                        <p><strong>Estado:</strong> <span id="modal-estado"></span></p>
                    </div>
                </div>
                
                <h6>Productos</h6>
                <div class="table-responsive">
                    <table class="table table-sm" id="modal-items">
                        <thead>
                            <tr>
                                <th>Medicamento</th>
                                <th>Presentación</th>
                                <th>Cantidad</th>
                                <th>Precio Unit.</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Items will be loaded dynamically -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Total:</th>
                                <th id="modal-total"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <a href="#" class="btn btn-primary" id="modal-print-btn" target="_blank">
                    <i class="bi bi-printer me-1"></i> Imprimir
                </a>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // View details modal
    const viewDetailsButtons = document.querySelectorAll('.view-details');
    
    viewDetailsButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            console.log('Viewing sale ID:', id); // Add this for debugging
            
            // Fetch sale details
            fetch(`get_sale_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Populate modal with sale details
                        document.getElementById('modal-cliente').textContent = data.venta.nombre_cliente;
                        document.getElementById('modal-fecha').textContent = data.venta.fecha_formateada;
                        document.getElementById('modal-tipo-pago').textContent = data.venta.tipo_pago;
                        document.getElementById('modal-estado').textContent = data.venta.estado;
                        document.getElementById('modal-total').textContent = '$' + parseFloat(data.venta.total).toFixed(2);
                        
                        // Set print button URL
                        // Make sure this line correctly sets the print URL
                        document.getElementById('modal-print-btn').href = `../dispensary/print_receipt.php?id=${id}`;
                        
                        // Clear previous items
                        const itemsTable = document.getElementById('modal-items').querySelector('tbody');
                        itemsTable.innerHTML = '';
                        
                        // Add items to table
                        data.items.forEach(item => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${item.nom_medicamento}</td>
                                <td>${item.presentacion_med}</td>
                                <td>${item.cantidad_vendida}</td>
                                <td>$${parseFloat(item.precio_unitario).toFixed(2)}</td>
                                <td>$${(item.cantidad_vendida * item.precio_unitario).toFixed(2)}</td>
                            `;
                            itemsTable.appendChild(row);
                        });
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los detalles de la venta');
                });
        });
    });
});
</script>