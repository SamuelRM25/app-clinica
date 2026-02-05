<?php
// index.php - Sistema de Gestión Médica Centro Médico RS
// Versión: 2.0 - Rediseño Minimalista con Modo Nocturno
session_start();

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
                <img src="assets/img/cmrs.png" alt="Centro Médico RS" class="main-logo">
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
    <style>
        /* 
     * Estilos CSS para el Sistema de Gestión Médica
     * Centro Médico RS - Versión Minimalista
     * Diseño: Fondo blanco, colores pastel, modo noche
     */

        /* Variables CSS para modo claro y oscuro */
        :root {
            /* Modo claro (predeterminado) */
            --color-background: #f8f9fa;
            --color-surface: #ffffff;
            --color-primary: #7c90db;
            --color-primary-light: #a3b1e8;
            --color-primary-dark: #5a6fca;
            --color-secondary: #8dd7bf;
            --color-text: #2d3748;
            --color-text-light: #718096;
            --color-border: #e2e8f0;
            --color-error: #e53e3e;
            --color-success: #38a169;
            --color-marble: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.08);
            --marble-pattern: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23f1f3f9' fill-opacity='0.4' fill-rule='evenodd'/%3E%3C/svg%3E");
        }

        /* Variables para modo oscuro */
        [data-theme="dark"] {
            --color-background: #1a202c;
            --color-surface: #2d3748;
            --color-primary: #7c90db;
            --color-primary-light: #a3b1e8;
            --color-primary-dark: #5a6fca;
            --color-secondary: #8dd7bf;
            --color-text: #f7fafc;
            --color-text-light: #cbd5e0;
            --color-border: #4a5568;
            --color-error: #fc8181;
            --color-success: #68d391;
            --color-marble: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.2);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 10px 30px rgba(0, 0, 0, 0.4);
            --marble-pattern: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%234a5568' fill-opacity='0.2' fill-rule='evenodd'/%3E%3C/svg%3E");
        }

        /* Reset y estilos base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--color-background);
            color: var(--color-text);
            min-height: 100vh;
            transition: background-color 0.3s ease, color 0.3s ease;
            line-height: 1.5;
        }

        /* Contenedor con efecto mármol */
        .marble-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-image: var(--marble-pattern), var(--color-marble);
            background-size: 300px, cover;
            background-attachment: fixed;
            position: relative;
            overflow-x: hidden;
        }

        /* Encabezado con logo */
        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem 2rem;
            background-color: var(--color-surface);
            box-shadow: var(--shadow-sm);
            position: relative;
            z-index: 10;
            animation: slideDown 0.5s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .main-logo {
            height: 50px;
            width: auto;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
            transition: transform 0.3s ease;
        }

        .main-logo:hover {
            transform: scale(1.05);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .clinic-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--color-text);
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .clinic-subname {
            font-size: 1rem;
            font-weight: 500;
            color: var(--color-primary);
            letter-spacing: 0.5px;
        }

        /* Control de tema */
        .theme-toggle {
            position: relative;
        }

        .theme-btn {
            background: var(--color-primary-light);
            border: none;
            border-radius: 50%;
            width: 44px;
            height: 44px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
            position: relative;
        }

        .theme-btn:hover {
            background: var(--color-primary);
            transform: rotate(15deg);
        }

        .theme-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            transition: opacity 0.3s ease, transform 0.3s ease;
        }

        .sun-icon {
            color: #f6ad55;
            opacity: 1;
        }

        .moon-icon {
            color: #a3b1e8;
            opacity: 0;
        }

        [data-theme="dark"] .sun-icon {
            opacity: 0;
        }

        [data-theme="dark"] .moon-icon {
            opacity: 1;
        }

        /* Contenido principal */
        .login-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            animation: fadeIn 0.8s ease-out 0.2s both;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Tarjeta de login */
        .login-card {
            background-color: var(--color-surface);
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 420px;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--color-border);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] .login-card:hover {
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }

        /* Encabezado de la tarjeta */
        .card-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .welcome-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            font-size: 0.95rem;
            color: var(--color-text-light);
            font-weight: 400;
        }

        /* Formulario */
        .login-form {
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .input-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            width: 20px;
            height: 20px;
            color: var(--color-text-light);
            transition: color 0.3s ease;
            z-index: 1;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            background-color: var(--color-background);
            border: 1px solid var(--color-border);
            border-radius: 10px;
            color: var(--color-text);
            transition: all 0.3s ease;
            outline: none;
            position: relative;
        }

        .form-input::placeholder {
            color: transparent;
        }

        .form-input:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(124, 144, 219, 0.2);
        }

        .form-input:focus+.input-label,
        .form-input:not(:placeholder-shown)+.input-label {
            transform: translate(3rem, -2.2rem) scale(0.85);
            color: var(--color-primary);
            background: var(--color-surface);
            padding: 0 0.5rem;
        }

        .input-label {
            position: absolute;
            left: 3rem;
            top: 1rem;
            color: var(--color-text-light);
            font-size: 1rem;
            pointer-events: none;
            transition: all 0.3s ease;
            transform-origin: left top;
        }

        /* Botón para mostrar/ocultar contraseña */
        .password-toggle {
            position: absolute;
            right: 14px;
            background: none;
            border: none;
            color: var(--color-text-light);
            cursor: pointer;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.3s ease;
            outline: none;
        }

        .password-toggle:hover {
            color: var(--color-primary);
        }

        .eye-icon {
            width: 20px;
            height: 20px;
        }

        /* Mensaje de error */
        .error-message {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background-color: rgba(229, 62, 62, 0.1);
            border-left: 4px solid var(--color-error);
            border-radius: 8px;
            margin-bottom: 1.5rem;
            animation: shake 0.5s ease;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-5px);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateX(5px);
            }
        }

        .error-icon {
            width: 20px;
            height: 20px;
            color: var(--color-error);
            flex-shrink: 0;
        }

        /* Botón de envío */
        .submit-btn {
            width: 100%;
            padding: 1rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(124, 144, 219, 0.3);
            position: relative;
            overflow: hidden;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(124, 144, 219, 0.4);
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .btn-text {
            position: relative;
            z-index: 1;
        }

        .btn-icon {
            width: 20px;
            height: 20px;
            transition: transform 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .submit-btn:hover .btn-icon {
            transform: translateX(5px);
        }

        /* Estado de carga del botón */
        .submit-btn.loading {
            pointer-events: none;
            opacity: 0.8;
        }

        .submit-btn.loading .btn-text::after {
            content: '';
            display: inline-block;
            width: 12px;
            height: 12px;
            margin-left: 8px;
            border: 2px solid white;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Pie de tarjeta */
        .card-footer {
            text-align: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--color-border);
        }

        .help-text {
            font-size: 0.875rem;
            color: var(--color-text-light);
            margin-bottom: 0.75rem;
        }

        .copyright {
            font-size: 0.875rem;
            color: var(--color-text-light);
            font-weight: 300;
        }

        /* Indicador de carga */
        .loading-indicator {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: var(--color-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .app-header {
                padding: 1rem 1.5rem;
                flex-direction: column;
                gap: 1rem;
            }

            .login-card {
                padding: 2rem 1.5rem;
                margin: 0 1rem;
            }

            .welcome-title {
                font-size: 1.5rem;
            }

            .clinic-name {
                font-size: 1.125rem;
            }

            .clinic-subname {
                font-size: 0.875rem;
            }
        }

        @media (max-width: 480px) {
            .login-main {
                padding: 1rem;
            }

            .login-card {
                padding: 1.5rem 1.25rem;
            }

            .card-header {
                margin-bottom: 1.5rem;
            }

            .form-input {
                padding: 0.875rem 0.875rem 0.875rem 2.75rem;
                font-size: 0.95rem;
            }

            .input-icon {
                left: 12px;
                width: 18px;
                height: 18px;
            }

            .input-label {
                left: 2.75rem;
                top: 0.875rem;
                font-size: 0.95rem;
            }

            .form-input:focus+.input-label,
            .form-input:not(:placeholder-shown)+.input-label {
                transform: translate(2.75rem, -2rem) scale(0.85);
            }
        }

        /* Animación sutil para el contenedor */
        .marble-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.1) 50%, transparent 100%);
            opacity: 0.5;
            pointer-events: none;
            animation: shimmer 8s infinite linear;
        }

        @keyframes shimmer {
            0% {
                transform: translateX(-100%);
            }

            100% {
                transform: translateX(100%);
            }
        }

        /* Efecto de partículas sutiles para el fondo */
        .marble-container::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image:
                radial-gradient(circle at 20% 30%, rgba(124, 144, 219, 0.05) 0%, transparent 20%),
                radial-gradient(circle at 80% 70%, rgba(141, 215, 191, 0.05) 0%, transparent 20%),
                radial-gradient(circle at 40% 80%, rgba(124, 144, 219, 0.05) 0%, transparent 20%);
            pointer-events: none;
            z-index: 0;
        }

        /* Ajuste de contraste para modo oscuro */
        [data-theme="dark"] .form-input {
            background-color: rgba(255, 255, 255, 0.05);
        }

        /* Soporte para preferencias de movimiento reducido */
        @media (prefers-reduced-motion: reduce) {

            *,
            *::before,
            *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }

            .marble-container::before,
            .marble-container::after {
                display: none;
            }
        }
    </style>

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