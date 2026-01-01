<?php
// inventory/print_receipt.php - Recibo de Venta - Centro Médico Herrera Saenz
// Versión: 3.0 - Diseño Minimalista con Modo Noche y Efecto Mármol
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
    $stmt = $conn->prepare("SELECT * FROM ventas WHERE id_venta = ?");
    $stmt->execute([$id_venta]);
    $venta = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$venta) {
        die("Venta no encontrada");
    }
    
    // Obtener items de la venta
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

// Formatear fecha
$fecha = new DateTime($venta['fecha_venta']);
$fecha_formateada = $fecha->format('d/m/Y');
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Venta #<?php echo str_pad($id_venta, 5, '0', STR_PAD_LEFT); ?> - Centro Médico Herrera Saenz</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
    /* 
     * Recibo de Venta Minimalista - Centro Médico Herrera Saenz
     * Diseño: Fondo blanco, colores pastel, efecto mármol
     * Versión: 3.0
     */
    
    /* Variables CSS */
    :root {
        --color-primary: #7c90db;
        --color-primary-light: #a3b1e8;
        --color-secondary: #8dd7bf;
        --color-accent: #f8b195;
        --color-text: #1e293b;
        --color-text-light: #64748b;
        --color-border: #e2e8f0;
        --color-success: #34d399;
        --color-error: #f87171;
        --color-surface: #ffffff;
        --color-background: #f8fafc;
        
        --radius-md: 12px;
        --radius-lg: 16px;
        
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
    }
    
    [data-theme="dark"] {
        --color-text: #0f172a;
        --color-text-light: #334155;
        --color-border: #cbd5e1;
        --color-surface: #ffffff;
        --color-background: #ffffff;
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }
    
    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--color-background);
        color: var(--color-text);
        padding: 20px;
        line-height: 1.5;
    }
    
    @media print {
        body {
            background-color: white;
            padding: 0;
        }
        
        .no-print {
            display: none !important;
        }
        
        .receipt-container {
            box-shadow: none !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 148mm !important;
        }
    }
    
    /* Contenedor principal */
    .container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    /* Botón de regreso */
    .back-button {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        padding: 0.75rem 1.5rem;
        color: var(--color-text);
        text-decoration: none;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        margin-bottom: 2rem;
    }
    
    .back-button:hover {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
        transform: translateY(-2px);
    }
    
    /* Contenedor del recibo */
    .receipt-container {
        width: 148mm;
        min-height: 210mm;
        margin: 0 auto 2rem;
        background-color: white;
        padding: 40px;
        box-shadow: var(--shadow-lg);
        position: relative;
        display: flex;
        flex-direction: column;
        border-radius: var(--radius-lg);
        background: var(--color-surface);
        color: var(--color-text);
    }
    
    /* Marca de agua */
    .watermark {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-45deg);
        font-size: 120px;
        color: rgba(0, 0, 0, 0.03);
        pointer-events: none;
        z-index: 0;
        font-weight: 900;
        text-transform: uppercase;
        white-space: nowrap;
        font-family: 'Playfair Display', serif;
        opacity: 0.5;
    }
    
    [data-theme="dark"] .watermark {
        color: rgba(255, 255, 255, 0.03);
    }
    
    /* Encabezado de la clínica */
    .clinic-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 2px solid var(--color-primary);
        padding-bottom: 20px;
        margin-bottom: 30px;
        position: relative;
        z-index: 1;
    }
    
    .logo-section {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .clinic-logo {
        height: 60px;
        width: auto;
    }
    
    .clinic-name {
        font-family: 'Playfair Display', serif;
        color: var(--color-primary);
        font-size: 24px;
        font-weight: 700;
        margin: 0;
    }
    
    .clinic-info {
        text-align: right;
        font-size: 11px;
        line-height: 1.5;
        color: var(--color-text);
        font-weight: 500;
    }
    
    /* Sección de información de la venta */
    .sale-section {
        background-color: rgba(124, 144, 219, 0.1);
        border: 1px solid var(--color-primary-light);
        border-radius: var(--radius-md);
        padding: 20px;
        margin-bottom: 30px;
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 15px;
        position: relative;
        z-index: 1;
    }
    
    .info-item {
        display: flex;
        flex-direction: column;
    }
    
    .info-label {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: var(--color-text-light);
        margin-bottom: 4px;
        font-weight: 700;
    }
    
    .info-value {
        font-size: 13px;
        font-weight: 700;
        color: var(--color-text);
        line-height: 1.2;
    }
    
    /* Contenido principal del recibo */
    .receipt-content {
        flex-grow: 1;
        position: relative;
        z-index: 1;
    }
    
    .receipt-title {
        color: var(--color-primary);
        font-size: 18px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 20px;
        border-left: 4px solid var(--color-primary);
        padding-left: 15px;
    }
    
    /* Tabla de detalles */
    .receipt-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 30px;
    }
    
    .receipt-table th {
        text-align: left;
        font-size: 12px;
        color: var(--color-text-light);
        padding-bottom: 10px;
        border-bottom: 1px solid var(--color-border);
    }
    
    .receipt-table td {
        padding: 15px 0;
        font-size: 14px;
        border-bottom: 1px solid var(--color-border);
        color: var(--color-text);
        font-weight: 500;
    }
    
    .receipt-table td:last-child {
        text-align: right;
        font-weight: 600;
    }
    
    /* Sección total */
    .total-section {
        display: flex;
        justify-content: flex-end;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 2px solid var(--color-border);
    }
    
    .total-box {
        background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
        color: white;
        padding: 20px 30px;
        border-radius: var(--radius-md);
        text-align: right;
        box-shadow: var(--shadow-md);
    }
    
    .total-label {
        font-size: 11px;
        text-transform: uppercase;
        opacity: 0.9;
        margin-bottom: 5px;
    }
    
    .total-amount {
        font-size: 28px;
        font-weight: 800;
    }
    
    /* Pie de página del recibo */
    .receipt-footer {
        margin-top: auto;
        border-top: 1px solid var(--color-border);
        padding-top: 30px;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        position: relative;
        z-index: 1;
    }
    
    .legal-note {
        font-size: 9px;
        color: var(--color-text-light);
        max-width: 300px;
        line-height: 1.4;
    }
    
    .thank-you {
        text-align: right;
        font-family: 'Playfair Display', serif;
        font-style: italic;
        color: var(--color-primary);
    }
    
    /* Botones de acción */
    .action-buttons {
        position: fixed;
        bottom: 30px;
        right: 30px;
        display: flex;
        gap: 15px;
        z-index: 100;
    }
    
    .action-button {
        padding: 12px 24px;
        border-radius: 50px;
        border: none;
        cursor: pointer;
        font-weight: 600;
        box-shadow: var(--shadow-md);
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    
    .action-button.primary {
        background-color: var(--color-primary);
        color: white;
    }
    
    .action-button.secondary {
        background-color: var(--color-surface);
        color: var(--color-text);
        border: 1px solid var(--color-border);
    }
    
    .action-button:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-lg);
    }
    
    /* Efecto mármol para fondo */
    .marble-effect {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        pointer-events: none;
        z-index: -1;
        opacity: 0.3;
        background-image: 
            radial-gradient(circle at 20% 30%, rgba(124, 144, 219, 0.05) 0%, transparent 30%),
            radial-gradient(circle at 80% 70%, rgba(141, 215, 191, 0.05) 0%, transparent 30%),
            radial-gradient(circle at 40% 80%, rgba(248, 177, 149, 0.05) 0%, transparent 30%);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        body {
            padding: 10px;
        }
        
        .receipt-container {
            width: 100%;
            padding: 20px;
        }
        
        .clinic-header {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .clinic-info {
            text-align: left;
        }
        
        .sale-section {
            grid-template-columns: 1fr;
        }
        
        .action-buttons {
            flex-direction: column;
            bottom: 20px;
            right: 20px;
        }
        
        .action-button {
            width: 100%;
            justify-content: center;
        }
        
        .watermark {
            font-size: 80px;
        }
    }
    
    @media (max-width: 480px) {
        .receipt-container {
            padding: 15px;
        }
        
        .action-buttons {
            position: static;
            margin-top: 2rem;
            flex-direction: column;
        }
    }
    </style>
</head>
<body>
    <!-- Efecto mármol -->
    <div class="marble-effect"></div>
    
    <div class="container">
        <!-- Botón de regreso (no se imprime) -->
        <a href="index.php" class="back-button no-print">
            <i class="bi bi-arrow-left"></i>
            Volver a Ventas
        </a>
        
        <!-- Recibo de venta -->
        <div class="receipt-container">
            <!-- Marca de agua -->
            <div class="watermark">HERRERA SAENZ</div>
            
            <!-- Encabezado de la clínica -->
            <header class="clinic-header">
                <div class="logo-section">
                    <img src="../../assets/img/herrerasaenz.png" alt="Centro Médico Herrera Saenz" class="clinic-logo">
                </div>
                <div class="clinic-info">
                    7ma Av 7-25 Zona 1, Atrás del parqueo Hospital Antiguo. Huehuetenango<br>
                    Tel: (+502) 4195-8112<br>
                </div>
            </header>
            
            <!-- Información de la venta -->
            <section class="sale-section">
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
            
            <!-- Contenido principal -->
            <main class="receipt-content">
                <h2 class="receipt-title">Detalle de la Venta</h2>
                <table class="receipt-table">
                    <thead>
                        <tr>
                            <th style="width: 50%;">Producto</th>
                            <th style="width: 15%; text-align: center;">Cant.</th>
                            <th style="width: 20%; text-align: right;">Precio Unit.</th>
                            <th style="width: 15%; text-align: right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($item['nom_medicamento']); ?></div>
                                    <div style="font-size: 0.875rem; color: var(--color-text-light);">
                                        <?php echo htmlspecialchars($item['mol_medicamento']); ?> • <?php echo htmlspecialchars($item['presentacion_med']); ?>
                                    </div>
                                </td>
                                <td style="text-align: center; font-weight: 600;"><?php echo $item['cantidad_vendida']; ?></td>
                                <td style="text-align: right;">Q<?php echo number_format($item['precio_unitario'], 2); ?></td>
                                <td style="text-align: right; font-weight: 600;">Q<?php echo number_format($item['cantidad_vendida'] * $item['precio_unitario'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="total-section">
                    <div class="total-box">
                        <div class="total-label">Total General</div>
                        <div class="total-amount">Q<?php echo number_format($venta['total'], 2); ?></div>
                    </div>
                </div>
            </main>
            
            <!-- Pie de página -->
            <footer class="receipt-footer">
                <div class="legal-note">
                    <strong>Información Importante:</strong><br>
                    Este recibo es un comprobante de venta de productos médicos. 
                    Para cualquier aclaración, favor de presentar este documento original.
                    Documento generado por Centro Médico Herrera Saenz Management System.
                </div>
                <div class="thank-you">
                    <h4 style="margin: 0; font-size: 16px;">¡Gracias por su compra!</h4>
                    <p style="margin: 5px 0 0; font-size: 13px;">Que tenga un buen día.</p>
                </div>
            </footer>
        </div>
    </div>
    
    <!-- Botones de acción (no se imprimen) -->
    <div class="action-buttons no-print">
        <button class="action-button secondary" onclick="window.history.back()">
            <i class="bi bi-arrow-left"></i>
            Volver
        </button>
        <button class="action-button primary" onclick="window.print()">
            <i class="bi bi-printer-fill"></i>
            Imprimir Recibo
        </button>
    </div>
    
    <script>
    // Funcionalidad del recibo
    document.addEventListener('DOMContentLoaded', function() {
        // Cambiar tema basado en preferencias del sistema
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const savedTheme = localStorage.getItem('dashboard-theme');
        
        if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
        
        console.log('Recibo de Venta - Centro Médico Herrera Saenz');
        console.log('ID de Venta: <?php echo $id_venta; ?>');
        console.log('Cliente: <?php echo htmlspecialchars($venta['nombre_cliente']); ?>');
        console.log('Total: Q<?php echo number_format($venta['total'], 2); ?>');
        
        // Función para imprimir
        window.printRecibo = function() {
            window.print();
        };
    });
    </script>
</body>
</html>