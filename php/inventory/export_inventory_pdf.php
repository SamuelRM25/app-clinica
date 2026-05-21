<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';



if (!isset($_SESSION['user_id'])) {
    die("No autorizado");
}
verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();

    $query = "
        SELECT i.*, ph.document_number, pi.unit_cost 
        FROM inventario i
        LEFT JOIN purchase_items pi ON i.id_purchase_item = pi.id
        LEFT JOIN purchase_headers ph ON pi.purchase_header_id = ph.id
        ORDER BY i.nom_medicamento ASC
    ";
    $stmt = $conn->query($query);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Inventario - Centro Médico RS</title>
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    
    <style>
        :root {
            --report-padding: 30px;
            --report-border-color: #e2e8f0;
        }

        body {
            background-color: #f1f5f9;
            padding: 2rem 0;
            color: #1e293b;
        }

        .report-page {
            background: white;
            width: 297mm; /* Landscape */
            min-height: 210mm;
            margin: 0 auto;
            padding: var(--report-padding);
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border-radius: 8px;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .report-header-premium {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid var(--color-primary);
            padding-bottom: 20px;
            margin-bottom: 25px;
        }

        .hospital-brand h1 {
            color: var(--color-primary);
            font-size: 22px;
            font-weight: 800;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .hospital-brand p {
            margin: 2px 0;
            color: #64748b;
            font-size: 13px;
        }

        .report-title {
            text-align: right;
        }

        .report-title h2 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            color: #334155;
        }

        .report-title p {
            margin: 2px 0;
            color: var(--color-primary);
            font-weight: 600;
            font-size: 13px;
        }

        .inventory-table-premium {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .inventory-table-premium th {
            background: #f8fafc;
            text-align: left;
            padding: 10px 8px;
            color: #64748b;
            border-bottom: 2px solid var(--report-border-color);
            font-weight: 700;
            text-transform: uppercase;
        }

        .inventory-table-premium td {
            padding: 8px;
            border-bottom: 1px solid var(--report-border-color);
        }

        .inventory-table-premium tr:nth-child(even) {
            background-color: #fcfdfe;
        }

        .val-stock {
            font-weight: 700;
            color: var(--color-primary);
        }

        .report-footer {
            border-top: 1px solid var(--report-border-color);
            padding-top: 12px;
            margin-top: 20px;
            text-align: center;
            font-size: 10px;
            color: #94a3b8;
        }

        .floating-actions {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 100;
        }

        @media print {
            @page {
                size: landscape;
                margin: 1cm;
            }
            body {
                background: white;
                padding: 0;
            }
            .report-page {
                box-shadow: none;
                margin: 0;
                width: 100%;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="floating-actions no-print">
        <button onclick="window.print()" class="action-btn" style="height: 50px; width: 50px; border-radius: 50%; padding: 0;">
            <i class="bi bi-printer-fill" style="font-size: 1.2rem;"></i>
        </button>
        <button onclick="window.close()" class="action-btn secondary" style="height: 50px; width: 50px; border-radius: 50%; padding: 0;">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="report-page">
        <header class="report-header-premium">
            <div class="hospital-brand">
                <h1>Centro Médico RS</h1>
                <p>Excelencia en Servicios de Salud | Amatitlán, Guatemala</p>
                <p><i class="bi bi-person"></i> Generado por: <?php echo $_SESSION['nombre']; ?></p>
            </div>
            <div class="report-title">
                <img src="../../assets/img/Logo.png" alt="Logo" style="height: 50px; margin-bottom: 8px;">
                <h2>REPORTE DE EXISTENCIAS</h2>
                <p><?php echo date('d/m/Y H:i'); ?></p>
            </div>
        </header>

        <table class="inventory-table-premium">
            <thead>
                <tr>
                    <th>Cód. Barras</th>
                    <th>Medicamento</th>
                    <th>Molécula</th>
                    <th>Presentación</th>
                    <th>Stock</th>
                    <th>Vencimiento</th>
                    <th>Factura</th>
                    <th>P. Compra</th>
                    <th>P. Venta</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><code style="font-size: 10px;"><?php echo $item['codigo_barras'] ?: '-'; ?></code></td>
                        <td><strong><?php echo htmlspecialchars($item['nom_medicamento']); ?></strong></td>
                        <td style="color: #64748b;"><?php echo htmlspecialchars($item['mol_medicamento']); ?></td>
                        <td><?php echo htmlspecialchars($item['presentacion_med']); ?></td>
                        <td class="val-stock"><?php echo $item['cantidad_med']; ?></td>
                        <td>
                            <?php 
                            $vence = strtotime($item['fecha_vencimiento']);
                            $vence_str = date('d/m/Y', $vence);
                            $color = ($vence < time()) ? '#ef4444' : (($vence < strtotime('+6 months')) ? '#f59e0b' : 'inherit');
                            echo "<span style='color: $color; font-weight: 500;'>$vence_str</span>";
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($item['document_number'] ?? 'N/A'); ?></td>
                        <td>Q<?php echo number_format($item['unit_cost'] ?? $item['precio_compra'], 2); ?></td>
                        <td style="font-weight: 600;">Q<?php echo number_format($item['precio_venta'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <footer class="report-footer">
            <p>Documento oficial de control de inventario - Centro Médico RS</p>
            <p>Página 1 de 1 - Generado el <?php echo date('d/m/Y H:i:s'); ?></p>
        </footer>
    </div>
</body>
</html>

</html>