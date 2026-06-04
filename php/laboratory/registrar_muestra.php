<?php
// laboratory/registrar_muestra.php - Register sample reception
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = hospital_id();

verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get pending orders
    $stmt = $conn->prepare("
        SELECT o.*, p.nombre, p.apellido, p.dpi,
               COUNT(op.id_prueba) as num_pruebas
        FROM ordenes_laboratorio o
        JOIN pacientes p ON o.id_paciente = p.id_paciente
        LEFT JOIN orden_pruebas op ON o.id_orden = op.id_orden
        WHERE o.estado = 'Pendiente' AND o.id_hospital = ?
        GROUP BY o.id_orden
        ORDER BY o.fecha_orden DESC
        LIMIT 50
    ");
    $stmt->execute([$id_hospital]);
    $ordenes_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $page_title = "Registrar Muestra - Laboratorio";
} catch (Exception $e) {
    error_log('Error en laboratory/registrar_muestra.php: ' . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <?php include '../../includes/theme_head.php'; ?>

    <style>
        .order-card {
            background: rgba(var(--color-card-rgb), 0.65);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.25rem;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .order-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
            border-color: var(--color-primary);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .order-number {
            font-weight: 700;
            color: var(--color-primary);
            font-size: 1.15rem;
            letter-spacing: 0.5px;
        }

        .patient-name {
            font-weight: 600;
            color: var(--color-text);
            font-size: 1.05rem;
            margin-top: 0.25rem;
        }
    </style>
</head>

<body>
    <div class="marble-effect"></div>

    <div class="dashboard-container">
        <!-- Header Superior -->
        <header class="dashboard-header">
            <div class="header-content">
                <!-- logo -->
                <div class="brand-container">
                    <img src="../../assets/img/cmhs.png" alt="Centro Médico Herrera Saenz" class="brand-logo" width="40"
                        height="40">
                </div>

                <!-- Controles -->
                <div class="header-controls">
                    <!-- Control de tema -->
                    <div class="theme-toggle">
                        <button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema claro/oscuro">
                            <i class="bi bi-sun theme-icon sun-icon"></i>
                            <i class="bi bi-moon theme-icon moon-icon"></i>
                        </button>
                    </div>

                    <!-- Información del usuario -->
                    <div class="header-user">
                        <div class="header-avatar">
                            <?php echo isset($_SESSION['nombre']) ? strtoupper(substr($_SESSION['nombre'], 0, 1)) : 'U'; ?>
                        </div>
                        <div class="header-details">
                            <span
                                class="header-name"><?php echo htmlspecialchars($_SESSION['nombre'] ?? 'Usuario'); ?></span>
                            <span class="header-role">Laboratorio</span>
                        </div>
                    </div>

                    <!-- Back Button -->
                    <a href="index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i>
                        Volver
                    </a>

                    <!-- Botón de cerrar sesión -->
                    <a href="../auth/logout.php" class="logout-btn">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Salir</span>
                    </a>
                </div>
            </div>
        </header>

        <main class="main-content">
            <div class="welcome-banner animate-in mb-4">
                <h1>Recepcionar Muestras</h1>
                <p>Marque las órdenes cuyas muestras biológicas han sido entregadas al laboratorio</p>
            </div>

            <div class="row">
                <div class="col-12 col-xl-8 mx-auto">
                    <section class="calendar-section animate-in delay-1">
                        <div class="section-header d-flex justify-content-between align-items-center mb-4">
                            <h3 class="section-title">
                                <i class="bi bi-droplet section-title-icon"></i>
                                Muestras Pendientes de Recepción
                            </h3>
                            <span class="badge bg-primary px-3 py-2 rounded-pill">
                                <?php echo count($ordenes_pendientes); ?> Pendientes
                            </span>
                        </div>

                        <?php if (count($ordenes_pendientes) > 0): ?>
                                <div class="pe-2" style="max-height: 70vh; overflow-y: auto;">
                                    <?php foreach ($ordenes_pendientes as $orden): ?>
                                            <div class="order-card animate-in">
                                                <div class="order-header">
                                                    <div>
                                                        <div class="order-number">
                                                            <i
                                                                class="bi bi-hash"></i><?php echo htmlspecialchars($orden['numero_orden']); ?>
                                                        </div>
                                                        <div class="patient-name">
                                                            <?php echo htmlspecialchars($orden['nombre'] . ' ' . $orden['apellido']); ?>
                                                        </div>
                                                        <?php if (!empty($orden['dpi'])): ?>
                                                                <small class="text-muted d-block mt-1">
                                                                    <i class="bi bi-card-text me-1"></i>DPI:
                                                                    <?php echo htmlspecialchars($orden['dpi']); ?>
                                                                </small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <span
                                                            class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded">
                                                            <i class="bi bi-check2-all me-1"></i><?php echo $orden['num_pruebas']; ?>
                                                            Pruebas
                                                        </span>
                                                    </div>
                                                </div>
                                                <div
                                                    class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-dashed">
                                                    <span class="text-muted small">
                                                        <i class="bi bi-calendar-event me-1"></i>
                                                        <?php echo date('d/m/Y H:i', strtotime($orden['fecha_orden'])); ?>
                                                    </span>
                                                    <button class="action-btn"
                                                        onclick="registerSample(<?php echo $orden['id_orden']; ?>, '<?php echo htmlspecialchars($orden['numero_orden']); ?>')">
                                                        <i class="bi bi-droplet-half me-1"></i>
                                                        Registrar Muestra
                                                    </button>
                                                </div>
                                            </div>
                                    <?php endforeach; ?>
                                </div>
                        <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-inbox text-muted" style="font-size: 4rem; opacity: 0.4;"></i>
                                    <h4 class="text-muted mt-3 fw-medium">No hay muestras pendientes</h4>
                                    <p class="text-muted small">Todas las muestras del día han sido recepcionadas correctamente.
                                    </p>
                                </div>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function registerSample(orderId, orderNumber) {
            Swal.fire({
                title: 'Registrar Recepción de Muestra',
                html: `
                <div class="text-start">
                    <p class="mb-4">Vas a confirmar la recepción de muestras para la orden: <strong class="text-primary">${orderNumber}</strong></p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted">Fecha y hora de recepción</label>
                        <input type="datetime-local" id="fecha_recepcion" class="form-control" value="${new Date().toISOString().slice(0, 16)}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted">Adjuntar Orden Escaneada (Opcional)</label>
                        <input type="file" id="archivo_orden" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted">Observaciones (opcional)</label>
                        <textarea id="observaciones" class="form-control" rows="3" placeholder="Ej. Tubo de ensayo rotulado, muestra de sangre en refrigeración..."></textarea>
                    </div>
                </div>
            `,
                showCancelButton: true,
                confirmButtonText: '<i class="bi bi-check-circle me-1"></i> Confirmar Recepción',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: 'var(--color-primary, #6366f1)',
                customClass: {
                    popup: 'border-0 rounded-4 shadow-lg bg-card',
                    confirmButton: 'action-btn px-4 py-2',
                    cancelButton: 'action-btn secondary px-4 py-2'
                },
                preConfirm: () => {
                    return {
                        id_orden: orderId,
                        fecha_recepcion: document.getElementById('fecha_recepcion').value,
                        observaciones: document.getElementById('observaciones').value,
                        archivo: document.getElementById('archivo_orden').files[0]
                    };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('id_orden', result.value.id_orden);
                    formData.append('fecha_recepcion', result.value.fecha_recepcion);
                    formData.append('observaciones', result.value.observaciones);
                    if (result.value.archivo) {
                        formData.append('archivo_orden', result.value.archivo);
                    }

                    fetch('api/register_sample.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Muestra Registrada!',
                                    text: data.message,
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(() => location.reload());
                            } else {
                                Swal.fire('Error', data.message, 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Error', 'Error al registrar la muestra', 'error');
                        });
                }
            });
        }
    </script>
</body>

</html>