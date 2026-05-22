<?php
// sales/generate_shift_report.php - Reporte de Ventas por Jornada - Centro Médico RS
// Reingenierizado con Diseño Dashboard Moderno
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int)($_SESSION['id_hospital'] ?? 0);

date_default_timezone_set('America/Guatemala');
verify_session();

if (!isset($_GET['date'])) {
    die("Fecha no especificada.");
}

$selected_date = $_GET['date'];
// Jornada 1: 08:00 AM a 05:00 PM (17:00)
// Jornada 2: 05:00 PM (17:00) a 08:00 AM del día siguiente
$start_date = $selected_date . ' 08:00:00';
$end_date = $selected_date . ' 17:00:00';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener ventas en el rango
    $query = "
        SELECT v.*, u.nombre as nombre_vendedor, u.apellido as apellido_vendedor
        FROM ventas v
        LEFT JOIN usuarios u ON v.id_usuario = u.idUsuario
        WHERE v.fecha_venta >= ? AND v.fecha_venta < ?
        AND v.id_hospital = ?
        ORDER BY v.fecha_venta ASC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute([$start_date, $end_date, $id_hospital]);
    $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calcular totales
    $total_sales = 0;
    $payment_methods = [];
    $sales_by_user = [];
    $ventas_pagadas = 0;
    $ventas_pendientes = 0;
    $ventas_canceladas = 0;

    foreach ($ventas as $venta) {
        // Conteo por estado
        switch ($venta['estado']) {
            case 'Pagado':
                $ventas_pagadas++;
                break;
            case 'Pendiente':
                $ventas_pendientes++;
                break;
            case 'Cancelado':
                $ventas_canceladas++;
                break;
        }

        if ($venta['estado'] !== 'Cancelado') { // Solo contar ventas válidas
            $total_sales += $venta['total'];

            // Métodos de pago
            $method = $venta['tipo_pago'];
            if (!isset($payment_methods[$method])) {
                $payment_methods[$method] = 0;
            }
            $payment_methods[$method] += $venta['total'];

            // Ventas por usuario
            $user_name = ($venta['nombre_vendedor'] && $venta['apellido_vendedor'])
                ? $venta['nombre_vendedor'] . ' ' . $venta['apellido_vendedor']
                : 'Sistema';

            if (!isset($sales_by_user[$user_name])) {
                $sales_by_user[$user_name] = ['count' => 0, 'total' => 0];
            }
            $sales_by_user[$user_name]['count']++;
            $sales_by_user[$user_name]['total'] += $venta['total'];
        }
    }

} catch (Exception $e) {
    die("Error al generar reporte: " . $e->getMessage());
}

// Obtener información del usuario
$user_name = $_SESSION['nombre'];
$user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';

// Título de la página
$page_title = "Reporte de Ventas por Jornada - Centro Médico RS";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Reporte de Ventas por Jornada - Centro Médico RS">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">

    <!-- Google Fonts - Inter (moderno y legible) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@700&display=swap"
        rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- CSS Crítico (incrustado para máxima velocidad) -->
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">

</head>

