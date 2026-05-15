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
$id_cobro = $_GET['id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        SELECT c.*, p.nombre as p_nom, p.apellido as p_ape, 
               d.nombre as d_nom, d.apellido as d_ape
        FROM cobros c
        JOIN pacientes p ON c.paciente_cobro = p.id_paciente
        LEFT JOIN usuarios d ON c.id_doctor = d.idUsuario
        WHERE c.in_cobro = ?
    ");
    $stmt->execute([$id_cobro]);
    $cobro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cobro)
        die("Cobro no encontrado");

    $fecha = new DateTime($cobro['fecha_consulta']);
    $fecha_formateada = $fecha->format('d/m/Y');
    $hora_formateada = $fecha->format('H:i');
    $paciente = $cobro['p_nom'] . ' ' . $cobro['p_ape'];
    $doctor = $cobro['d_nom'] ? 'Dr(a). ' . $cobro['d_nom'] . ' ' . $cobro['d_ape'] : 'N/A';
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
    <title>Recibo #
        <?php echo $id_cobro; ?>
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
            <div class="d-flex" style="display:flex; justify-content:space-between">
                <span>Fecha:
                    <?php echo $fecha_formateada; ?>
                </span>
                <span class="text-right">
                    <?php echo $hora_formateada; ?>
                </span>
            </div>
            <div>Recibo #:
                <?php echo str_pad($id_cobro, 5, '0', STR_PAD_LEFT); ?>
            </div>
            <div>Cliente:
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
                    <th style="width: 65%">Desc</th>
                    <th style="width: 35%" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($cobro['tipo_consulta']); ?>
                    </td>
                    <td class="text-right">Q
                        <?php echo number_format($cobro['cantidad_consulta'], 2); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="divider"></div>
        <div class="total-section">
            <span>TOTAL</span>
            <span>Q
                <?php echo number_format($cobro['cantidad_consulta'], 2); ?>
            </span>
        </div>
        <div class="footer">
            <p>Pago:
                <?php echo htmlspecialchars($cobro['tipo_pago']); ?>
            </p>
            <p>¡Gracias por su visita!</p>
            <p class="mt-2">Atendió:
                <?php echo htmlspecialchars($user_name); ?>
            </p>
        </div>
    </div>
    <script>
        window.onload = function () {
            window.print();
            setTimeout(function () { /* window.close(); */ }, 1000);
        };
    </script>
</body>

</html>