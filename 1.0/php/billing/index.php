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
    
    // Get all patients for the dropdown
    $stmt = $conn->prepare("SELECT id_paciente, CONCAT(nombre, ' ', apellido) as nombre_completo FROM pacientes ORDER BY nombre");
    $stmt->execute();
    $pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all billings with pagination
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    // Get total count for pagination
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM cobros");
    $stmt->execute();
    $total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Get billings data with patient name
    $stmt = $conn->prepare("
        SELECT c.*, CONCAT(p.nombre, ' ', p.apellido) as nombre_paciente 
        FROM cobros c
        JOIN pacientes p ON c.paciente_cobro = p.id_paciente
        ORDER BY c.fecha_consulta DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $cobros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $page_title = "Cobros - Clínica";
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
                    <h2>Gestión de Cobros</h2>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newBillingModal">
                    <i class="bi bi-plus-circle me-2"></i>Nuevo Cobro
                </button>
            </div>
            
            <!-- Billing Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Paciente</th>
                                    <th>Cantidad</th>
                                    <th>Fecha de Consulta</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($cobros) > 0): ?>
                                    <?php foreach ($cobros as $cobro): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($cobro['nombre_paciente']); ?></td>
                                            <td>$<?php echo number_format($cobro['cantidad_consulta'], 2); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($cobro['fecha_consulta'])); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info view-details" data-bs-toggle="modal" data-bs-target="#viewDetailsModal" data-id="<?php echo $cobro['in_cobro']; ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <a href="print_receipt.php?id=<?php echo $cobro['in_cobro']; ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-printer"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center">No hay cobros registrados</td>
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

<!-- New Billing Modal -->
<div class="modal fade" id="newBillingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Cobro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="newBillingForm">
                    <!-- Patient search functionality -->
                    <div class="mb-3">
                        <label for="paciente_search" class="form-label">Paciente</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="paciente_search" placeholder="Buscar paciente..." autocomplete="off">
                            <button class="btn btn-outline-secondary" type="button" id="searchPatientBtn">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <input type="hidden" id="paciente" name="paciente" required>
                        <div id="pacienteResults" class="list-group mt-2" style="position: absolute; z-index: 1000; width: 93%;"></div>
                        <div id="selectedPatient" class="mt-2 p-2 border rounded d-none">
                            <span id="patientName"></span>
                            <button type="button" class="btn btn-sm btn-link text-danger float-end" id="clearPatient">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cantidad" class="form-label">Cantidad a Cobrar (Q)</label>
                        <input type="number" class="form-control" id="cantidad" name="cantidad" min="0.01" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="fecha_consulta" class="form-label">Fecha de Consulta</label>
                        <input type="date" class="form-control" id="fecha_consulta" name="fecha_consulta" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveBillingBtn">Guardar</button>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles del Cobro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Paciente:</strong> <span id="modal-paciente"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Cantidad:</strong> $<span id="modal-cantidad"></span></p>
                    </div>
                </div>
                
                <div class="mb-3">
                    <p><strong>Fecha de Consulta:</strong> <span id="modal-fecha"></span></p>
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
    // Patient search functionality
    const pacienteSearch = document.getElementById('paciente_search');
    const pacienteInput = document.getElementById('paciente');
    const pacienteResults = document.getElementById('pacienteResults');
    const selectedPatient = document.getElementById('selectedPatient');
    const patientName = document.getElementById('patientName');
    const clearPatient = document.getElementById('clearPatient');
    const searchPatientBtn = document.getElementById('searchPatientBtn');
    
    // Search for patients as user types
    pacienteSearch.addEventListener('input', function() {
        const searchTerm = this.value.trim();
        
        if (searchTerm.length < 2) {
            pacienteResults.innerHTML = '';
            pacienteResults.classList.add('d-none');
            return;
        }
        
        fetch(`search_patients.php?term=${encodeURIComponent(searchTerm)}`)
            .then(response => response.json())
            .then(data => {
                pacienteResults.innerHTML = '';
                
                if (data.length === 0) {
                    pacienteResults.innerHTML = '<div class="list-group-item">No se encontraron pacientes</div>';
                } else {
                    data.forEach(patient => {
                        const item = document.createElement('a');
                        item.href = '#';
                        item.className = 'list-group-item list-group-item-action';
                        item.textContent = patient.nombre_completo;
                        item.addEventListener('click', function(e) {
                            e.preventDefault();
                            selectPatient(patient.id_paciente, patient.nombre_completo);
                        });
                        pacienteResults.appendChild(item);
                    });
                }
                
                pacienteResults.classList.remove('d-none');
            })
            .catch(error => {
                console.error('Error:', error);
            });
    });
    
    // Search button click
    searchPatientBtn.addEventListener('click', function() {
        if (pacienteSearch.value.trim().length >= 2) {
            const event = new Event('input');
            pacienteSearch.dispatchEvent(event);
        }
    });
    
    // Select a patient from results
    function selectPatient(id, name) {
        pacienteInput.value = id;
        patientName.textContent = name;
        selectedPatient.classList.remove('d-none');
        pacienteSearch.value = '';
        pacienteResults.innerHTML = '';
        pacienteResults.classList.add('d-none');
    }
    
    // Clear selected patient
    clearPatient.addEventListener('click', function() {
        pacienteInput.value = '';
        patientName.textContent = '';
        selectedPatient.classList.add('d-none');
        pacienteSearch.focus();
    });
    
    // Close results when clicking outside
    document.addEventListener('click', function(e) {
        if (!pacienteSearch.contains(e.target) && !pacienteResults.contains(e.target) && !searchPatientBtn.contains(e.target)) {
            pacienteResults.classList.add('d-none');
        }
    });
    
    // Save new billing
    document.getElementById('saveBillingBtn').addEventListener('click', function() {
        const form = document.getElementById('newBillingForm');
        
        // Basic validation
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Get form data
        const formData = new FormData(form);
        
        // Convert to JSON
        const data = {
            paciente: document.getElementById('paciente').value,
            cantidad: document.getElementById('cantidad').value,
            fecha_consulta: document.getElementById('fecha_consulta').value
        };
        
        // Send data to server
        fetch('save_billing.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert('Cobro guardado correctamente');
                // Close modal and reload page
                const modal = bootstrap.Modal.getInstance(document.getElementById('newBillingModal'));
                modal.hide();
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al guardar el cobro');
        });
    });
    
    // View details modal
    const viewDetailsButtons = document.querySelectorAll('.view-details');
    
    viewDetailsButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            
            // Fetch billing details
            fetch(`get_billing_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Populate modal with billing details
                        document.getElementById('modal-paciente').textContent = data.cobro.nombre_paciente;
                        document.getElementById('modal-cantidad').textContent = parseFloat(data.cobro.cantidad_consulta).toFixed(2);
                        document.getElementById('modal-fecha').textContent = data.cobro.fecha_formateada;
                        
                        // Set print button URL
                        document.getElementById('modal-print-btn').href = `print_receipt.php?id=${id}`;
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al cargar los detalles del cobro');
                });
        });
    });
    
    // Remove mark as paid functionality since we're not tracking estado anymore
});
</script>