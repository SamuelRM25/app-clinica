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
$page_title = "Iniciar sesión | Centro de Pruebas";
date_default_timezone_set('America/Guatemala');

// Mensaje de error (si viene de login.php o de una sesión cerrada)
$error_msg = null;
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case '2':                $error_msg = 'Demasiados intentos. Espere 60 segundos e intente de nuevo.'; break;
        case 'usuario_inactivo': $error_msg = 'Este usuario está desactivado. Pida al administrador que lo reactive.'; break;
        case 'sesion_expirada':
        case 'session_expired':  $error_msg = 'Su sesión expiró. Ingrese de nuevo para continuar.'; break;
        case 'session_invalid':  $error_msg = 'Su sesión no es válida. Ingrese de nuevo.'; break;
        case 'servidor':         $error_msg = 'El servidor no respondió. Intente de nuevo en unos minutos.'; break;
        default:                 $error_msg = 'El usuario o la contraseña no coinciden.';
    }
} elseif (isset($_GET['err']) && $_GET['err'] === 'security') {
    $error_msg = 'Por seguridad se cerró la sesión. Ingrese de nuevo.';
}

// Saludo y fecha iniciales (el script los mantiene al día en el navegador)
$hora_actual = (int) date('G');
$saludo = $hora_actual < 12 ? 'Buenos días' : ($hora_actual < 19 ? 'Buenas tardes' : 'Buenas noches');
$dias  = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$fecha_tira = $dias[(int) date('w')] . ' ' . date('d') . ' ' . $meses[(int) date('n') - 1] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
    <meta name="theme-color" content="#0F6670">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
    <link rel="manifest" href="manifest.json">

    <!-- Modo claro/oscuro antes de pintar (misma preferencia que el resto del sistema) -->
    <script>
        (function () {
            var t = null;
            try { t = localStorage.getItem('dashboard-theme'); } catch (e) {}
            document.documentElement.setAttribute('data-theme', t === 'dark' ? 'dark' : 'light');
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        :root {
            --ink: #17242E;
            --ink-2: #5C6B73;
            --petrol: #0F6670;
            --petrol-dark: #0B4F57;
            --canvas: #F2F5F5;
            --card: #FFFFFF;
            --line: #E1E7E8;
            --ring: rgba(15, 102, 112, .18);
            --danger: #B42318;
            --danger-bg: #FEF3F2;
            --danger-line: #FECDCA;

            /* Tira de ECG: papel milimetrado */
            --paper: #FBF6F5;
            --grid-minor: #F8E7E5;
            --grid-major: #F0CCC8;
            --ecg-ink: #17242E;
            --ecg-print: #6B4D4A;
            --ecg-head: #C2413B;
        }

        [data-theme="dark"] {
            --ink: #E6EEEF;
            --ink-2: #93A5AA;
            --petrol: #178089;
            --petrol-dark: #126870;
            --canvas: #0B1417;
            --card: #0F1A1E;
            --line: #1F3238;
            --ring: rgba(95, 227, 208, .20);
            --danger: #FDA29B;
            --danger-bg: rgba(180, 35, 24, .14);
            --danger-line: rgba(253, 162, 155, .28);

            /* Tira de ECG: pantalla de monitor */
            --paper: #071B1E;
            --grid-minor: rgba(95, 227, 208, .05);
            --grid-major: rgba(95, 227, 208, .11);
            --ecg-ink: #5FE3D0;
            --ecg-print: #7FB8B0;
            --ecg-head: #B9FFF4;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }

        body {
            font-family: 'IBM Plex Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--card);
            color: var(--ink);
            -webkit-font-smoothing: antialiased;
            font-feature-settings: "tnum" 1;
        }

        .auth {
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            grid-template-rows: auto 1fr;
        }

        /* ── Tira de ECG ───────────────────────────────────────────── */
        .strip {
            position: relative;
            overflow: hidden;
            background-color: var(--paper);
            background-image:
                linear-gradient(var(--grid-major) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-major) 1px, transparent 1px),
                linear-gradient(var(--grid-minor) 1px, transparent 1px),
                linear-gradient(90deg, var(--grid-minor) 1px, transparent 1px);
            background-size: 40px 40px, 40px 40px, 8px 8px, 8px 8px;
            background-position: -1px -1px;
            display: flex;
            flex-direction: column;
            padding: 1.25rem 1.25rem 1.5rem;
            min-height: 260px;
            border-bottom: 1px solid var(--line);
        }

        .print {
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-size: .78rem;
            color: var(--ecg-print);
            display: flex;
            flex-wrap: wrap;
            gap: .35rem 1.75rem;
        }
        .print b { font-weight: 500; color: var(--ecg-ink); }
        .print .opt { display: none; }

        .lead {
            position: relative;
            flex: 1;
            min-height: 150px;
            margin: .75rem 0;
        }
        .lead-name {
            position: absolute;
            left: 0;
            top: .25rem;
            font-family: 'IBM Plex Mono', ui-monospace, monospace;
            font-size: .78rem;
            color: var(--ecg-print);
        }
        .ecg-trace { position: absolute; inset: 0; }
        .ecg-trace svg { display: block; }
        .ecg-line { stroke: var(--ecg-ink); stroke-width: 1.8; }
        .ecg-line--old { opacity: .55; }
        .ecg-head { fill: var(--ecg-head); }
        [data-theme="dark"] .ecg-line { stroke-width: 2; filter: drop-shadow(0 0 3px rgba(95, 227, 208, .55)); }
        [data-theme="dark"] .ecg-head { filter: drop-shadow(0 0 6px #5FE3D0); }

        .welcome h2 {
            font-size: clamp(1.75rem, 6vw, 3.75rem);
            line-height: 1.05;
            font-weight: 600;
            letter-spacing: -.025em;
            color: var(--ink);
        }
        [data-theme="dark"] .welcome h2 { color: #F2FBFA; }
        .welcome p {
            margin-top: .6rem;
            font-size: 1.05rem;
            color: var(--ink-2);
            max-width: 34ch;
        }
        [data-theme="dark"] .welcome p { color: #9FC2BD; }

        /* ── Formulario ────────────────────────────────────────────── */
        .panel {
            position: relative;
            background: var(--card);
            display: flex;
            flex-direction: column;
            padding: 2rem 1.25rem 1.5rem;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: .8rem;
            text-decoration: none;
            color: var(--ink);
        }
        .brand img { width: 44px; height: 44px; display: block; }
        .brand-name { font-weight: 600; font-size: 1.15rem; letter-spacing: -.01em; line-height: 1.15; }
        .brand-sub { font-size: .85rem; color: var(--ink-2); }

        .form-wrap {
            width: 100%;
            max-width: 380px;
            margin: 2.25rem auto 0;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-wrap h1 {
            font-size: 1.75rem;
            font-weight: 600;
            letter-spacing: -.02em;
            margin-bottom: .4rem;
        }
        .form-wrap .lede { color: var(--ink-2); margin-bottom: 1.75rem; }

        .alert {
            display: flex;
            gap: .6rem;
            align-items: flex-start;
            padding: .8rem .95rem;
            margin-bottom: 1.25rem;
            border-radius: 10px;
            background: var(--danger-bg);
            border: 1px solid var(--danger-line);
            color: var(--danger);
            font-size: .9rem;
            line-height: 1.45;
        }
        .alert[hidden] { display: none; }

        .field { margin-bottom: 1.1rem; }
        .field label { display: block; font-size: .875rem; font-weight: 500; margin-bottom: .45rem; }
        .control { position: relative; }
        .control input {
            width: 100%;
            height: 48px;
            padding: 0 1rem;
            font: inherit;
            font-size: 1rem;
            color: var(--ink);
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 10px;
            outline: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .control input::placeholder { color: var(--ink-2); opacity: .65; }
        .control input:hover { border-color: #C5D0D2; }
        [data-theme="dark"] .control input:hover { border-color: #2C454C; }
        .control input:focus { border-color: var(--petrol); box-shadow: 0 0 0 4px var(--ring); }
        .control input.has-toggle { padding-right: 3rem; }

        .toggle-pass {
            position: absolute;
            right: .3rem;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            border: 0;
            border-radius: 8px;
            background: transparent;
            color: var(--ink-2);
            cursor: pointer;
            font-size: 1.1rem;
            display: grid;
            place-items: center;
        }
        .toggle-pass:hover { color: var(--ink); background: var(--canvas); }
        .toggle-pass:focus-visible { outline: 2px solid var(--petrol); outline-offset: 1px; }

        .caps { display: none; margin-top: .45rem; font-size: .82rem; color: #9A6200; }
        [data-theme="dark"] .caps { color: #F5C26B; }
        .caps.show { display: flex; gap: .35rem; align-items: center; }

        .submit {
            width: 100%;
            height: 50px;
            margin-top: .75rem;
            border: 0;
            border-radius: 10px;
            background: var(--petrol);
            color: #fff;
            font: inherit;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .6rem;
            transition: background-color .15s ease;
        }
        .submit:hover { background: var(--petrol-dark); }
        .submit:focus-visible { outline: none; box-shadow: 0 0 0 4px var(--ring); }
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

        .foot {
            margin-top: 2.5rem;
            font-size: .8rem;
            color: var(--ink-2);
            text-align: center;
        }

        .theme-btn {
            position: absolute;
            top: 1.6rem;
            right: 1.25rem;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: var(--card);
            color: var(--ink-2);
            cursor: pointer;
            display: grid;
            place-items: center;
            font-size: 1rem;
        }
        .theme-btn:hover { color: var(--ink); }
        .theme-btn:focus-visible { outline: 2px solid var(--petrol); outline-offset: 2px; }
        .theme-btn .bi-sun { display: none; }
        [data-theme="dark"] .theme-btn .bi-sun { display: inline; }
        [data-theme="dark"] .theme-btn .bi-moon { display: none; }

        @keyframes spin { to { transform: rotate(360deg); } }

        @media (min-width: 720px) {
            .print .opt { display: inline; }
            .strip { padding: 1.5rem 2rem 2rem; min-height: 320px; }
        }

        @media (min-width: 1024px) {
            .auth { grid-template-columns: minmax(0, 1.45fr) minmax(420px, 1fr); grid-template-rows: 1fr; }
            .strip { min-height: 100vh; min-height: 100dvh; padding: 2.5rem 3rem 3rem; border-bottom: 0; border-right: 1px solid var(--line); }
            .lead { margin: 2rem 0; flex: 0 0 240px; }
            .strip { justify-content: space-between; }
            .panel { padding: 2.5rem 3rem; }
            .theme-btn { top: 2.4rem; right: 3rem; }
            .form-wrap { margin-top: 0; }
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; }
        }
    </style>
</head>

<body>
    <div class="auth">
        <!-- Tira de ECG: hora y fecha reales -->
        <section class="strip" aria-label="Bienvenida">
            <div class="print" aria-hidden="true">
                <b>Centro de Pruebas</b>
                <span id="stripDate"><?php echo $fecha_tira; ?></span>
                <span id="stripTime"><?php echo date('H:i:s'); ?></span>
                <span class="opt">25 mm/s</span>
                <span class="opt">10 mm/mV</span>
            </div>

            <div class="lead">
                <span class="lead-name" aria-hidden="true">II</span>
                <div class="ecg-trace" data-ecg="loop" data-beat="190" data-speed="160"></div>
            </div>

            <div class="welcome">
                <h2 id="stripGreeting"><?php echo $saludo; ?>.</h2>
                <p>Ingrese para ver la agenda, los pacientes y los cobros de hoy.</p>
            </div>
        </section>

        <!-- Formulario -->
        <main class="panel">
            <div class="brand">
                <img src="assets/img/mark-cp.svg" alt="" width="44" height="44">
                <div>
                    <div class="brand-name">Centro de Pruebas</div>
                    <div class="brand-sub">Gestión clínica</div>
                </div>
            </div>

            <button type="button" id="themeSwitch" class="theme-btn" aria-label="Cambiar entre modo claro y oscuro" title="Modo claro u oscuro">
                <i class="bi bi-moon"></i>
                <i class="bi bi-sun"></i>
            </button>

            <div class="form-wrap">
                <h1>Iniciar sesión</h1>
                <p class="lede">Use el usuario que le asignó el administrador.</p>

                <div class="alert" id="formAlert" role="alert" <?php echo $error_msg ? '' : 'hidden'; ?>>
                    <i class="bi bi-exclamation-circle"></i>
                    <span id="formAlertText"><?php echo $error_msg ? htmlspecialchars($error_msg) : ''; ?></span>
                </div>

                <form id="loginForm" action="php/auth/login.php" method="POST" novalidate>
                    <?php echo csrf_field(); ?>

                    <div class="field">
                        <label for="usuario">Usuario</label>
                        <div class="control">
                            <input type="text" id="usuario" name="usuario" placeholder="Su usuario"
                                autocomplete="username" autocapitalize="none" spellcheck="false" autofocus required>
                        </div>
                    </div>

                    <div class="field">
                        <label for="password">Contraseña</label>
                        <div class="control">
                            <input type="password" id="password" name="password" class="has-toggle"
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
                    </button>
                </form>
            </div>

            <p class="foot">© <?php echo date('Y'); ?> RS Software Solutions</p>
        </main>
    </div>

    <script src="assets/js/ecg-strip.js"></script>
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

            // Hora y fecha de la tira, como en un electrocardiógrafo
            var dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
            var meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
            var tEl = document.getElementById('stripTime');
            var dEl = document.getElementById('stripDate');
            var gEl = document.getElementById('stripGreeting');
            function pad(n) { return (n < 10 ? '0' : '') + n; }
            function tick() {
                var d = new Date();
                tEl.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
                dEl.textContent = dias[d.getDay()] + ' ' + pad(d.getDate()) + ' ' + meses[d.getMonth()] + ' ' + d.getFullYear();
                var h = d.getHours();
                gEl.textContent = (h < 12 ? 'Buenos días' : (h < 19 ? 'Buenas tardes' : 'Buenas noches')) + '.';
            }
            tick();
            setInterval(tick, 1000);

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
            function checkCaps(e) { if (e.getModifierState) caps.classList.toggle('show', e.getModifierState('CapsLock')); }
            pass.addEventListener('keydown', checkCaps);
            pass.addEventListener('keyup', checkCaps);
            pass.addEventListener('blur', function () { caps.classList.remove('show'); });

            function showError(msg, field) {
                alertText.textContent = msg;
                alertBox.hidden = false;
                if (field) field.focus();
            }

            form.addEventListener('submit', function (e) {
                if (!user.value.trim()) { e.preventDefault(); showError('Escriba su usuario.', user); return; }
                if (!pass.value) { e.preventDefault(); showError('Escriba su contraseña.', pass); return; }
                btn.disabled = true;
                btn.classList.add('loading');
                btn.querySelector('.label').textContent = 'Ingresando';
            });

            // Si el navegador restaura la página (botón Atrás), reactivar el botón
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
