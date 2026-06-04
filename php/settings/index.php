<?php
// settings/index.php - Configuración del Sistema Modernizada - Centro Médico Herrera Saenz
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';
require_once '../../includes/breadcrumbs.php';

check_module_access('core');
verify_session();

// Solo administradores pueden acceder a configuraciones
if ($_SESSION['tipoUsuario'] !== 'admin') {
    header("Location: ../dashboard/index.php");
    exit;
}

$user_name = $_SESSION['nombre'];
$user_role = $_SESSION['tipoUsuario'];
$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener información actual de la clínica
    $stmt = $conn->prepare("SELECT * FROM configuracion_sistema WHERE id_hospital = ? LIMIT 1");
    $stmt->execute([$id_hospital]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'nombre_clinica' => 'Centro Médico Herrera Saenz',
        'direccion' => 'Ciudad de Guatemala',
        'telefono' => '5214-8836',
        'email' => 'info@herrerasaenz.com',
        'logo_path' => '../../assets/img/cmhs.png'
    ];

    // Obtener lista de usuarios
    $stmt_users = $conn->prepare("SELECT idUsuario, usuario, nombre, apellido, tipoUsuario, especialidad, email FROM usuarios WHERE id_hospital = ? ORDER BY nombre");
    $stmt_users->execute([$id_hospital]);
    $users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

    // Obtener habitaciones con conteo de camas
    $stmt_rooms = $conn->prepare("
        SELECT h.*,
               COUNT(c.id_cama) as cama_count
        FROM habitaciones h
        LEFT JOIN camas c ON h.id_habitacion = c.id_habitacion
        WHERE h.id_hospital = ?
        GROUP BY h.id_habitacion
        ORDER BY h.numero_habitacion
    ");
    $stmt_rooms->execute([$id_hospital]);
    $rooms = $stmt_rooms->fetchAll(PDO::FETCH_ASSOC);

    // Obtener médicos para tarifas de consulta/reconsulta
    $stmt_medicos = $conn->prepare("
        SELECT idUsuario, nombre, apellido, especialidad
        FROM usuarios
        WHERE id_hospital = ? AND tipoUsuario = 'doc'
        ORDER BY nombre
    ");
    $stmt_medicos->execute([$id_hospital]);
    $medicos = $stmt_medicos->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log($e->getMessage());
}

$page_title = "Configuración del Sistema";
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">

    <link rel="icon" type="image/png" href="../../assets/img/cmhs.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <?php include '../../includes/theme_head.php'; ?>

    <style>
        .settings-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            min-height: calc(100vh - 150px);
        }

        .settings-sidebar {
            background: var(--color-card);
            border-radius: var(--radius-xl);
            padding: 1.5rem;
            border: 1px solid var(--color-border);
            height: fit-content;
            position: sticky;
            top: 2rem;
        }

        .nav-pills .nav-link {
            color: var(--color-text-secondary);
            padding: 0.75rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all var(--transition-base);
            font-weight: 500;
        }

        .nav-pills .nav-link:hover {
            background: var(--color-surface);
            color: var(--color-primary);
        }

        .nav-pills .nav-link.active {
            background: var(--color-primary);
            color: white;
            box-shadow: 0 4px 12px rgba(var(--color-primary-rgb), 0.3);
        }

        .settings-content-card {
            background: var(--color-card);
            border-radius: var(--radius-xl);
            border: 1px solid var(--color-border);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .tab-pane {
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .user-row:hover {
            background: rgba(var(--color-primary-rgb), 0.03);
        }

        .theme-card {
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
            border-radius: var(--radius-lg);
            overflow: hidden;
        }

        .theme-card:hover {
            transform: translateY(-5px);
        }

        .theme-card.active {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 4px rgba(var(--color-primary-rgb), 0.1);
        }

        .theme-preview {
            height: 100px;
            position: relative;
        }

        /* Override global_dashboard.css to allow Bootstrap tabs to work */
        #settingsTabContent.tab-content,
        #settingsTabContent,
        #tarifasTabContent.tab-content,
        #tarifasTabContent {
            display: block !important;
        }
    </style>
</head>

<body>
    <div class="marble-effect"></div>

    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="header-content">
                <div class="brand-container">
                    <img src="../../assets/img/cmhs.png" alt="logo" class="brand-logo" width="40" height="40">
                </div>

                <div class="header-controls">
                    <div class="theme-toggle">
                        <button id="themeSwitch" class="theme-btn">
                            <i class="bi bi-sun theme-icon sun-icon"></i>
                            <i class="bi bi-moon theme-icon moon-icon"></i>
                        </button>
                    </div>

                    <div class="header-user">
                        <div class="header-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                        <div class="header-details">
                            <span class="header-name"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="header-role">Administrador</span>
                        </div>
                    </div>

                    <a href="../dashboard/index.php" class="logout-btn">
                        <i class="bi bi-speedometer2"></i>
                        <span>Panel Principal</span>
                    </a>
                </div>
            </div>
        </header>

        <main class="main-content">
            <?php render_breadcrumbs([
                ['label' => 'Dashboard', 'url' => '../dashboard/index.php'],
                ['label' => 'Configuración'],
            ]); ?>
            <div class="page-header mb-4">
                <h1 class="page-title">
                    <i class="bi bi-gear-fill text-primary"></i>
                    Configuración del Sistema
                </h1>
                <p class="page-subtitle">Administre los parámetros globales, usuarios y apariencia del sistema.</p>
            </div>

            <div class="settings-layout">
                <!-- Sidebar Navigation -->
                <aside class="settings-sidebar">
                    <div class="nav flex-column nav-pills" id="settingsTabs" role="tablist">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="pill" data-bs-target="#general"
                            type="button" role="tab">
                            <i class="bi bi-building"></i> General
                        </button>
                        <button class="nav-link" id="users-tab" data-bs-toggle="pill" data-bs-target="#users"
                            type="button" role="tab">
                            <i class="bi bi-people"></i> Usuarios
                        </button>
                        <button class="nav-link" id="security-tab" data-bs-toggle="pill" data-bs-target="#security"
                            type="button" role="tab">
                            <i class="bi bi-shield-lock"></i> Seguridad
                        </button>
                        <button class="nav-link" id="appearance-tab" data-bs-toggle="pill" data-bs-target="#appearance"
                            type="button" role="tab">
                            <i class="bi bi-palette"></i> Apariencia
                        </button>
                        <button class="nav-link" id="backup-tab" data-bs-toggle="pill" data-bs-target="#backup"
                            type="button" role="tab">
                            <i class="bi bi-database-fill-check"></i> Respaldo
                        </button>
                        <button class="nav-link" id="tarifas-tab" data-bs-toggle="pill" data-bs-target="#tarifas"
                            type="button" role="tab">
                            <i class="bi bi-currency-dollar"></i> Tarifas
                        </button>
                        <button class="nav-link" id="rooms-tab" data-bs-toggle="pill" data-bs-target="#rooms"
                            type="button" role="tab">
                            <i class="bi bi-door-open"></i> Habitaciones
                        </button>
                    </div>
                </aside>

                <!-- Tab Content -->
                <div class="tab-content" id="settingsTabContent">
                    <!-- Tab: General -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                        <div class="settings-content-card">
                            <h3 class="section-title mb-4">Información de la Clínica</h3>
                            <form id="generalSettingsForm" action="save_settings.php" method="POST">
                                <?php echo csrf_field(); ?>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Nombre de la Institución</label>
                                        <input type="text" name="nombre_clinica" class="form-control"
                                            value="<?php echo htmlspecialchars($config['nombre_clinica']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Correo de Contacto</label>
                                        <input type="email" name="email" class="form-control"
                                            value="<?php echo htmlspecialchars($config['email']); ?>" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Dirección Física</label>
                                        <input type="text" name="direccion" class="form-control"
                                            value="<?php echo htmlspecialchars($config['direccion']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Teléfono General</label>
                                        <input type="text" name="telefono" class="form-control"
                                            value="<?php echo htmlspecialchars($config['telefono']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Moneda Local</label>
                                        <select class="form-select" name="currency">
                                            <option value="GTQ" selected>Quetzal (Q)</option>
                                            <option value="USD">Dólar (USD)</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-4">
                                    <button type="submit" class="action-btn">
                                        <i class="bi bi-check2-circle"></i> Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Tab: Usuarios -->
                    <div class="tab-pane fade" id="users" role="tabpanel">
                        <div class="settings-content-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h3 class="section-title mb-0">Gestión de Usuarios</h3>
                                <button class="action-btn primary" onclick="openUserModal()">
                                    <i class="bi bi-person-plus"></i> Nuevo Usuario
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Usuario</th>
                                            <th>Rol</th>
                                            <th>Especialidad</th>
                                            <th>Email</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $u): ?>
                                                <tr class="user-row">
                                                    <td>
                                                        <div class="fw-bold">
                                                            <?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?>
                                                        </div>
                                                        <small
                                                            class="text-muted">@<?php echo htmlspecialchars($u['usuario']); ?></small>
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge <?php echo $u['tipoUsuario'] === 'admin' ? 'bg-primary-subtle text-primary' : 'bg-light text-dark'; ?> border">
                                                            <?php echo ucfirst($u['tipoUsuario']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($u['especialidad'] ?: 'N/A'); ?></td>
                                                    <td><?php echo htmlspecialchars($u['email'] ?: 'Sin email'); ?></td>
                                                    <td class="text-center">
                                                        <div class="d-flex justify-content-center gap-2">
                                                            <button class="action-btn sm secondary" title="Editar"
                                                                onclick='editUser(<?php echo json_encode($u); ?>)'>
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <button class="action-btn sm danger" title="Eliminar"
                                                                onclick="deleteUser(<?php echo $u['idUsuario']; ?>)">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="appearance" role="tabpanel">
                        <div class="settings-content-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div>
                                    <h3 class="section-title mb-1">Personalización Visual</h3>
                                    <p class="text-muted small mb-0">Selecciona un tema para cambiar colores,
                                        tipografía, bordes y sombras de todo el sistema.</p>
                                </div>
                                <span class="badge rounded-pill"
                                    style="background: var(--color-primary); color: white; padding: 0.5rem 1rem; font-size: 0.75rem;"
                                    id="activeThemeBadge">Tema Activo</span>
                            </div>

                            <div class="row g-3" id="themeGrid">
                                <?php
                                $themes = [
                                    // --- CLÁSICOS ---
                                    [
                                        'id' => 'blue',
                                        'name' => 'Azul Premium',
                                        'tag' => 'Clásico',
                                        'primary' => '#0d6efd',
                                        'bg' => '#f8f9fa',
                                        'surface' => '#ffffff',
                                        'text' => '#212529',
                                        'radius' => '0.75rem',
                                        'shadow' => '0 4px 20px rgba(13,110,253,0.15)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#0d6efd',
                                        'preview_bg' => '#f0f4ff',
                                        'preview_accent' => '#cfe2ff'
                                    ],
                                    [
                                        'id' => 'emerald',
                                        'name' => 'Salud Esmeralda',
                                        'tag' => 'Médico',
                                        'primary' => '#10b981',
                                        'bg' => '#f0fdf4',
                                        'surface' => '#ffffff',
                                        'text' => '#064e3b',
                                        'radius' => '1rem',
                                        'shadow' => '0 4px 20px rgba(16,185,129,0.15)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#10b981',
                                        'preview_bg' => '#ecfdf5',
                                        'preview_accent' => '#a7f3d0'
                                    ],
                                    [
                                        'id' => 'purple',
                                        'name' => 'Royal Purple',
                                        'tag' => 'Elegante',
                                        'primary' => '#8b5cf6',
                                        'bg' => '#f5f3ff',
                                        'surface' => '#ffffff',
                                        'text' => '#3b0764',
                                        'radius' => '1rem',
                                        'shadow' => '0 4px 20px rgba(139,92,246,0.2)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#8b5cf6',
                                        'preview_bg' => '#ede9fe',
                                        'preview_accent' => '#ddd6fe'
                                    ],
                                    [
                                        'id' => 'rose',
                                        'name' => 'Atardecer Clínico',
                                        'tag' => 'Cálido',
                                        'primary' => '#f43f5e',
                                        'bg' => '#fff1f2',
                                        'surface' => '#ffffff',
                                        'text' => '#4c0519',
                                        'radius' => '0.75rem',
                                        'shadow' => '0 4px 20px rgba(244,63,94,0.15)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#f43f5e',
                                        'preview_bg' => '#ffe4e6',
                                        'preview_accent' => '#fecdd3'
                                    ],
                                    // --- OSCUROS ---
                                    [
                                        'id' => 'midnight',
                                        'name' => 'Midnight Blue',
                                        'tag' => 'Dark',
                                        'primary' => '#60a5fa',
                                        'bg' => '#0f172a',
                                        'surface' => '#1e293b',
                                        'text' => '#e2e8f0',
                                        'radius' => '0.75rem',
                                        'shadow' => '0 4px 30px rgba(96,165,250,0.25)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#3b82f6',
                                        'preview_bg' => '#1e293b',
                                        'preview_accent' => '#1d4ed8'
                                    ],
                                    [
                                        'id' => 'obsidian',
                                        'name' => 'Obsidian',
                                        'tag' => 'Dark',
                                        'primary' => '#a78bfa',
                                        'bg' => '#09090b',
                                        'surface' => '#18181b',
                                        'text' => '#fafafa',
                                        'radius' => '0.5rem',
                                        'shadow' => '0 4px 30px rgba(167,139,250,0.2)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#7c3aed',
                                        'preview_bg' => '#18181b',
                                        'preview_accent' => '#3b0764'
                                    ],
                                    [
                                        'id' => 'slate',
                                        'name' => 'Slate Dark',
                                        'tag' => 'Dark',
                                        'primary' => '#38bdf8',
                                        'bg' => '#0c1320',
                                        'surface' => '#1e2a3b',
                                        'text' => '#cbd5e1',
                                        'radius' => '0.6rem',
                                        'shadow' => '0 4px 25px rgba(56,189,248,0.2)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#0ea5e9',
                                        'preview_bg' => '#1e2a3b',
                                        'preview_accent' => '#0369a1'
                                    ],
                                    // --- NATURALEZA ---
                                    [
                                        'id' => 'forest',
                                        'name' => 'Bosque Tropical',
                                        'tag' => 'Naturaleza',
                                        'primary' => '#16a34a',
                                        'bg' => '#f0fdf4',
                                        'surface' => '#dcfce7',
                                        'text' => '#052e16',
                                        'radius' => '1.25rem',
                                        'shadow' => '0 4px 20px rgba(22,163,74,0.15)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#15803d',
                                        'preview_bg' => '#bbf7d0',
                                        'preview_accent' => '#86efac'
                                    ],
                                    [
                                        'id' => 'ocean',
                                        'name' => 'Océano Profundo',
                                        'tag' => 'Naturaleza',
                                        'primary' => '#0891b2',
                                        'bg' => '#ecfeff',
                                        'surface' => '#cffafe',
                                        'text' => '#083344',
                                        'radius' => '1rem',
                                        'shadow' => '0 4px 20px rgba(8,145,178,0.2)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#06b6d4',
                                        'preview_bg' => '#cffafe',
                                        'preview_accent' => '#a5f3fc'
                                    ],
                                    [
                                        'id' => 'autumn',
                                        'name' => 'Otoño',
                                        'tag' => 'Naturaleza',
                                        'primary' => '#ea580c',
                                        'bg' => '#fff7ed',
                                        'surface' => '#ffedd5',
                                        'text' => '#431407',
                                        'radius' => '0.75rem',
                                        'shadow' => '0 4px 20px rgba(234,88,12,0.15)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#f97316',
                                        'preview_bg' => '#fed7aa',
                                        'preview_accent' => '#fbbf24'
                                    ],
                                    // --- PASTEL ---
                                    [
                                        'id' => 'lavender',
                                        'name' => 'Lavanda Suave',
                                        'tag' => 'Pastel',
                                        'primary' => '#7c3aed',
                                        'bg' => '#faf5ff',
                                        'surface' => '#f3e8ff',
                                        'text' => '#2e1065',
                                        'radius' => '1.5rem',
                                        'shadow' => '0 4px 20px rgba(124,58,237,0.1)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#a855f7',
                                        'preview_bg' => '#f3e8ff',
                                        'preview_accent' => '#ddd6fe'
                                    ],
                                    [
                                        'id' => 'peach',
                                        'name' => 'Durazno Clínico',
                                        'tag' => 'Pastel',
                                        'primary' => '#ec4899',
                                        'bg' => '#fdf2f8',
                                        'surface' => '#fce7f3',
                                        'text' => '#500724',
                                        'radius' => '1.25rem',
                                        'shadow' => '0 4px 20px rgba(236,72,153,0.1)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#db2777',
                                        'preview_bg' => '#fce7f3',
                                        'preview_accent' => '#fbcfe8'
                                    ],
                                    [
                                        'id' => 'mint',
                                        'name' => 'Menta Fresca',
                                        'tag' => 'Pastel',
                                        'primary' => '#059669',
                                        'bg' => '#f0fdfa',
                                        'surface' => '#ccfbf1',
                                        'text' => '#022c22',
                                        'radius' => '1.5rem',
                                        'shadow' => '0 4px 20px rgba(5,150,105,0.1)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#10b981',
                                        'preview_bg' => '#ccfbf1',
                                        'preview_accent' => '#6ee7b7'
                                    ],
                                    // --- PREMIUM / LUJO ---
                                    [
                                        'id' => 'gold',
                                        'name' => 'Gold Luxury',
                                        'tag' => 'Lujo',
                                        'primary' => '#d97706',
                                        'bg' => '#fffbeb',
                                        'surface' => '#fef3c7',
                                        'text' => '#292524',
                                        'radius' => '0.5rem',
                                        'shadow' => '0 4px 25px rgba(217,119,6,0.2)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#f59e0b',
                                        'preview_bg' => '#fef9c3',
                                        'preview_accent' => '#fde68a'
                                    ],
                                    [
                                        'id' => 'charcoal',
                                        'name' => 'Carbón Ejecutivo',
                                        'tag' => 'Lujo',
                                        'primary' => '#6366f1',
                                        'bg' => '#1c1c1e',
                                        'surface' => '#2c2c2e',
                                        'text' => '#f5f5f7',
                                        'radius' => '0.75rem',
                                        'shadow' => '0 8px 30px rgba(99,102,241,0.25)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#6366f1',
                                        'preview_bg' => '#2c2c2e',
                                        'preview_accent' => '#4f46e5'
                                    ],
                                    // --- NEÓN / FUTURISTA ---
                                    [
                                        'id' => 'neon',
                                        'name' => 'Neon Cyber',
                                        'tag' => 'Futurista',
                                        'primary' => '#22d3ee',
                                        'bg' => '#020617',
                                        'surface' => '#0f172a',
                                        'text' => '#f0fdfa',
                                        'radius' => '0.25rem',
                                        'shadow' => '0 0 20px rgba(34,211,238,0.3)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#06b6d4',
                                        'preview_bg' => '#0f172a',
                                        'preview_accent' => '#0e7490'
                                    ],
                                    [
                                        'id' => 'matrix',
                                        'name' => 'Matrix Green',
                                        'tag' => 'Futurista',
                                        'primary' => '#4ade80',
                                        'bg' => '#000000',
                                        'surface' => '#0a0a0a',
                                        'text' => '#bbf7d0',
                                        'radius' => '0.25rem',
                                        'shadow' => '0 0 20px rgba(74,222,128,0.25)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#22c55e',
                                        'preview_bg' => '#052e16',
                                        'preview_accent' => '#166534'
                                    ],
                                    // --- RETRO ---
                                    [
                                        'id' => 'retro',
                                        'name' => 'Retro Warm',
                                        'tag' => 'Retro',
                                        'primary' => '#b45309',
                                        'bg' => '#fffbf5',
                                        'surface' => '#fef3c7',
                                        'text' => '#292524',
                                        'radius' => '0.25rem',
                                        'shadow' => '4px 4px 0px rgba(0,0,0,0.15)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#92400e',
                                        'preview_bg' => '#fef3c7',
                                        'preview_accent' => '#fde68a'
                                    ],
                                    [
                                        'id' => 'bubblegum',
                                        'name' => 'Bubblegum Pop',
                                        'tag' => 'Retro',
                                        'primary' => '#ec4899',
                                        'bg' => '#fdf4ff',
                                        'surface' => '#fae8ff',
                                        'text' => '#4a044e',
                                        'radius' => '2rem',
                                        'shadow' => '6px 6px 0px rgba(236,72,153,0.3)',
                                        'font' => 'Inter',
                                        'preview_bar' => '#db2777',
                                        'preview_bg' => '#fce7f3',
                                        'preview_accent' => '#f0abfc'
                                    ],
                                ];
                                foreach ($themes as $t): ?>
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="theme-card" data-theme-id="<?php echo $t['id']; ?>"
                                                onclick="applyFullTheme(<?php echo htmlspecialchars(json_encode($t)); ?>)">
                                                <!-- Mini preview -->
                                                <div class="theme-preview"
                                                    style="background: <?php echo $t['preview_bg']; ?>; border-radius: calc(<?php echo $t['radius']; ?> - 2px) calc(<?php echo $t['radius']; ?> - 2px) 0 0; overflow:hidden; position:relative;">
                                                    <!-- Fake header bar -->
                                                    <div
                                                        style="background:<?php echo $t['preview_bar']; ?>; height:22px; display:flex; align-items:center; padding:0 8px; gap:5px;">
                                                        <div
                                                            style="width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,0.6);">
                                                        </div>
                                                        <div
                                                            style="width:6px;height:6px;border-radius:50%;background:rgba(255,255,255,0.4);">
                                                        </div>
                                                        <div
                                                            style="flex:1; height:4px; border-radius:2px; background:rgba(255,255,255,0.3); margin-left:4px;">
                                                        </div>
                                                    </div>
                                                    <!-- Fake cards -->
                                                    <div style="padding:8px; display:flex; gap:6px;">
                                                        <div
                                                            style="flex:1; background:<?php echo $t['surface']; ?>; border-radius:<?php echo $t['radius']; ?>; height:32px; box-shadow:<?php echo $t['shadow']; ?>;">
                                                        </div>
                                                        <div
                                                            style="flex:1; background:<?php echo $t['preview_accent']; ?>; border-radius:<?php echo $t['radius']; ?>; height:32px;">
                                                        </div>
                                                    </div>
                                                    <!-- Fake button -->
                                                    <div style="position:absolute; bottom:8px; right:8px;">
                                                        <div
                                                            style="background:<?php echo $t['primary']; ?>; color:white; font-size:7px; padding:3px 7px; border-radius:<?php echo $t['radius']; ?>; font-weight:600;">
                                                            Guardar</div>
                                                    </div>
                                                </div>
                                                <!-- Label -->
                                                <div style="padding:0.6rem; background:var(--color-card);">
                                                    <div style="font-weight:700; font-size:0.8rem; color:var(--color-text);">
                                                        <?php echo $t['name']; ?>
                                                    </div>
                                                    <div
                                                        style="font-size:0.7rem; color:var(--color-text-secondary); margin-top:2px;">
                                                        <span
                                                            style="background:<?php echo $t['primary']; ?>22; color:<?php echo $t['primary']; ?>; padding:1px 6px; border-radius:20px; font-weight:600;"><?php echo $t['tag']; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                <?php endforeach; ?>
                            </div>

                            <hr class="my-4">

                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <div>
                                    <label class="form-label fw-bold mb-1">Color de Acento Personalizado</label>
                                    <p class="text-muted small mb-0">Cambia solo el color primario manteniendo el resto
                                        del tema.</p>
                                </div>
                                <div class="d-flex align-items-center gap-2 ms-auto">
                                    <input type="color" class="form-control form-control-color" id="accentColorPicker"
                                        value="#0d6efd" title="Elegir color" style="width:50px; height:40px;">
                                    <button class="action-btn"
                                        onclick="updateAppTheme(document.getElementById('accentColorPicker').value)">
                                        <i class="bi bi-palette"></i> Aplicar Color
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Other tabs placeholders -->
                    <div class="tab-pane fade" id="security" role="tabpanel">
                        <div class="settings-content-card">
                            <h3 class="section-title mb-4">Seguridad y Acceso</h3>
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="card border p-3">
                                        <h5 class="fw-bold"><i class="bi bi-key me-2"></i>Políticas de Password</h5>
                                        <p class="small text-muted">Exigir longitud mínima y caracteres especiales.</p>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <label class="form-check-label">Habilitar seguridad estricta</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border p-3">
                                        <h5 class="fw-bold"><i class="bi bi-clock-history me-2"></i>Sesión Automática
                                        </h5>
                                        <p class="small text-muted">Cerrar sesión tras inactividad prolongada.</p>
                                        <select class="form-select form-select-sm">
                                            <option>30 minutos</option>
                                            <option selected>1 hora</option>
                                            <option>4 horas</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="backup" role="tabpanel">
                        <div class="settings-content-card text-center py-5">
                            <div class="mb-4">
                                <i class="bi bi-database-fill-gear text-primary" style="font-size: 4rem;"></i>
                            </div>
                            <h3>Respaldo de Base de Datos</h3>
                            <p class="text-muted mb-4">Genere una copia de seguridad completa de toda la información del
                                sistema.</p>
                            <button class="action-btn lg mx-auto">
                                <i class="bi bi-download"></i> Descargar SQL Backup
                            </button>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tarifas" role="tabpanel">
                        <div class="settings-content-card">
                            <h3 class="section-title mb-4">Tarifas de Servicios</h3>
                            <p class="text-muted mb-3"><i class="bi bi-hospital"></i> Hospital: <strong id="currentHospitalName">-</strong></p>

                            <ul class="nav nav-tabs mb-4" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="tarifas-consulta-tab" data-bs-toggle="tab" data-bs-target="#tarifas-consulta" type="button" role="tab">
                                        <i class="bi bi-calendar-check me-1"></i>Consulta/Reconsulta
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="tarifas-electro-tab" data-bs-toggle="tab" data-bs-target="#tarifas-electro" type="button" role="tab">
                                        <i class="bi bi-heart-pulse me-1"></i>Electrocardiograma
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="tarifas-procedimientos-tab" data-bs-toggle="tab" data-bs-target="#tarifas-procedimientos" type="button" role="tab">
                                        <i class="bi bi-bandaid me-1"></i>Procedimientos
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="tarifas-rayosx-tab" data-bs-toggle="tab" data-bs-target="#tarifas-rayosx" type="button" role="tab">
                                        <i class="bi bi-file-x me-1"></i>Rayos X
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="tarifas-ultrasonido-tab" data-bs-toggle="tab" data-bs-target="#tarifas-ultrasonido" type="button" role="tab">
                                        <i class="bi bi-waveform me-1"></i>Ultrasonido
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="tarifasTabContent">
                                <div class="tab-pane fade show active" id="tarifas-consulta" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">Precios por Médico</h5>
                                        <button class="action-btn primary btn-sm" onclick="openTarifaModal('consulta')">
                                            <i class="bi bi-plus-circle"></i> Agregar Tarifa
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Médico</th>
                                                    <th>Especialidad</th>
                                                    <th>Consulta Normal (Q)</th>
                                                    <th>Consulta Inhábil (Q)</th>
                                                    <th>Reconsulta Normal (Q)</th>
                                                    <th>Reconsulta Inhábil (Q)</th>
                                                    <th class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tarifa-consulta-body">
                                                <tr><td colspan="7" class="text-center text-muted">Cargando...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-end mt-3">
                                        <button class="action-btn primary" onclick="saveTarifaSection('tarifa-consulta-body')">
                                            <i class="bi bi-save"></i> Guardar Cambios
                                        </button>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="tarifas-electro" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">Electrocardiograma</h5>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Precio Normal (Q)</th>
                                                    <th>Precio Inhábil (Q)</th>
                                                    <th class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tarifa-electro-body">
                                                <tr><td colspan="3" class="text-center text-muted">Cargando...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-end mt-3">
                                        <button class="action-btn primary" onclick="saveTarifaSection('tarifa-electro-body')">
                                            <i class="bi bi-save"></i> Guardar Cambios
                                        </button>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="tarifas-procedimientos" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">Procedimientos Menores</h5>
                                        <button class="action-btn primary btn-sm" onclick="openTarifaModal('procedimiento')">
                                            <i class="bi bi-plus-circle"></i> Agregar Procedimiento
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Procedimiento</th>
                                                    <th>Precio Normal (Q)</th>
                                                    <th>Precio Inhábil (Q)</th>
                                                    <th class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tarifa-procedimiento-body">
                                                <tr><td colspan="4" class="text-center text-muted">Cargando...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-end mt-3">
                                        <button class="action-btn primary" onclick="saveTarifaSection('tarifa-procedimiento-body')">
                                            <i class="bi bi-save"></i> Guardar Cambios
                                        </button>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="tarifas-rayosx" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">Rayos X por Región</h5>
                                        <button class="action-btn primary btn-sm" onclick="openTarifaModal('rayos_x')">
                                            <i class="bi bi-plus-circle"></i> Agregar Región
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Región</th>
                                                    <th>Precio Normal (Q)</th>
                                                    <th>Precio Inhábil (Q)</th>
                                                    <th class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tarifa-rayos_x-body">
                                                <tr><td colspan="4" class="text-center text-muted">Cargando...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-end mt-3">
                                        <button class="action-btn primary" onclick="saveTarifaSection('tarifa-rayos_x-body')">
                                            <i class="bi bi-save"></i> Guardar Cambios
                                        </button>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="tarifas-ultrasonido" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">Ultrasonido</h5>
                                        <button class="action-btn primary btn-sm" onclick="openTarifaModal('ultrasonido')">
                                            <i class="bi bi-plus-circle"></i> Agregar Tipo
                                        </button>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Tipo</th>
                                                    <th>Normal (Q)</th>
                                                    <th>Inhábil (Q)</th>
                                                    <th>Radio Normal (Q)</th>
                                                    <th>Radio Inhábil (Q)</th>
                                                    <th class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tarifa-ultrasonido-body">
                                                <tr><td colspan="6" class="text-center text-muted">Cargando...</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-end mt-3">
                                        <button class="action-btn primary" onclick="saveTarifaSection('tarifa-ultrasonido-body')">
                                            <i class="bi bi-save"></i> Guardar Cambios
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="rooms" role="tabpanel">
                        <div class="settings-content-card">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h3 class="section-title mb-0">Gestión de Habitaciones</h3>
                                <button class="action-btn primary" onclick="openRoomModal()">
                                    <i class="bi bi-plus-circle"></i> Nueva Habitación
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="data-table" id="roomsTable">
                                    <thead>
                                        <tr>
                                            <th>Número</th>
                                            <th>Tipo</th>
                                            <th>Tarifa/Noche</th>
                                            <th>Camas</th>
                                            <th>Estado</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="roomsTableBody">
                                        <?php
                                        if (!empty($rooms)) {
                                            foreach ($rooms as $room) {
                                                echo '<tr>';
                                                echo '<td><span class="fw-bold">HB-' . htmlspecialchars($room['numero_habitacion']) . '</span></td>';
                                                echo '<td>' . htmlspecialchars($room['tipo_habitacion']) . '</td>';
                                                echo '<td>Q' . number_format($room['tarifa_por_noche'], 2) . '</td>';
                                                echo '<td>' . htmlspecialchars($room['cama_count']) . ' cama(s)</td>';
                                                $statusClass = $room['estado'] === 'Activa' ? 'bg-success text-white' : 'bg-secondary text-white';
                                                echo '<td><span class="badge ' . $statusClass . '">' . htmlspecialchars($room['estado']) . '</span></td>';
                                                echo '<td class="text-center">
                                                    <button class="action-btn sm secondary" onclick="editRoom(' . htmlspecialchars(json_encode($room)) . ')"><i class="bi bi-pencil"></i></button>
                                                    <button class="action-btn sm danger" onclick="deleteRoom(' . $room['id_habitacion'] . ')"><i class="bi bi-trash"></i></button>
                                                </td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<tr><td colspan="6" class="text-center text-muted py-4">No hay habitaciones registradas</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Usuario -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--color-card); border-radius: var(--radius-xl);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="userModalTitle">Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="userForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="idUsuario" id="userId">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Nombre</label>
                                <input type="text" name="nombre" id="userName" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Apellido</label>
                                <input type="text" name="apellido" id="userLastName" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Usuario (@)</label>
                                <input type="text" name="usuario" id="userHandle" class="form-control" required>
                            </div>
                            <div class="col-12" id="passwordField">
                                <label class="form-label small fw-bold">Contraseña</label>
                                <input type="password" name="password" class="form-control">
                                <small class="text-muted">Dejar vacío para no cambiar (solo en edición)</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Rol</label>
                                <select name="tipoUsuario" id="userRole" class="form-select" required>
                                    <option value="user">Usuario Estándar</option>
                                    <option value="doc">Médico</option>
                                    <option value="admin">Administrador</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Especialidad</label>
                                <input type="text" name="especialidad" id="userSpecialty" class="form-control"
                                    placeholder="Ej: Pediatría">
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Correo Electrónico</label>
                                <input type="email" name="email" id="userEmail" class="form-control">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="action-btn" onclick="saveUser()">Guardar Usuario</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Habitación -->
    <div class="modal fade" id="roomModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--color-card); border-radius: var(--radius-xl);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="roomModalTitle">Nueva Habitación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="roomForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id_habitacion" id="roomId">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Número de Habitación</label>
                                <input type="text" name="numero_habitacion" id="roomNumber" class="form-control" required placeholder="101">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Tipo</label>
                                <select name="tipo_habitacion" id="roomType" class="form-select" required>
                                    <option value="Privada">Privada</option>
                                    <option value="Semi-Privada">Semi-Privada</option>
                                    <option value="Compartida">Compartida</option>
                                    <option value="UCI">UCI</option>
                                    <option value="Quirofano">Quirófano</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Tarifa por Noche (Q)</label>
                                <input type="number" step="0.01" name="tarifa_por_noche" id="roomRate" class="form-control" required placeholder="0.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Estado</label>
                                <select name="estado" id="roomStatus" class="form-select">
                                    <option value="Activa">Activa</option>
                                    <option value="Inactiva">Inactiva</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="action-btn" onclick="saveRoom()">Guardar Habitación</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Tarifa -->
    <div class="modal fade" id="tarifaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="background: var(--color-card); border-radius: var(--radius-xl);">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="tarifaModalTitle">Nueva Tarifa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="tarifaForm">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id_tarifa" id="tarifaId">
                        <input type="hidden" name="tipo_servicio" id="tarifaTipo">
                        <div id="tarifa-fields-consulta" class="tarifa-fields d-none">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Médico</label>
                                <select name="id_medico" id="tarifaMedico" class="form-select" required>
                                    <option value="">Seleccione médico...</option>
                                </select>
                            </div>
                        </div>
                        <div id="tarifa-fields-nombre" class="tarifa-fields d-none mb-3">
                            <label class="form-label small fw-bold">Nombre del Servicio</label>
                            <input type="text" name="nombre_servicio" id="tarifaNombre" class="form-control" placeholder="Ej: Inyeccion, Ultrasonido Abdominal">
                        </div>
                        <div id="tarifa-fields-region" class="tarifa-fields d-none mb-3">
                            <label class="form-label small fw-bold">Número de Regiones</label>
                            <input type="number" name="region_count" id="tarifaRegion" class="form-control" min="1" max="10" placeholder="1">
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Precio Normal (Q)</label>
                                <input type="number" step="0.01" name="precio_normal" id="tarifaNormal" class="form-control" required placeholder="0.00">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Precio Inhábil (Q)</label>
                                <input type="number" step="0.01" name="precio_inhabil" id="tarifaInhabil" class="form-control" required placeholder="0.00">
                            </div>
                            <div class="col-12" id="tarifa-radio-field" class="d-none">
                                <label class="form-label small fw-bold">Precio Radio Normal (Q)</label>
                                <input type="number" step="0.01" name="precio_radio" id="tarifaRadio" class="form-control" placeholder="0.00">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="action-btn" onclick="saveTarifa()">Guardar Tarifa</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const userModal = new bootstrap.Modal(document.getElementById('userModal'));

        function openUserModal() {
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            document.getElementById('userModalTitle').innerText = 'Nuevo Usuario';
            userModal.show();
        }

        function editUser(user) {
            document.getElementById('userId').value = user.idUsuario;
            document.getElementById('userName').value = user.nombre;
            document.getElementById('userLastName').value = user.apellido;
            document.getElementById('userHandle').value = user.usuario;
            document.getElementById('userRole').value = user.tipoUsuario;
            document.getElementById('userSpecialty').value = user.especialidad || '';
            document.getElementById('userEmail').value = user.email || '';
            document.getElementById('userModalTitle').innerText = 'Editar Usuario';
            userModal.show();
        }

        async function saveUser() {
            const formData = new FormData(document.getElementById('userForm'));
            try {
                const response = await fetch('api/save_user.php', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();
                if (res.success) {
                    Swal.fire('Éxito', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'Fallo en la comunicación con el servidor', 'error');
            }
        }

        async function deleteUser(id) {
            const result = await Swal.fire({
                title: '¿Está seguro?',
                text: "Esta acción no se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: 'var(--color-danger)',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                try {
                    const response = await fetch('api/delete_user.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id=' + id
                    });
                    const res = await response.json();
                    if (res.success) {
                        Swal.fire('Eliminado', res.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                } catch (error) {
                    Swal.fire('Error', 'No se pudo eliminar el usuario', 'error');
                }
            }
        }

        function updateAppTheme(color) {
            const root = document.documentElement;
            root.style.setProperty('--color-primary', color);
            // Compute RGB for shadows
            const r = parseInt(color.slice(1, 3), 16);
            const g = parseInt(color.slice(3, 5), 16);
            const b = parseInt(color.slice(5, 7), 16);
            root.style.setProperty('--color-primary-rgb', `${r},${g},${b}`);
            localStorage.setItem('custom-primary-color', color);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Color actualizado', showConfirmButton: false, timer: 1500 });
        }

        function applyFullTheme(t) {
            const root = document.documentElement;
            root.style.setProperty('--color-primary', t.primary);
            root.style.setProperty('--color-bg', t.bg);
            root.style.setProperty('--color-surface', t.surface);
            root.style.setProperty('--color-text', t.text);
            root.style.setProperty('--radius-md', t.radius);
            root.style.setProperty('--radius-lg', `calc(${t.radius} + 0.25rem)`);
            root.style.setProperty('--radius-xl', `calc(${t.radius} + 0.5rem)`);
            root.style.setProperty('--shadow-md', t.shadow);
            // Compute RGB
            const r = parseInt(t.primary.slice(1, 3), 16);
            const g = parseInt(t.primary.slice(3, 5), 16);
            const b = parseInt(t.primary.slice(5, 7), 16);
            root.style.setProperty('--color-primary-rgb', `${r},${g},${b}`);
            // Persist
            localStorage.setItem('custom-full-theme', JSON.stringify(t));
            // Mark active card
            document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('active'));
            const card = document.querySelector(`.theme-card[data-theme-id="${t.id}"]`);
            if (card) card.classList.add('active');
            const badge = document.getElementById('activeThemeBadge');
            if (badge) badge.textContent = '✓ ' + t.name;
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: `Tema "${t.name}" aplicado`, showConfirmButton: false, timer: 2000 });
        }

        // Theme toggle (light/dark)
        document.getElementById('themeSwitch').addEventListener('click', () => {
            const current = document.documentElement.getAttribute('data-theme');
            const target = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', target);
            localStorage.setItem('dashboard-theme', target);
        });

        // Restore saved full theme on load
        document.addEventListener('DOMContentLoaded', () => {
            const savedTheme = localStorage.getItem('dashboard-theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            const saved = localStorage.getItem('custom-full-theme');
            if (saved) {
                try { applyFullTheme(JSON.parse(saved)); } catch (e) { }
            } else {
                const color = localStorage.getItem('custom-primary-color');
                if (color) updateAppTheme(color);
            }
        });

        // Room Management Functions
        const roomModal = new bootstrap.Modal(document.getElementById('roomModal'));

        function openRoomModal() {
            document.getElementById('roomForm').reset();
            document.getElementById('roomId').value = '';
            document.getElementById('roomModalTitle').innerText = 'Nueva Habitación';
            roomModal.show();
        }

        function editRoom(room) {
            document.getElementById('roomId').value = room.id_habitacion;
            document.getElementById('roomNumber').value = room.numero_habitacion;
            document.getElementById('roomType').value = room.tipo_habitacion;
            document.getElementById('roomRate').value = room.tarifa_por_noche;
            document.getElementById('roomStatus').value = room.estado;
            document.getElementById('roomModalTitle').innerText = 'Editar Habitación';
            roomModal.show();
        }

        async function saveRoom() {
            const formData = new FormData(document.getElementById('roomForm'));
            try {
                const response = await fetch('api/save_room.php', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();
                if (res.success) {
                    Swal.fire('Éxito', res.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'Fallo en la comunicación con el servidor', 'error');
            }
        }

        async function deleteRoom(id) {
            const result = await Swal.fire({
                title: '¿Está seguro?',
                text: "Esta acción eliminará la habitación y todas sus camas asociadas",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: 'var(--color-danger)',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                try {
                    const response = await fetch('api/delete_room.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'id=' + id
                    });
                    const res = await response.json();
                    if (res.success) {
                        Swal.fire('Eliminado', res.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                } catch (error) {
                    Swal.fire('Error', 'No se pudo eliminar la habitación', 'error');
                }
            }
        }

        const tarifaModal = new bootstrap.Modal(document.getElementById('tarifaModal'));
        let currentMedicos = [];

        async function loadTarifas() {
            console.log('[ Tarifas] Iniciando carga...');
            try {
                const response = await fetch('api/get_tarifas.php');
                console.log('[Tarifas] Response status:', response.status);
                if (!response.ok) {
                    console.error('HTTP error:', response.status, response.statusText);
                    return;
                }
                const contentType = response.headers.get('content-type');
                console.log('[Tarifas] Content-Type:', contentType);
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text.slice(0, 200));
                    return;
                }
                const res = await response.json();
                console.log('[Tarifas] Response:', JSON.stringify(res).slice(0, 200));
                if (!res.success || !res.tarifas) {
                    console.error('API error:', res.message || 'Unknown error');
                    return;
                }
                const t = res.tarifas;
                currentMedicos = t.medicos || [];
                console.log('[Tarifas] Medicos cargados:', currentMedicos.length);
                console.log('[Tarifas] Tarifas:', { consulta: t.consulta.length, reconsulta: t.reconsulta.length, electro: !!t.electrocardiograma, proc: t.procedimiento.length, rx: t.rayos_x.length, us: t.ultrasonido.length });

                renderConsultaReconsulta(t.consulta || [], t.reconsulta || []);
                renderElectro(t.electrocardiograma);
                renderProcedimientos(t.procedimiento || []);
                renderRayosX(t.rayos_x || []);
                renderUltrasonido(t.ultrasonido || []);
                console.log('[Tarifas] Renderizado completo');
            } catch (error) {
                console.error('Error loading tarifas:', error);
            }
        }

        function getMedicoName(idMedico) {
            const id = Number(idMedico);
            const m = currentMedicos.find(x => Number(x.idUsuario) === id);
            return m ? `${m.nombre} ${m.apellido}` : `Médico #${idMedico}`;
        }

        function getMedicoEspecialidad(idMedico) {
            const id = Number(idMedico);
            const m = currentMedicos.find(x => Number(x.idUsuario) === id);
            return m ? (m.especialidad || '-') : '-';
        }

        function renderConsultaReconsulta(consultas, reconsultas) {
            const body = document.getElementById('tarifa-consulta-body');
            if (consultas.length === 0 && reconsultas.length === 0) {
                body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4"><em>No hay tarifas de consulta configuradas. Haga clic en "Agregar Tarifa" para crear una.</em></td></tr>';
                return;
            }
            const map = {};
            consultas.forEach(c => { map[c.id_medico] = map[c.id_medico] || {}; map[c.id_medico].consulta = c; });
            reconsultas.forEach(r => { map[r.id_medico] = map[r.id_medico] || {}; map[r.id_medico].reconsulta = r; });

            let html = '';
            Object.keys(map).forEach(medId => {
                const c = map[medId].consulta;
                const r = map[medId].reconsulta;
                const consultaNormal = c ? c.precio_normal : '';
                const consultaInhabil = c ? c.precio_inhabil : '';
                const reconsNormal = r ? r.precio_normal : '';
                const reconsInhabil = r ? r.precio_inhabil : '';
                const idTarifa = c ? c.id_tarifa : (r ? r.id_tarifa : '');

                html += `<tr>
                    <td>${getMedicoName(parseInt(medId))}</td>
                    <td>${getMedicoEspecialidad(parseInt(medId))}</td>
                    <td>
                        <input type="number" step="0.01" class="form-control form-control-sm tarifa-input" value="${consultaNormal}"
                            data-medico="${medId}" data-tipo="consulta" data-field="precio_normal"
                            placeholder="0.00">
                    </td>
                    <td>
                        <input type="number" step="0.01" class="form-control form-control-sm tarifa-input" value="${consultaInhabil}"
                            data-medico="${medId}" data-tipo="consulta" data-field="precio_inhabil"
                            placeholder="0.00">
                    </td>
                    <td>
                        <input type="number" step="0.01" class="form-control form-control-sm tarifa-input" value="${reconsNormal}"
                            data-medico="${medId}" data-tipo="reconsulta" data-field="precio_normal"
                            placeholder="0.00">
                    </td>
                    <td>
                        <input type="number" step="0.01" class="form-control form-control-sm tarifa-input" value="${reconsInhabil}"
                            data-medico="${medId}" data-tipo="reconsulta" data-field="precio_inhabil"
                            placeholder="0.00">
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteTarifa(${idTarifa || 0}, 'consulta')" title="Eliminar">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>`;
            });
            body.innerHTML = html;
        }

        function renderElectro(electro) {
            const body = document.getElementById('tarifa-electro-body');
            if (!electro || !electro.id_tarifa) {
                body.innerHTML = `<tr>
                    <td><input type="number" step="0.01" class="form-control form-control-sm tarifa-input" id="electro-normal" value="" placeholder="0.00" data-tipo="electrocardiograma" data-field="precio_normal"></td>
                    <td><input type="number" step="0.01" class="form-control form-control-sm tarifa-input" id="electro-inhabil" value="" placeholder="0.00" data-tipo="electrocardiograma" data-field="precio_inhabil"></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-primary" onclick="saveElectroTarifa()"><i class="bi bi-check"></i></button>
                    </td>
                </tr>`;
            } else {
                body.innerHTML = `<tr>
                    <td><input type="number" step="0.01" class="form-control form-control-sm tarifa-input" id="electro-normal" value="${electro.precio_normal}" placeholder="0.00" data-tipo="electrocardiograma" data-field="precio_normal"></td>
                    <td><input type="number" step="0.01" class="form-control form-control-sm tarifa-input" id="electro-inhabil" value="${electro.precio_inhabil}" placeholder="0.00" data-tipo="electrocardiograma" data-field="precio_inhabil"></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-primary" onclick="saveElectroTarifa()"><i class="bi bi-check"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteTarifa(${electro.id_tarifa}, 'electrocardiograma')"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`;
            }
        }

        function renderProcedimientos(procedimientos) {
            const body = document.getElementById('tarifa-procedimiento-body');
            if (procedimientos.length === 0) {
                body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4"><em>No hay procedimientos configurados. Haga clic en "Agregar Procedimiento" para crear uno.</em></td></tr>';
                return;
            }
            let html = '';
            procedimientos.forEach(p => {
                html += `<tr>
                    <td>${p.nombre_servicio || '-'}</td>
                    <td><input type="number" step="0.01" class="form-control form-control-sm tarifa-input" value="${p.precio_normal}"
                        data-tipo="procedimiento" data-field="precio_normal" data-id="${p.id_tarifa}" placeholder="0.00"></td>
                    <td><input type="number" step="0.01" class="form-control form-control-sm tarifa-input" value="${p.precio_inhabil}"
                        data-tipo="procedimiento" data-field="precio_inhabil" data-id="${p.id_tarifa}" placeholder="0.00"></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteTarifa(${p.id_tarifa}, 'procedimiento')"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`;
            });
            body.innerHTML = html;
        }

        function renderRayosX(rayos_x) {
            const body = document.getElementById('tarifa-rayos_x-body');
            if (rayos_x.length === 0) {
                body.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4"><em>No hay regiones configuradas. Haga clic en "Agregar Región" para crear una.</em></td></tr>';
                return;
            }
            let html = '';
            rayos_x.forEach(r => {
                html += `<tr>
                    <td>${r.region_count} región${r.region_count > 1 ? 'es' : ''}</td>
                    <td><input type="number" step="0.01" class="form-control form-control-sm tarifa-input" value="${r.precio_normal}"
                        data-tipo="rayos_x" data-field="precio_normal" data-id="${r.id_tarifa}" placeholder="0.00"></td>
                    <td><input type="number" step="0.01" class="form-control form-control-sm tarifa-input" value="${r.precio_inhabil}"
                        data-tipo="rayos_x" data-field="precio_inhabil" data-id="${r.id_tarifa}" placeholder="0.00"></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteTarifa(${r.id_tarifa}, 'rayos_x')"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`;
            });
            body.innerHTML = html;
        }

        function renderUltrasonido(ultrasonidos) {
            const body = document.getElementById('tarifa-ultrasonido-body');
            if (ultrasonidos.length === 0) {
                body.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><em>No hay tipos de ultrasonido configurados. Haga clic en "Agregar Tipo" para crear uno.</em></td></tr>';
                return;
            }
            let html = '';
            ultrasonidos.forEach(u => {
                html += `<tr>
                    <td>${u.nombre_servicio || '-'}</td>
                    <td><input type="number" step="0.01" class="form-control form-control-sm tarifa-input" value="${u.precio_normal}"
                        data-tipo="ultrasonido" data-field="precio_normal" data-id="${u.id_tarifa}" placeholder="0.00"></td>
                    <td><input type="number" step="0.01" class="form-control form-control-sm tarifa-input" value="${u.precio_inhabil}"
                        data-tipo="ultrasonido" data-field="precio_inhabil" data-id="${u.id_tarifa}" placeholder="0.00"></td>
                    <td><input type="number" step="0.01" class="form-control form-control-sm tarifa-input" value="${u.precio_radio || 0}"
                        data-tipo="ultrasonido" data-field="precio_radio" data-id="${u.id_tarifa}" placeholder="0.00"></td>
                    <td>${u.precio_inhabil || '0.00'}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteTarifa(${u.id_tarifa}, 'ultrasonido')"><i class="bi bi-trash"></i></button>
                    </td>
                </tr>`;
            });
            body.innerHTML = html;
        }

        function openTarifaModal(tipo) {
            document.getElementById('tarifaForm').reset();
            document.getElementById('tarifaId').value = '';
            document.getElementById('tarifaTipo').value = tipo;
            document.getElementById('tarifaModalTitle').innerText = 'Nueva Tarifa';

            document.querySelectorAll('.tarifa-fields').forEach(el => el.classList.add('d-none'));
            document.getElementById('tarifa-radio-field').classList.add('d-none');

            if (tipo === 'consulta' || tipo === 'reconsulta') {
                document.getElementById('tarifa-fields-consulta').classList.remove('d-none');
                const select = document.getElementById('tarifaMedico');
                select.innerHTML = '<option value="">Seleccione médico...</option>';
                currentMedicos.forEach(m => {
                    select.innerHTML += `<option value="${m.idUsuario}">${m.nombre} ${m.apellido}</option>`;
                });
            } else if (tipo === 'procedimiento' || tipo === 'ultrasonido') {
                document.getElementById('tarifa-fields-nombre').classList.remove('d-none');
                if (tipo === 'ultrasonido') {
                    document.getElementById('tarifa-radio-field').classList.remove('d-none');
                }
            } else if (tipo === 'rayos_x') {
                document.getElementById('tarifa-fields-region').classList.remove('d-none');
            }

            tarifaModal.show();
        }

        async function saveTarifa() {
            const id_tarifa = document.getElementById('tarifaId').value;
            const tipo = document.getElementById('tarifaTipo').value;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            let payload = {
                action: id_tarifa ? 'update' : 'create',
                tipo_servicio: tipo,
                precio_normal: parseFloat(document.getElementById('tarifaNormal').value) || 0,
                precio_inhabil: parseFloat(document.getElementById('tarifaInhabil').value) || 0,
                csrf_token: csrfToken
            };

            if (id_tarifa) {
                payload.id_tarifa = parseInt(id_tarifa);
            }

            if (tipo === 'consulta' || tipo === 'reconsulta') {
                payload.id_medico = parseInt(document.getElementById('tarifaMedico').value) || 0;
            } else if (tipo === 'procedimiento' || tipo === 'ultrasonido') {
                payload.nombre_servicio = document.getElementById('tarifaNombre').value;
                if (tipo === 'ultrasonido') {
                    payload.precio_radio = parseFloat(document.getElementById('tarifaRadio').value) || 0;
                }
            } else if (tipo === 'rayos_x') {
                payload.region_count = parseInt(document.getElementById('tarifaRegion').value) || 1;
            }

            try {
                const response = await fetch('api/save_tarifas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify(payload)
                });
                const res = await response.json();
                if (res.success) {
                    Swal.fire('Éxito', res.message, 'success').then(() => {
                        tarifaModal.hide();
                        loadTarifas();
                    });
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'Fallo en la comunicación con el servidor', 'error');
            }
        }

        async function saveTarifaSection(sectionId) {
            const container = document.getElementById(sectionId);
            if (!container) return;
            const inputs = container.querySelectorAll('.tarifa-input');
            if (!inputs.length) return;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const items = [];

            inputs.forEach(input => {
                const tipo = input.dataset.tipo;
                const field = input.dataset.field;
                const value = parseFloat(input.value) || 0;
                const medico = parseInt(input.dataset.medico) || null;
                const id = parseInt(input.dataset.id) || null;

                if (!tipo || !field) return;
                if (id) return;

                let item = items.find(i => i.tipo_servicio === tipo && (medico ? i.id_medico === medico : true));
                if (!item) {
                    item = {
                        tipo_servicio: tipo,
                        precio_normal: 0,
                        precio_inhabil: 0,
                        precio_radio: 0
                    };
                    if (medico) item.id_medico = medico;
                    items.push(item);
                }
                item[field] = value;
            });

            inputs.forEach(input => {
                const tipo = input.dataset.tipo;
                const field = input.dataset.field;
                const value = parseFloat(input.value) || 0;
                const id = parseInt(input.dataset.id) || null;
                if (!tipo || !field || !id) return;
                let item = items.find(i => i.id_tarifa === id);
                if (!item) {
                    item = { id_tarifa: id, tipo_servicio: tipo, precio_normal: 0, precio_inhabil: 0, precio_radio: 0 };
                    items.push(item);
                }
                item[field] = value;
            });

            if (!items.length) {
                Swal.fire('Aviso', 'No hay datos para guardar', 'info');
                return;
            }

            try {
                const response = await fetch('api/save_tarifas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({
                        action: 'batch_save',
                        tarifas: items,
                        csrf_token: csrfToken
                    })
                });
                const res = await response.json();
                if (res.success) {
                    Swal.fire('Éxito', 'Tarifas guardadas correctamente', 'success');
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'Fallo al guardar tarifas', 'error');
            }
        }

        async function deleteTarifa(id_tarifa, tipo) {
            if (!id_tarifa || id_tarifa === 0) return;
            const result = await Swal.fire({
                title: '¿Está seguro?',
                text: 'Esta acción eliminará la tarifa seleccionada',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: 'var(--color-danger)',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });

            if (result.isConfirmed) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
                try {
                    const response = await fetch('api/save_tarifas.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                        body: JSON.stringify({ action: 'delete', id_tarifa: id_tarifa, csrf_token: csrfToken })
                    });
                    const res = await response.json();
                    if (res.success) {
                        Swal.fire('Eliminado', res.message, 'success').then(() => loadTarifas());
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                } catch (error) {
                    Swal.fire('Error', 'Fallo al eliminar tarifa', 'error');
                }
            }
        }

        async function saveElectroTarifa() {
            const normal = parseFloat(document.getElementById('electro-normal').value) || 0;
            const inutil = parseFloat(document.getElementById('electro-inhabil').value) || 0;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const existingId = document.querySelector('#tarifa-electro-body tr')?.querySelector('.btn-outline-danger')?.onclick?.toString()?.match(/\d+/)?.[0];

            try {
                const response = await fetch('api/save_tarifas.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({
                        action: 'create',
                        tipo_servicio: 'electrocardiograma',
                        precio_normal: normal,
                        precio_inhabil: inutil,
                        csrf_token: csrfToken
                    })
                });
                const res = await response.json();
                if (res.success) {
                    Swal.fire('Éxito', 'Tarifa actualizada', 'success').then(() => loadTarifas());
                } else {
                    Swal.fire('Error', res.message, 'error');
                }
            } catch (error) {
                Swal.fire('Error', 'Fallo al guardar tarifa', 'error');
            }
        }

        document.addEventListener('DOMContentLoaded', loadTarifas);
    </script>
    <?php output_keep_alive_script(); ?>
</body>

</html>