<?php
// print_receipt.php - Recibo de Cobro - Centro Médico Herrera Saenz
// Versión: 3.0 - Diseño Minimalista con Modo Noche y Efecto Mármol
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

// Verificar si se proporciona ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de cobro inválido");
}

$id_cobro = $_GET['id'];

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();
    
    // Obtener datos del cobro con información del paciente
    $stmt = $conn->prepare("
        SELECT c.*, CONCAT(p.nombre, ' ', p.apellido) as nombre_paciente, 
               p.id_paciente, p.fecha_nacimiento, p.genero, p.telefono, p.direccion
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

// Formatear fecha
$fecha = new DateTime($cobro['fecha_consulta']);
$fecha_formateada = $fecha->format('d/m/Y');

// Calcular edad
if ($cobro['fecha_nacimiento']) {
    $fecha_nac = new DateTime($cobro['fecha_nacimiento']);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha_nac)->y;
} else {
    $edad = 'N/A';
}

// Procesar envío del formulario para programar cita
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'schedule' && 
        isset($_POST['fecha_cita']) && isset($_POST['hora_cita'])) {
        try {
            // Necesitamos un ID de doctor. Usamos el primer doctor/admin disponible
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
            
            $mensaje = '<div class="notification success">Nueva cita agendada correctamente.</div>';
        } catch (Exception $e) {
            $mensaje = '<div class="notification error">Error al agendar la cita: ' . $e->getMessage() . '</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Cobro #<?php echo str_pad($id_cobro, 5, '0', STR_PAD_LEFT); ?> - Centro Médico Herrera Saenz</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
    /* 
     * Recibo de Cobro Minimalista - Centro Médico Herrera Saenz
     * Diseño: Fondo blanco, colores pastel, efecto mármol
     * Versión: 3.0
     */
    
    /* Variables CSS */
    :root {
        --color-primary: #7c90db;
        --color-primary-light: #a3b1e8;
        --color-secondary: #8dd7bf;
        --color-accent: #f8b195;
        --color-text: #1e293b;
        --color-text-light: #64748b;
        --color-border: #e2e8f0;
        --color-success: #34d399;
        --color-error: #f87171;
        --color-surface: #ffffff;
        --color-background: #f8fafc;
        
        --radius-md: 12px;
        --radius-lg: 16px;
        
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
    }
    
    [data-theme="dark"] {
        --color-text: #0f172a;
        --color-text-light: #334155;
        --color-border: #cbd5e1;
        --color-surface: #ffffff;
        --color-background: #ffffff;
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--color-background);
        color: var(--color-text);
        padding: 20px;
        line-height: 1.5;
    }
    
    @media print {
        body {
            background-color: white;
            padding: 0;
        }
        
        .no-print {
            display: none !important;
        }
        
        .receipt-container {
            box-shadow: none !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 148mm !important;
        }
    }
    
    /* Contenedor principal */
    .container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    /* Botón de regreso */
    .back-button {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 0.75rem 1.5rem;
        color: var(--color-text);
        text-decoration: none;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        margin-bottom: 2rem;
    }
    
    .back-button:hover {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
        transform: translateY(-2px);
    }
    
    /* Contenedor del recibo */
    .receipt-container {
        width: 148mm;
        min-height: 210mm;
        margin: 0 auto 2rem;
        background-color: white;
        padding: 40px;
        box-shadow: var(--shadow-lg);
        position: relative;
        display: flex;
        flex-direction: column;
        border-radius: var(--radius-lg);
        background: var(--color-surface);
        color: var(--color-text);
    }
    
    /* Marca de agua */
    .watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-45deg);
        font-size: 120px;
        color: rgba(0, 0, 0, 0.03);
        pointer-events: none;
        z-index: 0;
        font-weight: 900;
        text-transform: uppercase;
        white-space: nowrap;
        font-family: 'Playfair Display', serif;
        opacity: 0.5;
    }
    
    [data-theme="dark"] .watermark {
        color: rgba(255, 255, 255, 0.03);
    }
    
    /* Encabezado de la clínica */
    .clinic-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2px solid var(--color-primary);
        padding-bottom: 20px;
        margin-bottom: 30px;
        position: relative;
        z-index: 1;
    }
    
    .logo-section {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .clinic-logo {
        height: 60px;
        width: auto;
    }
    
    .clinic-name {
        font-family: 'Playfair Display', serif;
        color: var(--color-primary);
        font-size: 24px;
        font-weight: 700;
        margin: 0;
    }
    
    .clinic-info {
        text-align: right;
        font-size: 11px;
        line-height: 1.5;
        color: var(--color-text);
        font-weight: 500;
    }
    
    /* Sección de información del paciente */
    .patient-section {
        background-color: rgba(124, 144, 219, 0.1);
        border: 1px solid var(--color-primary-light);
        border-radius: var(--radius-md);
        padding: 20px;
        margin-bottom: 30px;
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 15px;
        position: relative;
        z-index: 1;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
    }
    
    .info-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--color-text-light);
        margin-bottom: 4px;
        font-weight: 700;
    }
    
    .info-value {
        font-size: 13px;
        font-weight: 700;
        color: var(--color-text);
        line-height: 1.2;
    }
    
    /* Contenido principal del recibo */
    .receipt-content {
        flex-grow: 1;
        position: relative;
        z-index: 1;
    }
    
    .receipt-title {
        color: var(--color-primary);
        font-size: 18px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 20px;
        border-left: 4px solid var(--color-primary);
        padding-left: 15px;
    }
    
    /* Tabla de detalles */
    .receipt-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
    }
    
    .receipt-table th {
        text-align: left;
        font-size: 12px;
        color: var(--color-text-light);
        padding-bottom: 10px;
        border-bottom: 1px solid var(--color-border);
    }
    
    .receipt-table td {
        padding: 15px 0;
        font-size: 14px;
        border-bottom: 1px solid var(--color-border);
        color: var(--color-text);
        font-weight: 500;
    }
    
    .receipt-table td:last-child {
        text-align: right;
        font-weight: 600;
    }
    
    /* Sección total */
    .total-section {
        display: flex;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid var(--color-border);
    }
    
    .total-box {
        background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
        color: white;
        padding: 20px 30px;
        border-radius: var(--radius-md);
        text-align: right;
        box-shadow: var(--shadow-md);
    }
    
    .total-label {
        font-size: 11px;
        text-transform: uppercase;
        opacity: 0.9;
        margin-bottom: 5px;
    }
    
    .total-amount {
        font-size: 28px;
        font-weight: 800;
    }
    
    /* Pie de página del recibo */
    .receipt-footer {
        margin-top: auto;
        border-top: 1px solid var(--color-border);
        padding-top: 30px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        position: relative;
        z-index: 1;
    }
    
    .legal-note {
        font-size: 9px;
        color: var(--color-text-light);
        max-width: 300px;
        line-height: 1.4;
    }
    
    .thank-you {
        text-align: right;
        font-family: 'Playfair Display', serif;
        font-style: italic;
        color: var(--color-primary);
    }
    
    /* Panel de acciones */
    .action-panel {
        max-width: 148mm;
        margin: 30px auto;
        background: var(--color-surface);
        padding: 25px;
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--color-border);
    }
    
    .action-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--color-text);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    /* Pestañas de acción */
    .action-tabs {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        border-bottom: 1px solid var(--color-border);
        padding-bottom: 0.5rem;
    }
    
    .tab-button {
        padding: 0.75rem 1.5rem;
        background: transparent;
        border: none;
        color: var(--color-text-light);
        font-weight: 500;
        font-size: 0.95rem;
        cursor: pointer;
        border-radius: var(--radius-md);
        transition: all 0.3s ease;
        position: relative;
    }
    
    .tab-button:hover {
        color: var(--color-text);
        background: var(--color-border);
    }
    
    .tab-button.active {
        color: var(--color-primary);
        background: var(--color-primary-light);
        opacity: 0.1;
    }
    
    .tab-button.active::after {
        content: '';
        position: absolute;
        bottom: -0.5rem;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--color-primary);
    }
    
    /* Contenido de pestañas */
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
        animation: fadeIn 0.5s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    /* Botones de acción */
    .action-buttons {
        display: flex;
        gap: 1rem;
        justify-content: center;
        margin-top: 2rem;
    }
    
    .action-button {
        padding: 0.875rem 1.75rem;
        border-radius: var(--radius-md);
        font-weight: 600;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }
    
    .action-button.primary {
        background: var(--color-primary);
        color: white;
    }
    
    .action-button.primary:hover {
        background: var(--color-primary-dark);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .action-button.secondary {
        background: var(--color-surface);
        color: var(--color-text);
        border: 1px solid var(--color-border);
    }
    
    .action-button.secondary:hover {
        background: var(--color-border);
    }
    
    /* Formulario para agendar cita */
    .appointment-form {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .form-label {
        font-weight: 500;
        color: var(--color-text);
        font-size: 0.875rem;
    }
    
    .form-control {
        padding: 0.75rem 1rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        font-size: 0.95rem;
        background: var(--color-surface);
        color: var(--color-text);
        transition: all 0.3s ease;
    }
    
    .form-control:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px var(--color-primary-light);
        opacity: 0.3;
    }
    
    /* Notificaciones */
    .notification {
        padding: 1rem;
        border-radius: var(--radius-md);
        margin-bottom: 1.5rem;
        font-weight: 500;
        animation: slideDown 0.4s ease-out;
    }
    
    .notification.success {
        background: var(--color-success);
        opacity: 0.1;
        color: var(--color-text);
        border-left: 4px solid var(--color-success);
    }
    
    .notification.error {
        background: var(--color-error);
        opacity: 0.1;
        color: var(--color-text);
        border-left: 4px solid var(--color-error);
    }
    
    /* Efecto mármol para fondo */
    .marble-effect {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        pointer-events: none;
        z-index: -1;
        opacity: 0.3;
        background-image: 
            radial-gradient(circle at 20% 30%, rgba(124, 144, 219, 0.05) 0%, transparent 30%),
            radial-gradient(circle at 80% 70%, rgba(141, 215, 191, 0.05) 0%, transparent 30%),
            radial-gradient(circle at 40% 80%, rgba(248, 177, 149, 0.05) 0%, transparent 30%);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        body {
            padding: 10px;
        }
        
        .receipt-container {
            width: 100%;
            padding: 20px;
        }
        
        .clinic-header {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .clinic-info {
            text-align: left;
        }
        
        .patient-section {
            grid-template-columns: 1fr;
        }
        
        .appointment-form {
            grid-template-columns: 1fr;
        }
        
        .action-buttons {
            flex-direction: column;
        }
        
        .action-button {
            width: 100%;
            justify-content: center;
        }
        
        .watermark {
            font-size: 80px;
        }
    }
    
    @media (max-width: 480px) {
        .receipt-container {
            padding: 15px;
        }
        
        .action-tabs {
            flex-direction: column;
        }
        
        .tab-button {
            width: 100%;
            text-align: left;
        }
    }
    </style>
</head>
<body>
    <!-- Efecto mármol -->
    <div class="marble-effect"></div>
    
    <div class="container">
        <!-- Botón de regreso -->
        <a href="index.php" class="back-button no-print">
            <i class="bi bi-arrow-left"></i>
            Volver a Cobros
        </a>
        
        <!-- Notificación -->
        <?php if (!empty($mensaje)): ?>
            <div class="notification-container no-print">
                <?php echo $mensaje; ?>
            </div>
        <?php endif; ?>
        
        <!-- Recibo de cobro -->
        <div class="receipt-container">
            <!-- Marca de agua -->
            <div class="watermark">HERRERA SAENZ</div>
            
            <!-- Encabezado de la clínica -->
            <header class="clinic-header">
                <div class="logo-section">
                    <img src="../../assets/img/herrerasaenz.png" alt="Centro Médico Herrera Saenz" class="clinic-logo">
                </div>
                <div class="clinic-info">
                    7ma Av 7-25 Zona 1, Atrás del parqueo Hospital Antiguo. Huehuetenango<br>
                    Tel: (+502) 4195-8112<br>
                </div>
            </header>
            
            <!-- Información del paciente -->
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
            
            <!-- Contenido principal -->
            <main class="receipt-content">
                <h2 class="receipt-title">Detalle de Recaudación</h2>
                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th style="width: 70%;">Descripción</th>
                            <th style="width: 30%; text-align: right;">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Consulta Médica General</td>
                            <td>Q<?php echo number_format($cobro['cantidad_consulta'], 2); ?></td>
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
            
            <!-- Pie de página -->
            <footer class="receipt-footer">
                <div class="legal-note">
                    <strong>Información Importante:</strong><br>
                    Este recibo es un comprobante de pago por servicios médicos prestados. 
                    Para cualquier aclaración, favor de presentar este documento original.
                    Documento generado por Centro Médico Herrera Saenz Management System.
                </div>
                <div class="thank-you">
                    <h4 style="margin: 0; font-size: 16px;">¡Gracias por su preferencia!</h4>
                    <p style="margin: 5px 0 0; font-size: 13px;">Recupérese pronto.</p>
                </div>
            </footer>
        </div>
        
        <!-- Panel de acciones (no se imprime) -->
        <div class="action-panel no-print">
            <h3 class="action-title">
                <i class="bi bi-gear-fill text-primary"></i>
                Panel de Acciones
            </h3>
            
            <!-- Pestañas -->
            <div class="action-tabs">
                <button class="tab-button active" data-tab="print">
                    <i class="bi bi-printer me-2"></i>Impresión
                </button>
                <button class="tab-button" data-tab="schedule">
                    <i class="bi bi-calendar-event me-2"></i>Agendar Cita
                </button>
            </div>
            
            <!-- Contenido de pestaña: Impresión -->
            <div class="tab-content active" id="print-tab">
                <div class="text-center py-3">
                    <p class="text-muted mb-4">El recibo está listo para ser guardado o impreso en formato profesional.</p>
                    <div class="action-buttons">
                        <button class="action-button secondary" onclick="window.history.back()">
                            <i class="bi bi-arrow-left me-2"></i>Volver
                        </button>
                        <button class="action-button primary" onclick="window.print()">
                            <i class="bi bi-printer-fill me-2"></i>Imprimir Recibo
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Contenido de pestaña: Agendar Cita -->
            <div class="tab-content" id="schedule-tab">
                <form method="post" class="appointment-form">
                    <input type="hidden" name="action" value="schedule">
                    <div class="form-group">
                        <label for="fecha_cita" class="form-label">Fecha de Cita</label>
                        <input type="date" 
                               class="form-control" 
                               id="fecha_cita" 
                               name="fecha_cita" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               required>
                    </div>
                    <div class="form-group">
                        <label for="hora_cita" class="form-label">Hora de Cita</label>
                        <input type="time" 
                               class="form-control" 
                               id="hora_cita" 
                               name="hora_cita" 
                               required>
                    </div>
                    <div class="form-group" style="grid-column: span 2;">
                        <button type="submit" class="action-button primary" style="width: 100%; margin-top: 1rem;">
                            <i class="bi bi-calendar-plus-fill me-2"></i>Confirmar y Agendar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    // Funcionalidad para pestañas
    document.addEventListener('DOMContentLoaded', function() {
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // Remover clase active de todos los botones y contenidos
                tabButtons.forEach(btn => btn.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));
                
                // Agregar clase active al botón clickeado
                this.classList.add('active');
                
                // Mostrar el contenido correspondiente
                document.getElementById(`${tabId}-tab`).classList.add('active');
            });
        });
        
        // Función para imprimir
        window.printRecibo = function() {
            window.print();
        };
        
        // Cambiar tema basado en preferencias del sistema
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const savedTheme = localStorage.getItem('dashboard-theme');
        
        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        
        console.log('Recibo de Cobro - Centro Médico Herrera Saenz');
        console.log('ID de Cobro: <?php echo $id_cobro; ?>');
        console.log('Paciente: <?php echo htmlspecialchars($cobro['nombre_paciente']); ?>');
    });
    </script>
</body>
</html>