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
    <title>Confirmar paciente duplicado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Paciente duplicado encontrado</h2>
        <p>Ya existe un paciente con el mismo nombre y apellido:</p>
        
        <div class="card mb-4">
            <div class="card-body">
                <strong>Nombre:</strong> <?= htmlspecialchars($patientData['nombre']) ?><br>
                <strong>Apellido:</strong> <?= htmlspecialchars($patientData['apellido']) ?><br>
                <strong>ID existente:</strong> <?= $existingPatientId ?>
            </div>
        </div>

        <form action="save_patient.php" method="post">
            <input type="hidden" name="confirm_action" value="">
            <input type="hidden" name="existing_patient_id" value="<?= $existingPatientId ?>">
            
            <!-- Campos ocultos con los datos del paciente -->
            <?php foreach ($patientData as $key => $value): ?>
                <input type="hidden" name="<?= $key ?>" value="<?= htmlspecialchars($value) ?>">
            <?php endforeach; ?>

            <div class="mb-3">
                <button type="button" class="btn btn-danger" 
                    onclick="setAction('replace')">
                    Reemplazar paciente existente
                </button>
                <small class="form-text text-muted">
                    (Elimina el paciente existente y crea uno nuevo)
                </small>
            </div>

            <div class="mb-3">
                <button type="button" class="btn btn-warning" 
                    onclick="setAction('overwrite')">
                    Sobreescribir paciente existente
                </button>
                <small class="form-text text-muted">
                    (Actualiza los datos del paciente existente)
                </small>
            </div>

            <div class="mb-3">
                <button type="button" class="btn btn-secondary" 
                    onclick="setAction('cancel')">
                    Cancelar operación
                </button>
                <small class="form-text text-muted">
                    (No realizar cambios)
                </small>
            </div>
        </form>

        <script>
            function setAction(action) {
                document.querySelector('[name="confirm_action"]').value = action;
                document.forms[0].submit();
            }
        </script>
    </div>
</body>
</html>