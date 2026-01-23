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
    <style>
        /* Reutilizando estilos base del dashboard (simplificado) */
        :root {
            --color-bg: #ffffff;
            --color-text: #1a1a1a;
            --color-border: #e9ecef;
            --color-card: #ffffff;
            --color-primary: #0d6efd;
            --space-md: 1rem;
            --space-lg: 1.5rem;
            --radius-md: 0.5rem;
            --font-family: 'Inter', sans-serif;
        }

        body {
            font-family: var(--font-family);
            background-color: #f8f9fa;
            color: var(--color-text);
            margin: 0;
            padding: 2rem;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: var(--color-card);
            padding: 2rem;
            border-radius: var(--radius-md);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--color-border);
        }

        .section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--color-primary);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .info-item label {
            display: block;
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 0.25rem;
        }

        .info-item div {
            font-weight: 500;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--color-border);
        }

        .badge {
            padding: 0.35em 0.65em;
            border-radius: 0.25rem;
            font-size: 0.75em;
            font-weight: 700;
        }

        .bg-warning {
            background-color: #ffc107;
            color: #000;
        }

        .bg-success {
            background-color: #198754;
            color: #fff;
        }

        .bg-danger {
            background-color: #dc3545;
            color: #fff;
        }

        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: var(--color-primary);
            color: white;
            text-decoration: none;
            border-radius: 0.375rem;
            font-weight: 500;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .file-attachment {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
        }
    </style>
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
                    <?php foreach ($pruebas as $prueba): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($prueba['codigo_prueba']); ?></td>
                            <td><?php echo htmlspecialchars($prueba['nombre_prueba']); ?></td>
                            <td><?php echo $prueba['estado']; ?></td>
                            <td><?php
                            // Check if precio exists in the prueba array (from catalogo_pruebas join)
                            $precio = isset($prueba['precio']) ? $prueba['precio'] : 0;
                            echo 'Q' . number_format($precio, 2);
                            ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
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