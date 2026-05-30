<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

if (!isset($_SESSION['user_id']) || $_SESSION['tipoUsuario'] !== 'admin') {
    die("No autorizado");
}
verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();

    $id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

    // Obtener compras con sus items
    $query = "SELECT 
                ph.id as compra_id, ph.purchase_date, ph.provider_name, ph.total_amount, 
                ph.status, ph.payment_status,
                pi.product_name, pi.quantity, pi.unit_cost, pi.subtotal as item_total
              FROM purchase_headers ph
              JOIN purchase_items pi ON ph.id = pi.purchase_header_id
              WHERE ph.id_hospital = ?
              ORDER BY ph.id DESC, pi.id ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute([$id_hospital]);
    $purchases = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $id = $row['compra_id'];
        if (!isset($purchases[$id])) {
            $purchases[$id] = [
                'date' => $row['purchase_date'],
                'provider' => $row['provider_name'],
                'total' => $row['total_amount'],
                'status' => $row['status'],
                'payment' => $row['payment_status'],
                'items' => []
            ];
        }
        $purchases[$id]['items'][] = [
            'name' => $row['product_name'],
            'qty' => $row['quantity'],
            'cost' => $row['unit_cost'],
            'total' => $row['item_total']
        ];
    }
} catch (Exception $e) {
    error_log('Error en purchases/export_purchases_pdf.php: ' . $e->getMessage());
    die("Error: " . 'Error del servidor.');
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Compras - Centro Médico RS</title>
    
    <!-- Google Fonts - Inter -->
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
            width: 210mm; /* A4 Portrait */
            min-height: 297mm;
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

        .purchase-box {
            background: #fff;
            border: 1px solid var(--report-border-color);
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
            page-break-inside: avoid;
        }

        .purchase-header-box {
            background: #f8fafc;
            padding: 12px 15px;
            border-bottom: 1px solid var(--report-border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .purchase-id {
            font-weight: 700;
            color: var(--color-primary);
        }

        .purchase-provider {
            font-weight: 600;
            color: #334155;
        }

        .purchase-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }

        .purchase-table th {
            text-align: left;
            padding: 8px 15px;
            color: #64748b;
            border-bottom: 1px solid var(--report-border-color);
            font-weight: 700;
            text-transform: uppercase;
        }

        .purchase-table td {
            padding: 8px 15px;
            border-bottom: 1px solid #f1f5f9;
        }

        .purchase-footer-box {
            padding: 10px 15px;
            background: #fff;
            display: flex;
            justify-content: flex-end;
            gap: 20px;
            font-size: 12px;
        }

        .purchase-total-val {
            font-weight: 800;
            color: var(--color-primary);
        }

        .report-footer {
            border-top: 1px solid var(--report-border-color);
            padding-top: 12px;
            margin-top: auto;
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
                size: portrait;
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
                <p><i class="bi bi-person"></i> Generado por: <?php echo htmlspecialchars($_SESSION['nombre']); ?></p>
            </div>
            <div class="report-title">
                <img src="../../assets/img/Logo.png" alt="Logo" style="height: 50px; margin-bottom: 8px;" width="50" height="50">
                <h2>HISTORIAL DE COMPRAS</h2>
                <p><?php echo date('d/m/Y H:i'); ?></p>
            </div>
        </header>

        <?php foreach ($purchases as $id => $p): ?>
            <div class="purchase-box">
                <div class="purchase-header-box">
                    <div>
                        <span class="purchase-id">#CP-<?php echo str_pad($id, 5, '0', STR_PAD_LEFT); ?></span>
                        <span class="mx-2 text-muted">|</span>
                        <span class="purchase-provider"><?php echo htmlspecialchars($p['provider']); ?></span>
                    </div>
                    <div class="text-muted" style="font-size: 11px;">
                        <i class="bi bi-calendar3 me-1"></i> <?php echo date('d/m/Y', strtotime($p['date'])); ?>
                    </div>
                </div>
                <table class="purchase-table">
                    <thead>
                        <tr>
                            <th>Producto / Medicamento</th>
                            <th style="text-align: center;">Cantidad</th>
                            <th style="text-align: right;">Costo U.</th>
                            <th style="text-align: right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($p['items'] as $item): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                                <td style="text-align: center;"><?php echo $item['qty']; ?></td>
                                <td style="text-align: right;">Q<?php echo number_format($item['cost'], 2); ?></td>
                                <td style="text-align: right;">Q<?php echo number_format($item['total'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="purchase-footer-box">
                    <div>Estado: <span class="badge" style="background: #f1f5f9; color: #64748b; font-size: 10px;"><?php echo $p['status']; ?></span></div>
                    <div>Pago: <span class="badge" style="background: #f1f5f9; color: #64748b; font-size: 10px;"><?php echo $p['payment']; ?></span></div>
                    <div>Total Compra: <span class="purchase-total-val">Q<?php echo number_format($p['total'], 2); ?></span></div>
                </div>
            </div>
        <?php endforeach; ?>

        <footer class="report-footer">
            <p>Documento oficial de control de compras - Centro Médico RS</p>
            <p>Generado el <?php echo date('d/m/Y H:i:s'); ?> - CMS v4.0</p>
        </footer>
    </div>
</body>
</html>

</html>