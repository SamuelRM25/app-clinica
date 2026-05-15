<?php
// index.php - Sistema de Gestión Médica
session_start();
require_once __DIR__ . '/config/hospital.php';

// Verificar si el usuario ya está autenticado
if (isset($_SESSION['user_id'])) {
    header("Location: php/dashboard/index.php");
    exit;
}

// Configuración inicial
$page_title = "Login - Centro Médico RS";
date_default_timezone_set('America/Mexico_City');
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="assets/img/Logo.png">

    <!-- Google Fonts - Inter para un diseño moderno y legible -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Incluir estilos CSS -->
    <?php include_once 'includes/header.php'; ?>
</head>

<body>
    <!-- Contenedor principal con efecto mármol -->
    <div class="marble-container">

        <!-- Encabezado con logo y control de tema -->
        <header class="app-header">
            <div class="logo-container">
                <img src="assets/img/Logo.png" alt="Centro Médico RS" class="main-logo">
            </div>

            <!-- Control de modo día/noche -->
            <div class="theme-toggle">
                <button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema">
                    <span class="theme-icon sun-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M12,7c-2.76,0-5,2.24-5,5s2.24,5,5,5s5-2.24,5-5S14.76,7,12,7L12,7z M2,13l2,0c0.55,0,1-0.45,1-1s-0.45-1-1-1l-2,0 c-0.55,0-1,0.45-1,1S1.45,13,2,13z M20,13l2,0c0.55,0,1-0.45,1-1s-0.45-1-1-1l-2,0c-0.55,0-1,0.45-1,1S19.45,13,20,13z M11,2v2 c0,0.55,0.45,1,1,1s1-0.45,1-1V2c0-0.55-0.45-1-1-1S11,1.45,11,2z M11,20v2c0,0.55,0.45,1,1,1s1-0.45,1-1v-2c0-0.55-0.45-1-1-1 C11.45,19,11,19.45,11,20z M5.99,4.58c-0.39-0.39-1.03-0.39-1.41,0c-0.39,0.39-0.39,1.03,0,1.41l1.06,1.06 c0.39,0.39,1.03,0.39,1.41,0s0.39-1.03,0-1.41L5.99,4.58z M18.36,16.95c-0.39-0.39-1.03-0.39-1.41,0c-0.39,0.39-0.39,1.03,0,1.41 l1.06,1.06c0.39,0.39,1.03,0.39,1.41,0c0.39-0.39,0.39-1.03,0-1.41L18.36,16.95z M19.42,5.99c0.39-0.39,0.39-1.03,0-1.41 c-0.39-0.39-1.03-0.39-1.41,0l-1.06,1.06c-0.39,0.39-0.39,1.03,0,1.41s1.03,0.39,1.41,0L19.42,5.99z M7.05,18.36 c0.39-0.39,0.39-1.03,0-1.41c-0.39-0.39-1.03-0.39-1.41,0l-1.06,1.06c-0.39,0.39-0.39,1.03,0,1.41s1.03,0.39,1.41,0L7.05,18.36z" />
                        </svg>
                    </span>
                    <span class="theme-icon moon-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M9.37,5.51C9.19,6.15,9.1,6.82,9.1,7.5c0,4.08,3.32,7.4,7.4,7.4c0.68,0,1.35-0.09,1.99-0.27C17.45,17.19,14.93,19,12,19 c-3.86,0-7-3.14-7-7C5,9.07,6.81,6.55,9.37,5.51z M12,3c-4.97,0-9,4.03-9,9s4.03,9,9,9s9-4.03,9-9c0-0.46-0.04-0.92-0.1-1.36 c-0.98,1.37-2.58,2.26-4.4,2.26c-2.98,0-5.4-2.42-5.4-5.4c0-1.81,0.89-3.42,2.26-4.4C12.92,3.04,12.46,3,12,3L12,3z" />
                        </svg>
                    </span>
                </button>
            </div>
        </header>

        <!-- Tarjeta de login minimalista -->
        <main class="login-main">
            <div class="login-card">
                <div class="card-header">
                    <h3 class="welcome-title">Acceso al Sistema</h3>
                    <p class="welcome-subtitle">Ingrese sus credenciales para continuar</p>
                </div>

                <form id="loginForm" class="login-form" action="php/auth/login.php" method="POST">
                    <!-- Campo de usuario -->
                    <div class="form-group">
                        <div class="input-container">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M12,4C9.79,4,8,5.79,8,8c0,1.13,0.5,2.14,1.28,2.85C7.17,11.6,6,13.18,6,15v2c0,0.55,0.45,1,1,1h10c0.55,0,1-0.45,1-1v-2 c0-1.82-1.17-3.4-2.72-4.15C15.5,10.14,16,9.13,16,8C16,5.79,14.21,4,12,4z M10,8c0-1.1,0.9-2,2-2s2,0.9,2,2s-0.9,2-2,2 S10,9.1,10,8z M12,13c-1.65,0-3,1.35-3,3v1h6v-1C15,14.35,13.65,13,12,13z" />
                            </svg>
                            <input type="text" id="usuario" name="usuario" class="form-input" required
                                autocomplete="username" placeholder=" ">
                            <label for="usuario" class="input-label">Usuario</label>
                        </div>
                    </div>

                    <!-- Campo de contraseña -->
                    <div class="form-group">
                        <div class="input-container">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M18,8h-1V6c0-2.76-2.24-5-5-5S7,3.24,7,6v2H6c-1.1,0-2,0.9-2,2v10c0,1.1,0.9,2,2,2h12c1.1,0,2-0.9,2-2V10C20,8.9,19.1,8,18,8z M9,6c0-1.66,1.34-3,3-3s3,1.34,3,3v2H9V6z M18,20H6V10h12V20z M12,17c1.1,0,2-0.9,2-2c0-1.1-0.9-2-2-2s-2,0.9-2,2 C10,16.1,10.9,17,12,17z" />
                            </svg>
                            <input type="password" id="password" name="password" class="form-input" required
                                autocomplete="current-password" placeholder=" ">
                            <label for="password" class="input-label">Contraseña</label>
                            <button type="button" class="password-toggle" aria-label="Mostrar contraseña">
                                <svg class="eye-icon" viewBox="0 0 24 24" fill="currentColor">
                                    <path
                                        d="M12,4.5C7,4.5,2.73,7.61,1,12c1.73,4.39,6,7.5,11,7.5s9.27-3.11,11-7.5C21.27,7.61,17,4.5,12,4.5z M12,17c-2.76,0-5-2.24-5-5 s2.24-5,5-5s5,2.24,5,5S14.76,17,12,17z M12,9c-1.66,0-3,1.34-3,3s1.34,3,3,3s3-1.34,3-3S13.66,9,12,9z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Mensaje de error -->
                    <?php if (isset($_GET['error'])): ?>
                        <div class="error-message" role="alert">
                            <svg class="error-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path
                                    d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" />
                            </svg>
                            <span>Usuario o contraseña incorrectos. Intente nuevamente.</span>
                        </div>
                    <?php endif; ?>

                    <!-- Botón de envío -->
                    <div class="form-group">
                        <button type="submit" class="submit-btn" id="loginButton">
                            <span class="btn-text">Iniciar Sesión</span>
                            <svg class="btn-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M10 17l5-5-5-5v10z" />
                            </svg>
                        </button>
                    </div>
                </form>

                <!-- Información adicional -->
                <div class="card-footer">
                    <p class="copyright">© <?php echo date('Y'); ?> RS SOLUTIONS</p>
                </div>
            </div>
        </main>

        <!-- Indicador de carga sutil -->
        <div class="loading-indicator" id="loadingIndicator">
            <div class="spinner"></div>
        </div>
    </div>

    <!-- Estilos CSS integrados para mejor rendimiento -->
    <link rel="stylesheet" href="assets/css/global_dashboard.css">

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
                    const submitBtn = document.querySelector('.submit-btn');
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