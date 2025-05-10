<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Mexico_City'); // Ajusta esto a tu zona horaria local

verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $page_title = "Compras - Clínica";
    include_once '../../includes/header.php';
} catch (Exception $e) {
    die("Error: No se pudo conectar a la base de datos");
}
?>

<div class="d-flex">
    <?php include_once '../../includes/sidebar.php'; ?>

    <div class="main-content flex-grow-1">
        <div class="container-fluid">
            <?php if (isset($_SESSION['purchase_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['purchase_status'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['purchase_message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php 
                unset($_SESSION['purchase_message']);
                unset($_SESSION['purchase_status']);
                ?>
            <?php endif; ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="d-flex align-items-center">
                    <a href="../dashboard/index.php" class="btn btn-outline-secondary me-3">
                        <i class="bi bi-arrow-left"></i> Regresar
                    </a>
                    <h2>Registro de Compras</h2>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPurchaseModal">
                    <i class="bi bi-plus-circle me-2"></i> Nueva Compra
                </button>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Presentación</th>
                                    <th>Molécula</th>
                                    <th>Casa Farmacéutica</th>
                                    <th>Cantidad</th>
                                    <th>Precio Unidad</th>
                                    <th>Fecha de Compra</th>
                                    <th>Abono</th>
                                    <th>Total</th>
                                    <th>Saldo</th>
                                    <th>Tipo de Pago</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->query("SELECT * FROM compras ORDER BY fecha_compra DESC");
                                while ($row = $stmt->fetch()) {
                                    // Define class based on estado_compra
                                    $estado_class = '';
                                    switch($row['estado_compra']) {
                                        case 'Pendiente':
                                            $estado_class = 'text-danger';
                                            break;
                                        case 'Abonado':
                                            $estado_class = 'text-warning';
                                            break;
                                        case 'Completo':
                                            $estado_class = 'text-success';
                                            break;
                                    }
                                    
                                    // Calculate remaining balance
                                    $saldo = $row['total_compra'] - $row['abono_compra'];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['nombre_compra']); ?></td>
                                    <td><?php echo htmlspecialchars($row['presentacion_compra']); ?></td>
                                    <td><?php echo htmlspecialchars($row['molecula_compra']); ?></td>
                                    <td><?php echo htmlspecialchars($row['casa_compra']); ?></td>
                                    <td><?php echo htmlspecialchars($row['cantidad_compra']); ?></td>
                                    <td>$<?php echo number_format($row['precio_unidad'], 2); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($row['fecha_compra'])); ?></td>
                                    <td>$<?php echo number_format($row['abono_compra'], 2); ?></td>
                                    <td>$<?php echo number_format($row['total_compra'], 2); ?></td>
                                    <td>$<?php echo number_format($saldo, 2); ?></td>
                                    <td><?php echo htmlspecialchars($row['tipo_pago']); ?></td>
                                    <td class="<?php echo $estado_class; ?>"><?php echo htmlspecialchars($row['estado_compra']); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary edit-btn" 
                                                data-id="<?php echo $row['id_compras']; ?>"
                                                data-bs-toggle="modal" data-bs-target="#editPurchaseModal">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger delete-btn"
                                                data-id="<?php echo $row['id_compras']; ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
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
</div>

<!-- Add Purchase Modal -->
<div class="modal fade" id="addPurchaseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nueva Compra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addPurchaseForm" action="save_purchase.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="nombre_compra" class="form-label">Nombre del Medicamento</label>
                        <input type="text" class="form-control" id="nombre_compra" name="nombre_compra" required>
                    </div>
                    <div class="mb-3">
                        <label for="presentacion_compra" class="form-label">Presentación</label>
                        <input type="text" class="form-control" id="presentacion_compra" name="presentacion_compra" required>
                    </div>
                    <div class="mb-3">
                        <label for="molecula_compra" class="form-label">Molécula</label>
                        <input type="text" class="form-control" id="molecula_compra" name="molecula_compra" required>
                    </div>
                    <div class="mb-3">
                        <label for="casa_compra" class="form-label">Casa Farmacéutica</label>
                        <input type="text" class="form-control" id="casa_compra" name="casa_compra" required>
                    </div>
                    <div class="mb-3">
                        <label for="cantidad_compra" class="form-label">Cantidad</label>
                        <input type="number" class="form-control" id="cantidad_compra" name="cantidad_compra" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="precio_unidad" class="form-label">Precio por Unidad (Q)</label>
                        <input type="number" step="0.01" class="form-control" id="precio_unidad" name="precio_unidad" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="fecha_compra" class="form-label">Fecha de Compra</label>
                        <input type="date" class="form-control" id="fecha_compra" name="fecha_compra" required>
                    </div>
                    <div class="mb-3">
                        <label for="abono_compra" class="form-label">Abono (Q)</label>
                        <input type="number" step="0.01" class="form-control" id="abono_compra" name="abono_compra" min="0" value="0">
                    </div>
                    <div class="mb-3">
                        <label for="total_compra" class="form-label">Total (Q)</label>
                        <input type="number" step="0.01" class="form-control" id="total_compra" name="total_compra" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="saldo_compra" class="form-label">Saldo Pendiente (Q)</label>
                        <input type="number" step="0.01" class="form-control" id="saldo_compra" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="tipo_pago" class="form-label">Tipo de Pago</label>
                        <select class="form-select" id="tipo_pago" name="tipo_pago" required>
                            <option value="">Seleccione...</option>
                            <option value="Al Contado">Al Contado</option>
                            <option value="Credito 30">Credito a 30 días</option>
                            <option value="Credito 60">Credito a 60 días</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="estado_compra" class="form-label">Estado de Pago</label>
                        <select class="form-select" id="estado_compra" name="estado_compra" required>
                            <option value="">Seleccione...</option>
                            <option value="Pendiente">Pendiente</option>
                            <option value="Abonado">Abonado</option>
                            <option value="Completo">Completo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Purchase Modal -->
<div class="modal fade" id="editPurchaseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Compra</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editPurchaseForm" action="update_purchase.php" method="POST">
                <input type="hidden" name="id_compras" id="edit_id_compras">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_nombre_compra" class="form-label">Nombre del Medicamento</label>
                        <input type="text" class="form-control" id="edit_nombre_compra" name="nombre_compra" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_presentacion_compra" class="form-label">Presentación</label>
                        <input type="text" class="form-control" id="edit_presentacion_compra" name="presentacion_compra" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_molecula_compra" class="form-label">Molécula</label>
                        <input type="text" class="form-control" id="edit_molecula_compra" name="molecula_compra" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_casa_compra" class="form-label">Casa Farmacéutica</label>
                        <input type="text" class="form-control" id="edit_casa_compra" name="casa_compra" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_cantidad_compra" class="form-label">Cantidad</label>
                        <input type="number" class="form-control" id="edit_cantidad_compra" name="cantidad_compra" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_precio_unidad" class="form-label">Precio por Unidad (Q)</label>
                        <input type="number" step="0.01" class="form-control" id="edit_precio_unidad" name="precio_unidad" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_fecha_compra" class="form-label">Fecha de Compra</label>
                        <input type="date" class="form-control" id="edit_fecha_compra" name="fecha_compra" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_abono_compra" class="form-label">Abono (Q)</label>
                        <input type="number" step="0.01" class="form-control" id="edit_abono_compra" name="abono_compra" min="0">
                    </div>
                    <div class="mb-3">
                        <label for="edit_total_compra" class="form-label">Total (Q)</label>
                        <input type="number" step="0.01" class="form-control" id="edit_total_compra" name="total_compra" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="edit_saldo_compra" class="form-label">Saldo Pendiente (Q)</label>
                        <input type="number" step="0.01" class="form-control" id="edit_saldo_compra" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="edit_tipo_pago" class="form-label">Tipo de Pago</label>
                        <select class="form-select" id="edit_tipo_pago" name="tipo_pago" required>
                            <option value="">Seleccione...</option>
                            <option value="Al Contado">Al Contado</option>
                            <option value="Credito 30">Credito a 30 días</option>
                            <option value="Credito 60">Credito a 60 días</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_estado_compra" class="form-label">Estado de Pago</label>
                        <select class="form-select" id="edit_estado_compra" name="estado_compra" required>
                            <option value="">Seleccione...</option>
                            <option value="Pendiente">Pendiente</option>
                            <option value="Abonado">Abonado</option>
                            <option value="Completo">Completo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Actualizar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set today's date as default for purchase date
    document.getElementById('fecha_compra').valueAsDate = new Date();
    
    // Calculate total when quantity or price changes (for add form)
    function calculateTotal() {
        const cantidad = parseFloat(document.getElementById('cantidad_compra').value) || 0;
        const precio = parseFloat(document.getElementById('precio_unidad').value) || 0;
        const total = cantidad * precio;
        document.getElementById('total_compra').value = total.toFixed(2);
        
        // Update saldo
        calculateSaldo();
    }
    
    // Calculate saldo when abono changes (for add form)
    function calculateSaldo() {
        const total = parseFloat(document.getElementById('total_compra').value) || 0;
        const abono = parseFloat(document.getElementById('abono_compra').value) || 0;
        const saldo = total - abono;
        document.getElementById('saldo_compra').value = saldo.toFixed(2);
        
        // Auto-update estado_compra based on abono
        const estadoSelect = document.getElementById('estado_compra');
        if (abono <= 0) {
            estadoSelect.value = 'Pendiente';
        } else if (abono < total) {
            estadoSelect.value = 'Abonado';
        } else {
            estadoSelect.value = 'Completo';
        }
    }
    
    document.getElementById('cantidad_compra').addEventListener('input', calculateTotal);
    document.getElementById('precio_unidad').addEventListener('input', calculateTotal);
    document.getElementById('abono_compra').addEventListener('input', calculateSaldo);
    
    // Calculate total when quantity or price changes (for edit form)
    function calculateEditTotal() {
        const cantidad = parseFloat(document.getElementById('edit_cantidad_compra').value) || 0;
        const precio = parseFloat(document.getElementById('edit_precio_unidad').value) || 0;
        const total = cantidad * precio;
        document.getElementById('edit_total_compra').value = total.toFixed(2);
        
        // Update saldo
        calculateEditSaldo();
    }
    
    // Calculate saldo when abono changes (for edit form)
    function calculateEditSaldo() {
        const total = parseFloat(document.getElementById('edit_total_compra').value) || 0;
        const abono = parseFloat(document.getElementById('edit_abono_compra').value) || 0;
        const saldo = total - abono;
        document.getElementById('edit_saldo_compra').value = saldo.toFixed(2);
        
        // Auto-update estado_compra based on abono
        const estadoSelect = document.getElementById('edit_estado_compra');
        if (abono <= 0) {
            estadoSelect.value = 'Pendiente';
        } else if (abono < total) {
            estadoSelect.value = 'Abonado';
        } else {
            estadoSelect.value = 'Completo';
        }
    }
    
    document.getElementById('edit_cantidad_compra').addEventListener('input', calculateEditTotal);
    document.getElementById('edit_precio_unidad').addEventListener('input', calculateEditTotal);
    document.getElementById('edit_abono_compra').addEventListener('input', calculateEditSaldo);
    
    // Handle edit button clicks
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            // Fetch purchase data
            fetch('get_purchase.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_id_compras').value = data.id_compras;
                    document.getElementById('edit_nombre_compra').value = data.nombre_compra;
                    document.getElementById('edit_presentacion_compra').value = data.presentacion_compra;
                    document.getElementById('edit_molecula_compra').value = data.molecula_compra;
                    document.getElementById('edit_casa_compra').value = data.casa_compra;
                    document.getElementById('edit_cantidad_compra').value = data.cantidad_compra;
                    document.getElementById('edit_precio_unidad').value = data.precio_unidad;
                    document.getElementById('edit_fecha_compra').value = data.fecha_compra;
                    document.getElementById('edit_abono_compra').value = data.abono_compra;
                    document.getElementById('edit_total_compra').value = data.total_compra;
                    document.getElementById('edit_tipo_pago').value = data.tipo_pago;
                    document.getElementById('edit_estado_compra').value = data.estado_compra;
                    
                    // Calculate saldo
                    calculateEditSaldo();
                });
        });
    });
    
    // Handle delete button clicks
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            Swal.fire({
                title: '¿Estás seguro?',
                text: "Esta acción no se puede revertir",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'delete_purchase.php?id=' + id;
                }
            });
        });
    });
});
</script>