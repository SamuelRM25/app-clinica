<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$db = new Database();
$conn = $db->getConnection();
$h = get_hospital_config($conn, $_SESSION['id_hospital'] ?? 1);

$page_title = "Suscripción";

$available_modules = [
    'core' => ['label' => 'Core / Consulta Externa', 'icon' => 'bi-house-heart', 'precio_mes' => 0, 'precio_anual' => 0, 'desc' => 'Pacientes, Citas, Historial clínico. Base obligatoria.'],
    'pharmacy' => ['label' => 'Farmacia / Punto de Venta', 'icon' => 'bi-capsule', 'precio_mes' => 199, 'precio_anual' => 1990, 'desc' => 'Despacho de medicamentos y punto de venta integrado.'],
    'hospitalization' => ['label' => 'Hospitalización', 'icon' => 'bi-hospital', 'precio_mes' => 299, 'precio_anual' => 2990, 'desc' => 'Gestión de camas, habitaciones, evoluciones y signos vitales.'],
    'laboratory' => ['label' => 'Laboratorio Clínico', 'icon' => 'bi-flask', 'precio_mes' => 249, 'precio_anual' => 2490, 'desc' => 'Órdenes, resultados, reactivos y control de calidad.'],
    'inventory' => ['label' => 'Inventario', 'icon' => 'bi-box-seam', 'precio_mes' => 149, 'precio_anual' => 1490, 'desc' => 'Control de stock de medicamentos e insumos.'],
    'imaging' => ['label' => 'Imagenología / Procedimientos', 'icon' => 'bi-activity', 'precio_mes' => 199, 'precio_anual' => 1990, 'desc' => 'Rayos X, Ultrasonidos, EKG y Procedimientos Menores.'],
    'purchases' => ['label' => 'Compras y Proveedores', 'icon' => 'bi-cart-plus', 'precio_mes' => 149, 'precio_anual' => 1490, 'desc' => 'Gestión de compras, pagos y cuentas por pagar.'],
    'sales' => ['label' => 'Ventas y Facturación', 'icon' => 'bi-receipt', 'precio_mes' => 149, 'precio_anual' => 1490, 'desc' => 'Registro de ventas con detalle por ítem.'],
    'finances' => ['label' => 'Cuentas Hospitalarias', 'icon' => 'bi-cash-stack', 'precio_mes' => 199, 'precio_anual' => 1990, 'desc' => 'Facturación compleja, abonos y saldos de hospitalización.'],
    'reports' => ['label' => 'Reportes Estadísticos', 'icon' => 'bi-graph-up', 'precio_mes' => 99, 'precio_anual' => 990, 'desc' => 'Reportes financieros, de pacientes y de desempeño.'],
];
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> — <?php echo htmlspecialchars($h['nombre'] ?? ''); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --sub-bg: #f0f2f6;
            --sub-surface: #ffffff;
            --sub-card: #ffffff;
            --sub-text: #0b0e14;
            --sub-text-secondary: #6b7280;
            --sub-border: rgba(0, 0, 0, 0.06);
            --sub-primary: #4f6ef7;
            --sub-primary-soft: rgba(79, 110, 247, 0.08);
            --sub-primary-glow: rgba(79, 110, 247, 0.25);
            --sub-success: #10b981;
            --sub-warning: #f59e0b;
            --sub-danger: #ef4444;
            --sub-radius: 14px;
            --sub-radius-sm: 10px;
            --sub-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 4px 16px rgba(0, 0, 0, 0.04);
            --sub-shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.06);
            --sub-ease: cubic-bezier(0.22, 1, 0.36, 1);
        }

        [data-theme="dark"] {
            --sub-bg: #0b0f1a;
            --sub-surface: #131824;
            --sub-card: #1a2030;
            --sub-text: #eef0f4;
            --sub-text-secondary: #8b95a5;
            --sub-border: rgba(255, 255, 255, 0.06);
            --sub-primary: #6b8aff;
            --sub-primary-soft: rgba(107, 138, 255, 0.1);
            --sub-primary-glow: rgba(107, 138, 255, 0.3);
            --sub-shadow: 0 1px 3px rgba(0, 0, 0, 0.2), 0 4px 16px rgba(0, 0, 0, 0.25);
            --sub-shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.35);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--sub-bg);
            color: var(--sub-text);
            min-height: 100vh;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        .sub-wrap {
            max-width: 1240px;
            margin: 0 auto;
            padding: 32px 24px 64px;
        }

        /* ── Header ── */
        .sub-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 36px;
        }

        .sub-header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .sub-header-left .back-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--sub-card);
            color: var(--sub-text);
            text-decoration: none;
            font-size: 1.2rem;
            border: 1px solid var(--sub-border);
            transition: all 0.25s var(--sub-ease);
            box-shadow: var(--sub-shadow);
        }

        .sub-header-left .back-btn:hover {
            transform: translateX(-3px);
            border-color: var(--sub-primary);
            color: var(--sub-primary);
        }

        .sub-header-left h1 {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1.3;
        }

        .sub-header-left h1 small {
            display: block;
            font-size: 0.85rem;
            font-weight: 400;
            color: var(--sub-text-secondary);
            letter-spacing: normal;
        }

        .sub-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .theme-btn-sub {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: var(--sub-card);
            border: 1px solid var(--sub-border);
            color: var(--sub-text-secondary);
            font-size: 1.15rem;
            cursor: pointer;
            transition: all 0.25s var(--sub-ease);
            box-shadow: var(--sub-shadow);
        }

        .theme-btn-sub:hover {
            color: var(--sub-primary);
            border-color: var(--sub-primary);
            transform: rotate(12deg);
        }

        .chip-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 16px;
            border-radius: 100px;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            background: var(--sub-primary-soft);
            color: var(--sub-primary);
            border: 1px solid transparent;
        }

        .chip-status.active { background: rgba(16, 185, 129, 0.12); color: var(--sub-success); border-color: rgba(16, 185, 129, 0.2); }
        .chip-status.expired { background: rgba(239, 68, 68, 0.1); color: var(--sub-danger); border-color: rgba(239, 68, 68, 0.15); }
        .chip-status.inactive { background: rgba(107, 114, 128, 0.12); color: var(--sub-text-secondary); }
        .chip-status.trial { background: rgba(245, 158, 11, 0.12); color: var(--sub-warning); border-color: rgba(245, 158, 11, 0.2); }

        .chip-status .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            animation: pulse-dot 2s ease-in-out infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        /* ── Grid ── */
        .sub-grid {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 28px;
            align-items: start;
        }

        @media (max-width: 900px) {
            .sub-grid { grid-template-columns: 1fr; }
        }

        /* ── Cards base ── */
        .sub-card {
            background: var(--sub-card);
            border-radius: var(--sub-radius);
            box-shadow: var(--sub-shadow);
            border: 1px solid var(--sub-border);
            transition: box-shadow 0.3s var(--sub-ease);
        }

        .sub-card:hover {
            box-shadow: var(--sub-shadow-lg);
        }

        .sub-card-body {
            padding: 28px;
        }

        /* ── Plan card ── */
        .plan-label {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--sub-text-secondary);
            margin-bottom: 4px;
        }

        .plan-name {
            font-size: 1.35rem;
            font-weight: 700;
        }

        .plan-divider {
            height: 1px;
            background: var(--sub-border);
            margin: 20px 0;
        }

        .plan-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
        }

        .plan-row-label {
            font-size: 0.88rem;
            color: var(--sub-text-secondary);
        }

        .plan-row-value {
            font-size: 0.9rem;
            font-weight: 600;
        }

        .plan-badge-list {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .plan-badge-list .badge {
            padding: 4px 12px;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 500;
            background: var(--sub-primary-soft);
            color: var(--sub-primary);
            border: 1px solid rgba(79, 110, 247, 0.15);
        }

        .payment-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .payment-list li {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            color: var(--sub-text-secondary);
        }

        .payment-list li i {
            width: 18px;
            color: var(--sub-primary);
            font-size: 1rem;
        }

        /* ── Billing toggle ── */
        .billing-toggle {
            display: flex;
            background: var(--sub-bg);
            border-radius: 10px;
            padding: 3px;
            border: 1px solid var(--sub-border);
        }

        .billing-toggle .btn-opt {
            padding: 6px 14px;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--sub-text-secondary);
            cursor: pointer;
            transition: all 0.25s var(--sub-ease);
            font-family: inherit;
        }

        .billing-toggle .btn-opt.active {
            background: var(--sub-card);
            color: var(--sub-primary);
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        }

        .billing-toggle .btn-opt .discount {
            display: inline-block;
            font-size: 0.6rem;
            font-weight: 700;
            background: var(--sub-success);
            color: #fff;
            padding: 1px 6px;
            border-radius: 100px;
            margin-left: 4px;
            vertical-align: middle;
        }

        .billing-toggle .btn-opt:hover:not(.active) {
            color: var(--sub-text);
        }

        /* ── Module grid ── */
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 12px;
            margin-top: 20px;
        }

        .module-item {
            position: relative;
            padding: 18px 16px;
            border-radius: var(--sub-radius-sm);
            background: var(--sub-bg);
            border: 1.5px solid transparent;
            cursor: pointer;
            transition: all 0.3s var(--sub-ease);
            user-select: none;
        }

        .module-item:hover {
            border-color: var(--sub-border);
            background: var(--sub-card);
        }

        .module-item.selected {
            border-color: var(--sub-primary);
            background: var(--sub-primary-soft);
        }

        .module-item.core {
            cursor: default;
            opacity: 0.85;
        }

        .module-item.core::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            pointer-events: none;
        }

        .module-item .check {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--sub-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.65rem;
            color: transparent;
            transition: all 0.3s var(--sub-ease);
        }

        .module-item.selected .check {
            background: var(--sub-primary);
            border-color: var(--sub-primary);
            color: #fff;
        }

        .module-item.core .check {
            background: var(--sub-success);
            border-color: var(--sub-success);
            color: #fff;
            opacity: 0.6;
        }

        .module-item .icon {
            font-size: 1.4rem;
            color: var(--sub-primary);
            margin-bottom: 8px;
            display: block;
        }

        .module-item .name {
            font-weight: 600;
            font-size: 0.88rem;
            margin-bottom: 2px;
        }

        .module-item .desc {
            font-size: 0.76rem;
            color: var(--sub-text-secondary);
            line-height: 1.4;
            margin-bottom: 8px;
        }

        .module-item .price {
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--sub-text);
        }

        .module-item .price .free {
            color: var(--sub-success);
            font-weight: 600;
        }

        .module-item .active-label {
            position: absolute;
            top: 10px;
            left: 10px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 2px 8px;
            border-radius: 100px;
            background: rgba(16, 185, 129, 0.12);
            color: var(--sub-success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        /* ── Total bar ── */
        .total-bar {
            margin-top: 24px;
            padding: 20px 24px;
            border-radius: var(--sub-radius-sm);
            background: var(--sub-bg);
            border: 1px solid var(--sub-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .total-bar .total-label {
            font-size: 0.8rem;
            color: var(--sub-text-secondary);
        }

        .total-bar .total-amount {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--sub-primary);
            letter-spacing: -0.03em;
        }

        .total-bar .total-amount small {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--sub-text-secondary);
        }

        .total-actions {
            display: flex;
            gap: 8px;
        }

        .btn-sub {
            padding: 10px 22px;
            border-radius: 10px;
            border: none;
            font-size: 0.85rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.25s var(--sub-ease);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-sub:active {
            transform: scale(0.96);
        }

        .btn-sub-primary {
            background: var(--sub-primary);
            color: #fff;
            box-shadow: 0 4px 14px var(--sub-primary-glow);
        }

        .btn-sub-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px var(--sub-primary-glow);
        }

        .btn-sub-ghost {
            background: transparent;
            color: var(--sub-text-secondary);
            border: 1px solid var(--sub-border);
        }

        .btn-sub-ghost:hover {
            border-color: var(--sub-text-secondary);
            color: var(--sub-text);
        }

        /* ── Modal ── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
            animation: fadeIn 0.2s ease;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-box {
            background: var(--sub-card);
            border-radius: var(--sub-radius);
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 500px;
            padding: 32px;
            border: 1px solid var(--sub-border);
            animation: slideUp 0.35s var(--sub-ease);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal-box h2 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .modal-box .modal-sub {
            font-size: 0.85rem;
            color: var(--sub-text-secondary);
            margin-bottom: 20px;
        }

        .modal-box .field {
            margin-bottom: 16px;
        }

        .modal-box .field label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--sub-text-secondary);
            margin-bottom: 4px;
        }

        .modal-box .field .val {
            font-size: 1rem;
            font-weight: 600;
            color: var(--sub-text);
        }

        .modal-box .field .val.primary {
            color: var(--sub-primary);
        }

        .modal-box .modules-summary {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 4px;
        }

        .modal-box .modules-summary .mod-tag {
            padding: 4px 10px;
            border-radius: 100px;
            font-size: 0.72rem;
            font-weight: 500;
            background: var(--sub-primary-soft);
            color: var(--sub-primary);
        }

        .modal-box textarea {
            width: 100%;
            padding: 10px 14px;
            border-radius: 10px;
            border: 1.5px solid var(--sub-border);
            background: var(--sub-bg);
            color: var(--sub-text);
            font-family: inherit;
            font-size: 0.88rem;
            resize: vertical;
            transition: border-color 0.2s;
        }

        .modal-box textarea:focus {
            outline: none;
            border-color: var(--sub-primary);
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 24px;
            justify-content: flex-end;
        }

        /* ── Entrance animation ── */
        .anim-in {
            animation: slideUp 0.5s var(--sub-ease) both;
        }

        .anim-in-d1 { animation-delay: 0.05s; }
        .anim-in-d2 { animation-delay: 0.1s; }
        .anim-in-d3 { animation-delay: 0.15s; }
        .anim-in-d4 { animation-delay: 0.2s; }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--sub-border); border-radius: 100px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--sub-text-secondary); }
    </style>
