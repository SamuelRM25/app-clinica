<?php
// index.php - Sistema de Gestión Médica
session_start();
require_once __DIR__ . '/config/hospital.php';
require_once __DIR__ . '/includes/functions.php';

error_log("INDEX DEBUG: session_id = " . session_id() . ", user_id = " . ($_SESSION['user_id'] ?? 'not set'));

// Verificar si el usuario ya está autenticado
if (isset($_SESSION['user_id'])) {
    error_log("INDEX DEBUG: Already authenticated. Redirecting to php/dashboard/index.php");
    header("Location: php/dashboard/index.php");
    exit;
}

// Configuración inicial
$page_title = "Login - Centro Médico RS";
date_default_timezone_set('America/Guatemala');
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/favicon.png">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" media="print" onload="this.media='all'">

    <style>
        :root {
            --color-primary: #6366f1;
            --color-primary-dark: #4f46e5;
            --color-bg: #f8fafc;
            --color-surface: #ffffff;
            --color-text: #1e293b;
            --color-text-muted: #64748b;
            --color-border: #e2e8f0;
            --shadow-premium: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] {
            --color-primary: #818cf8;
            --color-primary-dark: #6366f1;
            --color-bg: #0f172a;
            --color-surface: #1e293b;
            --color-text: #f1f5f9;
            --color-text-muted: #94a3b8;
            --color-border: #334155;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--color-bg);
            color: var(--color-text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Animated Background */
        .background-blob {
            position: absolute;
            width: 500px;
            height: 500px;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(168, 85, 247, 0.2) 100%);
            filter: blur(80px);
            border-radius: 50%;
            z-index: -1;
            animation: blob-float 20s infinite alternate;
        }

        .blob-1 { top: -100px; right: -100px; }
        .blob-2 { bottom: -100px; left: -100px; animation-delay: -5s; }

        @keyframes blob-float {
            0% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -50px) scale(1.1); }
            66% { transform: translate(-20px, 20px) scale(0.9); }
            100% { transform: translate(0, 0) scale(1); }
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 2rem;
            z-index: 10;
        }

        .login-card {
            background: var(--color-surface);
            border-radius: 1.5rem;
            padding: 2.5rem;
            box-shadow: var(--shadow-premium);
            border: 1px solid var(--color-border);
            backdrop-filter: blur(10px);
            transform: translateY(0);
            transition: transform 0.3s ease;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-img {
            width: 80px;
            height: auto;
            margin-bottom: 1rem;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));
        }

        .login-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(to right, var(--color-primary), #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .login-header p {
            color: var(--color-text-muted);
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--color-text-muted);
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            color: var(--color-text-muted);
            font-size: 1.1rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            background: var(--color-bg);
            border: 1px solid var(--color-border);
            border-radius: 0.75rem;
            color: var(--color-text);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 0.875rem;
            background: var(--color-primary);
            color: white;
            border: none;
            border-radius: 0.75rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .login-btn:hover {
            background: var(--color-primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        .login-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.875rem;
            color: var(--color-text-muted);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Animations */
        .animate-up {
            animation: slide-up 0.6s ease-out forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        @keyframes slide-up {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .delay-1 { animation-delay: 0.1s; }
        .delay-2 { animation-delay: 0.2s; }

        /* Theme Toggle */
        .theme-toggle {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 100;
        }

        .theme-btn {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--color-text);
            font-size: 1.2rem;
            box-shadow: var(--shadow-premium);
            transition: all 0.3s ease;
        }

        .theme-btn:hover {
            transform: rotate(15deg) scale(1.1);
        }

        /* Theme toggle display rules */
        [data-theme="light"] .sun-icon { display: block; }
        [data-theme="light"] .moon-icon { display: none; }
        [data-theme="dark"] .sun-icon { display: none; }
        [data-theme="dark"] .moon-icon { display: block; }

        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 1rem;
            background: none;
            border: none;
            color: var(--color-text-muted);
            cursor: pointer;
            display: flex;
            align-items: center;
            padding: 0;
            transition: color 0.2s ease;
            z-index: 5;
        }

        .password-toggle:hover {
            color: var(--color-primary);
        }

        .password-toggle:focus {
            outline: none;
        }

        /* Loading Indicator */
        .loading-indicator {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 999;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-left-color: var(--color-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .error-icon {
            width: 1.25rem;
            height: 1.25rem;
            flex-shrink: 0;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>

<body>
    <div class="background-blob blob-1"></div>
    <div class="background-blob blob-2"></div>

    <div class="theme-toggle">
        <button id="themeSwitch" class="theme-btn">
            <i class="bi bi-sun sun-icon"></i>
            <i class="bi bi-moon moon-icon"></i>
        </button>
    </div>

    <main class="login-container">
        <div class="login-card animate-up">
            <div class="logo-section">
                <img src="assets/img/Logo.png" alt="Logo" class="logo-img" width="40" height="40">
                <div class="login-header">
                    <h1>Centro Médico RS</h1>
                    <p>Gestión Clínica Inteligente</p>
                </div>
            </div>

            <form id="loginForm" action="php/auth/login.php" method="POST">
                <?php echo csrf_field(); ?>
                <?php if (isset($_GET['error'])): ?>
                    <div class="error-message">
                        <i class="bi bi-exclamation-circle"></i>
                        <span><?php echo $_GET['error'] === '2' ? 'Demasiados intentos. Espere 60 segundos.' : 'Usuario o contraseña incorrectos.'; ?></span>
                    </div>
                <?php endif; ?>

                <div class="form-group animate-up delay-1">
                    <label class="form-label" for="usuario">Usuario</label>
                    <div class="input-wrapper">
                        <i class="bi bi-person input-icon"></i>
                        <input type="text" id="usuario" name="usuario" class="form-input" placeholder="Nombre de usuario" required>
                    </div>
                </div>

                <div class="form-group animate-up delay-1">
                    <label class="form-label" for="password">Contraseña</label>
                    <div class="input-wrapper">
                        <i class="bi bi-lock input-icon"></i>
                        <input type="password" id="password" name="password" class="form-input" style="padding-right: 2.75rem;" placeholder="••••••••" required>
                        <button type="button" class="password-toggle">
                            <svg class="eye-icon" width="20" height="20" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12,4.5C7,4.5,2.73,7.61,1,12c1.73,4.39,6,7.5,11,7.5s9.27-3.11,11-7.5C21.27,7.61,17,4.5,12,4.5z M12,17c-2.76,0-5-2.24-5-5 s2.24-5,5-5s5,2.24,5,5S14.76,17,12,17z M12,9c-1.66,0-3,1.34-3,3s1.34,3,3,3s3-1.34,3-3S13.66,9,12,9z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" id="loginButton" class="login-btn animate-up delay-2">
                    <span>Entrar al Sistema</span>
                    <i class="bi bi-arrow-right"></i>
                </button>

            </form>

            <!-- Información adicional -->
            <div class="card-footer">
                <p class="copyright">© <?php echo date('Y'); ?> RS SOLUTIONS</p>
            </div>
        </div>

        <!-- Indicador de carga sutil -->
        <div class="loading-indicator" id="loadingIndicator">
            <div class="spinner"></div>
        </div>
    </main>

    <!-- Estilos CSS integrados para mejor rendimiento -->
    <link rel="stylesheet" href="assets/css/global_dashboard.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="assets/css/global_dashboard.css"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css"></noscript>

    <!-- JavaScript para funcionalidades -->
    <script>
        // Sistema de Gestión Médica - JavaScript
        // Centro Médico RS

        // Esperar a que el DOM esté completamente cargado
        document.addEventListener('DOMContentLoaded', function () {
            // Referencias a elementos del DOM
            const loginForm = document.getElementById('loginForm');
            const loginButton = document.getElementById('loginButton');
            const themeSwitch = document.getElementById('themeSwitch');
            const passwordToggle = document.querySelector('.password-toggle');
            const passwordInput = document.getElementById('password');
            const loadingIndicator = document.getElementById('loadingIndicator');

            // Verificar tema guardado en localStorage o preferencia del sistema
            function initializeTheme() {
                const savedTheme = localStorage.getItem('theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                // Aplicar tema guardado o detectar preferencia del sistema
                if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            }

            // Cambiar entre modo claro y oscuro
            function toggleTheme() {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';

                // Aplicar nuevo tema
                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);

                // Agregar animación sutil
                document.body.style.transition = 'background-color 0.3s ease, color 0.3s ease';

                // Pequeña animación en el botón de tema
                themeSwitch.style.transform = 'rotate(180deg)';
                setTimeout(() => {
                    themeSwitch.style.transform = 'rotate(0)';
                }, 300);
            }

            // Mostrar/ocultar contraseña
            function togglePasswordVisibility() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);

                // Cambiar icono del botón
                const eyeIcon = passwordToggle.querySelector('.eye-icon');
                if (type === 'text') {
                    eyeIcon.innerHTML = '<path d="M12 6.5c2.76 0 5 2.24 5 5 0 .51-.1 1-.24 1.46l3.06 3.06c1.39-1.23 2.49-2.77 3.18-4.53C21.27 7.11 17 4.5 12 4.5c-1.27 0-2.49.2-3.64.57l2.17 2.17c.46-.14.95-.24 1.47-.24zM2.71 3.16c-.39.39-.39 1.02 0 1.41l1.97 1.97C3.06 7.83 1.77 9.53 1 11.5 2.73 15.89 7 18.5 12 18.5c1.52 0 2.97-.3 4.31-.82l2.72 2.72c.39.39 1.02.39 1.41 0 .39-.39.39-1.02 0-1.41L4.13 3.16c-.39-.39-1.03-.39-1.42 0zM12 16.5c-2.76 0-5-2.24-5-5 0-.77.18-1.5.49-2.14l1.57 1.57c-.03.18-.06.37-.06.57 0 1.66 1.34 3 3 3 .2 0 .38-.03.57-.07L14.14 16c-.64.32-1.37.5-2.14.5zm2.97-5.33c-.15-1.4-1.25-2.5-2.65-2.5-.7 0-1.34.28-1.81.73l1.22 1.22c.2-.06.41-.1.63-.1 1.1 0 2 .9 2 2 0 .22-.04.43-.09.63l1.22 1.22c.45-.47.73-1.12.73-1.81 0-1.4-1.1-2.5-2.5-2.5z"/>';
                } else {
                    eyeIcon.innerHTML = '<path d="M12,4.5C7,4.5,2.73,7.61,1,12c1.73,4.39,6,7.5,11,7.5s9.27-3.11,11-7.5C21.27,7.61,17,4.5,12,4.5z M12,17c-2.76,0-5-2.24-5-5 s2.24-5,5-5s5,2.24,5,5S14.76,17,12,17z M12,9c-1.66,0-3,1.34-3,3s1.34,3,3,3s3-1.34,3-3S13.66,9,12,9z"/>';
                }
            }

            // Manejar envío del formulario
            function handleFormSubmit(event) {
                event.preventDefault();

                // Validar campos
                const usuario = document.getElementById('usuario').value.trim();
                const password = document.getElementById('password').value.trim();

                if (!usuario || !password) {
                    showError('Por favor, complete todos los campos');
                    return;
                }

                // Mostrar indicador de carga
                showLoading();

                // Deshabilitar botón
                loginButton.classList.add('loading');
                loginButton.disabled = true;

                // Simular envío del formulario (en producción esto se haría con fetch/AJAX)
                setTimeout(() => {
                    // En un sistema real, aquí se enviarían los datos al servidor
                    // Por ahora, solo enviaremos el formulario de manera tradicional
                    loginForm.submit();
                }, 1000);
            }

            // Mostrar indicador de carga
            function showLoading() {
                loadingIndicator.style.display = 'flex';
            }

            // Ocultar indicador de carga
            function hideLoading() {
                loadingIndicator.style.display = 'none';
            }

            // Mostrar mensaje de error personalizado
            function showError(message) {
                // Crear elemento de error si no existe
                let errorElement = document.querySelector('.error-message');
                if (!errorElement) {
                    errorElement = document.createElement('div');
                    errorElement.className = 'error-message';
                    errorElement.setAttribute('role', 'alert');

                    const errorIcon = document.createElement('svg');
                    errorIcon.className = 'error-icon';
                    errorIcon.setAttribute('viewBox', '0 0 24 24');
                    errorIcon.setAttribute('fill', 'currentColor');
                    errorIcon.innerHTML = '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>';

                    const errorText = document.createElement('span');

                    errorElement.appendChild(errorIcon);
                    errorElement.appendChild(errorText);

                    // Insertar antes del botón de submit
                    const submitBtn = document.querySelector('.login-btn');
                    submitBtn.parentElement.parentElement.insertBefore(errorElement, submitBtn.parentElement);
                }

                // Actualizar mensaje y mostrar
                errorElement.querySelector('span').textContent = message;
                errorElement.style.display = 'flex';

                // Ocultar después de 5 segundos
                setTimeout(() => {
                    errorElement.style.display = 'none';
                }, 5000);
            }

            // Inicializar tema
            initializeTheme();

            // Asignar event listeners
            themeSwitch.addEventListener('click', toggleTheme);
            passwordToggle.addEventListener('click', togglePasswordVisibility);
            loginForm.addEventListener('submit', handleFormSubmit);

            // Agregar validación en tiempo real
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('blur', function () {
                    if (this.value.trim()) {
                        this.classList.add('filled');
                    } else {
                        this.classList.remove('filled');
                    }
                });

                // Animar etiqueta al enfocar
                input.addEventListener('focus', function () {
                    this.parentElement.classList.add('focused');
                });

                input.addEventListener('blur', function () {
                    if (!this.value.trim()) {
                        this.parentElement.classList.remove('focused');
                    }
                });
            });

            // Permitir enviar formulario con Enter
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' && (event.target.matches('#usuario') || event.target.matches('#password'))) {
                    event.preventDefault();
                    loginForm.dispatchEvent(new Event('submit'));
                }
            });

            // Mostrar mensaje de bienvenida
            console.log('Sistema de Gestión Médica - Centro Médico RS');
            console.log('Versión 2.0 - Diseño Minimalista con Modo Nocturno');
        });
    </script>
</body>

</html>