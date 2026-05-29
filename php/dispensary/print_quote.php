<?php
// inventory/print_receipt.php - Recibo de Venta - Centro Médico RS
// Versión: 4.0 - Diseño Responsive con Sidebar Moderna y Efecto Mármol
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

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
        WHERE v.id_venta = ? AND v.id_hospital = ?
    ");
    $stmt->execute([$id_venta, $id_hospital]);
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
        WHERE dv.id_venta = ? AND dv.id_hospital = ?
    ");
    $stmt->execute([$id_venta, $id_hospital]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Información del usuario
    $user_name = $_SESSION['nombre'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

    // Estadísticas adicionales
    $stmt = $conn->prepare("SELECT COUNT(*) as total_ventas FROM ventas WHERE id_hospital = ?");
    $stmt->execute([$id_hospital]);
    $total_ventas = $stmt->fetch(PDO::FETCH_ASSOC)['total_ventas'] ?? 0;

    // Ventas del mes
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM ventas WHERE fecha_venta BETWEEN ? AND ? AND id_hospital = ?");
    $stmt->execute([$month_start, $month_end, $id_hospital]);
    $month_sales = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Título de la página
    $page_title = "Recibo de Venta #" . str_pad($id_venta, 5, '0', STR_PAD_LEFT) . " - Centro Médico RS";

} catch (Exception $e) {
    error_log('Error en dispensary/print_quote.php: ' . $e->getMessage());
    die("Error: " . 'Error del servidor.');
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
    <meta name="description" content="Recibo de venta del Centro Médico RS - Sistema de gestión médica">
    <title><?php echo htmlspecialchars($page_title); ?></title>

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

    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">

    <style>
        /* ===== SCREEN PREVIEW ===== */
        body {
            background: #f0f4f8;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding: 2rem 1rem;
            min-height: 100vh;
        }

        .receipt-container {
            background: white;
            width: 80mm;
            max-width: 320px;
            padding: 1.5rem 1.25rem;
            border-radius: 8px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.15);
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            color: #111;
        }

        .clinic-header h2 { font-size: 16px; margin: 0 0 0.25rem; }
        .clinic-info p { margin: 0; font-size: 11px; color: #555; }

        .divider {
            border: none;
            border-top: 1px dashed #bbb;
            margin: 0.75rem 0;
        }

        .receipt-details { line-height: 1.8; font-size: 11px; }

        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th {
            border-bottom: 1px solid #ccc;
            padding: 4px 2px;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .items-table td { padding: 4px 2px; font-size: 11px; vertical-align: top; }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }

        .total-section {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            font-weight: bold;
            padding: 0.5rem 0;
        }

        .footer { text-align: center; margin-top: 0.75rem; font-size: 11px; color: #444; }

        /* Print controls bar */
        .print-actions {
            width: 80mm;
            max-width: 320px;
            display: flex;
            gap: 0.5rem;
            margin: 1rem auto 0;
        }
        .print-actions button {
            flex: 1;
            padding: 0.6rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 13px;
        }
        .btn-print { background: #0d6efd; color: white; }
        .btn-close-win { background: #f0f4f8; color: #444; border: 1px solid #ccc; }

        /* ===== PRINT MEDIA — ONE PAGE ONLY ===== */
        @media print {
            /* Reset everything from global CSS */
            * { margin: 0 !important; padding: 0 !important; box-shadow: none !important; }

            html, body {
                width: 80mm;
                height: auto;
                background: white !important;
                display: block !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            /* Hide everything not the receipt */
            body > *:not(.receipt-container) { display: none !important; }
            .print-actions { display: none !important; }

            .receipt-container {
                width: 80mm;
                max-width: 100%;
                box-shadow: none;
                border-radius: 0;
                padding: 4mm 4mm;
                page-break-inside: avoid;
                break-inside: avoid;
                color: black;
            }

            /* Force everything onto 1 page */
            @page {
                size: 80mm auto;
                margin: 2mm;
            }
        }
    </style>
</head>

<body>
    <div class="receipt-container">
        <div class="clinic-header text-center">
            <h2 class="fw-bold">Centro Médico RS</h2>
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

    <!-- Print / Close actions (hidden on print) -->
    <div class="print-actions">
        <button class="btn-print" onclick="window.print()">
            🖨 Imprimir
        </button>
        <button class="btn-close-win" onclick="window.close()">
            ✕ Cerrar
        </button>
    </div>

    <script>
        // Auto-print after the page fully renders (fonts + layout ready)
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 400);
        });
    </script>
</body>

</html>