<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

verify_session();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de receta inválido");
}

$id_historial = $_GET['id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener receta y datos del paciente
    $stmt = $conn->prepare("
        SELECT 
            h.receta_medica, 
            h.fecha_consulta, 
            h.medico_responsable,
            h.especialidad_medico,
            p.nombre, 
            p.apellido,
            p.fecha_nacimiento,
            p.genero,
            p.telefono
        FROM historial_clinico h
        JOIN pacientes p ON h.id_paciente = p.id_paciente
        WHERE h.id_historial = ? AND h.id_hospital = ?
    ");
    $stmt->execute([$id_historial, $id_hospital]);
    $receta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$receta) {
        die("Receta médica no encontrada");
    }

    // Calcular edad
    $fecha_nac = new DateTime($receta['fecha_nacimiento']);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha_nac)->y;

    // Formatear fecha
    $fecha_consulta = new DateTime($receta['fecha_consulta']);
    $fecha_formateada = $fecha_consulta->format('d/m/Y');

    // Información de la clínica
    $clinica_nombre = "Centro Médico Herrera Sáenz";
    $clinica_direccion = "Ciudad de Guatemala, Guatemala";
    $clinica_telefono = "(+502) 4195-8112";

} catch (Exception $e) {
    error_log('Error en patients/print_prescription.php: ' . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receta Médica - <?php echo htmlspecialchars($receta['nombre'] . ' ' . $receta['apellido']); ?> - Centro Médico Herrera Sáenz</title>

    <!-- Google Fonts -->
<!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        /* Reset & Base fonts */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Courier New', Courier, monospace; /* Classic thermal printer font */
            background-color: #f4f6f9;
            color: #000;
            font-size: 12px;
            line-height: 1.4;
            padding: 40px 20px;
        }

        /* Screen Receipt Simulation */
        .prescription-container {
            max-width: 80mm;
            margin: 0 auto;
            background: #fff;
            padding: 20px 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid #e0e0e0;
            position: relative;
            border-radius: 8px;
        }

        /* Ticket styling elements */
        .ticket-header {
            text-align: center;
            margin-bottom: 12px;
        }
        .ticket-header h1 {
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 4px;
            font-family: 'Inter', sans-serif;
            letter-spacing: -0.5px;
        }
        .ticket-header p {
            font-size: 10px;
            color: #444;
            font-family: 'Inter', sans-serif;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 12px 0;
            width: 100%;
        }

        .ticket-meta {
            font-size: 11px;
            margin-bottom: 8px;
        }
        .meta-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        .meta-label {
            font-weight: bold;
        }

        .patient-info {
            font-size: 11px;
            margin-bottom: 8px;
        }
        .info-row {
            margin-bottom: 4px;
            display: flex;
        }
        .info-row .label {
            font-weight: bold;
            width: 80px;
            flex-shrink: 0;
        }
        .info-row .val {
            flex-grow: 1;
        }

        .prescription-body {
            margin-top: 12px;
        }
        .prescription-title {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
            margin-bottom: 12px;
            background: #000;
            color: #fff;
            padding: 3px 0;
            letter-spacing: 1px;
            font-family: 'Inter', sans-serif;
        }
        .prescription-content {
            white-space: pre-wrap;
            font-size: 12px;
            line-height: 1.5;
            font-weight: 600;
            padding: 5px 0;
        }

        .ticket-footer {
            text-align: center;
            margin-top: 30px;
            font-size: 10px;
        }
        .signature-area {
            margin-bottom: 15px;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 160px;
            margin: 0 auto 6px auto;
        }
        .doctor-name {
            font-weight: bold;
            font-size: 11px;
        }
        .doctor-specialty {
            font-style: italic;
            color: #444;
            font-size: 10px;
            margin-top: 2px;
        }
        .footer-msg {
            color: #555;
            font-size: 9px;
            margin-top: 15px;
            line-height: 1.3;
            font-family: 'Inter', sans-serif;
        }

        /* Print styles */
        @media print {
            body {
                background: none !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 80mm;
            }
            .prescription-container {
                width: 80mm;
                max-width: 80mm;
                box-shadow: none !important;
                border: none !important;
                padding: 10px 5px !important;
                margin: 0 !important;
            }
            .action-buttons {
                display: none !important;
            }
        }

        /* Floating control buttons on screen */
        .action-buttons {
            position: fixed;
            bottom: 25px;
            right: 25px;
            display: flex;
            gap: 12px;
            z-index: 1000;
        }
        .action-btn {
            background: #0d6efd;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
            transition: transform 0.2s, background 0.2s;
        }
        .action-btn:hover {
            transform: translateY(-2px);
            background: #0b5ed7;
        }
        .btn-close {
            background: #fff;
            color: #333;
            border: 1px solid #ccc;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }
        .btn-close:hover {
            background: #f8f9fa;
        }
    </style>
</head>

<body>
    <div class="prescription-container">
        <!-- Cabecera -->
        <div class="ticket-header">
            <h1><?php echo htmlspecialchars($clinica_nombre); ?></h1>
            <p><?php echo htmlspecialchars($clinica_direccion); ?></p>
            <p>Tel: <?php echo htmlspecialchars($clinica_telefono); ?></p>
        </div>

        <div class="divider"></div>

        <!-- Meta del ticket -->
        <div class="ticket-meta">
            <div class="meta-row">
                <span class="meta-label">Fecha:</span>
                <span><?php echo $fecha_formateada; ?></span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Folio:</span>
                <span>REC-<?php echo str_pad($id_historial, 5, '0', STR_PAD_LEFT); ?></span>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Información del paciente -->
        <div class="patient-info">
            <div class="info-row">
                <span class="label">Paciente:</span>
                <span class="val"><?php echo htmlspecialchars($receta['nombre'] . ' ' . $receta['apellido']); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Edad/Sexo:</span>
                <span class="val"><?php echo $edad; ?> años / <?php echo htmlspecialchars($receta['genero']); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Teléfono:</span>
                <span class="val"><?php echo htmlspecialchars($receta['telefono'] ?? 'N/A'); ?></span>
            </div>
            <div class="info-row">
                <span class="label">Médico:</span>
                <span class="val">Dr. <?php echo htmlspecialchars($receta['medico_responsable']); ?></span>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Cuerpo de la receta -->
        <div class="prescription-body">
            <div class="prescription-title">Receta Médica</div>
            <div class="prescription-content"><?php
                $raw_receta = $receta['receta_medica'];
                $clean_lines = array_map('trim', explode("\n", $raw_receta));
                echo htmlspecialchars(implode("\n", array_filter($clean_lines)));
            ?></div>
        </div>

        <div class="divider"></div>

        <!-- Pie del ticket -->
        <div class="ticket-footer">
            <div class="signature-area">
                <div class="signature-line"></div>
                <div class="doctor-name">Dr. <?php echo htmlspecialchars($receta['medico_responsable']); ?></div>
                <div class="doctor-specialty"><?php echo htmlspecialchars($receta['especialidad_medico'] ?? ''); ?></div>
            </div>
            <div class="footer-msg">
                Este documento es una receta médica válida.<br>
                ¡Gracias por su confianza!
            </div>
        </div>
    </div>

    <!-- Botones de acción flotantes -->
    <div class="action-buttons">
        <button class="action-btn btn-close" onclick="window.close()">
            <i class="bi bi-x-lg"></i> Cerrar
        </button>
        <button class="action-btn btn-print" onclick="window.print()">
            <i class="bi bi-printer"></i> Imprimir
        </button>
    </div>

    <script>
        // Auto-enfoque en el botón de imprimir para mejor accesibilidad
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelector('.btn-print').focus();
        });

        // Manejar tecla Escape para cerrar ventana
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                window.close();
            }
        });
    </script>
</body>

</html>