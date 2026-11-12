<?php
// index.php - Sistema de Gestión Médica
require_once __DIR__ . '/config/hospital.php';
require_once __DIR__ . '/includes/functions.php';
start_app_session();

error_log("INDEX DEBUG: session_id = " . session_id() . ", user_id = " . ($_SESSION['user_id'] ?? 'not set'));

// Verificar si el usuario ya está autenticado
if (isset($_SESSION['user_id'])) {
    // Si llegamos con ?loop=1, dashboard no encontró la sesión por cookies viejas
    // Rompemos el loop cerrando la sesión y mostrando el login
    if (isset($_GET['loop']) && $_GET['loop'] === '1') {
        error_log("INDEX DEBUG: Redirect loop detected (loop=1). Destroying session.");
        session_unset();
        session_destroy();
        // Fall through to show login page
    } else {
        error_log("INDEX DEBUG: Already authenticated. Redirecting to php/dashboard/index.php");
        header("Location: php/dashboard/index.php");
        exit;
    }
}

// Configuración inicial
$page_title = "Iniciar sesión · " . FARMA_NOMBRE;
date_default_timezone_set('America/Guatemala');

// Mensaje de error (si viene de login.php o de una sesión cerrada)
$error_msg = null;
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case '2':                $error_msg = 'Demasiados intentos. Espere 60 segundos e intente de nuevo.'; break;
        case 'usuario_inactivo': $error_msg = 'Usuario desactivado. Contacte al administrador.'; break;
        case 'sesion_expirada':
        case 'session_expired':  $error_msg = 'Su sesión expiró. Ingrese de nuevo para continuar.'; break;
        case 'servidor':         $error_msg = 'No se pudo completar el ingreso por un problema del servidor. Intente de nuevo en unos minutos.'; break;
        case 'session_invalid':  $error_msg = 'Su sesión no es válida. Ingrese de nuevo.'; break;
        default:                 $error_msg = 'Usuario o contraseña incorrectos.';
    }
} elseif (isset($_GET['err']) && $_GET['err'] === 'security') {
    $error_msg = 'Por seguridad se cerró la sesión. Ingrese de nuevo.';
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <meta name="theme-color" content="#0b2b26">
    <meta name="description" content="<?php echo htmlspecialchars(FARMA_NOMBRE . ' — punto de venta, inventario, compras y reportes para farmacias.'); ?>">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <link rel="icon" type="image/svg+xml" href="assets/img/farmacia/mark.svg">
    <link rel="apple-touch-icon" href="assets/img/farmacia/apple-touch-icon.png">
    <link rel="manifest" href="manifest.json">

    <!-- Aplicar modo claro/oscuro antes de pintar (misma preferencia que el resto del sistema) -->
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('dashboard-theme') || localStorage.getItem('theme'); } catch (e) {}
            if (t !== 'dark') t = 'light';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        /* ── ClinicApp Farmacia ─────────────────────────────────── */
        :root {
            --pine-950: #061612;
            --pine-900: #0b2b26;
            --green-600: #0e8a6a;
            --green-700: #0b6e57;
            --green-400: #2ed3a0;
            --coral: #f0674c;

            --bg: #f4f7f5;
            --card: #ffffff;
            --text: #10231e;
            --muted: #5c6f68;
            --border: #dfe8e3;
            --input-bg: #ffffff;
            --primary: var(--green-600);
            --primary-hover: var(--green-700);
            --on-primary: #ffffff;
            --ring: rgba(14, 138, 106, .16);
            --danger: #b42318;
            --danger-bg: #fef3f2;
            --danger-border: #fecdca;
            --font: 'Plus Jakarta Sans', 'Inter', -apple-system, 'Segoe UI', sans-serif;
        }

        [data-theme="dark"] {
            --bg: #08130f;
            --card: #0f1d19;
            --text: #e4efea;
            --muted: #92a8a0;
            --border: #1e332d;
            --input-bg: #0b1814;
            --primary: #2ec495;
            --primary-hover: #22a37b;
            --on-primary: #04140f;
            --ring: rgba(46, 196, 149, .22);
            --danger: #fda29b;
            --danger-bg: rgba(180, 35, 24, .14);
            --danger-border: rgba(253, 162, 155, .25);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            font-feature-settings: "tnum" 1;
        }

        .auth { min-height: 100vh; min-height: 100dvh; display: grid; grid-template-columns: minmax(0, 1.08fr) minmax(0, 1fr); }

        /* Panel de marca */
        .brand-panel {
            position: relative; overflow: hidden; color: #e3f2ec;
            background: var(--pine-900);
            background-image:
                radial-gradient(70% 60% at 100% 0%, rgba(46, 211, 160, .32), transparent 60%),
                radial-gradient(60% 50% at 0% 100%, rgba(240, 103, 76, .18), transparent 60%);
            padding: 44px 52px; display: flex; flex-direction: column;
        }
        .brand-panel::before {
            content: ''; position: absolute; inset: 0; opacity: .06; pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='56' viewBox='0 0 56 56'%3E%3Cpath fill='%23fff' d='M24 18h8v6h6v8h-6v6h-8v-6h-6v-8h6z'/%3E%3C/svg%3E");
        }
        .brand-top { display: flex; align-items: center; gap: 12px; position: relative; }
        .brand-top img { width: 44px; height: 44px; }
        .brand-top b { display: block; font-size: 1.25rem; font-weight: 800; color: #fff; letter-spacing: -.01em; line-height: 1.05; }
        .brand-top em { font-style: normal; font-size: .72rem; font-weight: 700; letter-spacing: .24em; text-transform: uppercase; color: var(--green-400); }

        .brand-copy { margin: auto 0; position: relative; max-width: 520px; padding: 40px 0; }
        .brand-copy h2 { font-size: clamp(2rem, 3.4vw, 3rem); font-weight: 800; letter-spacing: -.035em; line-height: 1.06; color: #fff; }
        .brand-copy h2 span { color: var(--green-400); }
        .brand-copy p { margin-top: 16px; font-size: 1.02rem; line-height: 1.6; color: #b8d3c9; max-width: 44ch; }
        .chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 26px; }
        .chips span {
            display: inline-flex; align-items: center; gap: 7px; padding: 8px 13px; border-radius: 999px;
            background: rgba(255, 255, 255, .07); border: 1px solid rgba(255, 255, 255, .1);
            font-size: .82rem; font-weight: 600; color: #e3f2ec;
        }
        .chips i { color: var(--green-400); }

        /* Comprobante decorativo */
        .ticket {
            position: absolute; right: 44px; bottom: 92px; width: 230px; transform: rotate(-6deg);
            background: #fff; color: #10231e; border-radius: 14px; padding: 16px 16px 20px;
            box-shadow: 0 30px 60px -20px rgba(0, 0, 0, .55); font-size: .74rem;
            -webkit-mask: radial-gradient(circle 6px at 50% 100%, transparent 98%, #000) 0 0 / 16px 100% repeat-x;
            mask: radial-gradient(circle 6px at 50% 100%, transparent 98%, #000) 0 0 / 16px 100% repeat-x;
        }
        .ticket h4 { font-size: .78rem; font-weight: 800; display: flex; justify-content: space-between; margin-bottom: 10px; }
        .ticket h4 small { color: #5c6f68; font-weight: 600; }
        .ticket li { list-style: none; display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px dashed #dfe8e3; color: #33463f; }
        .ticket .tot { display: flex; justify-content: space-between; margin-top: 10px; font-weight: 800; font-size: .95rem; }
        .ticket .tot span:last-child { color: var(--green-600); }

        .brand-foot { position: relative; font-size: .8rem; color: #8fb3a7; display: flex; align-items: center; gap: 8px; }

        /* Formulario */
        .form-panel { position: relative; display: flex; align-items: center; justify-content: center; padding: 40px 24px; }
        .theme-btn {
            position: absolute; top: 22px; right: 22px; width: 42px; height: 42px; border-radius: 12px;
            border: 1px solid var(--border); background: var(--card); color: var(--text); cursor: pointer; font-size: 1.05rem;
            display: grid; place-items: center;
        }
        .theme-btn .bi-sun { display: none; }
        [data-theme="dark"] .theme-btn .bi-sun { display: inline-block; }
        [data-theme="dark"] .theme-btn .bi-moon-stars { display: none; }
        .theme-btn:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }

        .form-wrap { width: 100%; max-width: 400px; }
        .mobile-brand { display: none; align-items: center; gap: 10px; margin-bottom: 28px; }
        .mobile-brand img { width: 40px; height: 40px; }
        .mobile-brand b { display: block; font-size: 1.15rem; font-weight: 800; line-height: 1.05; }
        .mobile-brand em { font-style: normal; font-size: .68rem; font-weight: 700; letter-spacing: .22em; text-transform: uppercase; color: var(--primary); }

        .form-head h1 { font-size: 1.85rem; font-weight: 800; letter-spacing: -.03em; }
        .form-head p { margin-top: 6px; color: var(--muted); font-size: .95rem; }

        .alert {
            display: flex; gap: 10px; align-items: flex-start; margin-top: 22px; padding: 12px 14px; border-radius: 12px;
            background: var(--danger-bg); border: 1px solid var(--danger-border); color: var(--danger); font-size: .88rem; font-weight: 600;
        }
        .alert[hidden] { display: none; }

        form { margin-top: 26px; display: flex; flex-direction: column; gap: 16px; }
        .field label { display: block; font-size: .82rem; font-weight: 700; margin-bottom: 7px; }
        .control { position: relative; }
        .control > i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 1.05rem; pointer-events: none; }
        .control input {
            width: 100%; height: 50px; padding: 0 14px 0 42px; border-radius: 13px;
            border: 1px solid var(--border); background: var(--input-bg); color: var(--text);
            font: 500 .98rem var(--font); transition: border-color .15s, box-shadow .15s;
        }
        .control input.has-toggle { padding-right: 48px; }
        .control input::placeholder { color: var(--muted); opacity: .7; }
        .control input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px var(--ring); }
        .toggle-pass {
            position: absolute; right: 6px; top: 50%; transform: translateY(-50%); width: 38px; height: 38px; border-radius: 10px;
            border: 0; background: transparent; color: var(--muted); cursor: pointer; font-size: 1.05rem;
        }
        .toggle-pass:hover { color: var(--text); background: var(--ring); }
        .caps { display: none; margin-top: 7px; font-size: .78rem; font-weight: 600; color: #b26a00; }
        .caps.show { display: block; }

        .submit {
            margin-top: 6px; height: 52px; border-radius: 13px; border: 0; cursor: pointer;
            background: var(--primary); color: var(--on-primary); font: 800 1rem var(--font);
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            box-shadow: 0 12px 24px -12px rgba(14, 138, 106, .7); transition: background .15s, transform .12s;
        }
        .submit:hover { background: var(--primary-hover); transform: translateY(-1px); }
        .submit:focus-visible { outline: 2px solid var(--primary); outline-offset: 3px; }
        .submit:disabled { opacity: .8; cursor: progress; transform: none; }
        .spinner { display: none; width: 18px; height: 18px; border-radius: 50%; border: 2px solid currentColor; border-right-color: transparent; animation: spin .7s linear infinite; }
        .submit.loading .spinner { display: inline-block; }
        .submit.loading .bi-arrow-right { display: none; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .form-foot { margin-top: 30px; text-align: center; color: var(--muted); font-size: .78rem; }
        .form-foot .dot { margin: 0 6px; }

        @media (max-width: 960px) {
            .auth { grid-template-columns: minmax(0, 1fr); }
            .brand-panel { display: none; }
            .mobile-brand { display: flex; }
            .form-panel { align-items: flex-start; padding-top: 72px; }
        }
        @media (prefers-reduced-motion: reduce) { .spinner { animation-duration: 2s; } .submit { transition: none; } }
    </style>
</head>

<body>
    <div class="auth">
        <!-- Panel de marca -->
        <aside class="brand-panel" aria-hidden="true">
            <div class="brand-top">
                <img src="assets/img/farmacia/mark.svg" alt="">
                <div><b><?php echo htmlspecialchars(FARMA_MARCA); ?></b><em><?php echo htmlspecialchars(FARMA_PRODUCTO); ?></em></div>
            </div>

            <div class="brand-copy">
                <h2><?php echo htmlspecialchars(FARMA_LEMA); ?><br><span>Ventas e inventario bajo control.</span></h2>
                <p>Punto de venta, inventario con vencimientos, compras a proveedores y reportes de utilidad en un solo lugar.</p>
                <div class="chips">
                    <span><i class="bi bi-upc-scan"></i>Punto de venta</span>
                    <span><i class="bi bi-box-seam"></i>Inventario</span>
                    <span><i class="bi bi-bag-check"></i>Compras</span>
                    <span><i class="bi bi-bar-chart-line"></i>Reportes</span>
                </div>
            </div>

            <div class="ticket">
                <h4>Venta <small>Ejemplo</small></h4>
                <ul>
                    <li><span>Acetaminofén 500 mg × 2</span><span>Q70.00</span></li>
                    <li><span>Suero oral × 3</span><span>Q48.00</span></li>
                    <li><span>Vitamina C 1 g</span><span>Q38.00</span></li>
                </ul>
                <div class="tot"><span>Total</span><span>Q156.00</span></div>
            </div>

            <div class="brand-foot"><i class="bi bi-shield-lock"></i> Acceso solo para personal autorizado</div>
        </aside>

        <!-- Formulario -->
        <main class="form-panel">
            <button type="button" id="themeSwitch" class="theme-btn" aria-label="Cambiar modo claro u oscuro" title="Modo claro / oscuro">
                <i class="bi bi-moon-stars"></i>
                <i class="bi bi-sun"></i>
            </button>

            <div class="form-wrap">
                <div class="mobile-brand">
                    <img src="assets/img/farmacia/mark.svg" alt="">
                    <div><b><?php echo htmlspecialchars(FARMA_MARCA); ?></b><em><?php echo htmlspecialchars(FARMA_PRODUCTO); ?></em></div>
                </div>

                <div class="form-head">
                    <h1>Bienvenido</h1>
                    <p>Ingrese con su usuario para abrir la farmacia.</p>
                </div>

                <div class="alert" id="formAlert" role="alert" <?php echo $error_msg ? '' : 'hidden'; ?>>
                    <i class="bi bi-exclamation-circle"></i>
                    <span id="formAlertText"><?php echo $error_msg ? htmlspecialchars($error_msg) : ''; ?></span>
                </div>

                <form id="loginForm" action="php/auth/login.php" method="POST" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="field">
                        <label for="usuario">Usuario</label>
                        <div class="control">
                            <i class="bi bi-person"></i>
                            <input type="text" id="usuario" name="usuario" placeholder="Su nombre de usuario"
                                autocomplete="username" autocapitalize="none" spellcheck="false" autofocus required>
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Contraseña</label>
                        <div class="control">
                            <i class="bi bi-lock"></i>
                            <input type="password" id="password" name="password" class="has-toggle" placeholder="••••••••"
                                autocomplete="current-password" required>
                            <button type="button" class="toggle-pass" id="togglePass" aria-label="Mostrar contraseña" aria-pressed="false">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="caps" id="capsWarn"><i class="bi bi-capslock"></i> Bloq Mayús está activado</div>
                    </div>

                    <button type="submit" id="loginButton" class="submit">
                        <span class="spinner" aria-hidden="true"></span>
                        <span class="label">Ingresar</span>
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </form>

                <p class="form-foot">© <?php echo date('Y'); ?> <?php echo htmlspecialchars(FARMA_NOMBRE); ?><span class="dot">·</span><?php echo htmlspecialchars(FARMA_EMPRESA); ?></p>
            </div>
        </main>
    </div>

    <script>
        (function () {
            var root = document.documentElement;
            var form = document.getElementById('loginForm');
            var btn = document.getElementById('loginButton');
            var user = document.getElementById('usuario');
            var pass = document.getElementById('password');
            var toggle = document.getElementById('togglePass');
            var caps = document.getElementById('capsWarn');
            var alertBox = document.getElementById('formAlert');
            var alertText = document.getElementById('formAlertText');

            // Modo claro / oscuro (se guarda igual que en el resto del sistema)
            document.getElementById('themeSwitch').addEventListener('click', function () {
                var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                root.setAttribute('data-theme', next);
                try { localStorage.setItem('dashboard-theme', next); } catch (e) {}
            });

            // Mostrar / ocultar contraseña
            toggle.addEventListener('click', function () {
                var show = pass.type === 'password';
                pass.type = show ? 'text' : 'password';
                toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
                toggle.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
                toggle.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
                pass.focus();
            });

            // Aviso de Bloq Mayús
            function checkCaps(e) {
                if (e.getModifierState) caps.classList.toggle('show', e.getModifierState('CapsLock'));
            }
            pass.addEventListener('keydown', checkCaps);
            pass.addEventListener('keyup', checkCaps);
            pass.addEventListener('blur', function () { caps.classList.remove('show'); });

            function showError(msg, field) {
                alertText.textContent = msg;
                alertBox.hidden = false;
                if (field) field.focus();
            }

            // Envío: validar y mostrar estado de carga (sin retrasos artificiales)
            form.addEventListener('submit', function (e) {
                if (!user.value.trim()) { e.preventDefault(); showError('Ingrese su usuario.', user); return; }
                if (!pass.value) { e.preventDefault(); showError('Ingrese su contraseña.', pass); return; }
                btn.disabled = true;
                btn.classList.add('loading');
                btn.querySelector('.label').textContent = 'Ingresando…';
            });

            // Si el navegador restaura la página desde caché (botón Atrás), reactivar el botón
            window.addEventListener('pageshow', function () {
                btn.disabled = false;
                btn.classList.remove('loading');
                btn.querySelector('.label').textContent = 'Ingresar';
            });
        })();
    </script>
    <script>window.ES_CREADOR = <?php echo isset($_SESSION['es_creador']) && $_SESSION['es_creador'] ? 'true' : 'false'; ?>;</script>
    <script defer src="assets/js/security.js"></script>
</body>

</html>
