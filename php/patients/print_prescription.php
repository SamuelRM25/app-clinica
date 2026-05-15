<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



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
    $clinica_direccion = "Dirección de prueba";
    $clinica_telefono = "(+502) 4195-8112";
    $clinica_email = "contacto@herrerasaenz.com";

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receta Médica - <?php echo htmlspecialchars($receta['nombre'] . ' ' . $receta['apellido']); ?> - Centro
        Médico Herrera Sáenz</title>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
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
                    <span
                        class="info-value"><?php echo htmlspecialchars($receta['nombre'] . ' ' . $receta['apellido']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Edad / Género</span>
                    <span class="info-value"><?php echo $edad; ?> años /
                        <?php echo htmlspecialchars($receta['genero']); ?></span>
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
        document.addEventListener('DOMContentLoaded', function () {
            // Optimizar para dispositivos móviles
            if (window.matchMedia('(max-width: 768px)').matches) {
                document.querySelector('.prescription-content').style.fontSize = '16px';
            }

            // Auto-enfoque en el botón de imprimir para mejor accesibilidad
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