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
            p.genero,
            p.telefono
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
    
    // Información de la clínica
    $clinica_nombre = "Centro Médico Herrera Sáenz";
    $clinica_direccion = "7ma Av 7-25 Zona 1, Atrás del parqueo Hospital Antiguo. Huehuetenango";
    $clinica_telefono = "(+502) 4195-8112";
    $clinica_email = "contacto@cmrs.com";
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receta Médica - <?php echo htmlspecialchars($receta['nombre'] . ' ' . $receta['apellido']); ?> - Centro Médico Herrera Sáenz</title>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            /* Colores del dashboard */
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #0dcaf0;
            --text-color: #1a1a1a;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --background-color: #ffffff;
        }
        
        body.dark-mode {
            --primary-color: #3b82f6;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
            --text-color: #e2e8f0;
            --text-muted: #94a3b8;
            --border-color: #2d3748;
            --background-color: #0f172a;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        /* Contenedor de receta */
        .prescription-container {
            width: 210mm;
            min-height: 297mm;
            background: white;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
            position: relative;
        }
        
        /* Cabecera */
        .prescription-header {
            padding: 40px;
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            z-index: 1;
        }
        
        .clinic-info h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .clinic-details {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .prescription-meta {
            text-align: right;
        }
        
        .prescription-meta strong {
            font-size: 16px;
        }
        
        /* Símbolo Rx de fondo */
        .rx-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-15deg);
            font-size: 200px;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.1);
            pointer-events: none;
            z-index: 0;
        }
        
        /* Información del paciente */
        .patient-info {
            padding: 30px 40px;
            background-color: #f8fafc;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 500;
        }
        
        /* Cuerpo de la receta */
        .prescription-body {
            padding: 50px 40px;
            min-height: 400px;
        }
        
        .prescription-title {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .prescription-title h2 {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary-color);
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .prescription-content {
            font-size: 18px;
            line-height: 2;
            white-space: pre-wrap;
            font-family: 'Courier New', monospace;
            background-color: #f8fafc;
            padding: 30px;
            border-radius: 8px;
            border: 1px dashed var(--border-color);
        }
        
        /* Pie de página */
        .prescription-footer {
            padding: 30px 40px;
            border-top: 1px solid var(--border-color);
            background-color: #f8fafc;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        
        .doctor-signature {
            text-align: center;
        }
        
        .signature-line {
            width: 250px;
            height: 1px;
            background-color: var(--text-color);
            margin: 0 auto 10px;
        }
        
        .doctor-name {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .doctor-specialty {
            font-size: 14px;
            color: var(--text-muted);
        }
        
        .document-meta {
            text-align: right;
            font-size: 12px;
            color: var(--text-muted);
        }
        
        /* Botones de acción */
        .action-buttons {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            gap: 15px;
            z-index: 1000;
        }
        
        .action-btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn-print {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-close {
            background-color: var(--secondary-color);
            color: white;
        }
        
        /* Estilos para impresión */
        @media print {
            body {
                padding: 0;
                background: white;
            }
            
            .prescription-container {
                box-shadow: none;
                border-radius: 0;
                width: 210mm;
                height: 297mm;
            }
            
            .action-buttons {
                display: none;
            }
            
            .prescription-header {
                background: var(--primary-color) !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .prescription-container {
                width: 100%;
                max-width: 400px;
                margin: 20px;
            }
            
            .prescription-header {
                padding: 30px 20px;
            }
            
            .clinic-info h1 {
                font-size: 24px;
            }
            
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .prescription-body {
                padding: 30px 20px;
            }
            
            .footer-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .action-buttons {
                bottom: 20px;
                right: 20px;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="prescription-container">
        <!-- Marca de agua -->
        <div class="rx-watermark">Rx</div>
        
        <!-- Cabecera -->
        <header class="prescription-header">
            <div class="header-content">
                <div class="clinic-info">
                    <h1>Centro Médico Herrera Sáenz</h1>
                    <div class="clinic-details">
                        <?php echo htmlspecialchars($clinica_direccion); ?><br>
                        Tel: <?php echo htmlspecialchars($clinica_telefono); ?>
                    </div>
                </div>
                <div class="prescription-meta">
                    <strong>Fecha de Emisión</strong><br>
                    <?php echo $fecha_formateada; ?><br>
                    <strong>Folio</strong><br>
                    #REC-<?php echo str_pad($id_historial, 5, '0', STR_PAD_LEFT); ?>
                </div>
            </div>
        </header>
        
        <!-- Información del paciente -->
        <section class="patient-info">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Paciente</span>
                    <span class="info-value"><?php echo htmlspecialchars($receta['nombre'] . ' ' . $receta['apellido']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Edad / Género</span>
                    <span class="info-value"><?php echo $edad; ?> años / <?php echo htmlspecialchars($receta['genero']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Teléfono</span>
                    <span class="info-value"><?php echo htmlspecialchars($receta['telefono'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Médico</span>
                    <span class="info-value">Dr. <?php echo htmlspecialchars($receta['medico_responsable']); ?></span>
                </div>
            </div>
        </section>
        
        <!-- Cuerpo de la receta -->
        <main class="prescription-body">
            <div class="prescription-title">
                <h2>Prescripción Médica</h2>
            </div>
            <div class="prescription-content">
                <?php 
                // Sanitizar y formatear contenido de la receta
                $raw_receta = $receta['receta_medica'];
                $clean_lines = array_map('trim', explode("\n", $raw_receta));
                $formatted_content = htmlspecialchars(implode("\n", array_filter($clean_lines)));
                echo $formatted_content;
                ?>
            </div>
        </main>
        
        <!-- Pie de página -->
        <footer class="prescription-footer">
            <div class="footer-content">
                <div class="doctor-signature">
                    <div class="signature-line"></div>
                    <div class="doctor-name">Dr. <?php echo htmlspecialchars($receta['medico_responsable']); ?></div>
                    <div class="doctor-specialty"><?php echo htmlspecialchars($receta['especialidad_medico']); ?></div>
                </div>
                <div class="document-meta">
                    <div>Documento generado por CMS - Herrera Sáenz</div>
                    <div>Este es un documento médico válido y confidencial</div>
                </div>
            </div>
        </footer>
    </div>
    
    <!-- Botones de acción -->
    <div class="action-buttons">
        <button class="action-btn btn-close" onclick="window.close()">
            <i class="bi bi-x-lg"></i>
            Cerrar
        </button>
        <button class="action-btn btn-print" onclick="window.print()">
            <i class="bi bi-printer"></i>
            Imprimir
        </button>
    </div>
    
    <script>
        // Mejorar experiencia de impresión
        document.addEventListener('DOMContentLoaded', function() {
            // Optimizar para dispositivos móviles
            if (window.matchMedia('(max-width: 768px)').matches) {
                document.querySelector('.prescription-content').style.fontSize = '16px';
            }
            
            // Auto-enfoque en el botón de imprimir para mejor accesibilidad
            document.querySelector('.btn-print').focus();
        });
        
        // Manejar tecla Escape para cerrar ventana
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.close();
            }
        });
    </script>
</body>
</html>