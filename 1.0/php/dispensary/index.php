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
    
    // Get inventory items for the sale form
    $stmt = $conn->prepare("
        SELECT id_inventario, nom_medicamento, mol_medicamento, 
               presentacion_med, casa_farmaceutica, cantidad_med
        FROM inventario
        WHERE cantidad_med > 0
        ORDER BY nom_medicamento
    ");
    $stmt->execute();
    $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $page_title = "Ventas de Medicamentos - Clínica";
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
                    <h2>Ventas de Medicamentos</h2>
                </div>
            </div>
            
            <!-- Sale form directly on the page instead of in a modal -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Nueva Venta</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre del Cliente/Paciente</label>
                            <input type="text" class="form-control" id="nombre_cliente" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo de Pago</label>
                            <select class="form-select" id="tipo_pago" required>
                                <option value="Efectivo">Efectivo</option>
                                <option value="Tarjeta">Tarjeta</option>
                                <option value="Seguro Médico">Seguro Médico</option>
                            </select>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6>Agregar Productos</h6>
                    <div class="row mb-3">
                        <div class="col-md-5">
                            <label class="form-label">Medicamento</label>
                            <input type="text" class="form-control" id="buscar_medicamento" placeholder="Buscar por nombre o molécula...">
                            <div id="resultados_busqueda" class="list-group mt-2 position-absolute" style="z-index: 1000; width: 90%;"></div>
                            <input type="hidden" id="id_medicamento_seleccionado">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cantidad</label>
                            <input type="number" class="form-control" id="cantidad" min="1" value="1">
                            <div class="form-text">Disponible: <span id="cantidad_disponible">0</span></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Precio Unitario (Q)</label>
                            <input type="number" class="form-control" id="precio_unitario" min="0.01" step="0.01">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="button" class="btn btn-primary" id="agregar_item">
                                <i class="bi bi-plus"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-sm" id="items_table">
                            <thead>
                                <tr>
                                    <th>Medicamento</th>
                                    <th>Cantidad</th>
                                    <th>Precio Unit.</th>
                                    <th>Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Items will be added here dynamically -->
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">Total:</th>
                                    <th>Q<span id="total_venta">0.00</span></th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="button" class="btn btn-primary" id="guardar_venta">Imprimir</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Print Template (Hidden) -->
<div id="receipt-template" style="display: none;">
    <!-- Keep the receipt template as is -->
</div>

<?php include_once '../../includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Remove the modal show code since we're not using a modal anymore
    
    // Cart items array
    let cartItems = [];
    
    // Store all inventory items for search
    const inventarioItems = <?php echo json_encode($inventario); ?>;
    
    // Real-time search for medications
    const buscarMedicamento = document.getElementById('buscar_medicamento');
    const resultadosBusqueda = document.getElementById('resultados_busqueda');
    
    buscarMedicamento.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase().trim();
        resultadosBusqueda.innerHTML = '';
        
        if (searchTerm.length < 2) {
            resultadosBusqueda.style.display = 'none';
            return;
        }
        
        const filteredItems = inventarioItems.filter(item => 
            item.nom_medicamento.toLowerCase().includes(searchTerm) || 
            item.mol_medicamento.toLowerCase().includes(searchTerm)
        );
        
        if (filteredItems.length > 0) {
            resultadosBusqueda.style.display = 'block';
            
            filteredItems.slice(0, 5).forEach(item => {
                const div = document.createElement('div');
                div.className = 'list-group-item list-group-item-action';
                div.setAttribute('data-id', item.id_inventario); // Add data-id attribute
                div.innerHTML = `
                    <div class="d-flex justify-content-between">
                        <div>
                            <strong>${item.nom_medicamento}</strong> - ${item.presentacion_med}
                            <br><small>${item.mol_medicamento}</small>
                        </div>
                        <div class="text-end">
                            <small>Disponible: ${item.cantidad_med}</small>
                        </div>
                    </div>
                `;
                
                div.addEventListener('click', async function() {
                    // Set selected medication
                    document.getElementById('id_medicamento_seleccionado').value = item.id_inventario;
                    buscarMedicamento.value = `${item.nom_medicamento} - ${item.presentacion_med}`;
                    document.getElementById('cantidad_disponible').textContent = item.cantidad_med;
                    document.getElementById('cantidad').max = item.cantidad_med;
                    
                    // Get price from compras table
                    const precio = await getPrecioCompra(item.id_inventario);
                    document.getElementById('precio_unitario').value = precio > 0 ? precio.toFixed(2) : '5.00';
                    
                    // Hide search results
                    resultadosBusqueda.style.display = 'none';
                });
                
                resultadosBusqueda.appendChild(div);
            });
        } else {
            resultadosBusqueda.style.display = 'block';
            resultadosBusqueda.innerHTML = '<div class="list-group-item">No se encontraron resultados</div>';
        }
    });
    
    // Hide search results when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== buscarMedicamento && e.target !== resultadosBusqueda) {
            resultadosBusqueda.style.display = 'none';
        }
    });
    
    // Function to get price from compras table
    async function getPrecioCompra(idInventario) {
        try {
            const response = await fetch(`get_precio.php?id_inventario=${idInventario}`);
            const data = await response.json();
            if (data.status === 'success') {
                return data.precio_unidad;
            } else {
                console.error('Error fetching price:', data.message);
                return 0;
            }
        } catch (error) {
            console.error('Error:', error);
            return 0;
        }
    }
    
    // Hide search results when clicking outside - fixed null check
    document.addEventListener('click', function(e) {
        if (e.target !== buscarMedicamento && resultadosBusqueda && !resultadosBusqueda.contains(e.target)) {
            resultadosBusqueda.style.display = 'none';
        }
    });
    
    // Modified event listener for medication selection
    document.addEventListener('click', async function(e) {
        if (e.target.closest('.list-group-item')) {
            const item = e.target.closest('.list-group-item');
            const itemData = inventarioItems.find(i => i.id_inventario == item.getAttribute('data-id'));
            
            if (itemData) {
                // Set selected medication
                document.getElementById('id_medicamento_seleccionado').value = itemData.id_inventario;
                buscarMedicamento.value = `${itemData.nom_medicamento} - ${itemData.presentacion_med}`;
                document.getElementById('cantidad_disponible').textContent = itemData.cantidad_med;
                document.getElementById('cantidad').max = itemData.cantidad_med;
                
                // Get price from compras table
                const precio = await getPrecioCompra(itemData.id_inventario);
                document.getElementById('precio_unitario').value = precio > 0 ? precio.toFixed(2) : '5.00';
                
                // Hide search results
                resultadosBusqueda.style.display = 'none';
            }
        }
    });
    
    // Add item to cart
    document.getElementById('agregar_item').addEventListener('click', function() {
        const idMedicamento = document.getElementById('id_medicamento_seleccionado').value;
        
        if (!idMedicamento) {
            alert('Por favor seleccione un medicamento');
            return;
        }
        
        const cantidad = parseInt(document.getElementById('cantidad').value);
        const disponible = parseInt(document.getElementById('cantidad_disponible').textContent);
        const precioUnitario = parseFloat(document.getElementById('precio_unitario').value);
        
        if (isNaN(cantidad) || cantidad <= 0) {
            alert('Por favor ingrese una cantidad válida');
            return;
        }
        
        if (cantidad > disponible) {
            alert('La cantidad solicitada excede la disponible en inventario');
            return;
        }
        
        if (isNaN(precioUnitario) || precioUnitario <= 0) {
            alert('Por favor ingrese un precio válido');
            return;
        }
        
        // Find selected medication
        const selectedMed = inventarioItems.find(item => item.id_inventario == idMedicamento);
        
        if (!selectedMed) {
            alert('Medicamento no encontrado');
            return;
        }
        
        // Check if item already exists in cart
        const existingItemIndex = cartItems.findIndex(item => item.id_inventario === idMedicamento);
        
        if (existingItemIndex !== -1) {
            // Update quantity if item exists
            const newQuantity = cartItems[existingItemIndex].cantidad + cantidad;
            if (newQuantity > disponible) {
                alert('La cantidad total excede la disponible en inventario');
                return;
            }
            cartItems[existingItemIndex].cantidad = newQuantity;
            cartItems[existingItemIndex].subtotal = newQuantity * precioUnitario;
        } else {
            // Add new item to cart
            cartItems.push({
                id_inventario: idMedicamento,
                nombre: selectedMed.nom_medicamento,
                presentacion: selectedMed.presentacion_med,
                cantidad: cantidad,
                precio_unitario: precioUnitario,
                subtotal: cantidad * precioUnitario
            });
        }
        
        // Update cart display
        updateCartDisplay();
        
        // Reset form
        document.getElementById('id_medicamento_seleccionado').value = '';
        buscarMedicamento.value = '';
        document.getElementById('cantidad').value = '1';
        document.getElementById('precio_unitario').value = '';
        document.getElementById('cantidad_disponible').textContent = '0';
    });
    
    // Update cart display function
    function updateCartDisplay() {
        const tbody = document.querySelector('#items_table tbody');
        tbody.innerHTML = '';
        
        let total = 0;
        
        cartItems.forEach((item, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${item.nombre} (${item.presentacion})</td>
                <td>${item.cantidad}</td>
                <td>$${item.precio_unitario.toFixed(2)}</td>
                <td>$${item.subtotal.toFixed(2)}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger remove-item" data-index="${index}">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            
            total += item.subtotal;
        });
        
        document.getElementById('total_venta').textContent = total.toFixed(2);
        
        // Add event listeners to remove buttons
        document.querySelectorAll('.remove-item').forEach(button => {
            button.addEventListener('click', function() {
                const index = parseInt(this.getAttribute('data-index'));
                cartItems.splice(index, 1);
                updateCartDisplay();
            });
        });
    }
    
    // Save sale and print receipt
    document.getElementById('guardar_venta').addEventListener('click', function() {
        const nombreCliente = document.getElementById('nombre_cliente').value.trim();
        const tipoPago = document.getElementById('tipo_pago').value;
        
        if (!nombreCliente) {
            alert('Por favor ingrese el nombre del cliente');
            return;
        }
        
        if (cartItems.length === 0) {
            alert('Por favor agregue al menos un producto a la venta');
            return;
        }
        
        // Calculate total
        const total = cartItems.reduce((sum, item) => sum + item.subtotal, 0);
        
        // Prepare data for AJAX request
        const ventaData = {
            nombre_cliente: nombreCliente,
            tipo_pago: tipoPago,
            total: total,
            estado: 'Pagado',
            items: cartItems
        };
        
        // Send AJAX request
        fetch('save_venta.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(ventaData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Open print window
                const printWindow = window.open(`print_receipt.php?id=${data.id_venta}`, '_blank', 'width=800,height=600');
                
                // Reset form and cart
                document.getElementById('nombre_cliente').value = '';
                cartItems = [];
                updateCartDisplay();
                
                // No need to close modal since we're not using one
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al guardar la venta');
        });
    });
    
    // Prepare receipt with sale data
    function prepareReceipt(cliente, tipoPago, items, total) {
        // Set receipt date and time
        const now = new Date();
        const formattedDate = now.toLocaleDateString('es-ES');
        const formattedTime = now.toLocaleTimeString('es-ES');
        document.getElementById('receipt-datetime').textContent = `Fecha: ${formattedDate} Hora: ${formattedTime}`;
        
        // Set customer and payment info
        document.getElementById('receipt-cliente').textContent = cliente;
        document.getElementById('receipt-tipo-pago').textContent = tipoPago;
        
        // Set items
        const tbody = document.querySelector('#receipt-items tbody');
        tbody.innerHTML = '';
        
        items.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="text-align: left;">${item.nombre}</td>
                <td style="text-align: right;">${item.cantidad}</td>
                <td style="text-align: right;">$${item.precio_unitario.toFixed(2)}</td>
                <td style="text-align: right;">$${item.subtotal.toFixed(2)}</td>
            `;
            tbody.appendChild(tr);
        });
        
        // Set total
        document.getElementById('receipt-total').textContent = `$${total.toFixed(2)}`;
    }
});
</script>