</head>
<body>

<div class="sub-wrap">
    <!-- Header -->
    <header class="sub-header anim-in">
        <div class="sub-header-left">
            <a href="../dashboard/index.php" class="back-btn"><i class="bi bi-arrow-left"></i></a>
            <div>
                <h1>Suscripción <small><?php echo htmlspecialchars($h['nombre'] ?? 'Hospital'); ?></small></h1>
            </div>
        </div>
        <div class="sub-header-actions">
            <?php
            $chip_class = match ($h['estado_suscripcion'] ?? '') {
                'Activo' => 'active',
                'Vencido' => 'expired',
                'Inactivo' => 'inactive',
                default => 'trial'
            };
            ?>
            <span class="chip-status <?php echo $chip_class; ?>">
                <span class="dot"></span>
                <?php echo strtoupper($h['estado_suscripcion'] ?? 'PRUEBA'); ?>
            </span>
            <button id="themeToggleSub" class="theme-btn-sub" aria-label="Toggle theme">
                <i class="bi bi-moon-stars"></i>
            </button>
        </div>
    </header>

    <!-- Grid -->
    <div class="sub-grid">
        <!-- Plan details -->
        <div class="sub-card anim-in anim-in-d1">
            <div class="sub-card-body">
                <div class="plan-label">Plan actual</div>
                <div class="plan-name"><?php echo $h['tipo_suscripcion'] ?? '—'; ?></div>

                <div class="plan-divider"></div>

                <div class="plan-row">
                    <span class="plan-row-label">Estado</span>
                    <span class="chip-status <?php echo $chip_class; ?>" style="padding:3px 12px;font-size:0.7rem;">
                        <span class="dot"></span>
                        <?php echo $h['estado_suscripcion'] ?? '—'; ?>
                    </span>
                </div>

                <div class="plan-row">
                    <span class="plan-row-label">Vencimiento</span>
                    <span class="plan-row-value" style="color:var(--sub-primary);">
                        <?php if (($h['tipo_suscripcion'] ?? '') === 'De por vida'): ?>
                            <span style="color:var(--sub-success);">♾ De por vida</span>
                        <?php else: ?>
                            <?php echo $h['fecha_vencimiento'] ?? '—'; ?>
                        <?php endif; ?>
                    </span>
                </div>

                <div class="plan-divider"></div>

                <div class="plan-row-label" style="margin-bottom:10px;">Módulos activos</div>
                <div class="plan-badge-list">
                    <?php foreach ($h['modulos_activos'] as $mod): ?>
                        <span class="badge"><?php echo ucfirst($mod); ?></span>
                    <?php endforeach; ?>
                </div>

                <div class="plan-divider"></div>

                <div class="plan-row-label" style="margin-bottom:10px;">Métodos de pago</div>
                <ul class="payment-list">
                    <li><i class="bi bi-bank"></i> Transferencia Bancaria (BI, Banrural, G&T)</li>
                    <li><i class="bi bi-credit-card"></i> Tarjeta de Crédito/Débito</li>
                    <li><i class="bi bi-paypal"></i> PayPal / Enlace de Pago</li>
                </ul>
            </div>
        </div>

        <!-- Module selector -->
        <div class="sub-card anim-in anim-in-d2">
            <div class="sub-card-body">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:4px;">
                    <h2 style="font-size:1.1rem;font-weight:700;">Solicitar / Renovar Módulos</h2>
                    <div class="billing-toggle" id="billingToggle">
                        <button class="btn-opt active" data-value="mes">Mensual</button>
                        <button class="btn-opt" data-value="anual">Anual <span class="discount">-15%</span></button>
                        <button class="btn-opt" data-value="vida">De por vida</button>
                    </div>
                </div>
                <p style="font-size:0.82rem;color:var(--sub-text-secondary);">
                    Selecciona los módulos que necesitas. Los que ya tienes activos están marcados.
                </p>

                <div class="module-grid" id="moduleGrid">
                    <?php foreach ($available_modules as $key => $mod):
                        $is_core = ($key === 'core');
                        $is_active = in_array($key, $h['modulos_activos']);
                        $sel = ($is_core || $is_active) ? 'selected' : '';
                        ?>
                        <div class="module-item <?php echo $sel; ?><?php echo $is_core ? ' core' : ''; ?>"
                             data-module="<?php echo $key; ?>"
                             data-price-mes="<?php echo $mod['precio_mes']; ?>"
                             data-price-anual="<?php echo $mod['precio_anual']; ?>">
                            <?php if ($is_active && !$is_core): ?>
                                <span class="active-label">Activo</span>
                            <?php endif; ?>
                            <span class="check"><i class="bi bi-check"></i></span>
                            <span class="icon"><i class="bi <?php echo $mod['icon']; ?>"></i></span>
                            <div class="name"><?php echo $mod['label']; ?></div>
                            <div class="desc"><?php echo $mod['desc']; ?></div>
                            <div class="price" id="price-<?php echo $key; ?>">
                                <?php if ($mod['precio_mes'] === 0): ?>
                                    <span class="free">Incluido</span>
                                <?php else: ?>
                                    Q<?php echo $mod['precio_mes']; ?>/mes
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Total bar -->
                <div class="total-bar">
                    <div>
                        <div class="total-label">Total estimado</div>
                        <div class="total-amount" id="totalPrice">Q0 <small>/mes</small></div>
                    </div>
                    <div class="total-actions">
                        <button class="btn-sub btn-sub-ghost" onclick="resetSelection()">Restablecer</button>
                        <button class="btn-sub btn-sub-primary" onclick="openRequestModal()">
                            <i class="bi bi-send"></i> Enviar Solicitud
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="requestModal">
    <div class="modal-box">
        <h2><i class="bi bi-send-check" style="color:var(--sub-primary);margin-right:8px;"></i>Confirmar Solicitud</h2>
        <p class="modal-sub">Revisa los módulos seleccionados antes de enviar.</p>

        <div class="field">
            <label>Módulos solicitados</label>
            <div class="modules-summary" id="reqModules"></div>
        </div>
        <div class="field">
            <label>Tipo de suscripción</label>
            <div class="val" id="reqTipo">—</div>
        </div>
        <div class="field">
            <label>Total estimado</label>
            <div class="val primary" id="reqTotal">—</div>
        </div>
        <div class="field">
            <label>Mensaje adicional <span style="font-weight:400;color:var(--sub-text-secondary);">(opcional)</span></label>
            <textarea id="reqMensaje" rows="2" placeholder="Cualquier consulta o aclaración..."></textarea>
        </div>

        <div class="modal-actions">
            <button class="btn-sub btn-sub-ghost" onclick="closeModal()">Cancelar</button>
            <button class="btn-sub btn-sub-primary" onclick="submitRequest()">
                <i class="bi bi-check2-circle"></i> Confirmar y Enviar
            </button>
        </div>
    </div>
