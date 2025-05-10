<?php
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: php/dashboard/index.php");
    exit;
}
$page_title = "Login - Clínica";
include_once 'includes/header.php';
?>

<div class="auth-wrapper animate__animated animate__fadeIn">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-logo text-center mb-4 animate__animated animate__zoomIn animate__delay-1s">
                <img src="assets/img/logo.png" alt="Clínica Logo" class="mb-3 logo-pulse">
                <h1 class="h3 text-gradient">Sistema de Gestión Médica</h1>
                <p class="text-muted">Ingrese sus credenciales para continuar</p>
            </div>

            <div class="card border-0 shadow-lg animate__animated animate__fadeInUp animate__delay-1s">
                <div class="card-body p-4">
                    <form id="loginForm" action="php/auth/login.php" method="POST">
                        <div class="mb-4">
                            <label for="usuario" class="form-label">Usuario</label>
                            <div class="input-group input-group-lg form-floating-group">
                                <span class="input-group-text bg-primary text-white">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" class="form-control form-control-lg" id="usuario" name="usuario" placeholder="Nombre de usuario" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label">Contraseña</label>
                            <div class="input-group input-group-lg form-floating-group">
                                <span class="input-group-text bg-primary text-white">
                                    <i class="bi bi-lock"></i>
                                </span>
                                <input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="Contraseña" required>
                            </div>
                        </div>

                        <?php if(isset($_GET['error'])): ?>
                        <div class="alert alert-danger animate__animated animate__shakeX" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            Credenciales inválidas. Por favor intente nuevamente.
                        </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary w-100 btn-lg mt-4 btn-login-pulse">
                            <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
                        </button>
                    </form>
                </div>
            </div>

            <div class="text-center mt-4 animate__animated animate__fadeIn animate__delay-2s">
                <p class="text-muted">
                    © <?php echo date('Y'); ?> RS SOLUTION. Todos los derechos reservados.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Agregar Animate.css para animaciones -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

<style>
/* Estilos personalizados para la página de login */
:root {
    --primary-color: #3a56b7;
    --secondary-color: #1e3a8a;
    --accent-color: #2a9cc8;
    --success-color: #1cc88a;
    --background-color: #f8f9fc;
    --card-bg: #ffffff;
    --text-color: #2d3748;
    --text-muted-color: #4a5568;
    --shadow-color: rgba(0, 0, 0, 0.1);
    --input-bg: #ffffff;
    --input-text: #2d3748;
    --input-border: #cbd5e0;
}

body {
    background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
    min-height: 100vh;
    font-family: 'Nunito', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    color: var(--text-color);
}

.auth-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    padding: 2rem 1rem;
}

.auth-container {
    width: 100%;
    max-width: 450px;
}

.auth-card {
    background-color: transparent;
}

.card {
    border-radius: 1rem;
    overflow: hidden;
    transition: all 0.3s ease;
    background-color: var(--card-bg);
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.175);
}

.cursor-pointer {
    cursor: pointer;
}

.input-group-text {
    transition: all 0.3s ease;
}

.form-control {
    border-radius: 0.5rem;
    padding: 0.75rem 1.25rem;
    transition: all 0.3s ease;
    border: 1px solid var(--input-border);
    background-color: var(--input-bg);
    color: var(--input-text);
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.25rem rgba(58, 86, 183, 0.25);
}

.form-label {
    color: var(--text-color);
    font-weight: 600;
}

.btn-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
    border-radius: 0.5rem;
    padding: 0.75rem 1.25rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background-color: var(--secondary-color);
    border-color: var(--secondary-color);
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.logo-pulse {
    animation: pulse 2s infinite;
    max-width: 120px;
    height: auto;
}

.text-gradient {
    background: linear-gradient(90deg, #3a56b7, #2a9cc8);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    font-weight: 700;
}

.text-muted {
    color: var(--text-muted-color) !important;
}

.btn-login-pulse {
    position: relative;
    overflow: hidden;
}

.btn-login-pulse:after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 5px;
    height: 5px;
    background: rgba(255, 255, 255, 0.5);
    opacity: 0;
    border-radius: 100%;
    transform: scale(1, 1) translate(-50%);
    transform-origin: 50% 50%;
}

.btn-login-pulse:hover:after {
    animation: ripple 1s ease-out;
}

.hover-effect {
    transition: all 0.3s ease;
}

.hover-effect:hover {
    background-color: var(--primary-color);
    color: white;
}

.form-floating-group .form-control:focus {
    border-color: var(--primary-color);
    box-shadow: none;
}

.form-floating-group .form-control:focus + .form-floating-label {
    color: var(--primary-color);
}

/* Animación de pulso para el logo */
@keyframes pulse {
    0% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
    100% {
        transform: scale(1);
    }
}

/* Animación de ondas para el botón */
@keyframes ripple {
    0% {
        transform: scale(0, 0);
        opacity: 0.5;
    }
    20% {
        transform: scale(25, 25);
        opacity: 0.5;
    }
    100% {
        opacity: 0;
        transform: scale(40, 40);
    }
}

/* Media queries para responsividad */
@media (max-width: 576px) {
    .auth-container {
        max-width: 100%;
    }
    
    .card-body {
        padding: 1.5rem;
    }
    
    .btn-lg {
        padding: 0.5rem 1rem;
    }
    
    .h3 {
        font-size: 1.5rem;
    }
}

@media (max-width: 768px) {
    .auth-wrapper {
        padding: 1rem 0.5rem;
    }
}

@media (prefers-reduced-motion: reduce) {
    .animate__animated {
        animation: none !important;
    }
    
    .logo-pulse {
        animation: none !important;
    }
}

/* Modo oscuro */
@media (prefers-color-scheme: dark) {
    :root {
        --background-color: #1a1c23;
        --card-bg: #2d3748;
        --text-color: #f7fafc;
        --text-muted-color: #cbd5e0;
        --shadow-color: rgba(0, 0, 0, 0.3);
        --input-bg: #4a5568;
        --input-text: #f7fafc;
        --input-border: #718096;
    }
    
    body {
        background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
    }
    
    .text-gradient {
        background: linear-gradient(90deg, #63b3ed, #4fd1c5);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .input-group-text {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Funcionalidad para mostrar/ocultar contraseña
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    
    if (togglePassword && password) {
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // Cambiar el ícono
            this.querySelector('i').classList.toggle('bi-eye');
            this.querySelector('i').classList.toggle('bi-eye-slash');
        });
    }
    
    // Animación al enviar el formulario
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Iniciando...';
            submitBtn.disabled = true;
            
            // Permitir que el formulario se envíe normalmente
        });
    }
    
    // Efecto de entrada para los campos
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('input-group-focus');
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.classList.remove('input-group-focus');
        });
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>