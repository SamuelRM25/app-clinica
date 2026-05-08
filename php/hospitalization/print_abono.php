<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}
require_once '../../config/database.php';
require_once '../../includes/functions.php';
verify_session();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID inválido");
}
$id_abono = $_GET['id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

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
        WHERE a.id_abono = ?
    ");
    $stmt->execute([$id_abono]);
    $abono = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$abono)
        die("Abono no encontrado");

    $fecha = new DateTime($abono['fecha_abono']);
    $fecha_formateada = $fecha->format('d/m/Y');
    $hora_formateada = $fecha->format('H:i');
    $paciente = $abono['p_nom'] . ' ' . $abono['p_ape'];
    $user_name = $abono['u_nom'] ?? $_SESSION['nombre'];

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo Abono #
        <?php echo $id_abono; ?>
    </title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;700&display=swap');

        :root {
            --font-family: 'Roboto Mono', monospace;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            font-size: 11px;
            line-height: 1.2;
            background-color: #fff;
            color: #000;
        }

        .receipt-container {
            width: 72mm;
            margin: 0 auto;
            padding: 2mm;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .fw-bold {
            font-weight: 700;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }

        .clinic-header h2 {
            font-size: 14px;
            margin-bottom: 2px;
        }

        .clinic-info {
            font-size: 10px;
        }

        .receipt-details {
            margin: 5px 0;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .items-table th {
            text-align: left;
            border-bottom: 1px dashed #000;
            padding-bottom: 2px;
        }

        .items-table td {
            padding: 2px 0;
            vertical-align: top;
        }

        .total-section {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 700;
            margin-top: 5px;
        }

        .footer {
            margin-top: 10px;
            font-size: 10px;
            text-align: center;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
            }

            @page {
                size: 80mm auto;
                margin: 0;
            }
        }
    </style>
</head>

<body>
    <div class="receipt-container">
        <div class="clinic-header text-center">
            <h2 class="fw-bold">CENTRO MÉDICO HERRERA SAENZ</h2>
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