</div>

<script>
const modulePrices = <?php
$prices = [];
foreach ($available_modules as $k => $m) {
    $prices[$k] = ['mes' => $m['precio_mes'], 'anual' => $m['precio_anual']];
}
echo json_encode($prices);
?>;

const activeModules = <?php echo json_encode($h['modulos_activos']); ?>;
let currentBilling = 'mes';

/* Theme */
const html = document.documentElement;
const themeBtn = document.getElementById('themeToggleSub');
const saved = localStorage.getItem('sub_theme') || 'light';
html.setAttribute('data-theme', saved);
themeBtn.innerHTML = saved === 'dark' ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon-stars"></i>';

themeBtn.addEventListener('click', () => {
    const cur = html.getAttribute('data-theme');
    const next = cur === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('sub_theme', next);
    themeBtn.innerHTML = next === 'dark' ? '<i class="bi bi-sun"></i>' : '<i class="bi bi-moon-stars"></i>';
});

/* Billing toggle */
document.querySelectorAll('#billingToggle .btn-opt').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('#billingToggle .btn-opt').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentBilling = btn.dataset.value;
        updateAllPrices();
        updateTotal();
    });
});

/* Toggle module */
document.querySelectorAll('.module-item:not(.core)').forEach(card => {
    card.addEventListener('click', () => {
        card.classList.toggle('selected');
        updateTotal();
    });
});

