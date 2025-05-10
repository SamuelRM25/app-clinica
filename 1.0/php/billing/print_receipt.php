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
        SELECT c.*, CONCAT(p.nombre, ' ', p.apellido) as nombre_paciente, p.id_paciente 
        FROM cobros c
        JOIN pacientes p ON c.paciente_cobro = p.id_paciente
        WHERE c.in_cobro = ?
    ");
    $stmt->execute([$id_cobro]);
    $cobro = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cobro) {
        die("Cobro no encontrado");
    }
    
    // Removing the query for doctores table since it doesn't exist
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Format date
$fecha = new DateTime($cobro['fecha_consulta']);
$fecha_formateada = $fecha->format('d/m/Y');

// Process form submission for appointment scheduling
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'schedule' && 
        isset($_POST['doctor']) && isset($_POST['fecha_cita']) && isset($_POST['hora_cita'])) {
        // Schedule new appointment
        try {
            $stmt = $conn->prepare("
                INSERT INTO citas (id_paciente, id_doctor, fecha_cita, hora_cita, estado, motivo) 
                VALUES (?, ?, ?, ?, 'Pendiente', 'Seguimiento de consulta')
            ");
            $stmt->execute([
                $cobro['id_paciente'],
                $_POST['doctor'],
                $_POST['fecha_cita'],
                $_POST['hora_cita']
            ]);
            
            $mensaje = '<div class="alert alert-success">Nueva cita agendada correctamente.</div>';
        } catch (Exception $e) {
            $mensaje = '<div class="alert alert-danger">Error al agendar la cita: ' . $e->getMessage() . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Cobro #<?php echo $id_cobro; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .receipt-container {
            width: 80mm;
            margin: 0 auto 20px;
            background-color: white;
            padding: 15px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .text-center {
            text-align: center;
        }
        .mb-2 {
            margin-bottom: 10px;
        }
        .mb-3 {
            margin-bottom: 15px;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        .total-row {
            font-weight: bold;
            border-top: 1px dashed #000;
            margin-top: 10px;
            padding-top: 10px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
        }
        .action-container {
            max-width: 500px;
            margin: 20px auto;
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .print-button {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
        }
        @media print {
            .action-container, .print-button, .alert {
                display: none;
            }
            body {
                background-color: white;
                padding: 0;
            }
            .receipt-container {
                box-shadow: none;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($mensaje)): ?>
            <?php echo $mensaje; ?>
        <?php endif; ?>
        
        <div class="receipt-container">
            <div class="text-center mb-3">
                <h2 style="margin: 0;">InterClinic</h2>
                <p style="margin: 5px 0;">Santa Cruz Barillas</p>
                <p style="margin: 5px 0;">Tel: +502 42594302</p>
            </div>
            
            <div class="divider"></div>
            
            <div class="mb-3">
                <p><strong>Fecha:</strong> <?php echo $fecha_formateada; ?></p>
                <p><strong>Paciente:</strong> <?php echo htmlspecialchars($cobro['nombre_paciente']); ?></p>
            </div>
            
            <div class="divider"></div>
            
            <div class="mb-3">
                <h3 class="text-center" style="margin: 5px 0;">DETALLE DE COBRO</h3>
                <p class="text-center">Consulta Médica</p>
                
                <div class="total-row">
                    <p style="text-align: right;"><strong>Total: Q<?php echo number_format($cobro['cantidad_consulta'], 2); ?></strong></p>
                </div>
            </div>
            
            <div class="divider"></div>
            
            <div class="footer">
                <p>¡Gracias por su preferencia!</p>
                <p>Recupérese pronto</p>
            </div>
        </div>
        
        <div class="action-container">
            <h4 class="mb-3">Opciones</h4>
            
            <ul class="nav nav-tabs" id="actionTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="print-tab" data-bs-toggle="tab" data-bs-target="#print" type="button" role="tab" aria-controls="print" aria-selected="true">Imprimir Recibo</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button" role="tab" aria-controls="schedule" aria-selected="false">Agendar Nueva Cita</button>
                </li>
            </ul>
            
            <div class="tab-content p-3 border border-top-0 rounded-bottom" id="actionTabsContent">
                <div class="tab-pane fade show active" id="print" role="tabpanel" aria-labelledby="print-tab">
                    <div class="mt-3 text-center">
                        <p>Haga clic en el botón para imprimir el recibo.</p>
                        <button class="btn btn-success" onclick="window.print();">
                            <i class="bi bi-printer me-2"></i>Imprimir Recibo
                        </button>
                    </div>
                </div>
                
                <div class="tab-pane fade" id="schedule" role="tabpanel" aria-labelledby="schedule-tab">
                    <form method="post" class="mt-3">
                        <input type="hidden" name="action" value="schedule">
                        
                        <div class="mb-3">
                            <label for="fecha_cita" class="form-label">Fecha de Cita</label>
                            <input type="date" class="form-control" id="fecha_cita" name="fecha_cita" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="hora_cita" class="form-label">Hora de Cita</label>
                            <input type="time" class="form-control" id="hora_cita" name="hora_cita" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-calendar-plus me-2"></i>Agendar Cita
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="text-center">
            <a href="../billing/index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-2"></i>Volver a Cobros
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>