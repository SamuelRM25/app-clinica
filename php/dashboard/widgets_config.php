<?php
// widgets_config.php - Configuración de Widgets del Dashboard
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

verify_session();

$user_id = $_SESSION['user_id'];
$hospital_id = $_SESSION['id_hospital'];

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Procesar actualización si se envía el formulario
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_widgets') {
        $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
            $_SESSION['error_msg'] = 'Token CSRF inválido';
            header("Location: widgets_config.php");
            exit;
        }
        $widgets = $_POST['widgets'] ?? [];
        $all_possible_widgets = [
            'widget-appointments', 'widget-hospitalized', 'widget-alerts',
            'widget-revenue', 'widget-inventory', 'widget-patients',
            'widget-calendar', 'widget-labs'
        ];

        // Extra config values
        $display_limit  = max(1, min(50, (int)($_POST['display_limit']  ?? 5)));
        $refresh_mins   = max(1, min(60, (int)($_POST['refresh_mins']   ?? 5)));
        $alert_threshold= max(1, min(100,(int)($_POST['alert_threshold']?? 10)));
        $compact_mode   = isset($_POST['compact_mode']) ? 1 : 0;

        $conn->beginTransaction();

        foreach ($all_possible_widgets as $w_id) {
            $is_enabled = in_array($w_id, $widgets) ? 1 : 0;
            $stmt = $conn->prepare("
                INSERT INTO widget_settings (id_hospital, widget_id, is_enabled)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE is_enabled = ?
            ");
            $stmt->execute([$hospital_id, $w_id, $is_enabled, $is_enabled]);
        }

        // Save extra config as JSON in a meta widget entry
        $meta = json_encode([
            'display_limit'   => $display_limit,
            'refresh_mins'    => $refresh_mins,
            'alert_threshold' => $alert_threshold,
            'compact_mode'    => $compact_mode
        ]);
        $stmt = $conn->prepare("
            INSERT INTO widget_settings (id_hospital, widget_id, is_enabled, config_json)
            VALUES (?, '__meta__', 1, ?)
            ON DUPLICATE KEY UPDATE config_json = ?
        ");
        // Only execute if the table has config_json column (graceful degradation)
        try { $stmt->execute([$hospital_id, $meta, $meta]); } catch(\Exception $e) {}

        $conn->commit();
        $_SESSION['success_msg'] = "Configuración actualizada correctamente.";
        header("Location: index.php");
        exit;
    }

    // Obtener configuración actual
    $stmt = $conn->prepare("SELECT widget_id, is_enabled FROM widget_settings WHERE id_hospital = ?");
    $stmt->execute([$hospital_id]);
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Valores por defecto si no hay registros
    // Load extra config meta
    $stmt_meta = $conn->prepare("SELECT config_json FROM widget_settings WHERE id_hospital = ? AND widget_id = '__meta__'");
    try { $stmt_meta->execute([$hospital_id]); $meta_row = $stmt_meta->fetch(PDO::FETCH_ASSOC); } catch(\Exception $e) { $meta_row = false; }
    $meta_cfg = $meta_row ? (json_decode($meta_row['config_json'] ?? '{}', true) ?: []) : [];
    $cfg_display_limit   = $meta_cfg['display_limit']   ?? 5;
    $cfg_refresh_mins    = $meta_cfg['refresh_mins']    ?? 5;
    $cfg_alert_threshold = $meta_cfg['alert_threshold'] ?? 10;
    $cfg_compact_mode    = $meta_cfg['compact_mode']    ?? 0;

    $widget_list = [
        'widget-appointments' => [
            'name'    => 'Citas de Hoy',
            'desc'    => 'Lista de pacientes citados para el día actual.',
            'icon'    => 'bi-calendar-day',
            'color'   => 'primary',
            'enabled' => $settings['widget-appointments'] ?? 1
        ],
        'widget-hospitalized' => [
            'name'    => 'Pacientes Hospitalizados',
            'desc'    => 'Camas ocupadas y pacientes en encamamiento activo.',
            'icon'    => 'bi-hospital',
            'color'   => 'info',
            'enabled' => $settings['widget-hospitalized'] ?? 1
        ],
        'widget-alerts' => [
            'name'    => 'Alertas de Inventario',
            'desc'    => 'Avisos de stock bajo y medicamentos próximos a vencer.',
            'icon'    => 'bi-exclamation-triangle',
            'color'   => 'warning',
            'enabled' => $settings['widget-alerts'] ?? 1
        ],
        'widget-revenue' => [
            'name'    => 'Ingresos del Día',
            'desc'    => 'Resumen de ventas, cobros y facturación del día en curso.',
            'icon'    => 'bi-cash-stack',
            'color'   => 'success',
            'enabled' => $settings['widget-revenue'] ?? 1
        ],
        'widget-inventory' => [
            'name'    => 'Estado de Inventario',
            'desc'    => 'Medicamentos con stock crítico, vencidos y pendientes.',
            'icon'    => 'bi-box-seam',
            'color'   => 'danger',
            'enabled' => $settings['widget-inventory'] ?? 0
        ],
        'widget-patients' => [
            'name'    => 'Nuevos Pacientes',
            'desc'    => 'Pacientes registrados recientemente y sin historial.',
            'icon'    => 'bi-people-fill',
            'color'   => 'primary',
            'enabled' => $settings['widget-patients'] ?? 0
        ],
        'widget-calendar' => [
            'name'    => 'Mini Calendario',
            'desc'    => 'Vista rápida del calendario de citas de la semana.',
            'icon'    => 'bi-calendar-week',
            'color'   => 'info',
            'enabled' => $settings['widget-calendar'] ?? 0
        ],
        'widget-labs' => [
            'name'    => 'Resultados de Laboratorio',
            'desc'    => 'Exámenes pendientes de entrega o con resultados recientes.',
            'icon'    => 'bi-droplet-half',
            'color'   => 'warning',
            'enabled' => $settings['widget-labs'] ?? 0
        ],
    ];

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}

