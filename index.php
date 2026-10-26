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
$page_title = "Iniciar sesión · Clínica Médica Dr. Roberto Morales";
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
    <meta name="theme-color" content="#1b2440">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <link rel="icon" type="image/png" href="assets/img/favicon-cmrm.png">
    <link rel="apple-touch-icon" href="assets/img/apple-touch-icon-cmrm.png">
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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        /* ── Paleta CMRM (tomada del logo) ───────────────────────── */
        :root {
            --navy-950: #0e1424;
            --navy-900: #141b30;
            --navy-800: #1b2440;
            --navy-700: #283251;
            --navy-600: #3a4670;
            --gold-300: #ecd48a;
            --gold-400: #e2bf63;
            --gold-500: #c9a24f;
            --gold-600: #a8783a;

            --bg: #ffffff;
            --surface: #f7f7f4;
            --text: #1c2236;
            --muted: #6b7183;
            --border: #e4e3dd;
            --input-bg: #ffffff;
            --primary: var(--navy-700);
            --primary-hover: var(--navy-800);
            --ring: rgba(40, 50, 81, .16);
            --danger: #b42318;
            --danger-bg: #fef3f2;
            --danger-border: #fecdca;
        }

        [data-theme="dark"] {
            --bg: #0e1424;
            --surface: #141b30;
            --text: #eef0f6;
            --muted: #98a0b8;
            --border: #27304a;
            --input-bg: #141b30;
            --primary: #c9a24f;
            --primary-hover: #b98f42;
            --ring: rgba(201, 162, 79, .22);
            --danger: #fda29b;
            --danger-bg: rgba(180, 35, 24, .14);
            --danger-border: rgba(253, 162, 155, .25);
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { height: 100%; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            transition: background-color .25s ease, color .25s ease;
        }

        .auth {
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            grid-template-columns: minmax(0, 1fr);
        }

        /* ── Panel de marca (solo escritorio) ────────────────────── */
        .brand-panel {
            display: none;
            position: relative;
            overflow: hidden;
            color: #fff;
            background:
                radial-gradient(120% 90% at 15% 10%, rgba(201, 162, 79, .14) 0%, rgba(201, 162, 79, 0) 55%),
                radial-gradient(90% 80% at 100% 100%, #283251 0%, rgba(40, 50, 81, 0) 60%),
                linear-gradient(160deg, #1b2440 0%, #141b30 100%);
        }

        /* Anillos dorados muy sutiles, eco del círculo del monograma */
        .brand-panel::before,
        .brand-panel::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            border: 1px solid rgba(226, 191, 99, .14);
            pointer-events: none;
        }
        .brand-panel::before { width: 720px; height: 720px; right: -320px; bottom: -300px; }
        .brand-panel::after  { width: 980px; height: 980px; right: -450px; bottom: -430px; border-color: rgba(226, 191, 99, .07); }

        .brand-inner {
            position: relative;
            z-index: 1;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 3.5rem 4rem;
        }

        .brand-mark { width: 148px; height: auto; display: block; filter: drop-shadow(0 6px 18px rgba(0, 0, 0, .25)); }

        .brand-copy { max-width: 26rem; }
        .brand-rule { width: 48px; height: 2px; background: linear-gradient(90deg, var(--gold-400), var(--gold-600)); border-radius: 2px; margin-bottom: 1.5rem; }
        .brand-copy h2 {
            font-size: 2rem;
            line-height: 1.2;
            font-weight: 600;
            letter-spacing: -.02em;
            margin-bottom: .9rem;
        }
        .brand-copy h2 span { color: var(--gold-400); }
        .brand-copy p { color: rgba(255, 255, 255, .68); font-size: 1rem; line-height: 1.6; }

        .brand-foot { font-size: .8rem; color: rgba(255, 255, 255, .45); letter-spacing: .02em; }

        /* ── Panel del formulario ────────────────────────────────── */
        .form-panel {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 5rem 1.25rem 2rem;
        }

        .form-wrap {
            width: 100%;
            max-width: 400px;
            animation: rise .5s cubic-bezier(.2, .8, .2, 1) both;
        }

        .logo {
            display: block;
            width: 100%;
            max-width: 380px;
            height: auto;
            margin: 0 auto 3rem;
        }
        .logo-dark { display: none; }
        [data-theme="dark"] .logo-light { display: none; }
        [data-theme="dark"] .logo-dark { display: block; }

        .form-head { margin-bottom: 1.75rem; }
        .form-head h1 { font-size: 1.5rem; font-weight: 600; letter-spacing: -.015em; margin-bottom: .35rem; }
        .form-head p { color: var(--muted); font-size: .95rem; }

        .alert {
            display: flex;
            gap: .6rem;
            align-items: flex-start;
            padding: .8rem .95rem;
            margin-bottom: 1.25rem;
            border-radius: 10px;
            background: var(--danger-bg);
            border: 1px solid var(--danger-border);
            color: var(--danger);
            font-size: .875rem;
            line-height: 1.45;
        }
        .alert i { font-size: 1rem; line-height: 1.3; }
        .alert[hidden] { display: none; }

        .field { margin-bottom: 1.1rem; }
        .field label {
            display: block;
            font-size: .85rem;
            font-weight: 500;
            margin-bottom: .45rem;
            color: var(--text);
        }
        .control { position: relative; }
        .control > i {
            position: absolute;
            left: .95rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 1.05rem;
            pointer-events: none;
            transition: color .15s ease;
        }
        .control input {
            width: 100%;
            height: 48px;
            padding: 0 1rem 0 2.75rem;
            font: inherit;
            font-size: .95rem;
            color: var(--text);
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            outline: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .control input::placeholder { color: var(--muted); opacity: .7; }
        .control input:hover { border-color: color-mix(in srgb, var(--primary) 35%, var(--border)); }
        .control input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px var(--ring); }
        .control:focus-within > i { color: var(--primary); }
        .control input.has-toggle { padding-right: 3rem; }

        .toggle-pass {
            position: absolute;
            right: .35rem;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            font-size: 1.1rem;
            display: grid;
            place-items: center;
        }
        .toggle-pass:hover { color: var(--text); background: var(--surface); }
        .toggle-pass:focus-visible { outline: 2px solid var(--primary); outline-offset: 1px; }

        .caps {
            display: none;
            margin-top: .45rem;
            font-size: .8rem;
            color: var(--gold-600);
        }
        [data-theme="dark"] .caps { color: var(--gold-400); }
        .caps.show { display: flex; gap: .35rem; align-items: center; }

        .submit {
            width: 100%;
            height: 50px;
            margin-top: .9rem;
            border: 0;
            border-radius: 10px;
            background: var(--primary);
            color: #fff;
            font: inherit;
            font-weight: 600;
            font-size: .95rem;
            letter-spacing: .01em;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .55rem;
            box-shadow: 0 1px 2px rgba(20, 27, 48, .12), 0 8px 20px -8px rgba(20, 27, 48, .45);
            transition: background-color .15s ease, transform .15s ease, box-shadow .15s ease;
        }
        [data-theme="dark"] .submit { color: var(--navy-900); box-shadow: 0 8px 20px -10px rgba(201, 162, 79, .55); }
        .submit:hover { background: var(--primary-hover); }
        .submit:active { transform: translateY(1px); }
        .submit:focus-visible { outline: none; box-shadow: 0 0 0 4px var(--ring); }
        .submit .bi-arrow-right { transition: transform .15s ease; }
        .submit:hover .bi-arrow-right { transform: translateX(3px); }
        .submit[disabled] { opacity: .85; cursor: progress; }
        .spinner {
            display: none;
            width: 18px;
            height: 18px;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }
        .submit.loading .spinner { display: inline-block; }
        .submit.loading .bi-arrow-right { display: none; }

        .form-foot {
            margin-top: 2.5rem;
            text-align: center;
            font-size: .78rem;
            color: var(--muted);
        }
        .form-foot .dot { margin: 0 .4rem; opacity: .5; }

        .theme-btn {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--muted);
            cursor: pointer;
            display: grid;
            place-items: center;
            font-size: 1.05rem;
            transition: color .15s ease, border-color .15s ease, transform .3s ease;
        }
        .theme-btn:hover { color: var(--text); border-color: var(--muted); }
        .theme-btn .bi-sun { display: none; }
        [data-theme="dark"] .theme-btn .bi-sun { display: inline; }
        [data-theme="dark"] .theme-btn .bi-moon-stars { display: none; }

        @keyframes rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (min-width: 960px) {
            .auth { grid-template-columns: minmax(420px, 44%) 1fr; }
            .brand-panel { display: block; }
            .form-panel { padding: 3rem 2rem; }
        }

        @media (max-width: 959px) {
            .form-panel::before {
                content: "";
                position: absolute;
                inset: 0 0 auto 0;
                height: 4px;
                background: linear-gradient(90deg, var(--navy-700), var(--gold-500), var(--navy-700));
            }
        }

        @media (max-width: 480px) {
            .logo { max-width: 310px; margin-bottom: 2.25rem; }
            .form-head h1 { font-size: 1.35rem; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
    </style>
</head>

<body>
    <div class="auth">
        <!-- Panel de marca -->
        <aside class="brand-panel" aria-hidden="true">
            <div class="brand-inner">
                <img src="assets/img/monogram_cmrm.svg" alt="" class="brand-mark">

                <div class="brand-copy">
                    <div class="brand-rule"></div>
                    <h2>Sistema de<br><span>Gestión Clínica</span></h2>
                    <p>Pacientes, citas, laboratorio, farmacia, encamamiento y cobros en un solo lugar.</p>
                </div>

                <div class="brand-foot"><i class="bi bi-shield-lock"></i>&nbsp; Acceso exclusivo para personal autorizado</div>
            </div>
        </aside>

        <!-- Formulario -->
        <main class="form-panel">
            <button type="button" id="themeSwitch" class="theme-btn" aria-label="Cambiar modo claro u oscuro" title="Modo claro / oscuro">
                <i class="bi bi-moon-stars"></i>
                <i class="bi bi-sun"></i>
            </button>

            <div class="form-wrap">
                <img src="assets/img/logo_cmrm.svg" alt="Clínica Médica Dr. Roberto Morales" class="logo logo-light" width="380" height="91">
                <img src="assets/img/logo_cmrm_dark.svg" alt="Clínica Médica Dr. Roberto Morales" class="logo logo-dark" width="380" height="91">

                <div class="form-head">
                    <h1>Iniciar sesión</h1>
                    <p>Ingrese sus credenciales para continuar.</p>
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

                <p class="form-foot">© <?php echo date('Y'); ?> Clínica Médica Dr. Roberto Morales<span class="dot">·</span>RS Solutions</p>
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
