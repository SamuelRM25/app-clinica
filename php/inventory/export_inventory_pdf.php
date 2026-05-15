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
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Reporte de Inventario - PDF</title>
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
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
        <h1>Centro Médico RS</h1>
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
        Sistema de Gestión Médica - Centro Médico RS | Página 1 de 1
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