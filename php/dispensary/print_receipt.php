<?php
// inventory/print_receipt.php - Recibo de Venta - Centro Médico Herrera Saenz
// Versión: 4.0 - Diseño Responsive con Sidebar Moderna y Efecto Mármol
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

// Verificar si se proporciona ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de venta inválido");
}

$id_venta = $_GET['id'];

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener datos de la venta
    $stmt = $conn->prepare("
        SELECT v.*, u.nombre as Cajero
        FROM ventas v
        LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
        WHERE v.id_venta = ?
    ");
    $stmt->execute([$id_venta]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$venta) {
        die("Venta no encontrada");
    }

    $nit_cliente = $venta['nit_cliente'] ?? 'C/F';
    $cajero = $venta['Cajero'] ?? $user_name;

    // Obtener items de la venta
    $stmt = $conn->prepare("
        SELECT dv.*, i.nom_medicamento, i.mol_medicamento, i.presentacion_med
        FROM detalle_ventas dv
        JOIN inventario i ON dv.id_inventario = i.id_inventario
        WHERE dv.id_venta = ?
    ");
    $stmt->execute([$id_venta]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Información del usuario
    $user_name = $_SESSION['nombre'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

    // Estadísticas adicionales
    $stmt = $conn->prepare("SELECT COUNT(*) as total_ventas FROM ventas");
    $stmt->execute();
    $total_ventas = $stmt->fetch(PDO::FETCH_ASSOC)['total_ventas'] ?? 0;

    // Ventas del mes
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM ventas WHERE fecha_venta BETWEEN ? AND ?");
    $stmt->execute([$month_start, $month_end]);
    $month_sales = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Título de la página
    $page_title = "Recibo de Venta #" . str_pad($id_venta, 5, '0', STR_PAD_LEFT) . " - Centro Médico Herrera Saenz";

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Formatear fecha
$fecha = new DateTime($venta['fecha_venta']);
$fecha_formateada = $fecha->format('d/m/Y');
$hora_formateada = $fecha->format('H:i');
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Recibo de venta del Centro Médico Herrera Saenz - Sistema de gestión médica">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            /* Printable area */
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

        .mb-1 {
            margin-bottom: 2px;
        }

        .mb-2 {
            margin-bottom: 5px;
        }

        .mt-2 {
            margin-top: 5px;
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

            .no-print {
                display: none;
            }

            @page {
                size: 80mm auto;
                /* continuous */
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
                <p>Tel: (+502) 5214-8836</p>
            </div>
        </div>

        <div class="divider"></div>

        <div class="receipt-details">
            <div class="d-flex justify-content-between">
                <span>Fecha: <?php echo $fecha_formateada; ?></span>
                <span class="text-right"><?php echo $hora_formateada; ?></span>
            </div>
            <div>Recibo #: <?php echo str_pad($id_venta, 5, '0', STR_PAD_LEFT); ?></div>
            <div>Cliente: <?php echo htmlspecialchars($venta['nombre_cliente']); ?></div>
            <div>NIT: <?php echo htmlspecialchars($nit_cliente); ?></div>
        </div>

        <div class="divider"></div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 50%">Desc</th>
                    <th style="width: 15%" class="text-center">Cant</th>
                    <th style="width: 35%" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['nom_medicamento']); ?><br>
                            <small
                                style="font-size: 9px;"><?php echo htmlspecialchars($item['presentacion_med']); ?></small>
                        </td>
                        <td class="text-center"><?php echo $item['cantidad_vendida']; ?></td>
                        <td class="text-right">Q<?php echo number_format($item['subtotal'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="divider"></div>

        <div class="total-section">
            <span>TOTAL</span>
            <span>Q<?php echo number_format($venta['total'], 2); ?></span>
        </div>

        <div class="footer">
            <p>¡Gracias por su compra!</p>
            <p class="mt-2">Atendió: <?php echo htmlspecialchars($cajero); ?></p>
        </div>
    </div>

    <!-- Auto Print -->
    <script>
        window.onload = function () {
            window.print();
            // Optional: window.close(); after print? Users usually prefer to see it first.
            // But user asked for efficiency.
            // Listen for print completion or just focus
            setTimeout(function () {
                // window.close(); 
            }, 1000);
        };
    </script>
</body>

</html>