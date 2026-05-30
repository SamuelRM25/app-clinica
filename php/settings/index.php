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
    </script>
    <?php output_keep_alive_script(); ?>
</body>

</html>