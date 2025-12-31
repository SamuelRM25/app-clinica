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

    // Ensure reservation table exists
    $conn->exec("CREATE TABLE IF NOT EXISTS reservas_inventario (
        id_reserva INT AUTO_INCREMENT PRIMARY KEY,
        id_inventario INT NOT NULL,
        cantidad INT NOT NULL,
        session_id VARCHAR(255) NOT NULL,
        fecha_reserva TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (id_inventario),
        INDEX (session_id)
    )");

    // Clean up old reservations (> 60 mins)
    $conn->exec("DELETE FROM reservas_inventario WHERE fecha_reserva < (NOW() - INTERVAL 1 HOUR)");
    
    // Get inventory items for the sale form, subtracting RESERVED items
    $stmt = $conn->prepare("
        SELECT i.id_inventario, i.nom_medicamento, i.mol_medicamento, 
               i.presentacion_med, i.casa_farmaceutica, i.cantidad_med,
               (i.cantidad_med - COALESCE((SELECT SUM(cantidad) FROM reservas_inventario WHERE id_inventario = i.id_inventario), 0)) as disponible
        FROM inventario i
        WHERE i.cantidad_med > 0 AND i.estado != 'Pendiente'
        ORDER BY i.nom_medicamento
    ");
    $stmt->execute();
    $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $page_title = "Ventas de Medicamentos - Clínica";
    include_once '../../includes/header.php';
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!-- Inject Dashboard Custom Styles -->
<link rel="stylesheet" href="../../assets/css/dashboard-reengineered.css">

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .pos-container {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 1.5rem;
        height: calc(100vh - 120px);
    }

    .pos-selection-area {
        overflow-y: auto;
        padding-right: 0.5rem;
    }

    .pos-cart-area {
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    .search-results-floating {
        position: absolute;
        top: 100%;
        left: 0;
        right: -10px; /* Expand to the right to use available space */
        z-index: 100; /* Above other cards */
        background: white;
        border-radius: 16px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.15);
        border: 1px solid rgba(0,0,0,0.05);
        overflow: hidden;
        display: none;
        max-height: 500px;
        overflow-y: auto;
    }

    .search-item {
        padding: 12px 16px;
        cursor: pointer;
        transition: background 0.2s;
        border-bottom: 1px solid #f1f5f9;
    }

    .search-item:last-child { border-bottom: none; }
    .search-item:hover { background: #f8fafc; }

    .cart-item-row {
        transition: all 0.2s;
    }

    .cart-item-row:hover {
        background: rgba(37, 99, 235, 0.02);
    }

    .empty-cart-state {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        opacity: 0.5;
        text-align: center;
        padding: 2rem;
    }

    .badge-stock {
        font-size: 0.65rem;
        padding: 2px 8px;
        border-radius: 50px;
    }

    .total-display {
        background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        color: white;
        border-radius: 12px;
        padding: 1.5rem;
        box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.2);
    }

    @media (max-width: 1200px) {
        .pos-container {
            grid-template-columns: 1fr;
            height: auto;
        }
        .pos-selection-area { height: auto; }
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
            <li><a href="../dispensary/index.php" class="nav-link active"><i class="bi bi-cart4"></i> Dispensario</a></li>
            <li><a href="../inventory/index.php" class="nav-link"><i class="bi bi-box-seam"></i> Inventario</a></li>
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
        <div class="container-fluid">
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold text-dark mb-0">Dispensario Médico</h2>
                    <p class="text-muted text-sm mb-0">Venta de Medicamentos e Insumos</p>
                </div>
                <div class="text-end">
                    <span class="badge bg-light text-dark shadow-sm border px-3 py-2 rounded-pill">
                        <i class="bi bi-calendar3 me-2 text-primary"></i><?php echo date('d/m/Y'); ?>
                    </span>
                </div>
            </div>

            <div class="pos-container">
                <!-- Left Column: Selection -->
                <div class="pos-selection-area animate__animated animate__fadeInLeft animate__delay-1s">
                    <div class="card-glass h-100 p-4">
                        <h5 class="fw-bold mb-4 flex-between">
                            <span><i class="bi bi-search me-2 text-primary"></i>Búsqueda de Productos</span>
                        </h5>
                        
                        <div class="position-relative mb-4">
                            <label class="uppercase-label">Nombre o Molécula</label>
                            <div class="input-group shadow-sm rounded-3 overflow-hidden">
                                <span class="input-group-text border-0 bg-white"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control border-0 py-3" id="buscar_medicamento" placeholder="Empieza a escribir para buscar...">
                            </div>
                            <div id="resultados_busqueda" class="search-results-floating"></div>
                            <input type="hidden" id="id_medicamento_seleccionado">
                        </div>

                        <div id="selection_details" class="p-4 rounded-4 bg-white border border-dashed border-2 d-none shadow-sm" style="margin-top: 2rem;">
                            <div class="row g-3 align-items-center">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h4 class="fw-bold text-dark mb-0" id="selected_name">---</h4>
                                            <p class="text-sm text-muted mb-0" id="selected_molecule">---</p>
                                        </div>
                                        <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill px-3 py-1" style="font-size: 0.7rem;">Seleccionado</span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="uppercase-label text-muted" style="font-size: 0.65rem;">Precio Unitario</label>
                                    <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
                                        <span class="input-group-text bg-light border-0">Q</span>
                                        <input type="number" class="form-control border-0 fw-bold bg-light" id="precio_unitario" step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="uppercase-label text-muted" style="font-size: 0.65rem;">Cantidad</label>
                                    <div class="input-group input-group-sm shadow-sm rounded-3 overflow-hidden">
                                        <input type="number" class="form-control border-0 fw-bold" id="cantidad" min="1" value="1">
                                        <span class="input-group-text bg-white border-0 text-muted" style="font-size: 0.65rem;">Disp: <span id="cantidad_disponible" class="ms-1 fw-bold text-primary">0</span></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <button class="btn btn-primary w-100 rounded-3 py-2 shadow-lg animate__animated animate__pulse animate__infinite animate__slow" id="agregar_item">
                                        <i class="bi bi-cart-plus-fill me-1"></i>Añadir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Cart & Checkout -->
                <div class="pos-cart-area">
                    <div class="card-glass h-100 d-flex flex-column overflow-hidden">
                        <div class="p-4 border-bottom bg-light bg-opacity-10">
                            <h5 class="fw-bold mb-3"><i class="bi bi-person-badge me-2 text-primary"></i>Datos de Venta</h5>
                            <div class="mb-3">
                                <label class="uppercase-label">Nombre del Cliente</label>
                                <input type="text" class="form-control rounded-3" id="nombre_cliente" placeholder="Nombre completo...">
                            </div>
                            <div class="mb-0">
                                <label class="uppercase-label">Tipo de Pago</label>
                                <select class="form-select rounded-3" id="tipo_pago">
                                    <option value="Efectivo">Efectivo</option>
                                    <option value="Tarjeta">Tarjeta</option>
                                    <option value="Seguro Médico">Seguro Médico</option>
                                </select>
                            </div>
                        </div>

                        <div class="p-0 flex-grow-1 overflow-y-auto" style="min-height: 100px;">
                            <table class="table table-hover mb-0 d-none" id="items_table_container">
                                <thead class="bg-light sticky-top" style="z-index: 1;">
                                    <tr>
                                        <th class="ps-4">Item</th>
                                        <th class="text-center">Cant.</th>
                                        <th class="text-end pe-4">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="items_table_body">
                                    <!-- Items via JS -->
                                </tbody>
                            </table>
                            
                            <div id="cart_empty_state" class="empty-cart-state">
                                <div class="bg-light rounded-circle p-4 mb-3">
                                    <i class="bi bi-cart-x fs-1 text-muted opacity-50"></i>
                                </div>
                                <h6 class="fw-bold mb-1">Carrito Vacío</h6>
                                <p class="text-xs text-muted mb-0">Busca y añade productos para realizar una venta.</p>
                            </div>
                        </div>

                        <div class="p-4 bg-light bg-opacity-25 border-top">
                            <div class="total-display shadow-lg mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="text-xs opacity-75 uppercase-label text-white">Total a Pagar</span>
                                    <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-25">Ready</span>
                                </div>
                                <div class="d-flex align-items-baseline">
                                    <span class="fs-4 me-1 opacity-75">Q</span>
                                    <h2 class="fw-black mb-0" id="total_venta" style="font-size: 2.25rem;">0.00</h2>
                                </div>
                            </div>

                            <button class="btn btn-primary w-100 py-3 rounded-4 shadow-lg fw-bold fs-5" id="guardar_venta">
                                <i class="bi bi-printer-fill me-2"></i>PROCESAR E IMPRIMIR
                            </button>
                        </div>
                    </div>
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

<script src="../../assets/js/dashboard-reengineered.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Cart items array
    let cartItems = [];
    
    // Store all inventory items for search
    const inventarioItems = <?php echo json_encode($inventario); ?>;
    
    // UI Elements
    const buscarMedicamento = document.getElementById('buscar_medicamento');
    const resultadosBusqueda = document.getElementById('resultados_busqueda');
    const selectionDetails = document.getElementById('selection_details');
    const selectedNameEl = document.getElementById('selected_name');
    const selectedMoleculeEl = document.getElementById('selected_molecule');
    const cantidadDisponibleEl = document.getElementById('cantidad_disponible');
    const precioUnitarioEl = document.getElementById('precio_unitario');
    const cantidadEl = document.getElementById('cantidad');
    const cartEmptyState = document.getElementById('cart_empty_state');
    const itemsTableContainer = document.getElementById('items_table_container');
    const itemsTableBody = document.getElementById('items_table_body');
    const totalVentaEl = document.getElementById('total_venta');
    
    // Real-time search for medications
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
            
            filteredItems.slice(0, 15).forEach(item => {
                const isSelected = cartItems.some(ci => ci.id_inventario == item.id_inventario);
                const hasReservations = item.cantidad_med > item.disponible;
                
                const div = document.createElement('div');
                div.className = `search-item ${isSelected ? 'bg-primary bg-opacity-5' : ''}`;
                div.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="flex-grow-1">
                            <div class="fw-bold text-dark fs-5">${item.nom_medicamento}</div>
                            <div class="text-xs text-muted">
                                <span class="text-primary fw-medium">${item.mol_medicamento}</span> • ${item.presentacion_med}
                            </div>
                            <div class="text-xs text-secondary mt-1">${item.casa_farmaceutica}</div>
                        </div>
                        <div class="text-end ms-3">
                            <div class="badge ${item.disponible > 5 ? 'bg-success' : (item.disponible > 0 ? 'bg-warning' : 'bg-danger')} bg-opacity-10 text-${item.disponible > 5 ? 'success' : (item.disponible > 0 ? 'bg-warning' : 'bg-danger')} p-2 rounded-3 border-0">
                                <div class="text-xs uppercase-label mb-0" style="font-size: 0.6rem;">Disponible</div>
                                <div class="fs-6 fw-bold">${item.disponible}</div>
                            </div>
                            ${hasReservations ? `
                                <div class="text-xs text-warning mt-1" style="font-size: 0.65rem;">
                                    <i class="bi bi-clock-history"></i> ${item.cantidad_med - item.disponible} reserv.
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
                
                div.addEventListener('click', function() {
                    selectItem(item);
                });
                
                resultadosBusqueda.appendChild(div);
            });
        } else {
            resultadosBusqueda.style.display = 'block';
            resultadosBusqueda.innerHTML = '<div class="p-3 text-center text-muted text-sm">No se encontraron resultados</div>';
        }
    });

    window.autoSelect = function(id) {
        const item = inventarioItems.find(i => i.id_inventario == id);
        if (item) selectItem(item);
    };

    async function selectItem(item) {
        document.getElementById('id_medicamento_seleccionado').value = item.id_inventario;
        buscarMedicamento.value = item.nom_medicamento;
        
        selectedNameEl.textContent = item.nom_medicamento + ' (' + item.presentacion_med + ')';
        selectedMoleculeEl.textContent = item.mol_medicamento + ' • ' + item.casa_farmaceutica;
        // Show selection details
        selectionDetails.classList.remove('d-none');
        selectionDetails.classList.add('animate__animated', 'animate__fadeIn');
        
        // Use available stock for max validation
        cantidadDisponibleEl.textContent = item.disponible;
        cantidadEl.max = item.disponible;
        cantidadEl.value = 1;

        // Fetch real price
        const precio = await getPrecioCompra(item.id_inventario);
        precioUnitarioEl.value = precio > 0 ? precio.toFixed(2) : '0.00';
        
        resultadosBusqueda.style.display = 'none';
        precioUnitarioEl.focus();
    }
    
    // Hide search results when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== buscarMedicamento && !resultadosBusqueda.contains(e.target)) {
            resultadosBusqueda.style.display = 'none';
        }
    });
    
    // Function to get price
    async function getPrecioCompra(idInventario) {
        try {
            const response = await fetch(`get_precio.php?id_inventario=${idInventario}`);
            const data = await response.json();
            return data.status === 'success' ? parseFloat(data.precio_venta) : 0;
        } catch (error) {
            console.error('Error:', error);
            return 0;
        }
    }
    
    // Add item to cart
    document.getElementById('agregar_item').addEventListener('click', function() {
        const idMedicamento = document.getElementById('id_medicamento_seleccionado').value;
        if (!idMedicamento) return;
        
        const cantidad = parseInt(cantidadEl.value);
        const precioUnitario = parseFloat(precioUnitarioEl.value);
        const disponible = parseInt(cantidadDisponibleEl.textContent);
        
        if (isNaN(cantidad) || cantidad <= 0 || cantidad > disponible) {
            Swal.fire('Error', 'Cantidad inválida o insuficiente stock', 'error');
            return;
        }
        
        if (isNaN(precioUnitario) || precioUnitario < 0) {
            Swal.fire('Error', 'Precio inválido', 'error');
            return;
        }
        
        const selectedMed = inventarioItems.find(item => item.id_inventario == idMedicamento);
        
        const existingIndex = cartItems.findIndex(item => item.id_inventario === idMedicamento);
        if (existingIndex !== -1) {
            const newCant = cartItems[existingIndex].cantidad + cantidad;
            if (newCant > disponible) {
                Swal.fire('Error', 'Excede stock disponible', 'warning');
                return;
            }
            cartItems[existingIndex].cantidad = newCant;
            cartItems[existingIndex].subtotal = newCant * precioUnitario;
        } else {
            cartItems.push({
                id_inventario: idMedicamento,
                nombre: selectedMed.nom_medicamento,
                presentacion: selectedMed.presentacion_med,
                cantidad: cantidad,
                precio_unitario: precioUnitario,
                subtotal: cantidad * precioUnitario
            });
        }
        
        updateCartDisplay();

        // Sync Reservation with Server
        fetch('reserve_item.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                id_inventario: idMedicamento,
                cantidad: cartItems.find(i => i.id_inventario === idMedicamento).cantidad
            })
        });
        
        // Reset and animate out
        selectionDetails.classList.add('animate__fadeOut');
        setTimeout(() => {
            selectionDetails.classList.add('d-none');
            selectionDetails.classList.remove('animate__animated', 'animate__fadeIn', 'animate__fadeOut');
            document.getElementById('id_medicamento_seleccionado').value = '';
            buscarMedicamento.value = '';
            buscarMedicamento.focus();
        }, 300);
    });
    
    function updateCartDisplay() {
        itemsTableBody.innerHTML = '';
        let total = 0;
        
        if (cartItems.length === 0) {
            cartEmptyState.classList.remove('d-none');
            itemsTableContainer.classList.add('d-none');
        } else {
            cartEmptyState.classList.add('d-none');
            itemsTableContainer.classList.remove('d-none');
            
            cartItems.forEach((item, index) => {
                const tr = document.createElement('tr');
                tr.className = 'cart-item-row animate__animated animate__fadeIn';
                tr.innerHTML = `
                    <td class="ps-4">
                        <div class="fw-bold">${item.nombre}</div>
                        <div class="text-xs text-muted">Q${item.precio_unitario.toFixed(2)} unit.</div>
                    </td>
                    <td class="text-center align-middle">
                        <span class="badge bg-light text-dark border px-3">${item.cantidad}</span>
                    </td>
                    <td class="text-end pe-4 align-middle">
                        <div class="fw-bold">Q${item.subtotal.toFixed(2)}</div>
                        <a href="javascript:void(0)" class="text-danger text-xs remove-item" data-index="${index}">Eliminar</a>
                    </td>
                `;
                itemsTableBody.appendChild(tr);
                total += item.subtotal;
            });
        }
        
        totalVentaEl.textContent = total.toFixed(2);
        
        document.querySelectorAll('.remove-item').forEach(btn => {
            btn.onclick = function() {
                const idx = parseInt(this.getAttribute('data-index'));
                const removedItem = cartItems[idx];
                
                // Release reservation on server
                fetch('release_item.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_inventario: removedItem.id_inventario })
                });

                cartItems.splice(idx, 1);
                updateCartDisplay();
            };
        });
    }
    
    document.getElementById('guardar_venta').addEventListener('click', function() {
        const nombreCliente = document.getElementById('nombre_cliente').value.trim();
        const tipoPago = document.getElementById('tipo_pago').value;
        
        if (!nombreCliente) {
            Swal.fire('Atención', 'Por favor ingrese el nombre del cliente', 'warning');
            return;
        }
        
        if (cartItems.length === 0) {
            Swal.fire('Carrito vacío', 'Añada al menos un producto', 'info');
            return;
        }
        
        const total = cartItems.reduce((sum, item) => sum + item.subtotal, 0);
        
        const ventaData = {
            nombre_cliente: nombreCliente,
            tipo_pago: tipoPago,
            total: total,
            estado: 'Pagado',
            items: cartItems
        };
        
        Swal.fire({
            title: 'Procesando venta...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });
        
        fetch('save_venta.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(ventaData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: '¡Venta completada!',
                    text: 'Se abrirá el comprobante de impresión.',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.open(`print_receipt.php?id=${data.id_venta}`, '_blank', 'width=800,height=600');
                    location.reload();
                });
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'No se pudo guardar la venta', 'error');
        });
    });
});
</script>