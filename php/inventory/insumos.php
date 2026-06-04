<?php
// inventory/insumos.php - Módulo de Insumos
// Centro Médico Herrera Saenz

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';
require_once '../../includes/breadcrumbs.php';

check_module_access('inventory');
verify_session();

$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);

date_default_timezone_set('America/Guatemala');

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener inventario para la búsqueda
    $stmt = $conn->prepare("SELECT id_inventario, nom_medicamento, mol_medicamento, presentacion_med, cantidad_med, precio_venta FROM inventario WHERE cantidad_med > 0 AND id_hospital = ? ORDER BY nom_medicamento ASC");
    $stmt->execute([$id_hospital]);
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("php/inventory/insumos.php error: " . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}

$page_title = "Gestión de Insumos - logo";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo htmlspecialchars($page_title); ?>
    </title>
    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
</head>

<body>
    <div class="main-container">
        <?php render_breadcrumbs([
            ['label' => 'Dashboard', 'url' => '../dashboard/index.php'],
            ['label' => 'Inventario', 'url' => 'index.php'],
            ['label' => 'Descarga de Insumos'],
        ]); ?>
        <div class="card">
            <div class="header d-flex justify-content-between align-items-center">
                <h3 class="mb-0"><i class="bi bi-box-fill me-2"></i>Descarga de Insumos</h3>
                <a href="index.php" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>Volver</a>
            </div>
            <div class="card-body p-4">
                <form id="insumosForm">
                    <div class="mb-4 search-container">
                        <label class="form-label fw-bold">Buscar en Inventario</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchInput" class="form-control"
                                placeholder="Escriba el nombre del medicamento...">
                        </div>
                        <div id="searchResults" class="search-results"></div>
                        <input type="hidden" id="selectedId" name="id_inventario">
                    </div>

                    <div id="selectionPanel" style="display: none;">
                        <div class="alert alert-info border-0 shadow-sm">
                            <div class="fw-bold fs-5" id="displayName">---</div>
                            <div class="small text-muted" id="displayDetails">---</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Disponibles</label>
                                <input type="text" id="displayStock" class="form-control" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Cantidad a Descargar</label>
                                <input type="number" id="inputQuantity" name="cantidad" class="form-control" min="1"
                                    required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Precio Venta (Editable)</label>
                                <div class="input-group">
                                    <span class="input-group-text">Q</span>
                                    <input type="number" id="inputPrice" name="precio_venta" class="form-control"
                                        step="0.01" required>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <button type="button" class="btn btn-secondary me-2" onclick="resetForm()">Cancelar</button>
                            <button type="submit" class="btn btn-primary px-5"><i
                                    class="bi bi-check-circle me-2"></i>Registrar Descarga</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const inventory = <?php echo json_encode($inventory); ?>;
        const searchInput = document.getElementById('searchInput');
        const searchResults = document.getElementById('searchResults');
        const selectionPanel = document.getElementById('selectionPanel');

        searchInput.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }

            const filtered = inventory.filter(item =>
                item.nom_medicamento.toLowerCase().includes(query) ||
                item.mol_medicamento.toLowerCase().includes(query)
            ).slice(0, 10);

            if (filtered.length > 0) {
                searchResults.innerHTML = filtered.map(item => `
                    <div class="search-item" onclick="selectItem(${item.id_inventario})">
                        <div class="fw-bold">${item.nom_medicamento}</div>
                        <div class="small text-muted">${item.mol_medicamento} - ${item.presentacion_med}</div>
                        <div class="small text-primary">Stock: ${item.cantidad_med}</div>
                    </div>
                `).join('');
                searchResults.style.display = 'block';
            } else {
                searchResults.innerHTML = '<div class="p-3 text-muted">No se encontraron productos.</div>';
                searchResults.style.display = 'block';
            }
        });

        function selectItem(id) {
            const item = inventory.find(i => i.id_inventario == id);
            if (!item) return;

            document.getElementById('selectedId').value = item.id_inventario;
            document.getElementById('displayName').innerText = item.nom_medicamento;
            document.getElementById('displayDetails').innerText = `${item.mol_medicamento} - ${item.presentacion_med}`;
            document.getElementById('displayStock').value = item.cantidad_med;
            document.getElementById('inputQuantity').max = item.cantidad_med;
            document.getElementById('inputQuantity').value = 1;
            document.getElementById('inputPrice').value = item.precio_venta;

            searchInput.value = '';
            searchResults.style.display = 'none';
            selectionPanel.style.display = 'block';
        }

        function resetForm() {
            selectionPanel.style.display = 'none';
            document.getElementById('insumosForm').reset();
            document.getElementById('selectedId').value = '';
        }

        document.getElementById('insumosForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const btn = this.querySelector('button[type="submit"]');

            Swal.fire({
                title: '¿Confirmar descarga?',
                text: "Se rebajará el stock del inventario.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#0d6efd',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, registrar'
            }).then((result) => {
                if (result.isConfirmed) {
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Procesando...';
                    fetch('save_insumos.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire('¡Éxito!', data.message, 'success').then(() => {
                                    window.location.reload();
                                });
                            } else {
                                Swal.fire('Error', data.message, 'error');
                                btn.disabled = false;
                                btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Registrar Descarga';
                            }
                        })
                        .catch(error => {
                            Swal.fire('Error', 'Error de conexión con el servidor', 'error');
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Registrar Descarga';
                        });
                }
            });
        });
    </script>
</body>

</html>