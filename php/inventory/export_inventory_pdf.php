<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

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
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario - PDF</title>
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
            color: #198754;
        }

        .meta {
            font-size: 9pt;
            color: #666;
            margin-top: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th {
            background-color: #f2f2f2;
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 9pt;
        }

        td {
            border: 1px solid #ddd;
            padding: 6px;
            font-size: 8pt;
            vertical-align: top;
        }

        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 8pt;
            color: #999;
            padding: 10px 0;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                margin: 0;
            }

            button {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="no-print"
        style="background: #e9ecef; padding: 10px; margin-bottom: 20px; border-radius: 5px; text-align: right;">
        <button onclick="window.print()"
            style="background: #198754; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold;">
            🖨️ Imprimir / Guardar como PDF
        </button>
        <button onclick="window.close()"
            style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-left: 10px;">
            Cerrar
        </button>
    </div>

    <div class="header">
        <img src="../../assets/img/Logo.png" class="logo" alt="Logo" onerror="this.style.display='none'">
        <h1>Centro Médico Herrera Saenz</h1>
        <p>Reporte Completo de Inventario</p>
        <div class="meta">Fecha de generación:
            <?php echo date('d/m/Y H:i'); ?> | Usuario:
            <?php echo $_SESSION['nombre']; ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Cód. Barras</th>
                <th>Medicamento</th>
                <th>Molécula</th>
                <th>Pres.</th>
                <th>Cant.</th>
                <th>Vence</th>
                <th>Factura</th>
                <th>P. Compra</th>
                <th>P. Venta</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <?php echo $item['codigo_barras']; ?>
                    </td>
                    <td><strong>
                            <?php echo htmlspecialchars($item['nom_medicamento']); ?>
                        </strong></td>
                    <td>
                        <?php echo htmlspecialchars($item['mol_medicamento']); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($item['presentacion_med']); ?>
                    </td>
                    <td>
                        <?php echo $item['cantidad_med']; ?>
                    </td>
                    <td>
                        <?php echo date('d/m/y', strtotime($item['fecha_vencimiento'])); ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($item['document_number'] ?? 'N/A'); ?>
                    </td>
                    <td>Q
                        <?php echo number_format($item['unit_cost'] ?? $item['precio_compra'], 2); ?>
                    </td>
                    <td>Q
                        <?php echo number_format($item['precio_venta'], 2); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        Sistema de Gestión Médica - Centro Médico Herrera Saenz | Página 1 de 1
    </div>

    <script>
        // Auto-trigger print after loading
        window.onload = function () {
            // Uncomment to auto-print
            // window.print();
        };
    </script>
</body>

</html>