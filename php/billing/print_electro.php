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
$id_electro = $_GET['id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Try finding in electrocardiogramas first, if not try cobros with type 'Electrocardiograma'
    // But since save_electro.php inserts into electrocardiogramas, we look there.
    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $stmt = $conn->prepare("
        SELECT e.*, p.nombre as p_nom, p.apellido as p_ape,
               d.nombre as d_nom, d.apellido as d_ape
        FROM electrocardiogramas e
        JOIN pacientes p ON e.id_paciente = p.id_paciente
        LEFT JOIN usuarios d ON e.id_doctor = d.idUsuario
        WHERE e.id_electro = ? AND e.id_hospital = ?
    ");
    $stmt->execute([$id_electro, $id_hospital]);
    $electro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$electro)
        die("Electrocardiograma no encontrado");

    $fecha = new DateTime($electro['fecha_realizado']);
    $fecha_formateada = $fecha->format('d/m/Y');
    $hora_formateada = $fecha->format('H:i');
    $paciente = $electro['p_nom'] . ' ' . $electro['p_ape'];
    $doctor = $electro['d_nom'] ? 'Dr(a). ' . $electro['d_nom'] . ' ' . $electro['d_ape'] : 'N/A';
    $user_name = $_SESSION['nombre'];

} catch (Exception $e) {
    error_log("Error en print_electro: " . $e->getMessage());
    die("Error al generar el recibo.");
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo Electrocardiograma #<?php echo htmlspecialchars($id_electro); ?></title>
    <link rel="stylesheet" href="../../assets/css/print_thermal.css">
</head>

<body>
    <div class="receipt-container">
        <div class="clinic-header text-center">
            <h2 class="fw-bold">Centro Médico Herrera Saenz</h2>
            <div class="clinic-info">
                <p>7a Av 7-25 Zona 1 HH</p>
                <p>Tel: (502) 5214-8836</p>
            </div>
        </div>
        <div class="divider"></div>
        <div class="receipt-details">
            <div style="display:flex; justify-content:space-between">
                <span>Fecha: <?php echo $fecha_formateada; ?></span>
                <span class="text-right"><?php echo $hora_formateada; ?></span>
            </div>
            <div>Recibo #: <?php echo str_pad($id_electro, 5, '0', STR_PAD_LEFT); ?></div>
            <div>Paciente: <?php echo htmlspecialchars($paciente); ?></div>
            <div>Doctor: <?php echo htmlspecialchars($doctor); ?></div>
        </div>
        <div class="divider"></div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 65%">Descripción</th>
                    <th style="width: 35%" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Electrocardiograma</td>
                    <td class="text-right">Q<?php echo number_format($electro['precio'], 2); ?></td>
                </tr>
            </tbody>
        </table>
        <div class="divider"></div>
        <div class="total-section">
            <span>TOTAL</span>
            <span>Q<?php echo number_format($electro['precio'], 2); ?></span>
        </div>
        <div class="footer">
            <p>Estado: <?php echo htmlspecialchars($electro['estado_pago']); ?></p>
            <p>¡Gracias por su visita!</p>
            <p>Atendió: <?php echo htmlspecialchars($user_name); ?></p>
        </div>
    </div>
    <script>
        window.onload = function () { window.print(); };
    </script>
</body>

</html>