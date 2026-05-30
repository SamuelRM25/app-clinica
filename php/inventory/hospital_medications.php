<?php
// inventory/hospital_medications.php - Módulo de Inventario Reingenierizado
// Centro Médico RS - Sistema de Gestión Médica

session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Incluir configuraciones y funciones
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/breadcrumbs.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

// Establecer zona horaria
date_default_timezone_set('America/Guatemala');
verify_session();

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener información del usuario
    $user_id = $_SESSION['user_id'];
    $user_type = $_SESSION['tipoUsuario'];
    $can_manage_inventory = ($user_type === 'admin' || in_array($user_id, [1, 6]));

    // Obtener los medicamentos de hospitalización
    $stmt = $conn->prepare("
        SELECT 
            ch.id_cargo, 
            ch.descripcion as cargo_descripcion, 
            ch.cantidad as cargo_cantidad, 
            ch.fecha_cargo, 
            ch.referencia_id, 
            p.nombre as nombre_paciente, 
            p.apellido as apellido_paciente,
            i.nom_medicamento as inv_medicamento,
            u.nombre as registrado_por_nombre
        FROM cargos_hospitalarios ch
        JOIN cuenta_hospitalaria cu ON ch.id_cuenta = cu.id_cuenta
        JOIN encamamientos e ON cu.id_encamamiento = e.id_encamamiento
        JOIN pacientes p ON e.id_paciente = p.id_paciente
        LEFT JOIN usuarios u ON ch.registrado_por = u.idUsuario
        LEFT JOIN inventario i ON ch.referencia_id = i.id_inventario AND ch.referencia_tabla = 'inventario'
        WHERE ch.tipo_cargo IN ('Medicamento', 'Insumo') AND ch.cancelado = 0 AND ch.id_hospital = ?
        ORDER BY ch.fecha_cargo DESC
    ");
    $stmt->execute([$id_hospital]);
    $hospital_meds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener catalogo de inventario para el modal
    $stmt_inv = $conn->prepare("SELECT id_inventario, nom_medicamento, presentacion_med, stock_hospital FROM inventario WHERE id_hospital = ? ORDER BY nom_medicamento ASC");
    $stmt_inv->execute([$id_hospital]);
    $inventory_list = $stmt_inv->fetchAll(PDO::FETCH_ASSOC);

    $page_title = "Medicamentos Hospitalarios - Inventario";

} catch (Throwable $e) {
    error_log("Error en medicamentos hospitalarios: " . $e->getMessage());
    error_log('Error en php/inventory/hospital_medications.php: ' . $e->getMessage());
    echo "DEBUG ERROR: " . 'Error del servidor.' . " at " . $e->getFile() . ":" . $e->getLine();
    die("<br>Error al cargar la información. contacte al administrador.");
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    <!-- Google Fonts -->
<!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">

    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
</head>

<body>
    <div class="dashboard-header">
        <div class="d-flex align-items-center gap-3">
            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Volver al Inventario
            </a>
            <h4 class="mb-0 fw-bold">Medicamentos Hospitalarios</h4>
        </div>
    </div>

    <div class="main-content">
        <?php render_breadcrumbs([
            ['label' => 'Dashboard', 'url' => '../dashboard/index.php'],
            ['label' => 'Inventario', 'url' => 'index.php'],
            ['label' => 'Medicamentos Hospitalarios'],
        ]); ?>
        <div class="appointments-section">
            <h5 class="mb-4"><i class="bi bi-hospital me-2 text-primary"></i> Registro de Administración a Pacientes
            </h5>

            <div class="table-responsive">
                <table class="table table-hover align-middle" id="hospitalMedsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Paciente</th>
                            <th>Descripción (Hospitalización)</th>
                            <th>Cantidad</th>
                            <th>Estado</th>
                            <th>Registrado Por</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($hospital_meds as $med): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($med['fecha_cargo'])); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($med['nombre_paciente'] . ' ' . $med['apellido_paciente']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($med['cargo_descripcion']); ?>
                                        <?php if ($med['referencia_id'])
                                            echo '<br><small class="text-success">Vinculado: ' . htmlspecialchars($med['inv_medicamento']) . '</small>'; ?>
                                    </td>
                                    <td><span class="badge bg-secondary"><?php echo $med['cargo_cantidad']; ?></span></td>
                                    <td>
                                        <?php if ($med['referencia_id']): ?>
                                                <span class="status-badge status-linked"><i class="bi bi-check-circle"></i>
                                                    Descargado</span>
                                        <?php else: ?>
                                                <span class="status-badge status-pending"><i class="bi bi-clock"></i> Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small
                                            class="text-muted"><?php echo htmlspecialchars($med['registrado_por_nombre'] ?? 'Sistema'); ?></small>
                                    </td>
                                    <td>
                                        <?php if (!$med['referencia_id'] && $can_manage_inventory): ?>
                                                <button class="btn btn-sm btn-primary"
                                                    onclick="openLinkModal(<?php echo $med['id_cargo']; ?>, '<?php echo htmlspecialchars(addslashes($med['cargo_descripcion'])); ?>', <?php echo $med['cargo_cantidad']; ?>)">
                                                    <i class="bi bi-link-45deg"></i> Asignar
                                                </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal para vincular a inventario -->
    <div class="modal fade" id="linkInventoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-seam text-primary me-2"></i> Asignar a Inventario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="linkInventoryForm" action="api/link_hospital_medication.php" method="POST">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id_cargo" id="link_id_cargo">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <strong>Descarga de Inventario</strong><br>
                            Al confirmar, se descontará la cantidad del <strong>Stock Hospital</strong> del medicamento
                            seleccionado.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Medicamento Administrado</label>
                            <input type="text" class="form-control" id="link_cargo_desc" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Buscar Medicamento en Inventario</label>
                            <select class="form-select" name="id_inventario" id="link_id_inventario" required>
                                <option value="">Seleccione un medicamento...</option>
                                <?php foreach ($inventory_list as $inv): ?>
                                        <option value="<?php echo $inv['id_inventario']; ?>">
                                            <?php echo htmlspecialchars($inv['nom_medicamento'] . ' ' . $inv['presentacion_med'] . ' (Hosp: ' . $inv['stock_hospital'] . ')'); ?>
                                        </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Cantidad a Descontar</label>
                            <input type="number" class="form-control" name="cantidad" id="link_cantidad" required
                                min="1" step="0.01">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnConfirmLink">Confirmar y Descontar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function () {
            $('#hospitalMedsTable').DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                },
                order: [[0, 'desc']],
                pageLength: 25
            });

            // Handle Form submission with AJAX
            $('#linkInventoryForm').on('submit', function (e) {
                e.preventDefault();

                const btn = $('#btnConfirmLink');
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...');

                $.ajax({
                    type: 'POST',
                    url: $(this).attr('action'),
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Éxito!',
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Ocurrió un error al procesar la solicitud.'
                            });
                            btn.prop('disabled', false).html('Confirmar y Descontar');
                        }
                    },
                    error: function () {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de servidor',
                            text: 'No se pudo comunicar con el servidor.'
                        });
                        btn.prop('disabled', false).html('Confirmar y Descontar');
                    }
                });
            });
        });

        function openLinkModal(idCargo, desc, cant) {
            $('#link_id_cargo').val(idCargo);
            $('#link_cargo_desc').val(desc);
            $('#link_cantidad').val(cant);
            $('#link_id_inventario').val(''); // reset
            var myModal = new bootstrap.Modal(document.getElementById('linkInventoryModal'));
            myModal.show();
        }
    </script>
</body>

</html>