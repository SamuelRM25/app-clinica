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
$id_abono = $_GET['id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_hospital = $_SESSION['id_hospital'] ?? 0;

    $stmt = $conn->prepare("
        SELECT a.*, 
               p.nombre as p_nom, p.apellido as p_ape,
               c.saldo_pendiente, c.total_general,
               u.nombre as u_nom
        FROM abonos_hospitalarios a
        JOIN cuenta_hospitalaria c ON a.id_cuenta = c.id_cuenta
        JOIN encamamientos e ON c.id_encamamiento = e.id_encamamiento
        JOIN pacientes p ON e.id_paciente = p.id_paciente
        LEFT JOIN usuarios u ON a.registrado_por = u.idUsuario
        WHERE a.id_abono = ? AND e.id_hospital = ?
    ");
    $stmt->execute([$id_abono, $id_hospital]);
    $abono = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$abono)
        die("Abono no encontrado");

    $fecha = new DateTime($abono['fecha_abono']);
    $fecha_formateada = $fecha->format('d/m/Y');
    $hora_formateada = $fecha->format('H:i');
    $paciente = $abono['p_nom'] . ' ' . $abono['p_ape'];
    $user_name = $abono['u_nom'] ?? $_SESSION['nombre'];

} catch (Exception $e) {
    error_log("Error en print_abono: " . $e->getMessage());
    die("Error al generar el recibo.");
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo Abono #
        <?php echo htmlspecialchars($id_abono); ?>
    </title>
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
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
            <div class="d-flex" style="display:flex; justify-content:space-between">
                <span>Fecha:
                    <?php echo $fecha_formateada; ?>
                </span>
                <span class="text-right">
                    <?php echo $hora_formateada; ?>
                </span>
            </div>
            <div>Recibo Abono #:
                <?php echo str_pad($id_abono, 5, '0', STR_PAD_LEFT); ?>
            </div>
            <div>Cliente:
                <?php echo htmlspecialchars($paciente); ?>
            </div>
        </div>
        <div class="divider"></div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 65%">Concepto</th>
                    <th style="width: 35%" class="text-right">Monto</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Abono a Cuenta</td>
                    <td class="text-right">Q
                        <?php echo number_format($abono['monto'], 2); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="divider"></div>
        <div class="total-section">
            <span>TOTAL ABONADO</span>
            <span>Q
                <?php echo number_format($abono['monto'], 2); ?>
            </span>
        </div>
        <div class="mt-2 text-right" style="font-size: 10px;">
            <p>Saldo Pendiente: Q
                <?php echo number_format($abono['saldo_pendiente'], 2); ?>
            </p>
        </div>
        <div class="footer">
            <p>Pago:
                <?php echo htmlspecialchars($abono['metodo_pago']); ?>
            </p>
            <p>¡Gracias por su pago!</p>
            <p class="mt-2">Registró:
                <?php echo htmlspecialchars($user_name); ?>
            </p>
        </div>
    </div>
    <script>
        window.onload = function () {
            window.print();
        };
    </script>
</body>

</html>