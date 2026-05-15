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

    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">

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
                    <p class="text-muted small mb-4">Seleccione el tema o personalice los colores para adaptar la identidad visual del sistema. (Se guarda localmente en su navegador).</p>

                    <div class="row g-3 mb-4">
                        <?php
                        $themes = [
                            'classic' => ['label' => 'Classic Medical', 'colors' => ['#7c90db', '#f8fafc', '#1e293b']],
                            'midnight' => ['label' => 'Midnight Pro', 'colors' => ['#3b82f6', '#0f172a', '#f8fafc']],
                            'emerald' => ['label' => 'Emerald Health', 'colors' => ['#10b981', '#f0fdf4', '#064e3b']],
                            'purple' => ['label' => 'Royal Purple', 'colors' => ['#8b5cf6', '#f5f3ff', '#2e1065']],
                            'sunset' => ['label' => 'Sunset Clinical', 'colors' => ['#f43f5e', '#fff1f2', '#4c0519']],
                        ];
                        foreach ($themes as $id => $t):
                            // El activo real vendrá del localStorage ahora, pero dejamos el visual inicial
                        ?>
                        <div class="col-md-4">
                            <div class="theme-card" data-theme-id="<?php echo $id; ?>" data-color="<?php echo $t['colors'][0]; ?>" onclick="applyPresetTheme('<?php echo $id; ?>', '<?php echo $t['colors'][0]; ?>', this)">
                                <div class="theme-preview" style="background: <?php echo $t['colors'][1]; ?>; border: 1px solid #ddd;">
                                    <div class="theme-dot" style="background: <?php echo $t['colors'][0]; ?>"></div>
                                    <div class="theme-dot" style="background: <?php echo $t['colors'][2]; ?>"></div>
                                </div>
                                <div class="fw-bold small"><?php echo $t['label']; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="card-title fw-bold">Personalización Avanzada</h5>
                            <hr>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Color Principal Personalizado</label>
                                    <input type="color" class="form-control form-control-color" id="customColorPicker" value="#0d6efd" title="Elige tu color" onchange="updateCustomColor(this.value)">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Modo Visual</label>
                                    <select class="form-select" id="themeModeSelector" onchange="updateThemeMode(this.value)">
                                        <option value="light">Día (Claro)</option>
                                        <option value="dark">Noche (Oscuro)</option>
                                    </select>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
                                <button type="button" class="btn btn-outline-danger" onclick="resetStyles()">Restaurar Valores por Defecto</button>
                            </div>
                        </div>
                    </div>
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

        // Theme management UI logic
        function applyPresetTheme(id, color, el) {
            document.querySelectorAll('.theme-card').forEach(c => c.classList.remove('active'));
            el.classList.add('active');
            updateCustomColor(color);
            document.getElementById('customColorPicker').value = color;
        }

        function updateCustomColor(color) {
            localStorage.setItem('custom-primary-color', color);
            // Trigger storage event manually for current window
            window.dispatchEvent(new Event('storage'));
            document.documentElement.style.setProperty('--color-primary-day', color);
            document.documentElement.style.setProperty('--color-primary-night', color);
            document.documentElement.style.setProperty('--color-primary', color);
            // Si el color es claro, quiza el texto deba ser oscuro. Simplificamos asumiendo que es un color primario.
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Color actualizado',
                showConfirmButton: false,
                timer: 1500
            });
        }

        function updateThemeMode(mode) {
            document.documentElement.setAttribute('data-theme', mode);
            localStorage.setItem('dashboard-theme', mode);
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Modo visual actualizado',
                showConfirmButton: false,
                timer: 1500
            });
        }

        function resetStyles() {
            localStorage.removeItem('custom-primary-color');
            localStorage.removeItem('dashboard-theme');
            location.reload();
        }

        // Initialize UI from localStorage
        window.addEventListener('DOMContentLoaded', () => {
            const savedColor = localStorage.getItem('custom-primary-color');
            if (savedColor) {
                document.getElementById('customColorPicker').value = savedColor;
            }
            const savedTheme = localStorage.getItem('dashboard-theme') || 'light';
            document.getElementById('themeModeSelector').value = savedTheme;
        });
    </script>
    </body>

</html>