function updateAllPrices() {
    document.querySelectorAll('.module-item').forEach(card => {
        const key = card.dataset.module;
        const el = document.getElementById('price-' + key);
        if (!el) return;
        const p = modulePrices[key];
        if (p.mes === 0) { el.innerHTML = '<span class="free">Incluido</span>'; return; }
        if (currentBilling === 'mes') el.textContent = `Q${p.mes}/mes`;
        if (currentBilling === 'anual') el.textContent = `Q${Math.round(p.anual)}/año`;
        if (currentBilling === 'vida') el.textContent = `Q${Math.round(p.anual * 5)} única vez`;
    });
}

function updateTotal() {
    let total = 0;
    document.querySelectorAll('.module-item.selected').forEach(card => {
        const key = card.dataset.module;
        const p = modulePrices[key];
        if (currentBilling === 'mes') total += p.mes;
        if (currentBilling === 'anual') total += p.anual;
        if (currentBilling === 'vida') total += p.anual * 5;
    });
    const label = currentBilling === 'mes' ? '/mes' : currentBilling === 'anual' ? '/año' : ' única vez';
    document.getElementById('totalPrice').innerHTML = `Q${total} <small>${label}</small>`;
}

function resetSelection() {
    document.querySelectorAll('.module-item').forEach(card => {
        if (card.classList.contains('core')) return;
        card.classList.remove('selected');
        if (activeModules.includes(card.dataset.module)) {
            card.classList.add('selected');
        }
    });
    updateTotal();
}

