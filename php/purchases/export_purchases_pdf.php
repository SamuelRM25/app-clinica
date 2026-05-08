<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['tipoUsuario'] !== 'admin') {
    die("No autorizado");
}
verify_session();

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener compras con sus items
    $query = "SELECT 
                ph.id as compra_id, ph.purchase_date, ph.provider_name, ph.total_amount, 
                ph.status, ph.payment_status,
                pi.product_name, pi.quantity, pi.unit_cost, pi.subtotal as item_total
              FROM purchase_headers ph
              JOIN purchase_items pi ON ph.id = pi.purchase_header_id
              ORDER BY ph.id DESC, pi.id ASC";

    $stmt = $conn->query($query);
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
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Respaldo de Compras - PDF</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            margin: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .logo {
            height: 60px;
            margin-bottom: 10px;
        }

        h1 {
            margin: 0;
            font-size: 18pt;
            color: #0d6efd;
        }

        .purchase-box {
            border: 1px solid #ccc;
            margin-bottom: 20px;
            padding: 10px;
            page-break-inside: avoid;
        }

        .purchase-header {
            background: #f8f9fa;
            padding: 5px;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
            margin-bottom: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            border-bottom: 1px solid #ddd;
            padding: 5px;
            text-align: left;
            font-size: 9pt;
            color: #555;
        }

        td {
            padding: 4px;
            border-bottom: 1px dotted #eee;
            font-size: 9pt;
        }

        .total-row {
            font-weight: bold;
            text-align: right;
            margin-top: 10px;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                margin: 0;
            }
        }
    </style>
</head>

<body>
    <div class="no-print"
        style="background: #e9ecef; padding: 10px; margin-bottom: 20px; border-radius: 5px; text-align: right;">
        <button onclick="window.print()"
            style="background: #0d6efd; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold;">
            🖨️ Imprimir / Guardar como PDF
        </button>
        <button onclick="window.close()"
            style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-left: 10px;">
            Cerrar
        </button>
    </div>

    <div class="header">
        <img src="../../assets/img/Logo.png" class="logo" alt="Logo" onerror="this.style.display='none'">
        <h1>Centro Médico RS</h1>
        <p>Respaldo de Compras Registradas</p>
        <div style="font-size: 9pt; color: #666;">Generado el:
            <?php echo date('d/m/Y H:i'); ?>
        </div>
    </div>

    <?php foreach ($purchases as $id => $p): ?>
        <div class="purchase-box">
            <div class="purchase-header">
                Compra #
                <?php echo $id; ?> - Fecha:
                <?php echo $p['date']; ?> - Proveedor:
                <?php echo htmlspecialchars($p['provider']); ?>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th style="width: 80px;">Cant.</th>
                        <th style="width: 100px;">Costo U.</th>
                        <th style="width: 100px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($p['items'] as $item): ?>
                        <tr>
                            <td>
                                <?php echo htmlspecialchars($item['name']); ?>
                            </td>
                            <td>
                                <?php echo $item['qty']; ?>
                            </td>
                            <td>Q
                                <?php echo number_format($item['cost'], 2); ?>
                            </td>
                            <td>Q
                                <?php echo number_format($item['total'], 2); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="total-row">
                Monto Total: Q
                <?php echo number_format($p['total'], 2); ?> | Estado:
                <?php echo $p['status']; ?> (
                <?php echo $p['payment']; ?>)
            </div>
        </div>
    <?php endforeach; ?>

    <script>
        // window.onload = () => window.print();
    </script>
</body>

</html>