<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';

$id_hospital = hospital_id();
verify_session();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID inválido");
}
$id_examen = $_GET['id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        SELECT *
        FROM examenes_realizados
        WHERE id_examen_realizado = ? AND id_hospital = ?
    ");
    $stmt->execute([$id_examen, $id_hospital]);
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orden)
        die("Orden no encontrada (ID: " . htmlspecialchars($id_examen) . ")");

    $fecha = new DateTime($orden['fecha_examen']);
    $fecha_formateada = $fecha->format('d/m/Y');
    $hora_formateada = $fecha->format('H:i');
    $paciente = $orden['nombre_paciente'];
    $user_name = $_SESSION['nombre'];

} catch (Exception $e) {
    error_log("Error en print_lab_receipt: " . $e->getMessage());
    die("Error al generar el recibo.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo Laboratorio #<?php echo htmlspecialchars($id_examen); ?></title>
    <link rel="stylesheet" href="../../assets/css/print_thermal.css">
</head>
<body>
    <div class="receipt-container">
        <div class="clinic-header text-center">
            <h2 class="fw-bold">CENTRO MEDICO HERRERA SAENZ</h2>
            <p>7a Av 7-25 Zona 1 HH</p>
            <p>Tel: (+502) 5214-8836</p>
        </div>
        <hr class="divider">
        <div class="receipt-details">
            <div class="row">
                <span>Fecha: <?php echo $fecha_formateada; ?></span>
                <span><?php echo $hora_formateada; ?></span>
            </div>
            <div class="row">
                <span>Recibo #: <?php echo str_pad($id_examen, 5, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="row">
                <span>Paciente:</span>
            </div>
            <div class="row">
                <span class="fw-bold"><?php echo htmlspecialchars($paciente); ?></span>
            </div>
        </div>
        <hr class="divider">
        <table class="items-table">
            <thead>
                <tr>
                    <th>Descripcion</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($orden['tipo_examen']); ?></td>
                    <td>Q<?php echo number_format($orden['cobro'], 2); ?></td>
                </tr>
            </tbody>
        </table>
        <hr class="divider">
        <div class="total-section">
            <span>TOTAL</span>
            <span>Q<?php echo number_format($orden['cobro'], 2); ?></span>
        </div>
        <div class="footer">
            <p>Pago: <?php echo htmlspecialchars($orden['tipo_pago']); ?></p>
            <p>Gracias por su visita!</p>
            <p>Atendio: <?php echo htmlspecialchars($user_name); ?></p>
        </div>
    </div>
    <script>
        window.onload = function () {
            window.print();
            setTimeout(function() {
                window.close();
            }, 1000);
        };
    </script>
</body>
</html>