<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';


verify_session();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID inválido");
}
$id_orden = $_GET['id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        SELECT o.*, p.nombre as p_nom, p.apellido as p_ape,
               d.nombre as d_nom, d.apellido as d_ape
        FROM ordenes_laboratorio o
        JOIN pacientes p ON o.id_paciente = p.id_paciente
        LEFT JOIN usuarios d ON o.id_doctor = d.idUsuario
        WHERE o.id_orden = ?
    ");
    $stmt->execute([$id_orden]);
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orden)
        die("Orden no encontrada");

    // Fetch tests details
    $stmtD = $conn->prepare("
        SELECT cp.nombre_prueba, cp.precio
        FROM orden_pruebas op
        JOIN catalogo_pruebas cp ON op.id_prueba = cp.id_prueba
        WHERE op.id_orden = ?
    ");
    $stmtD->execute([$id_orden]);
    $detalles = $stmtD->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    foreach ($detalles as $d)
        $total += $d['precio'];

    $fecha = new DateTime($orden['fecha_orden']);
    $fecha_formateada = $fecha->format('d/m/Y');
    $hora_formateada = $fecha->format('H:i');
    $paciente = $orden['p_nom'] . ' ' . $orden['p_ape'];
    $doctor = $orden['d_nom'] ? 'Dr(a). ' . $orden['d_nom'] . ' ' . $orden['d_ape'] : 'N/A';
    $user_name = $_SESSION['nombre'];

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden Laboratorio #
        <?php echo $orden['numero_orden']; ?>
    </title>
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
</head>

<body>
    <div class="receipt-container">
        <div class="clinic-header text-center">
            <h2 class="fw-bold">Centro Médico RS</h2>
            <div class="clinic-info">
                <p>7a Av 7-25 Zona 1 HH</p>
                <p>Tel: (502) 5214-8836</p>
            </div>
        </div>
        <div class="divider"></div>
        <div class="receipt-details">
            <div style="display:flex; justify-content:space-between">
                <span>Fecha:
                    <?php echo $fecha_formateada; ?>
                </span>
                <span class="text-right">
                    <?php echo $hora_formateada; ?>
                </span>
            </div>
            <div>Orden #:
                <?php echo htmlspecialchars($orden['numero_orden']); ?>
            </div>
            <div>Paciente:
                <?php echo htmlspecialchars($paciente); ?>
            </div>
            <div>Doctor:
                <?php echo htmlspecialchars($doctor); ?>
            </div>
        </div>
        <div class="divider"></div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 65%">Prueba</th>
                    <th style="width: 35%" class="text-right">Precio</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $d): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($d['nombre_prueba']); ?>
                            </td>
                            <td class="text-right">Q
                                <?php echo number_format($d['precio'], 2); ?>
                            </td>
                        </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="divider"></div>
        <div class="total-section">
            <span>TOTAL</span>
            <span>Q
                <?php echo number_format($total, 2); ?>
            </span>
        </div>
        <div class="footer">
            <p>Estado:
                <?php echo htmlspecialchars($orden['estado']); ?>
            </p>
            <p>¡Gracias por su visita!</p>
            <p>Generado por:
                <?php echo htmlspecialchars($user_name); ?>
            </p>
        </div>
    </div>
    <script>
        window.onload = function () { window.print(); };
    </script>
</body>

</html>