<?php
// settings/index.php - Configuración del Sistema - Centro Médico RS
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';
require_once '../../includes/module_guard.php';

check_module_access('core');



verify_session();

// Solo administradores pueden acceder a configuraciones
if ($_SESSION['tipoUsuario'] !== 'admin') {
    header("Location: ../dashboard/index.php");
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Obtener información actual de la clínica (ejemplo de tabla de config)
    // Si no existe la tabla, se podría crear o usar valores por defecto
    $stmt = $conn->query("SELECT * FROM configuracion_sistema LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'nombre_clinica' => 'Centro Médico RS',
        'direccion' => 'Ciudad de Guatemala',
        'telefono' => '5214-8836',
        'email' => 'info@herrerasaenz.com',
        'logo_path' => '../../assets/img/Logo.png'
    ];

    $page_title = "Configuración del Sistema";
} catch (Exception $e) {
    $config = [
        'nombre_clinica' => 'Centro Médico RS',
        'direccion' => 'Ciudad de Guatemala',
        'telefono' => '5214-8836',
        'email' => 'info@herrerasaenz.com',
        'logo_path' => '../../assets/img/Logo.png'
    ];
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <?php apply_hospital_theme(); ?>

    <style>
        :root {
            --color-primary: #7c90db;
            --color-primary-dark: #5a6ebf;
            --color-bg: #f8fafc;
            --color-surface: #ffffff;
            --color-text: #1e293b;
            --color-text-muted: #64748b;
            --color-border: #e2e8f0;
            --radius-lg: 1rem;
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] {
            --color-bg: #0f172a;
            --color-surface: #1e293b;
            --color-text: #f8fafc;
            --color-text-muted: #94a3b8;
            --color-border: #334155;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--color-bg);
            color: var(--color-text);
            margin: 0;
            display: flex;
        }

        .settings-container {
            flex: 1;
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .settings-nav {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            padding: 1rem;
            border-radius: var(--radius-lg);
            height: fit-content;
            position: sticky;
            top: 2rem;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--color-text-muted);
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            margin-bottom: 0.25rem;
        }

        .nav-item.active {
            background: var(--color-primary);
            color: white;
        }

        .nav-item:hover:not(.active) {
            background: var(--color-bg);
            color: var(--color-text);
        }

        .settings-content {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            padding: 2.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
        }

        .theme-card {
            border: 2px solid var(--color-border);
            border-radius: 0.75rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .theme-card:hover {
            border-color: var(--color-primary-light);
            transform: translateY(-2px);
        }

        .theme-card.active {
            border-color: var(--color-primary);
            background: rgba(124, 144, 219, 0.05);
        }

        .theme-preview {
            height: 60px;
            border-radius: 0.4rem;
            margin-bottom: 0.75rem;
            display: flex;
            gap: 4px;
            padding: 8px;
        }

        .theme-dot { width: 15px; height: 15px; border-radius: 50%; }

        .form-section {
            margin-bottom: 2.5rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--color-primary-dark);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--color-text-muted);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--color-border);
            border-radius: 0.5rem;
            background: var(--color-bg);
            color: var(--color-text);
            font-family: inherit;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-primary);
        }

        .action-btn {
            background: var(--color-primary);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn:hover {
            background: var(--color-primary-dark);
        }

        .profile-banner {
            height: 120px;
            background: linear-gradient(135deg, #7c90db, #5a6ebf);
            border-radius: 0.75rem;
            margin-bottom: 3rem;
            position: relative;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            position: absolute;
            bottom: -50px;
            left: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            box-shadow: var(--shadow-md);
            border: 4px solid var(--color-surface);
        }

        .animate-in {
            animation: fadeInUp 0.5s ease forwards;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    <div class="settings-container">
        <header class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 style="margin: 0; font-size: 1.875rem;">Configuración</h1>
                <p style="color: var(--color-text-muted); margin: 0.25rem 0 0 0;">Gestione los parámetros generales del
                    sistema</p>
            </div>
            <button id="themeSwitch" class="action-btn"
                style="background: transparent; color: var(--color-text); border: 1px solid var(--color-border); padding: 0.5rem 1rem;">
                <i class="bi bi-sun"></i>
            </button>
        </header>

        <div class="settings-grid">
            <aside class="settings-nav">
                <a href="#general" class="nav-item active">
                    <i class="bi bi-building"></i>
                    General
                </a>
                <a href="#users" class="nav-item">
                    <i class="bi bi-people"></i>
                    Usuarios
                </a>
                <a href="#security" class="nav-item">
                    <i class="bi bi-shield-lock"></i>
                    Seguridad
                </a>
                <a href="#backup" class="nav-item">
                    <i class="bi bi-database"></i>
                    Respaldo
                </a>
                <a href="#appearance" class="nav-item">
                    <i class="bi bi-palette"></i>
                    Apariencia
                </a>
            </aside>

            <main class="settings-content animate-in">
                <div id="general-section">
                    <div class="profile-banner">
                        <div class="profile-avatar">
                            <i class="bi bi-image text-muted"></i>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Información de la Clínica</h3>
                        <form action="save_settings.php" method="POST">
                            <div class="row" style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                                <div class="form-group" style="flex: 1; min-width: 300px;">
                                    <label class="form-label">Nombre de la Institución</label>
                                    <input type="text" name="nombre" class="form-control"
                                        value="<?php echo htmlspecialchars($config['nombre_clinica']); ?>">
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 300px;">
                                    <label class="form-label">Correo Electrónico</label>
                                    <input type="email" name="email" class="form-control"
                                        value="<?php echo htmlspecialchars($config['email']); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Dirección Física</label>
                                <input type="text" name="direccion" class="form-control"
                                    value="<?php echo htmlspecialchars($config['direccion']); ?>">
                            </div>

                            <div class="row" style="display: flex; gap: 1.5rem; flex-wrap: wrap;">
                                <div class="form-group" style="flex: 1; min-width: 200px;">
                                    <label class="form-label">Teléfono</label>
                                    <input type="text" name="telefono" class="form-control"
                                        value="<?php echo htmlspecialchars($config['telefono']); ?>">
                                </div>
                                <div class="form-group" style="flex: 1; min-width: 200px;">
                                    <label class="form-label">Moneda del Sistema</label>
                                    <select class="form-control">
                                        <option value="GTQ">Quetzal (Q)</option>
                                        <option value="USD">Dólar ($)</option>
                                    </select>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: flex-end; margin-top: 2rem;">
                                <button type="submit" class="action-btn">
                                    <i class="bi bi-save"></i>
                                    Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="appearance-section" style="display: none;">
                    <h3 class="section-title"><i class="bi bi-palette me-2"></i>Personalización Visual</h3>
                    <p class="text-muted small mb-4">Seleccione el tema que mejor se adapte a la identidad de su hospital.</p>

                    <form action="save_settings.php" method="POST">
                        <input type="hidden" name="action" value="save_theme">
                        <input type="hidden" name="tema" id="selectedTheme" value="<?php echo $_SESSION['hospital_tema']; ?>">

                        <div class="row g-3">
                            <?php
                            $themes = [
                                'classic' => ['label' => 'Classic Medical', 'colors' => ['#7c90db', '#f8fafc', '#1e293b']],
                                'midnight' => ['label' => 'Midnight Pro', 'colors' => ['#3b82f6', '#0f172a', '#f8fafc']],
                                'emerald' => ['label' => 'Emerald Health', 'colors' => ['#10b981', '#f0fdf4', '#064e3b']],
                                'purple' => ['label' => 'Royal Purple', 'colors' => ['#8b5cf6', '#f5f3ff', '#2e1065']],
                                'sunset' => ['label' => 'Sunset Clinical', 'colors' => ['#f43f5e', '#fff1f2', '#4c0519']],
                            ];
                            foreach ($themes as $id => $t):
                                $isActive = ($_SESSION['hospital_tema'] == $id);
                            ?>
                            <div class="col-md-4">
                                <div class="theme-card <?php echo $isActive ? 'active' : ''; ?>" onclick="selectTheme('<?php echo $id; ?>', this)">
                                    <div class="theme-preview" style="background: <?php echo $t['colors'][1]; ?>; border: 1px solid #ddd;">
                                        <div class="theme-dot" style="background: <?php echo $t['colors'][0]; ?>"></div>
                                        <div class="theme-dot" style="background: <?php echo $t['colors'][2]; ?>"></div>
                                    </div>
                                    <div class="fw-bold small"><?php echo $t['label']; ?></div>
                                    <?php if ($isActive): ?>
                                        <span class="badge bg-primary position-absolute top-0 end-0 m-2" style="font-size: 0.6rem;">Activo</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div style="display: flex; justify-content: flex-end; margin-top: 3rem;">
                            <button type="submit" class="action-btn">
                                <i class="bi bi-check-lg"></i>
                                Aplicar Tema
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.getElementById('themeSwitch')?.addEventListener('click', () => {
            const html = document.documentElement;
            const current = html.getAttribute('data-theme');
            const target = current === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', target);
            localStorage.setItem('dashboard-theme', target);
        });

        // Toggle aside active state
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function (e) {
                const target = this.getAttribute('href').substring(1);
                document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                // Show/hide sections
                document.getElementById('general-section').style.display = (target === 'general') ? 'block' : 'none';
                document.getElementById('appearance-section').style.display = (target === 'appearance') ? 'block' : 'none';
            });
        });

        function selectTheme(id, el) {
            document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('active'));
            el.classList.add('active');
            document.getElementById('selectedTheme').value = id;
        }
    </script>
    </body>

</html>