function openRequestModal() {
    const selected = getSelected();
    if (selected.length === 0) {
        Swal.fire('Atención', 'Selecciona al menos un módulo.', 'warning');
        return;
    }

    const tipoMap = { mes: 'Mensual', anual: 'Anual', vida: 'De por vida' };
    document.getElementById('reqTipo').textContent = tipoMap[currentBilling];
    document.getElementById('reqTotal').textContent = document.getElementById('totalPrice').textContent;

    const names = selected.map(k => {
        const card = document.querySelector(`.module-item[data-module="${k}"]`);
        return card ? card.querySelector('.name').textContent : k;
    });
    document.getElementById('reqModules').innerHTML = names.map(n =>
        `<span class="mod-tag">${n}</span>`
    ).join('');

    document.getElementById('requestModal').classList.add('open');
}

function closeModal() {
    document.getElementById('requestModal').classList.remove('open');
}

function getSelected() {
    const arr = [];
    document.querySelectorAll('.module-item.selected').forEach(c => arr.push(c.dataset.module));
    return arr;
}

async function submitRequest() {
    const tipoMap = { mes: 'Mensual', anual: 'Anual', vida: 'De por vida' };
    const fd = new FormData();
    fd.append('modulos', JSON.stringify(getSelected()));
    fd.append('tipo_suscripcion', tipoMap[currentBilling]);
    fd.append('mensaje', document.getElementById('reqMensaje').value);

    try {
        const r = await fetch('api/request_modules.php', { method: 'POST', body: fd });
        const js = await r.json();
        closeModal();
        Swal.fire(
            js.status === 'success' ? '¡Solicitud Enviada!' : 'Error',
            js.message,
            js.status === 'success' ? 'success' : 'error'
        );
    } catch (e) {
        Swal.fire('Error', 'No se pudo enviar la solicitud.', 'error');
    }
}

/* Close modal on overlay click */
document.getElementById('requestModal').addEventListener('click', (e) => {
    if (e.target === e.currentTarget) closeModal();
});

/* Init */
updateTotal();
</script>
</body>
</html>
