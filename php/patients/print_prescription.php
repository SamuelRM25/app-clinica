<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

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
            p.genero
        FROM historial_clinico h
        JOIN pacientes p ON h.id_paciente = p.id_paciente
        WHERE h.id_historial = ?
    ");
    $stmt->execute([$id_historial]);
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
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receta Médica - <?php echo htmlspecialchars($receta['nombre'] . ' ' . $receta['apellido']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --professional-blue: #1e3a8a;
            --clinical-blue: #3b82f6;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 40px;
            background-color: #e5e7eb;
            color: var(--text-dark);
        }

        .prescription-container {
            width: 148mm;
            min-height: 210mm;
            margin: 0 auto;
            background-color: white;
            padding: 40px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            position: relative;
            display: flex;
            flex-direction: column;
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

        /* Rx Section */
        .rx-section {
            flex-grow: 1;
            position: relative;
            padding-left: 50px;
            z-index: 1;
        }

        .rx-symbol {
            position: absolute;
            left: 0;
            top: -5px;
            font-size: 42px;
            font-family: 'Playfair Display', serif;
            color: var(--clinical-blue);
            font-style: italic;
            font-weight: 700;
        }

        .prescription-body {
            font-size: 15px;
            line-height: 1.8;
            white-space: pre-wrap;
            min-height: 350px;
            padding-top: 5px;
        }

        /* Footer / Signature */
        .prescription-footer {
            margin-top: auto;
            border-top: 1px solid #e5e7eb;
            padding-top: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: flex-end;
        }

        .doctor-signature {
            text-align: center;
        }

        .signature-line {
            width: 200px;
            height: 1px;
            background-color: var(--text-dark);
            margin: 0 auto 10px;
        }

        .doctor-name {
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .doctor-specialty {
            font-size: 12px;
            color: var(--text-muted);
        }

        .qr-placeholder {
            text-align: right;
            font-size: 9px;
            color: #ccc;
        }

        /* Decorative Elements */
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

        /* Utility Buttons */
        .action-buttons {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            gap: 15px;
            z-index: 100;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: transform 0.2s;
        }

        .btn:hover { transform: translateY(-2px); }
        .btn-print { background-color: var(--clinical-blue); color: white; }
        .btn-close { background-color: white; color: var(--text-dark); }

        @media print {
            body { 
                background-color: white; 
                padding: 0;
            }
            .prescription-container { 
                box-shadow: none;
                padding: 0;
                width: 148mm;
                height: 210mm;
            }
            .action-buttons { display: none; }
        }
    </style>
</head>
<body>
    <div class="prescription-container">
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
                <span class="info-value"><?php echo htmlspecialchars($receta['nombre'] . ' ' . $receta['apellido']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Fecha</span>
                <span class="info-value"><?php echo $fecha_formateada; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Edad / Género</span>
                <span class="info-value"><?php echo $edad; ?> años / <?php echo htmlspecialchars($receta['genero']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Folio de Consulta</span>
                <span class="info-value">#REC-<?php echo str_pad($id_historial, 5, '0', STR_PAD_LEFT); ?></span>
            </div>
        </section>

        <main class="rx-section">
            <div class="rx-symbol">Rx</div>
            <div class="prescription-body">
<?php 
    // Sanitize lines to prevent alignment issues
    $raw_receta = $receta['receta_medica'];
    $clean_lines = array_map('trim', explode("\n", $raw_receta));
    echo htmlspecialchars(implode("\n", $clean_lines));
?>
            </div>
        </main>

        <footer class="prescription-footer">
            <div class="doctor-signature">
                <div class="signature-line"></div>
                <div class="doctor-name">Dr(a). <?php echo htmlspecialchars($receta['medico_responsable']); ?></div>
                <div class="doctor-specialty"><?php echo htmlspecialchars($receta['especialidad_medico']); ?></div>
            </div>
            <div class="qr-placeholder">
                <div style="margin-bottom: 5px;">Documento generado por CM Siloé Management System</div>
                <div style="font-size: 8px;">Este es un documento médico válido y confidencial.</div>
            </div>
        </footer>
    </div>

    <div class="action-buttons">
        <button class="btn btn-close" onclick="window.close()">Cerrar</button>
        <button class="btn btn-print" onclick="window.print()">
            Imprimir Receta
        </button>
    </div>

    <script>
        // Set date to local formatting if needed or keep PHP value
    </script>
</body>
</html>