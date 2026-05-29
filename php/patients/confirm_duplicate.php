<?php
session_start();


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

    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
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
                        <span
                            class="detail-value"><?php echo htmlspecialchars($_SESSION['existing_patient_nombre'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Apellido</span>
                        <span
                            class="detail-value"><?php echo htmlspecialchars($_SESSION['existing_patient_apellido'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Fecha de registro</span>
                        <span
                            class="detail-value"><?php echo isset($_SESSION['existing_patient_fecha']) ? date('d/m/Y', strtotime($_SESSION['existing_patient_fecha'])) : 'N/A'; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Consultas previas</span>
                        <span class="detail-value"><?php echo $_SESSION['existing_patient_consultas'] ?? '0'; ?>
                            consultas</span>
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
                        <span
                            class="detail-value"><?php echo htmlspecialchars($patientData['telefono'] ?? 'N/A'); ?></span>
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
        <?php echo csrf_field(); ?>
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
        themeToggle.addEventListener('click', function () {
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
            switch (action) {
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
            confirmButton.onclick = function () {
                document.getElementById('duplicateForm').submit();
            };
        }

        // Cerrar modal de confirmación
        function closeConfirmation() {
            document.getElementById('confirmationModal').classList.remove('show');
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('confirmationModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closeConfirmation();
            }
        });

        // Soporte de teclado (Escape para cerrar)
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeConfirmation();
            }
        });

        // Efecto de carga sutil para las tarjetas
        document.addEventListener('DOMContentLoaded', function () {
            const cards = document.querySelectorAll('.patient-card');
            cards.forEach((card, index) => {
                // Añadir retraso escalonado para animación
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>

</html>