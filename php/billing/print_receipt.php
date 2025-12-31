<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de cobro inválido");
}

$id_cobro = $_GET['id'];

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get billing data with patient name
    $stmt = $conn->prepare("
        SELECT c.*, CONCAT(p.nombre, ' ', p.apellido) as nombre_paciente, p.id_paciente, p.fecha_nacimiento, p.genero
        FROM cobros c
        JOIN pacientes p ON c.paciente_cobro = p.id_paciente
        WHERE c.in_cobro = ?
    ");
    $stmt->execute([$id_cobro]);
    $cobro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cobro) {
        die("Cobro no encontrado");
    }
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Format date
$fecha = new DateTime($cobro['fecha_consulta']);
$fecha_formateada = $fecha->format('d/m/Y');

// Calculate age
$fecha_nac = new DateTime($cobro['fecha_nacimiento'] ?? '1900-01-01');
$hoy = new DateTime();
$edad = $hoy->diff($fecha_nac)->y;

// Process form submission for appointment scheduling
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'schedule' && 
        isset($_POST['fecha_cita']) && isset($_POST['hora_cita'])) {
        // Schedule new appointment
        try {
            // Need a doctor ID. Defaulting to first doctor/admin
            $stmt_doc = $conn->query("SELECT id_usuario FROM usuarios WHERE tipoUsuario IN ('admin', 'doc') LIMIT 1");
            $default_doc = $stmt_doc->fetch(PDO::FETCH_ASSOC);
            $id_doctor = $default_doc['id_usuario'] ?? 1;

            $stmt = $conn->prepare("
                INSERT INTO citas (id_paciente, id_doctor, fecha_cita, hora_cita, estado, motivo) 
                VALUES (?, ?, ?, ?, 'Pendiente', 'Seguimiento de consulta')
            ");
            $stmt->execute([
                $cobro['id_paciente'],
                $id_doctor,
                $_POST['fecha_cita'],
                $_POST['hora_cita']
            ]);
            
            $mensaje = '<div class="alert alert-success shadow-sm border-0 mb-4 animate__animated animate__fadeIn">Nueva cita agendada correctamente.</div>';
        } catch (Exception $e) {
            $mensaje = '<div class="alert alert-danger shadow-sm border-0 mb-4 animate__animated animate__fadeIn">Error al agendar la cita: ' . $e->getMessage() . '</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Cobro #<?php echo str_pad($id_cobro, 5, '0', STR_PAD_LEFT); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <style>
        :root {
            --professional-blue: #1e3a8a;
            --clinical-blue: #3b82f6;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
            --success-green: #10b981;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 40px;
            background-color: #f0f2f5;
            color: var(--text-dark);
        }

        .receipt-container {
            width: 148mm;
            min-height: 210mm;
            margin: 0 auto;
            background-color: white;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            position: relative;
            display: flex;
            flex-direction: column;
            border-radius: 4px;
        }

        /* Branding Header */
        .clinic-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--professional-blue);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .logo-section img {
            height: 60px;
            margin-bottom: 10px;
        }

        .clinic-name {
            font-family: 'Playfair Display', serif;
            color: var(--professional-blue);
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }

        .clinic-info {
            text-align: right;
            font-size: 11px;
            line-height: 1.5;
            color: var(--text-muted);
        }

        /* Patient Info Section */
        .patient-section {
            background-color: #f3f4f6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 4px;
            font-weight: 600;
        }

        .info-value {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
            line-height: 1.2;
        }

        /* Billing Content */
        .billing-content {
            flex-grow: 1;
            z-index: 1;
        }

        .billing-title {
            color: var(--professional-blue);
            font-size: 18px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            border-left: 4px solid var(--clinical-blue);
            padding-left: 15px;
        }

        .billing-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .billing-table th {
            text-align: left;
            font-size: 12px;
            color: var(--text-muted);
            padding-bottom: 10px;
            border-bottom: 1px solid #e5e7eb;
        }

        .billing-table td {
            padding: 15px 0;
            font-size: 14px;
            border-bottom: 1px solid #f3f4f6;
        }

        .total-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #f3f4f6;
        }

        .total-box {
            background-color: var(--professional-blue);
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            text-align: right;
        }

        .total-label {
            font-size: 11px;
            text-transform: uppercase;
            opacity: 0.8;
            margin-bottom: 5px;
        }

        .total-amount {
            font-size: 24px;
            font-weight: 800;
        }

        /* Footer */
        .receipt-footer {
            margin-top: auto;
            border-top: 1px solid #e5e7eb;
            padding-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .legal-note {
            font-size: 9px;
            color: var(--text-muted);
            max-width: 300px;
        }

        .thank-you {
            text-align: right;
            font-family: 'Playfair Display', serif;
            font-style: italic;
            color: var(--clinical-blue);
        }

        /* Watermark */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 100px;
            color: rgba(0, 0, 0, 0.03);
            pointer-events: none;
            z-index: 0;
            font-weight: 900;
            text-transform: uppercase;
            white-space: nowrap;
        }

        /* Action UI */
        .action-container {
            max-width: 148mm;
            margin: 30px auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .nav-link {
            font-weight: 600;
            color: var(--text-muted);
            border: none !important;
        }

        .nav-link.active {
            color: var(--clinical-blue) !important;
            background: transparent !important;
            border-bottom: 2px solid var(--clinical-blue) !important;
        }

        .btn-custom {
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-print {
            background-color: var(--clinical-blue);
            color: white;
            border: none;
        }

        .btn-print:hover {
            background-color: var(--professional-blue);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
        }

        @media print {
            body { 
                background-color: white; 
                padding: 0;
            }
            .receipt-container { 
                box-shadow: none;
                padding: 0;
                width: 148mm;
                height: 210mm;
            }
            .action-container, .btn-float-back, .alert { display: none; }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <?php if (!empty($mensaje)): ?>
            <div class="max-w-148 mx-auto" style="width: 148mm; margin: 0 auto;">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>

        <div class="receipt-container">
            <div class="watermark">CMSILOE</div>
            
            <header class="clinic-header" style="z-index: 1; position: relative;">
                <div class="logo-section">
                    <img src="../../assets/img/siloe.png" alt="Clinica Siloe">
                    <h1 class="clinic-name">Clínica Médica Siloé</h1>
                </div>
                <div class="clinic-info">
                    <strong>Servicios Médicos Integrales</strong><br>
                    Nentón, Huehuetenango, Guatemala<br>
                    Tel: (+502) 4623-2418<br>
                    Email: contacto@clinicasiloe.com
                </div>
            </header>

            <section class="patient-section">
                <div class="info-item">
                    <span class="info-label">Paciente</span>
                    <span class="info-value"><?php echo htmlspecialchars($cobro['nombre_paciente']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Fecha</span>
                    <span class="info-value"><?php echo $fecha_formateada; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Edad / Género</span>
                    <span class="info-value"><?php echo $edad; ?> años / <?php echo htmlspecialchars($cobro['genero'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">ID de Cobro</span>
                    <span class="info-value">#REC-<?php echo str_pad($id_cobro, 5, '0', STR_PAD_LEFT); ?></span>
                </div>
            </section>

            <main class="billing-content">
                <h2 class="billing-title">Detalle de Recaudación</h2>
                <table class="billing-table">
                    <thead>
                        <tr>
                            <th style="width: 70%;">Descripción</th>
                            <th style="width: 30%; text-align: right;">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Consulta Médica General</td>
                            <td style="text-align: right; font-weight: 600;">Q<?php echo number_format($cobro['cantidad_consulta'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>

                <div class="total-section">
                    <div class="total-box">
                        <div class="total-label">Total a Pagar</div>
                        <div class="total-amount">Q<?php echo number_format($cobro['cantidad_consulta'], 2); ?></div>
                    </div>
                </div>
            </main>

            <footer class="receipt-footer">
                <div class="legal-note">
                    <strong>Información Importante:</strong><br>
                    Este recibo es un comprobante de pago por servicios médicos prestados. 
                    Para cualquier aclaración, favor de presentar este documento original.
                    Documento generado por CM Siloé Management System.
                </div>
                <div class="thank-you">
                    <h4 style="margin: 0; font-size: 16px;">¡Gracias por su preferencia!</h4>
                    <p style="margin: 5px 0 0; font-size: 13px;">Recupérese pronto.</p>
                </div>
            </footer>
        </div>

        <div class="action-container">
            <h5 class="mb-4 fw-bold"><i class="bi bi-gear-fill me-2 text-primary"></i>Panel de Acciones</h5>
            
            <ul class="nav nav-tabs mb-4" id="actionTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="print-tab" data-bs-toggle="tab" data-bs-target="#print" type="button" role="tab">
                        <i class="bi bi-printer me-2"></i>Impresión
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button" role="tab">
                        <i class="bi bi-calendar-event me-2"></i>Agendar Cita
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="actionTabsContent">
                <div class="tab-pane fade show active" id="print" role="tabpanel">
                    <div class="text-center py-3">
                        <p class="text-muted mb-4">El recibo está listo para ser guardado o impreso en formato profesional.</p>
                        <div class="d-flex justify-content-center gap-3">
                            <a href="../billing/index.php" class="btn btn-light btn-custom">
                                <i class="bi bi-arrow-left me-2"></i>Volver
                            </a>
                            <button class="btn btn-print btn-custom" onclick="window.print();">
                                <i class="bi bi-printer-fill me-2"></i>Imprimir Recibo
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="tab-pane fade" id="schedule" role="tabpanel">
                    <form method="post" class="row g-3 px-2">
                        <input type="hidden" name="action" value="schedule">
                        <div class="col-md-6">
                            <label for="fecha_cita" class="form-label fw-600">Fecha de Cita</label>
                            <input type="date" class="form-control shadow-sm" id="fecha_cita" name="fecha_cita" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="hora_cita" class="form-label fw-600">Hora de Cita</label>
                            <input type="time" class="form-control shadow-sm" id="hora_cita" name="hora_cita" required>
                        </div>
                        <div class="col-12 mt-4 text-center">
                            <button type="submit" class="btn btn-primary btn-custom shadow-sm px-5">
                                <i class="bi bi-calendar-plus-fill me-2"></i>Confirmar y Agendar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>