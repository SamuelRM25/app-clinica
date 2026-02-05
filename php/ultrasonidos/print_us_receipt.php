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
$id_us = $_GET['id'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("
        SELECT u.*, p.nombre as p_nom, p.apellido as p_ape
        FROM ultrasonidos u
        JOIN pacientes p ON u.id_paciente = p.id_paciente
        WHERE u.id_ultrasonido = ?
    ");
    $stmt->execute([$id_us]);
    $us = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$us)
        die("Registro no encontrado");

    $fecha = new DateTime($us['fecha_ultrasonido']);
    $fecha_formateada = $fecha->format('d/m/Y');
    $hora_formateada = $fecha->format('H:i');
    $paciente = $us['p_nom'] . ' ' . $us['p_ape'];
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
    <title>Recibo Ultrasonido #
        <?php echo $id_us; ?>
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
            <h2 class="fw-bold">Centro Médico RS</h2>
            <div class="clinic-info">
                <p>Dirección del cliente</p>
                <p>Tel: (502) 0000-0000</p>
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
            <div>Recibo #:
                <?php echo str_pad($id_us, 5, '0', STR_PAD_LEFT); ?>
            </div>
            <div>Paciente:
                <?php echo htmlspecialchars($paciente); ?>
            </div>
            <div>Estudio:
                <?php echo htmlspecialchars($us['tipo_ultrasonido']); ?>
            </div>
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
                    <td>Ultrasonido -
                        <?php echo htmlspecialchars($us['tipo_ultrasonido']); ?>
                    </td>
                    <td class="text-right">Q
                        <?php echo number_format($us['cobro'], 2); ?>
                    </td>
                </tr>
            </tbody>
        </table>
        <div class="divider"></div>
        <div class="total-section">
            <span>TOTAL</span>
            <span>Q
                <?php echo number_format($us['cobro'], 2); ?>
            </span>
        </div>
        <div class="footer">
            <p>Pago:
                <?php echo htmlspecialchars($us['tipo_pago']); ?>
            </p>
            <p>¡Gracias por su visita!</p>
            <p>Atendió:
                <?php echo htmlspecialchars($user_name); ?>
            </p>
        </div>
    </div>
    <script>
        window.onload = function () { window.print(); };
    </script>
</body>

</html>