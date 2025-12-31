<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de venta inválido");
}

$id_venta = $_GET['id'];

try {
    // Initialize database connection
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get sale data
    $stmt = $conn->prepare("SELECT * FROM ventas WHERE id_venta = ?");
    $stmt->execute([$id_venta]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$venta) {
        die("Venta no encontrada");
    }
    
    // Get sale items
    $stmt = $conn->prepare("
        SELECT dv.*, i.nom_medicamento, i.mol_medicamento, i.presentacion_med
        FROM detalle_ventas dv
        JOIN inventario i ON dv.id_inventario = i.id_inventario
        WHERE dv.id_venta = ?
    ");
    $stmt->execute([$id_venta]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

// Format date
$fecha = new DateTime($venta['fecha_venta']);
$fecha_formateada = $fecha->format('d/m/Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Venta #<?php echo str_pad($id_venta, 5, '0', STR_PAD_LEFT); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Playfair+Display:ital,wght@1,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --professional-blue: #1e3a8a;
            --clinical-blue: #3b82f6;
            --accent-green: #10b981;
            --text-dark: #1f2937;
            --text-muted: #6b7280;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 40px;
            background-color: #e5e7eb;
            color: var(--text-dark);
        }

        .receipt-container {
            width: 148mm;
            min-height: 210mm;
            margin: 0 auto;
            background-color: white;
            padding: 40px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .clinic-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--professional-blue);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .logo-section img {
            height: 60px;
            margin-bottom: 10px;
        }

        .clinic-name {
            font-family: 'Playfair Display', serif;
            color: var(--professional-blue);
            font-size: 24px;
            font-weight: 700;
            margin: 0;
        }

        .clinic-info {
            text-align: right;
            font-size: 11px;
            line-height: 1.5;
            color: var(--text-muted);
        }

        .sale-meta {
            background-color: #f3f4f6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 4px;
            font-weight: 600;
        }

        .info-value {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
        }

        .items-section {
            flex-grow: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th {
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            border-bottom: 1px solid #e5e7eb;
            padding: 10px 5px;
        }

        td {
            padding: 15px 5px;
            border-bottom: 1px solid #f3f4f6;
            font-size: 13px;
        }

        .item-name {
            font-weight: 700;
            color: var(--text-dark);
        }

        .item-details {
            font-size: 11px;
            color: var(--text-muted);
        }

        .price-col {
            text-align: right;
            font-family: monospace;
            font-weight: 600;
        }

        .total-section {
            margin-top: 30px;
            border-top: 2px solid var(--professional-blue);
            padding-top: 20px;
        }

        .total-row {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 20px;
        }

        .total-label {
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            color: var(--professional-blue);
        }

        .total-amount {
            font-size: 24px;
            font-weight: 800;
            color: var(--professional-blue);
        }

        .receipt-footer {
            margin-top: 40px;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
            text-align: center;
            font-size: 11px;
            color: var(--text-muted);
        }

        .thanks-msg {
            font-family: 'Playfair Display', serif;
            font-style: italic;
            font-size: 18px;
            color: var(--clinical-blue);
            margin-bottom: 15px;
        }

        .action-buttons {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            gap: 15px;
            z-index: 100;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: transform 0.2s;
        }

        .btn-print { background-color: var(--clinical-blue); color: white; }
        .btn-close { background-color: white; color: var(--text-dark); }

        @media print {
            body { background-color: white; padding: 0; }
            .receipt-container { box-shadow: none; padding: 0; width: 148mm; height: 210mm; }
            .action-buttons { display: none; }
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(0, 0, 0, 0.02);
            pointer-events: none;
            z-index: 0;
            font-weight: 900;
            text-transform: uppercase;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="watermark">PAGADO</div>
        
        <header class="clinic-header" style="z-index: 1; position: relative;">
            <div class="logo-section">
                <img src="../../assets/img/siloe.png" alt="Clinica Siloe">
                <h1 class="clinic-name">Clínica Médica Siloé</h1>
            </div>
            <div class="clinic-info">
                <strong>Servicios Médicos Integrales</strong><br>
                Nentón, Huehuetenango, Guatemala<br>
                Tel: (+502) 4623-2418<br>
                Email: contacto@clinicasiloe.com
            </div>
        </header>

        <section class="sale-meta">
            <div class="info-item">
                <span class="info-label">Cliente</span>
                <span class="info-value"><?php echo htmlspecialchars($venta['nombre_cliente']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Fecha</span>
                <span class="info-value"><?php echo $fecha_formateada; ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Método de Pago</span>
                <span class="info-value"><?php echo htmlspecialchars($venta['tipo_pago']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">No. Recibo</span>
                <span class="info-value">#VNT-<?php echo str_pad($id_venta, 5, '0', STR_PAD_LEFT); ?></span>
            </div>
        </section>

        <main class="items-section">
            <table style="z-index: 1; position: relative;">
                <thead>
                    <tr>
                        <th style="width: 50%;">Producto</th>
                        <th style="text-align: center;">Cant.</th>
                        <th style="text-align: right;">Precio</th>
                        <th style="text-align: right;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <div class="item-name"><?php echo htmlspecialchars($item['nom_medicamento']); ?></div>
                            <div class="item-details"><?php echo htmlspecialchars($item['mol_medicamento']); ?> • <?php echo htmlspecialchars($item['presentacion_med']); ?></div>
                        </td>
                        <td style="text-align: center; font-weight: 600;"><?php echo $item['cantidad_vendida']; ?></td>
                        <td class="price-col">Q<?php echo number_format($item['precio_unitario'], 2); ?></td>
                        <td class="price-col">Q<?php echo number_format($item['cantidad_vendida'] * $item['precio_unitario'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="total-section">
                <div class="total-row">
                    <span class="total-label">Total Gral.</span>
                    <span class="total-amount">Q<?php echo number_format($venta['total'], 2); ?></span>
                </div>
            </div>
        </main>

        <footer class="receipt-footer">
            <div class="thanks-msg">¡Gracias por su visita!</div>
            <div style="margin-bottom: 5px;">Documento generado por CM Siloé Management System</div>
        </footer>
    </div>

    <div class="action-buttons">
        <button class="btn btn-close" onclick="window.close()">Cerrar</button>
        <button class="btn btn-print" onclick="window.print()">Imprimir Recibo</button>
    </div>
</body>
</html>