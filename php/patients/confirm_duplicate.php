<?php
session_start();
require_once '../../includes/functions.php';

verify_session();

// Recuperar datos de la sesión
$patientData = $_SESSION['duplicate_patient_data'] ?? null;
$existingPatientId = $_SESSION['existing_patient_id'] ?? null;

// Redirigir si no hay datos
if (!$patientData || !$existingPatientId) {
    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Paciente Duplicado - Centro Médico Herrera Sáenz</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="Logo.png">
    
    <style>
        :root {
            /* Modo Claro - Colores Pastel */
            --primary-light: #a3c4f3;        /* Azul pastel */
            --secondary-light: #b5e6d9;      /* Verde pastel */
            --warning-light: #ffd6a5;        /* Naranja pastel */
            --danger-light: #ffadad;         /* Rojo pastel */
            --accent-light: #c7ceea;         /* Lavanda pastel */
            --background-light: #ffffff;     /* Fondo blanco */
            --surface-light: #f9f9f9;        /* Superficie clara */
            --text-light: #333333;           /* Texto oscuro */
            --text-muted-light: #666666;     /* Texto secundario */
            --border-light: #e0e0e0;         /* Bordes sutiles */
            
            /* Modo Oscuro */
            --primary-dark: #3a506b;         /* Azul oscuro */
            --secondary-dark: #1c2541;       /* Azul más oscuro */
            --warning-dark: #ff9f1c;         /* Naranja */
            --danger-dark: #e71d36;          /* Rojo */
            --accent-dark: #8a89c0;          /* Lavanda oscuro */
            --background-dark: #121212;      /* Fondo oscuro */
            --surface-dark: #1e1e1e;         /* Superficie oscura */
            --text-dark: #f5f5f5;            /* Texto claro */
            --text-muted-dark: #b0b0b0;      /* Texto secundario oscuro */
            --border-dark: #333333;          /* Bordes oscuros */
            
            /* Variables activas (inician en modo claro) */
            --primary: var(--primary-light);
            --secondary: var(--secondary-light);
            --warning: var(--warning-light);
            --danger: var(--danger-light);
            --accent: var(--accent-light);
            --background: var(--background-light);
            --surface: var(--surface-light);
            --text: var(--text-light);
            --text-muted: var(--text-muted-light);
            --border: var(--border-light);
            
            /* Efecto mármol (transparencia sutil) */
            --marble-effect: linear-gradient(45deg, transparent 98%, rgba(255,255,255,0.1) 100%);
            
            /* Transiciones */
            --transition-fast: 0.2s ease;
            --transition-normal: 0.3s ease;
        }
        
        /* Aplicar modo oscuro si está activo */
        body.dark-mode {
            --primary: var(--primary-dark);
            --secondary: var(--secondary-dark);
            --warning: var(--warning-dark);
            --danger: var(--danger-dark);
            --accent: var(--accent-dark);
            --background: var(--background-dark);
            --surface: var(--surface-dark);
            --text: var(--text-dark);
            --text-muted: var(--text-muted-dark);
            --border: var(--border-dark);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: background-color var(--transition-normal), 
                        color var(--transition-normal),
                        border-color var(--transition-normal);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--background);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Efecto de textura de mármol sutil en el fondo */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: var(--marble-effect);
            opacity: 0.3;
            pointer-events: none;
            z-index: -1;
        }
        
        /* Contenedor principal */
        .minimal-container {
            width: 100%;
            max-width: 700px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            animation: fadeInUp 0.4s ease-out;
        }
        
        /* Animación de entrada */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Animación sutil para elementos interactivos */
        @keyframes subtlePulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.9;
            }
        }
        
        /* Encabezado minimalista */
        .minimal-header {
            background: var(--primary);
            color: white;
            padding: 24px;
            text-align: center;
            position: relative;
        }
        
        /* Contenedor del logo */
        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 12px;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 20px;
        }
        
        .header-title {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .header-subtitle {
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* Sección de alerta */
        .minimal-alert {
            background: var(--warning);
            border-left: 4px solid darkorange;
            padding: 16px 20px;
            margin: 20px;
            border-radius: 0 8px 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: subtlePulse 3s infinite;
        }
        
        .alert-icon {
            font-size: 20px;
            color: #333;
        }
        
        .alert-content h5 {
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .alert-content p {
            font-size: 13px;
            margin: 0;
            opacity: 0.9;
        }
        
        /* Tarjetas de pacientes */
        .patient-cards {
            padding: 0 20px 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .patient-card {
            background: var(--background);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            transition: transform var(--transition-fast), box-shadow var(--transition-fast);
        }
        
        .patient-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }
        
        .card-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
        }
        
        .patient-id {
            background: var(--accent);
            color: var(--text);
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        /* Grid de detalles del paciente */
        .patient-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        
        .detail-value {
            font-size: 14px;
            color: var(--text);
            font-weight: 500;
        }
        
        /* Botones de acción */
        .action-buttons {
            padding: 0 20px 30px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .btn-minimal {
            padding: 14px 20px;
            border-radius: 10px;
            border: 1px solid var(--border);
            font-weight: 500;
            font-size: 14px;
            transition: all var(--transition-fast);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--background);
            color: var(--text);
        }
        
        .btn-minimal:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary-minimal {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .btn-warning-minimal {
            background: var(--warning);
            color: #333;
            border-color: var(--warning);
        }
        
        .btn-danger-minimal {
            background: var(--danger);
            color: white;
            border-color: var(--danger);
        }
        
        /* Interruptor de modo noche */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .theme-toggle-btn {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: var(--surface);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all var(--transition-fast);
            color: var(--text);
        }
        
        .theme-toggle-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* Modal de confirmación */
        .confirmation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1050;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .confirmation-modal.show {
            display: flex;
            animation: fadeInUp 0.3s ease-out;
        }
        
        .confirmation-content {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 30px;
            max-width: 500px;
            width: 100%;
            text-align: center;
        }
        
        .confirmation-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .confirmation-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .confirmation-message {
            color: var(--text-muted);
            margin-bottom: 24px;
            font-size: 14px;
            line-height: 1.5;
        }
        
        .confirmation-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .minimal-container {
                margin: 0;
            }
            
            .patient-details {
                grid-template-columns: 1fr;
            }
            
            .confirmation-content {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Interruptor de modo noche -->
    <div class="theme-toggle">
        <button class="theme-toggle-btn" id="themeToggle" aria-label="Cambiar tema">
            <i class="bi bi-moon"></i>
        </button>
    </div>

    <div class="minimal-container">
        <!-- Encabezado -->
        <div class="minimal-header">
            <div class="logo-container">
                <div class="logo-icon">
                    <i class="bi bi-heart-pulse"></i>
                </div>
                <div>
                    <h1 class="header-title">Paciente Duplicado</h1>
                    <p class="header-subtitle">Centro Médico Herrera Sáenz</p>
                </div>
            </div>
        </div>

        <!-- Alerta -->
        <div class="minimal-alert">
            <div class="alert-icon">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="alert-content">
                <h5>Paciente ya registrado</h5>
                <p>Ya existe un paciente con el mismo nombre y apellido. Seleccione cómo desea proceder.</p>
            </div>
        </div>

        <!-- Tarjetas de pacientes -->
        <div class="patient-cards">
            <!-- Paciente existente -->
            <div class="patient-card">
                <div class="card-header">
                    <h3 class="card-title">Paciente Existente</h3>
                    <div class="patient-id">ID: #<?php echo str_pad($existingPatientId, 5, '0', STR_PAD_LEFT); ?></div>
                </div>
                <div class="patient-details">
                    <div class="detail-item">
                        <span class="detail-label">Nombre</span>
                        <span class="detail-value"><?php echo htmlspecialchars($_SESSION['existing_patient_nombre'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Apellido</span>
                        <span class="detail-value"><?php echo htmlspecialchars($_SESSION['existing_patient_apellido'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Fecha de registro</span>
                        <span class="detail-value"><?php echo isset($_SESSION['existing_patient_fecha']) ? date('d/m/Y', strtotime($_SESSION['existing_patient_fecha'])) : 'N/A'; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Consultas previas</span>
                        <span class="detail-value"><?php echo $_SESSION['existing_patient_consultas'] ?? '0'; ?> consultas</span>
                    </div>
                </div>
            </div>

            <!-- Nuevo paciente -->
            <div class="patient-card">
                <div class="card-header">
                    <h3 class="card-title">Nuevo Paciente</h3>
                    <div class="patient-id">Pendiente</div>
                </div>
                <div class="patient-details">
                    <div class="detail-item">
                        <span class="detail-label">Nombre</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patientData['nombre']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Apellido</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patientData['apellido']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Teléfono</span>
                        <span class="detail-value"><?php echo htmlspecialchars($patientData['telefono'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Estado</span>
                        <span class="detail-value">Por registrar</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones de acción -->
        <div class="action-buttons">
            <button type="button" class="btn-minimal btn-primary-minimal" onclick="confirmAction('overwrite')">
                <i class="bi bi-pencil-square"></i>
                Actualizar paciente existente
            </button>
            
            <button type="button" class="btn-minimal btn-warning-minimal" onclick="confirmAction('replace')">
                <i class="bi bi-arrow-repeat"></i>
                Reemplazar paciente existente
            </button>
            
            <button type="button" class="btn-minimal" onclick="confirmAction('cancel')">
                <i class="bi bi-x-circle"></i>
                Cancelar operación
            </button>
        </div>
    </div>

    <!-- Modal de confirmación -->
    <div id="confirmationModal" class="confirmation-modal">
        <div class="confirmation-content">
            <div class="confirmation-icon" id="confirmationIcon"></div>
            <h3 class="confirmation-title" id="confirmationTitle"></h3>
            <p class="confirmation-message" id="confirmationMessage"></p>
            <div class="confirmation-actions">
                <button type="button" class="btn-minimal" onclick="closeConfirmation()">Cancelar</button>
                <button type="button" class="btn-minimal" id="confirmButton">Confirmar</button>
            </div>
        </div>
    </div>

    <!-- Formulario oculto para enviar datos -->
    <form id="duplicateForm" action="save_patient.php" method="post" style="display: none;">
        <input type="hidden" name="confirm_action" id="confirmAction" value="">
        <input type="hidden" name="existing_patient_id" value="<?php echo $existingPatientId; ?>">
        
        <!-- Datos del paciente -->
        <?php foreach ($patientData as $key => $value): ?>
            <input type="hidden" name="<?php echo $key; ?>" value="<?php echo htmlspecialchars($value); ?>">
        <?php endforeach; ?>
    </form>

    <script>
        // Gestión del modo noche/día
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        
        // Comprobar preferencia guardada
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            body.classList.add('dark-mode');
            themeToggle.innerHTML = '<i class="bi bi-sun"></i>';
        }
        
        // Alternar tema
        themeToggle.addEventListener('click', function() {
            body.classList.toggle('dark-mode');
            
            if (body.classList.contains('dark-mode')) {
                localStorage.setItem('theme', 'dark');
                themeToggle.innerHTML = '<i class="bi bi-sun"></i>';
            } else {
                localStorage.setItem('theme', 'light');
                themeToggle.innerHTML = '<i class="bi bi-moon"></i>';
            }
        });
        
        // Gestión del modal de confirmación
        function confirmAction(action) {
            const modal = document.getElementById('confirmationModal');
            const icon = document.getElementById('confirmationIcon');
            const title = document.getElementById('confirmationTitle');
            const message = document.getElementById('confirmationMessage');
            const confirmButton = document.getElementById('confirmButton');
            
            // Configurar modal según la acción
            switch(action) {
                case 'overwrite':
                    icon.innerHTML = '<i class="bi bi-pencil-square" style="color: var(--primary); font-size: 48px;"></i>';
                    title.textContent = '¿Actualizar paciente existente?';
                    message.textContent = 'Se actualizarán los datos del paciente existente con la nueva información. El historial médico se mantendrá intacto.';
                    confirmButton.className = 'btn-minimal btn-primary-minimal';
                    confirmButton.innerHTML = '<i class="bi bi-check-circle"></i> Actualizar';
                    break;
                    
                case 'replace':
                    icon.innerHTML = '<i class="bi bi-arrow-repeat" style="color: var(--warning); font-size: 48px;"></i>';
                    title.textContent = '¿Reemplazar paciente existente?';
                    message.textContent = 'Se eliminará el paciente existente y se creará uno nuevo. Toda la información anterior se perderá permanentemente.';
                    confirmButton.className = 'btn-minimal btn-warning-minimal';
                    confirmButton.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Reemplazar';
                    break;
                    
                case 'cancel':
                    icon.innerHTML = '<i class="bi bi-x-circle" style="color: var(--text-muted); font-size: 48px;"></i>';
                    title.textContent = '¿Cancelar operación?';
                    message.textContent = 'No se realizarán cambios. Será redirigido a la lista de pacientes.';
                    confirmButton.className = 'btn-minimal';
                    confirmButton.innerHTML = '<i class="bi bi-arrow-left"></i> Volver';
                    break;
            }
            
            // Establecer acción y mostrar modal
            document.getElementById('confirmAction').value = action;
            modal.classList.add('show');
            
            // Manejar confirmación
            confirmButton.onclick = function() {
                document.getElementById('duplicateForm').submit();
            };
        }
        
        // Cerrar modal de confirmación
        function closeConfirmation() {
            document.getElementById('confirmationModal').classList.remove('show');
        }
        
        // Cerrar modal al hacer clic fuera
        document.getElementById('confirmationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeConfirmation();
            }
        });
        
        // Soporte de teclado (Escape para cerrar)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeConfirmation();
            }
        });
        
        // Efecto de carga sutil para las tarjetas
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.patient-card');
            cards.forEach((card, index) => {
                // Añadir retraso escalonado para animación
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>