<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>

    <!-- Botones de acción (no se imprimen) -->
    <div class="action-buttons no-print">
        <button onclick="window.print()" class="action-btn">
            <i class="bi bi-printer"></i>
            Imprimir Reporte
        </button>
        <button onclick="window.close()" class="action-btn secondary">
            <i class="bi bi-x-circle"></i>
            Cerrar Ventana
        </button>
    </div>

    <!-- Contenedor del reporte -->
    <div class="report-container">
        <!-- Encabezado -->
        <div class="report-header animate-in">
            <h1 class="clinic-name">Centro Médico RS</h1>
            <h2 class="report-title">Reporte de Ventas por Jornada</h2>
            <div class="period-info">
                <i class="bi bi-calendar-range"></i>
                <span><?php echo date('d/m/Y h:i A', strtotime($start_date)); ?></span>
                <i class="bi bi-arrow-right"></i>
                <span><?php echo date('d/m/Y h:i A', strtotime($end_date)); ?></span>
            </div>
        </div>

        <!-- Resumen estadístico -->
        <div class="stats-summary animate-in delay-1">
            <!-- Total vendido -->
            <div class="summary-card">
                <div class="summary-header">
                    <div>
                        <div class="summary-title">Total Vendido</div>
                        <div class="summary-value">Q<?php echo number_format($total_sales, 2); ?></div>
                    </div>
                    <div class="summary-icon primary">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                </div>
                <ul class="summary-details">
                    <li>
                        <span>Transacciones:</span>
                        <span class="fw-semibold"><?php echo count($ventas); ?></span>
                    </li>
                    <li>
                        <span>Promedio por venta:</span>
                        <span
                            class="fw-semibold">Q<?php echo count($ventas) > 0 ? number_format($total_sales / count($ventas), 2) : '0.00'; ?></span>
                    </li>
                </ul>
            </div>

            <!-- Métodos de pago -->
            <div class="summary-card">
                <div class="summary-header">
                    <div>
                        <div class="summary-title">Métodos de Pago</div>
                        <div class="summary-value"><?php echo count($payment_methods); ?></div>
                    </div>
                    <div class="summary-icon success">
                        <i class="bi bi-credit-card"></i>
                    </div>
                </div>
                <ul class="summary-details">
                    <?php foreach ($payment_methods as $method => $amount): ?>
                            <li>
                                <span><?php echo $method; ?>:</span>
                                <span class="fw-semibold text-success">Q<?php echo number_format($amount, 2); ?></span>
                            </li>
                    <?php endforeach; ?>
                    <?php if (empty($payment_methods)): ?>
                            <li class="text-muted fst-italic">Sin registros</li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Ventas por usuario -->
            <div class="summary-card">
                <div class="summary-header">
                    <div>
                        <div class="summary-title">Ventas por Vendedor</div>
                        <div class="summary-value"><?php echo count($sales_by_user); ?></div>
                    </div>
                    <div class="summary-icon info">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
                <ul class="summary-details">
                    <?php foreach ($sales_by_user as $user => $data): ?>
                            <li>
                                <span class="text-truncate" title="<?php echo $user; ?>"><?php echo $user; ?>:</span>
                                <span class="fw-semibold"><?php echo $data['count']; ?> ventas</span>
                            </li>
                    <?php endforeach; ?>
                    <?php if (empty($sales_by_user)): ?>
                            <li class="text-muted fst-italic">Sin registros</li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Estado de ventas -->
            <div class="summary-card">
                <div class="summary-header">
                    <div>
                        <div class="summary-title">Estado de Ventas</div>
                        <div class="summary-value"><?php echo count($ventas); ?></div>
                    </div>
                    <div class="summary-icon warning">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                </div>
                <ul class="summary-details">
                    <li>
                        <span>Pagadas:</span>
                        <span class="fw-semibold text-success"><?php echo $ventas_pagadas; ?></span>
                    </li>
                    <li>
                        <span>Pendientes:</span>
                        <span class="fw-semibold text-warning"><?php echo $ventas_pendientes; ?></span>
                    </li>
                    <li>
                        <span>Canceladas:</span>
                        <span class="fw-semibold text-danger"><?php echo $ventas_canceladas; ?></span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Detalle de transacciones -->
        <section class="transactions-section animate-in delay-2">
            <h3 class="section-title">
                <i class="bi bi-receipt section-title-icon"></i>
                Detalle de Transacciones
            </h3>

            <div class="table-responsive">
                <?php if (count($ventas) > 0): ?>
                        <table class="transactions-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Venta</th>
                                    <th>Cliente</th>
                                    <th>Vendedor</th>
                                    <th>Método Pago</th>
                                    <th>Estado</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ventas as $index => $venta): ?>
                                        <?php
                                        $fecha_venta = new DateTime($venta['fecha_venta']);
                                        $hora_venta = $fecha_venta->format('h:i A');
                                        $fecha_formateada = $fecha_venta->format('d/m/Y');
                                        $vendedor_nombre = ($venta['nombre_vendedor'] && $venta['apellido_vendedor'])
                                            ? $venta['nombre_vendedor'] . ' ' . substr($venta['apellido_vendedor'], 0, 1) . '.'
                                            : 'Sistema';
                                        ?>
                                        <tr>
                                            <td class="text-muted"><?php echo $index + 1; ?></td>
                                            <td>
                                                <div class="transaction-cell">
                                                    <div class="transaction-avatar">
                                                        <?php echo strtoupper(substr($venta['nombre_cliente'] ?? 'C', 0, 1)); ?>
                                                    </div>
                                                    <div class="transaction-info">
                                                        <div class="transaction-id">
                                                            #VTA-<?php echo str_pad($venta['id_venta'], 5, '0', STR_PAD_LEFT); ?></div>
                                                        <div class="transaction-time"><?php echo $hora_venta; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="client-name"><?php echo htmlspecialchars($venta['nombre_cliente']); ?></div>
                                            </td>
                                            <td>
                                                <div class="vendedor-name"><?php echo $vendedor_nombre; ?></div>
                                            </td>
                                            <td>
                                                <span class="payment-badge">
                                                    <i class="bi bi-credit-card"></i>
                                                    <?php echo htmlspecialchars($venta['tipo_pago']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = match ($venta['estado']) {
                                                    'Pagado' => 'pagado',
                                                    'Pendiente' => 'pendiente',
                                                    'Cancelado' => 'cancelado',
                                                    default => 'pendiente'
                                                };
                                                ?>
                                                <span class="status-badge <?php echo $status_class; ?>">
                                                    <?php echo htmlspecialchars($venta['estado']); ?>
                                                </span>
                                            </td>
                                            <td class="amount-cell">
                                                Q<?php echo number_format($venta['total'], 2); ?>
                                            </td>
                                        </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="6" class="text-right fw-bold">Total General:</td>
                                    <td class="amount-cell fw-bold">Q<?php echo number_format($total_sales, 2); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="bi bi-inbox"></i>
                            </div>
                            <h4 class="text-muted mb-2">No se encontraron ventas en este período</h4>
                            <p class="text-muted mb-3">El reporte está vacío para la fecha seleccionada.</p>
                        </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Resumen final -->
        <div class="final-summary animate-in delay-3">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-item-label">Total Transacciones</div>
                    <div class="summary-item-value"><?php echo count($ventas); ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-item-label">Ventas Válidas</div>
                    <div class="summary-item-value"><?php echo $ventas_pagadas + $ventas_pendientes; ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-item-label">Ventas Canceladas</div>
                    <div class="summary-item-value text-danger"><?php echo $ventas_canceladas; ?></div>
                </div>
                <div class="summary-item">
                    <div class="summary-item-label">Total Recaudado</div>
                    <div class="summary-item-value text-success">Q<?php echo number_format($total_sales, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Firmas -->
        <div class="signature-section animate-in delay-4">
            <div class="signature-grid">
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Firma Cajero</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line"></div>
                    <div class="signature-label">Firma Administración</div>
                </div>
            </div>
        </div>

        <!-- Pie de página -->
        <div class="footer-info animate-in delay-4">
            <p class="mb-2">
                Reporte generado el <strong><?php echo date('d/m/Y h:i A'); ?></strong>
                por <strong><?php echo htmlspecialchars($user_name); ?></strong> -
                <?php echo htmlspecialchars($user_specialty); ?>
            </p>
            <p class="text-muted">
                Centro Médico RS - Sistema de Gestión Médica v4.0
            </p>
        </div>
    </div>

    <!-- JavaScript Optimizado -->
    <script>
        // Reporte de Ventas por Jornada Reingenierizado

        (function () {
            'use strict';

            // ==========================================================================
            // CONFIGURACIÓN Y CONSTANTES
            // ==========================================================================
            const CONFIG = {
                themeKey: 'dashboard-theme',
                transitionDuration: 300
            };

            // ==========================================================================
            // REFERENCIAS A ELEMENTOS DOM
            // ==========================================================================
            const DOM = {
                html: document.documentElement,
                body: document.body,
                printButton: document.querySelector('[onclick="window.print()"]'),
                closeButton: document.querySelector('[onclick="window.close()"]')
            };

            // ==========================================================================
            // MANEJO DE TEMA (DÍA/NOCHE)
            // ==========================================================================
            class ThemeManager {
                constructor() {
                    this.theme = this.getInitialTheme();
                    this.applyTheme(this.theme);
                }

                getInitialTheme() {
                    // 1. Verificar preferencia guardada
                    const savedTheme = localStorage.getItem(CONFIG.themeKey);
                    if (savedTheme) return savedTheme;

                    // 2. Verificar preferencia del sistema
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (prefersDark) return 'dark';

                    // 3. Tema por defecto (día)
                    return 'light';
                }

                applyTheme(theme) {
                    DOM.html.setAttribute('data-theme', theme);
                    localStorage.setItem(CONFIG.themeKey, theme);
                }
            }

            // ==========================================================================
            // ANIMACIONES Y EFECTOS
            // ==========================================================================
            class AnimationManager {
                constructor() {
                    this.setupPrintButton();
                    this.setupCloseButton();
                    this.animateElements();
                }

                setupPrintButton() {
                    if (DOM.printButton) {
                        DOM.printButton.addEventListener('click', (e) => {
                            e.preventDefault();

                            // Animación en el botón
                            DOM.printButton.style.transform = 'scale(0.95)';
                            setTimeout(() => {
                                DOM.printButton.style.transform = '';
                            }, 200);

                            // Retardo para permitir animación antes de imprimir
                            setTimeout(() => {
                                window.print();
                            }, 300);
                        });
                    }
                }

                setupCloseButton() {
                    if (DOM.closeButton) {
                        DOM.closeButton.addEventListener('click', (e) => {
                            e.preventDefault();

                            // Animación en el botón
                            DOM.closeButton.style.transform = 'scale(0.95)';
                            setTimeout(() => {
                                DOM.closeButton.style.transform = '';
                            }, 200);

                            // Confirmar antes de cerrar si hay datos importantes
                            if (<?php echo count($ventas); ?> > 0) {
                                if (confirm('¿Está seguro que desea cerrar el reporte?')) {
                                    window.close();
                                }
                            } else {
                                window.close();
                            }
                        });
                    }
                }

                animateElements() {
                    // Añadir clases de animación a elementos
                    const elements = document.querySelectorAll('.summary-card, .transactions-section, .final-summary, .signature-section');
                    elements.forEach((el, index) => {
                        el.style.animationDelay = `${(index + 1) * 0.1}s`;
                    });
                }
            }

            // ==========================================================================
            // INICIALIZACIÓN DE LA APLICACIÓN
            // ==========================================================================
            document.addEventListener('DOMContentLoaded', () => {
                // Inicializar componentes
                const themeManager = new ThemeManager();
                const animationManager = new AnimationManager();

                // Log de inicialización
                console.log('Reporte de Ventas por Jornada v4.0');
                console.log('Período: <?php echo $selected_date; ?>');
                console.log('Total transacciones: <?php echo count($ventas); ?>');
                console.log('Total vendido: Q<?php echo number_format($total_sales, 2); ?>');
                console.log('Generado por: <?php echo htmlspecialchars($user_name); ?>');
            });

            // ==========================================================================
            // MANEJO DE IMPRESIÓN
            // ==========================================================================
            window.addEventListener('beforeprint', () => {
                // Cambiar a tema claro para impresión
                DOM.html.setAttribute('data-theme', 'light');

                // Ocultar botones de acción
                const actionButtons = document.querySelector('.action-buttons');
                if (actionButtons) {
                    actionButtons.classList.add('d-none');
                }
            });

            window.addEventListener('afterprint', () => {
                // Restaurar tema original
                const themeManager = new ThemeManager();

                // Mostrar botones de acción
                const actionButtons = document.querySelector('.action-buttons');
                if (actionButtons) {
                    actionButtons.classList.remove('d-none');
                }
            });

        })();

        // Estilos adicionales para mejorar la impresión
        const printStyles = document.createElement('style');
        printStyles.textContent = `
        @media print {
            @page {
                margin: 0.5in;
                size: letter;
            }
            
            body {
                font-size: 11pt !important;
                line-height: 1.3 !important;
            }
            
            .report-header {
                margin-bottom: 15pt !important;
            }
            
            .clinic-name {
                font-size: 16pt !important;
            }
            
            .report-title {
                font-size: 14pt !important;
            }
            
            .summary-value {
                font-size: 14pt !important;
            }
            
            .transactions-table {
                font-size: 9pt !important;
            }
            
            .transaction-avatar {
                display: none !important;
            }
            
            .signature-line {
                margin-top: 30pt !important;
            }
        }
    `;
        document.head.appendChild(printStyles);
    </script>
</body>

</html>