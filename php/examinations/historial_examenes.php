<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();
$page_title = "Historial de Exámenes";
include_once '../../includes/header.php';

$limit = 20; // Registros por página
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page > 1) ? ($page - 1) * $limit : 0;

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener total de registros
    $stmt_count = $conn->query("SELECT COUNT(*) as total FROM examenes_realizados");
    $total_registros = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    $total_paginas = ceil($total_registros / $limit);

    // Obtener exámenes paginados
    $stmt = $conn->prepare("
        SELECT id_examen_realizado, nombre_paciente, tipo_examen, cobro, fecha_examen 
        FROM examenes_realizados 
        ORDER BY fecha_examen DESC 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $examenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $examenes = [];
    $total_paginas = 1;
    $error_message = "Error de conexión: " . $e->getMessage();
}
?>

<!-- Styles -->
<link rel="stylesheet" href="../../assets/css/dashboard-reengineered.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
<!-- Datepicker -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<div class="dashboard-wrapper">
    <!-- Mobile Overlay -->
    <div class="dashboard-mobile-overlay"></div>

    <!-- Sidebar Reengineered -->
    <div class="sidebar-glass p-3 d-flex flex-column">
        <div class="brand-section">
            <div class="d-flex align-items-center mb-3 mb-md-0 me-md-auto text-decoration-none">
                <img src="../../assets/img/siloe.png" alt="Logo" style="height: 40px; margin-right: 15px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));">
                <!-- <span class="brand-logo-text">SILOÉ</span> -->
            </div>
        </div>
        
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="../dashboard/index.php" class="nav-link">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
            </li>
            
            <?php if ($_SESSION['tipoUsuario'] === 'admin' || $_SESSION['tipoUsuario'] === 'user'): ?>
            <li>
                <a href="../appointments/index.php" class="nav-link">
                    <i class="bi bi-calendar"></i>
                    Citas
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($_SESSION['tipoUsuario'] === 'admin' || $_SESSION['tipoUsuario'] === 'doc' || $_SESSION['tipoUsuario'] === 'user'): ?>
            <li>
                <a href="../patients/index.php" class="nav-link">
                    <i class="bi bi-people"></i>
                    Pacientes
                </a>
            </li>
            <?php endif; ?>

            <?php if ($_SESSION['tipoUsuario'] === 'admin' || $_SESSION['tipoUsuario'] === 'user'): ?>
                <li>
                    <a href="../minor_procedures/index.php" class="nav-link">
                        <i class="bi bi-bandaid"></i>
                        Proc. Menores
                    </a>
                </li>
                <li>
                    <a href="../examinations/index.php" class="nav-link active">
                        <i class="bi bi-file-earmark-medical"></i>
                        Exámenes
                    </a>
                </li>
            <?php endif; ?>
            
            <?php if ($_SESSION['tipoUsuario'] === 'admin' || $_SESSION['tipoUsuario'] === 'user'): ?>
            <li>
                <a href="../dispensary/index.php" class="nav-link">
                    <i class="bi bi-calendar-check"></i>
                    Despacho
                </a>
            </li>
            <li>
                <a href="../inventory/index.php" class="nav-link">
                    <i class="bi bi-box-seam"></i>
                    Inventario
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($_SESSION['tipoUsuario'] === 'admin'): ?>
            <li>
                <a href="../purchases/index.php" class="nav-link">
                    <i class="bi bi-cart-plus"></i>
                    Compras
                </a>
            </li>
            <li>
                <a href="../sales/index.php" class="nav-link">
                    <i class="bi bi-shop"></i>
                    Ventas
                </a>
            </li>
            <li>
                <a href="../reports/index.php" class="nav-link">
                    <i class="bi bi-file-earmark-text"></i>
                    Reportes
                </a>
            </li>
            <?php endif; ?>
            
            <?php if ($_SESSION['tipoUsuario'] === 'admin' || $_SESSION['tipoUsuario'] === 'user'): ?>
            <li>
                <a href="../billing/index.php" class="nav-link">
                    <i class="bi bi-cash-coin"></i>
                    Cobros
                </a>
            </li>
            <?php endif; ?>
        </ul>
        
        <div class="mt-auto">
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle p-2 rounded hover-effect" id="dropdownUser1" data-bs-toggle="dropdown" aria-expanded="false" style="color: var(--text-color);">
                    <div class="avatar-circle me-2 bg-primary text-white d-flex align-items-center justify-content-center rounded-circle" style="width: 32px; height: 32px;">
                        <?php echo strtoupper(substr($_SESSION['nombre'], 0, 1)); ?>
                    </div>
                    <strong><?php echo htmlspecialchars($_SESSION['nombre']); ?></strong>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="dropdownUser1">
                    <li><a class="dropdown-item" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content-glass">
        <div class="container-fluid">
            
            <!-- Header -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mb-4 animate__animated animate__fadeInDown">
                <div class="mb-3 mb-md-0">
                    <h2 class="fw-bold text-dark mb-1">Historial de Exámenes</h2>
                    <p class="text-muted mb-0">Visualice actividades y genere reportes</p>
                </div>
                <div>
                     <a href="index.php" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm me-2">
                        <i class="bi bi-arrow-left me-2"></i> Regresar
                    </a>
                    <button type="button" class="btn btn-primary rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#reportModal">
                        <i class="bi bi-file-earmark-pdf-fill me-2"></i> Generar Reporte
                    </button>
                </div>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger border-0 card-glass mb-4"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Table Card -->
             <div class="card-glass animate__animated animate__fadeInUp animate__delay-1s">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="background: transparent;">
                            <thead class="bg-light bg-opacity-50 text-uppercase text-xs font-weight-bolder opacity-7">
                                <tr>
                                    <th class="ps-4">Paciente</th>
                                    <th>Tipo de Examen</th>
                                    <th>Cobro (Q)</th>
                                    <th>Fecha y Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($examenes)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-5 text-muted">
                                            <i class="bi bi-clipboard-x fs-1 d-block mb-3"></i>
                                            No se encontraron registros recientes
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $prev_jornada = null;
                                    foreach ($examenes as $exam): 
                                        // Calcular fecha de jornada (Si es antes de las 8am, pertenece al día anterior)
                                        $timestamp = strtotime($exam['fecha_examen']);
                                        $hora = (int)date('H', $timestamp);
                                        $fecha_base = date('Y-m-d', $timestamp);
                                        
                                        if ($hora < 8) {
                                            $jornada_date = date('Y-m-d', strtotime('-1 day', $timestamp));
                                        } else {
                                            $jornada_date = $fecha_base;
                                        }

                                        // Mostrar divisor si cambia la jornada
                                        if ($jornada_date !== $prev_jornada):
                                            $display_date = date('d/m/Y', strtotime($jornada_date));
                                            // Formato amigable: Hoy, Ayer, o fecha
                                            if ($jornada_date == date('Y-m-d')) {
                                                $display_text = "Jornada de Hoy ($display_date)";
                                            } elseif ($jornada_date == date('Y-m-d', strtotime('-1 day'))) {
                                                $display_text = "Jornada de Ayer ($display_date)";
                                            } else {
                                                $display_text = "Jornada del " . $display_date;
                                            }
                                    ?>
                                        <tr class="table-active bg-primary bg-opacity-10 border-bottom border-primary border-opacity-25">
                                            <td colspan="4" class="py-2 ps-4">
                                                <small class="text-primary fw-bold text-uppercase tracking-wider">
                                                    <i class="bi bi-calendar-range me-2"></i><?php echo $display_text; ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php 
                                        $prev_jornada = $jornada_date;
                                        endif; 
                                    ?>
                                        <tr class="align-middle">
                                            <td class="ps-4">
                                                <div class="d-flex flex-column justify-content-center">
                                                    <h6 class="mb-0 text-sm fw-bold text-dark"><?php echo htmlspecialchars($exam['nombre_paciente']); ?></h6>
                                                </div>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0 text-primary"><?php echo htmlspecialchars($exam['tipo_examen']); ?></p>
                                            </td>
                                            <td>
                                                <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-2 border border-success border-opacity-10 rounded-pill">
                                                    Q<?php echo number_format($exam['cobro'], 2); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-secondary text-xs font-weight-bold">
                                                    <?php echo date('h:i A', strtotime($exam['fecha_examen'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                 <?php if ($total_paginas > 1): ?>
                <div class="card-footer border-0 p-3 bg-transparent">
                    <nav aria-label="Paginación de exámenes">
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link rounded-start border-0 shadow-sm" href="?page=<?php echo $page - 1; ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <li class="page-item active">
                                <span class="page-link border-0 shadow-sm bg-primary border-primary"><?php echo $page; ?></span>
                            </li>

                            <?php if ($page < $total_paginas): ?>
                                <li class="page-item">
                                    <a class="page-link rounded-end border-0 shadow-sm" href="?page=<?php echo $page + 1; ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                 </div>
                 <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- Mobile Toggle Button -->
<button class="mobile-nav-toggle" id="sidebarToggle">
    <i class="bi bi-list fs-4"></i>
</button>

<!-- Report Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg card-glass">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold text-primary"><i class="bi bi-file-earmark-pdf me-2"></i>Reporte por Jornada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3">
                    La jornada comprende desde las <strong>08:00 AM</strong> de la fecha seleccionada hasta las <strong>08:00 AM</strong> del día siguiente.
                </p>
                <div class="form-group mb-4">
                    <label class="form-label fw-bold">Seleccionar Fecha de Jornada</label>
                    <input type="text" class="form-control" id="reportDate" placeholder="Seleccionar fecha...">
                </div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-pill" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary rounded-pill px-4" id="btnGenerateReport">
                    <i class="bi bi-download me-2"></i>Generar y Descargar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="../../assets/js/dashboard-reengineered.js"></script>
<!-- Flatpickr -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<!-- jsPDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Datepicker
    flatpickr("#reportDate", {
        locale: "es",
        dateFormat: "Y-m-d",
        defaultDate: "today",
        allowInput: true
    });

    // Handle Report Generation
    document.getElementById('btnGenerateReport').addEventListener('click', function() {
        const date = document.getElementById('reportDate').value;
        const btn = this;
        
        // Loading state
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generando...';

        fetch(`get_report_data.php?date=${date}`)
            .then(response => response.json())
            .then(res => {
                if (res.status === 'success') {
                    generatePDF(res);
                    bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Hubo un problema al generar el reporte', 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-download me-2"></i>Generar y Descargar';
            });
    });

    function generatePDF(res) {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();
        
        // Colors
        const primaryColor = [58, 86, 183]; // Matches dashboard CSS
        
        doc.setFillColor(...primaryColor);
        doc.rect(0, 0, 210, 40, 'F');
        
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(22);
        doc.setFont('helvetica', 'bold');
        doc.text("CLÍNICA SILOÉ", 105, 18, { align: 'center' });
        
        doc.setFontSize(14);
        doc.setFont('helvetica', 'normal');
        doc.text("Reporte de Exámenes Clínicos", 105, 28, { align: 'center' });
        
        // Metadata Info
        doc.setTextColor(50, 50, 50);
        doc.setFontSize(10);
        doc.setFont('helvetica', 'bold');
        doc.text("Información del Reporte:", 14, 50);
        
        doc.setFont('helvetica', 'normal');
        doc.text(`Jornada Reportada: ${res.metadata.jornada_start} - ${res.metadata.jornada_end}`, 14, 56);
        doc.text(`Generado por: ${res.metadata.generated_by}`, 14, 62);
        doc.text(`Fecha de Creación: ${res.metadata.generated_at}`, 14, 68);

        // Data Table
        const tableBody = res.data.map(item => [
            item.nombre_paciente,
            item.tipo_examen,
            new Date(item.fecha_examen).toLocaleString('es-GT', {
                year: 'numeric',
                month: '2-digit', 
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            }),
            `Q${parseFloat(item.cobro).toFixed(2)}`,
            item.usuario || 'N/A'
        ]);

        doc.autoTable({
            startY: 75,
            head: [['Paciente', 'Examen', 'Fecha y Hora', 'Cobro', 'Usuario']],
            body: tableBody,
            theme: 'grid',
            headStyles: {
                fillColor: primaryColor,
                textColor: [255, 255, 255],
                fontStyle: 'bold'
            },
            columnStyles: {
                0: { cellWidth: 50 },
                1: { cellWidth: 50 },
                2: { cellWidth: 35 },
                3: { cellWidth: 20, halign: 'right' },
                4: { cellWidth: 25 }
            },
            foot: [['', '', 'TOTAL ACUMULADO', `Q${res.total.toFixed(2)}`, '']],
            footStyles: {
                fillColor: [240, 240, 240],
                textColor: [0, 0, 0],
                fontStyle: 'bold',
                halign: 'right'
            }
        });

        // Save
        const fileName = `Reporte_Examenes_Jornada_${document.getElementById('reportDate').value}.pdf`;
        doc.save(fileName);
        
        // Logic for Print Prompt
        const pdfBlob = doc.output('bloburl');
        
        Swal.fire({
            title: 'Reporte Generado',
            text: '¿Desea imprimir el reporte ahora?',
            icon: 'success',
            showCancelButton: true,
            confirmButtonText: 'Sí, Imprimir',
            cancelButtonText: 'Solo Descargar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.open(pdfBlob, '_blank'); 
            }
        });
    }
});
</script>

<?php include_once '../../includes/footer.php'; ?>