$page_title = "Configuración de Widgets - CMS";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <style>
        .widget-config-card {
            background: var(--color-surface);
            border-radius: 1.25rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s ease;
        }
        .widget-config-card:hover {
            box-shadow: 0 8px 15px rgba(0,0,0,0.05);
            transform: translateY(-2px);
        }
        .widget-info {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        .widget-icon-box {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: var(--color-bg);
            color: var(--color-primary);
        }
        .widget-text h4 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .widget-text p {
            margin: 0;
            font-size: 0.9rem;
            color: var(--color-text-muted);
        }
        /* Switch Toggle */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: var(--color-primary);
        }
        input:checked + .slider:before {
            transform: translateX(24px);
        }
    </style>
</head>
<body>
    <div class="marble-effect"></div>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-content">
                <div class="brand-container">
                    <img src="../../assets/img/Logo.png" alt="Logo" class="brand-logo">
                </div>
                <div class="header-controls">
                    <a href="index.php" class="action-btn secondary">
                        <i class="bi bi-arrow-left"></i> Volver al Dashboard
                    </a>
                </div>
            </div>
        </header>

        <main class="main-content">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Personalización del Dashboard</h1>
                    <p class="page-subtitle">Active o desactive los módulos que desea ver en su pantalla principal.</p>
                </div>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="update_widgets">

                <div class="row">
                    <div class="col-lg-9 mx-auto">

                        <!-- Section: Widgets -->
                        <div style="margin-bottom: 2rem;">
                            <h3 style="font-size: 1rem; font-weight: 700; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1rem;">
                                <i class="bi bi-grid-3x3-gap me-2"></i>Módulos del Dashboard
                            </h3>
                            <div class="row g-3">
                            <?php foreach ($widget_list as $id => $data): ?>
                                <div class="col-md-6">
                                    <div class="widget-config-card animate-in">
                                        <div class="widget-info">
                                            <div class="widget-icon-box" style="color: var(--color-<?php echo $data['color']; ?>); background: rgba(var(--color-<?php echo $data['color']; ?>-rgb,0,0,0), 0.1);">
                                                <i class="bi <?php echo $data['icon']; ?>"></i>
                                            </div>
                                            <div class="widget-text">
                                                <h4><?php echo $data['name']; ?></h4>
                                                <p><?php echo $data['desc']; ?></p>
                                            </div>
                                        </div>
                                        <div class="widget-control">
                                            <label class="switch">
                                                <input type="checkbox" name="widgets[]" value="<?php echo $id; ?>" <?php echo $data['enabled'] ? 'checked' : ''; ?>>
                                                <span class="slider"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Section: Limits & Behavior -->
                        <div style="background: var(--color-card); border: 1px solid var(--color-border); border-radius: var(--radius-xl); padding: 1.75rem; margin-bottom: 1.5rem;">
                            <h3 style="font-size: 1rem; font-weight: 700; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1.5rem;">
                                <i class="bi bi-sliders me-2"></i>Límites y Comportamiento
                            </h3>
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.4px; display: block; margin-bottom: 0.5rem;">Registros por Widget</label>
                                    <select name="display_limit" style="width:100%; padding: 0.6rem 0.875rem; border: 1.5px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text); font-size: 0.875rem;">
                                        <?php foreach ([3,5,10,15,20,25,50] as $n): ?>
                                            <option value="<?php echo $n; ?>" <?php echo $cfg_display_limit == $n ? 'selected' : ''; ?>><?php echo $n; ?> registros</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small style="color: var(--color-text-secondary); font-size: 0.75rem;">Máximo de filas por widget de lista.</small>
                                </div>
                                <div class="col-md-4">
                                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.4px; display: block; margin-bottom: 0.5rem;">Auto-Actualización</label>
                                    <select name="refresh_mins" style="width:100%; padding: 0.6rem 0.875rem; border: 1.5px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text); font-size: 0.875rem;">
                                        <?php foreach ([1,2,5,10,15,30,60] as $n): ?>
                                            <option value="<?php echo $n; ?>" <?php echo $cfg_refresh_mins == $n ? 'selected' : ''; ?>><?php echo $n; ?> min<?php echo $n > 1 ? 's' : ''; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small style="color: var(--color-text-secondary); font-size: 0.75rem;">Intervalo de refresco automático.</small>
                                </div>
                                <div class="col-md-4">
                                    <label style="font-size: 0.8rem; font-weight: 700; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.4px; display: block; margin-bottom: 0.5rem;">Umbral de Alerta de Stock</label>
                                    <select name="alert_threshold" style="width:100%; padding: 0.6rem 0.875rem; border: 1.5px solid var(--color-border); border-radius: var(--radius-md); background: var(--color-surface); color: var(--color-text); font-size: 0.875rem;">
                                        <?php foreach ([5,10,15,20,25,50,100] as $n): ?>
                                            <option value="<?php echo $n; ?>" <?php echo $cfg_alert_threshold == $n ? 'selected' : ''; ?>>≤ <?php echo $n; ?> unidades</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small style="color: var(--color-text-secondary); font-size: 0.75rem;">Punto donde se activa la alerta de stock bajo.</small>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Display Options -->
                        <div style="background: var(--color-card); border: 1px solid var(--color-border); border-radius: var(--radius-xl); padding: 1.75rem; margin-bottom: 2rem;">
                            <h3 style="font-size: 1rem; font-weight: 700; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1.5rem;">
                                <i class="bi bi-layout-wtf me-2"></i>Opciones de Visualización
                            </h3>
                            <div class="widget-config-card" style="margin-bottom: 0;">
                                <div class="widget-info">
                                    <div class="widget-icon-box"><i class="bi bi-layout-split"></i></div>
                                    <div class="widget-text">
                                        <h4>Modo Compacto</h4>
                                        <p>Reduce el espaciado de los widgets para mostrar más información en pantalla.</p>
                                    </div>
                                </div>
                                <div class="widget-control">
                                    <label class="switch">
                                        <input type="checkbox" name="compact_mode" <?php echo $cfg_compact_mode ? 'checked' : ''; ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-3">
                            <a href="index.php" class="action-btn secondary"><i class="bi bi-x"></i> Cancelar</a>
                            <button type="submit" class="action-btn" style="padding: 1rem 2.5rem;">
                                <i class="bi bi-save me-2"></i> Guardar Configuración
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </main>
    </div>
</body>
</html>
