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
    <title>Recibo de Venta #<?php echo $id_venta; ?></title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .receipt-container {
            width: 80mm;
            margin: 0 auto;
            background-color: white;
            padding: 15px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .text-center {
            text-align: center;
        }
        .mb-2 {
            margin-bottom: 10px;
        }
        .mb-3 {
            margin-bottom: 15px;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 5px;
            text-align: left;
        }
        th:last-child, td:last-child {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            border-top: 1px dashed #000;
            margin-top: 10px;
            padding-top: 10px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
        }
        .print-button {
            display: block;
            margin: 20px auto;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        @media print {
            .print-button {
                display: none;
            }
            body {
                background-color: white;
                padding: 0;
            }
            .receipt-container {
                box-shadow: none;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="text-center mb-3">
                <h2 style="margin: 0;">InterClinic</h2>
                <p style="margin: 5px 0;">Santa Cruz Barillas</p>
                <p style="margin: 5px 0;">Tel: +502 42594302</p>
         </div>
        
        <div class="divider"></div>
        
        <div class="mb-3">
            <p><strong>Recibo #:</strong> <?php echo $id_venta; ?></p>
            <p><strong>Fecha:</strong> <?php echo $fecha_formateada; ?></p>
            <p><strong>Cliente:</strong> <?php echo htmlspecialchars($venta['nombre_cliente']); ?></p>
            <p><strong>Tipo de Pago:</strong> <?php echo htmlspecialchars($venta['tipo_pago']); ?></p>
        </div>
        
        <div class="divider"></div>
        
        <div class="mb-3">
            <h3 class="text-center" style="margin: 5px 0;">DETALLE DE VENTA</h3>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cant.</th>
                        <th>Precio</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['nom_medicamento']); ?> - <?php echo htmlspecialchars($item['presentacion_med']); ?></td>
                        <td><?php echo $item['cantidad_vendida']; ?></td>
                        <td>Q<?php echo number_format($item['precio_unitario'], 2); ?></td>
                        <td>Q<?php echo number_format($item['cantidad_vendida'] * $item['precio_unitario'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="total-row">
                <p style="text-align: right;"><strong>Total: Q<?php echo number_format($venta['total'], 2); ?></strong></p>
            </div>
        </div>
        
        <div class="divider"></div>
        
        <div class="footer">
            <p>¡Gracias por su compra!</p>
            <p>Recupérese pronto</p>
        </div>
    </div>
    
    <button class="print-button" onclick="window.print();">Imprimir Recibo</button>
    
    <script>
        // Auto-print when page loads (optional)
        /*
        window.onload = function() {
            window.print();
        };
        */
    </script>
</body>
</html>