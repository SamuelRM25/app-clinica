<?php
// laboratory/ver_orden.php - Read-only view of a laboratory order
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

 require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



date_default_timezone_set('America/Guatemala');
verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_orden = $_GET['id'] ?? null;
    if (!$id_orden) {
        header("Location: index.php");
        exit;
    }

    // Obtener información de la orden y paciente
    $stmt = $conn->prepare("
        SELECT ol.*, p.nombre, p.apellido, p.genero, p.fecha_nacimiento,
               u.nombre as doctor_nombre, u.apellido as doctor_apellido
        FROM ordenes_laboratorio ol
        JOIN pacientes p ON ol.id_paciente = p.id_paciente
        LEFT JOIN usuarios u ON ol.id_doctor = u.idUsuario
        WHERE ol.id_orden = ?
    ");
    $stmt->execute([$id_orden]);
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orden) {
        throw new Exception("Orden no encontrada");
    }

    // Obtener pruebas de la orden
    $stmt = $conn->prepare("
        SELECT op.*, cp.nombre_prueba, cp.codigo_prueba, cp.precio
        FROM orden_pruebas op
        JOIN catalogo_pruebas cp ON op.id_prueba = cp.id_prueba
        WHERE op.id_orden = ?
    ");
    $stmt->execute([$id_orden]);
    $pruebas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener resultados si existen
    // Note: resultados_laboratorio links via id_orden_prueba, not id_orden
    $stmt = $conn->prepare("
        SELECT rl.* FROM resultados_laboratorio rl
        INNER JOIN orden_pruebas op ON rl.id_orden_prueba = op.id_orden_prueba
        WHERE op.id_orden = ?
    ");
    $stmt->execute([$id_orden]);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $page_title = "Ver Orden #" . $orden['numero_orden'];

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
</head>

<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 style="margin:0">Orden #<?php echo htmlspecialchars($orden['numero_orden']); ?></h1>
                <span class="badge <?php echo $orden['estado'] == 'Completada' ? 'bg-success' : 'bg-warning'; ?>">
                    <?php echo $orden['estado']; ?>
                </span>
            </div>
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

        <div class="section">
            <h3 class="section-title">Información del Paciente</h3>
            <div class="info-grid">
                <div class="info-item">
                    <label>Paciente</label>
                    <div style="font-size: 1.2rem">
                        <?php echo htmlspecialchars($orden['nombre'] . ' ' . $orden['apellido']); ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Fecha de Nacimiento</label>
                    <div><?php echo date('d/m/Y', strtotime($orden['fecha_nacimiento'])); ?></div>
                </div>
                <div class="info-item">
                    <label>Doctor Solicitante</label>
                    <div>
                        <?php echo $orden['doctor_nombre'] ? "Dr. {$orden['doctor_nombre']} {$orden['doctor_apellido']}" : 'N/A'; ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Fecha de Orden</label>
                    <div><?php echo date('d/m/Y H:i', strtotime($orden['fecha_orden'])); ?></div>
                </div>
            </div>
        </div>

        <div class="section">
            <h3 class="section-title">Pruebas Solicitadas</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Prueba</th>
                        <th>Estado</th>
                        <th>Precio</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total = 0;
                    foreach ($pruebas as $prueba):
                        $precio = isset($prueba['precio']) ? $prueba['precio'] : 0;
                        $total += $precio;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($prueba['codigo_prueba']); ?></td>
                            <td><?php echo htmlspecialchars($prueba['nombre_prueba']); ?></td>
                            <td><?php echo $prueba['estado']; ?></td>
                            <td><?php echo 'Q' . number_format($precio, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background-color: #f8f9fa; font-weight: bold;">
                        <td colspan="3" style="text-align: right;">TOTAL:</td>
                        <td><?php echo 'Q' . number_format($total, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php if (!empty($orden['observaciones'])): ?>
            <div class="section">
                <h3 class="section-title">Observaciones</h3>
                <p><?php echo nl2br(htmlspecialchars($orden['observaciones'])); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($orden['archivo_resultados'])): ?>
            <div class="section">
                <h3 class="section-title">Resultados Adjuntos</h3>
                <div class="file-attachment">
                    <i class="bi bi-file-earmark-pdf" style="font-size: 2rem; color: var(--color-danger)"></i>
                    <div>
                        <div><strong>Archivo de Resultados</strong></div>
                        <small class="text-muted">Adjunto procesado</small>
                    </div>
                    <a href="<?php echo htmlspecialchars($orden['archivo_resultados']); ?>" target="_blank" class="btn"
                        style="margin-left: auto;">
                        <i class="bi bi-download"></i> Ver/Descargar
                    </a>
                </div>

                <?php
                $ext = pathinfo($orden['archivo_resultados'], PATHINFO_EXTENSION);
                if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'])):
                    ?>
                    <div style="margin-top: 1rem; text-align: center;">
                        <img src="<?php echo htmlspecialchars($orden['archivo_resultados']); ?>"
                            style="max-width: 100%; border-radius: 0.5rem; border: 1px solid #dee2e6;">
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>