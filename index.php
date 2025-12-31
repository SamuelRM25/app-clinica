<?php
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: php/dashboard/index.php");
    exit;
}
$page_title = "Login - CM Siloé";
// We don't include header.php here to have full control over the login layout without navbar/sidebar
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <!-- Custom Login CSS -->
    <link rel="stylesheet" href="assets/css/login-reengineered.css">
    <!-- Font -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-container">
        <!-- Left Side: Visual/Brand -->
        <div class="auth-side-art">
            <div class="auth-side-content">
                <img src="assets/img/siloe.png" alt="CM Siloé Logo" class="auth-side-logo animate__animated animate__zoomIn">
                <h2 class="animate__animated animate__fadeInUp animate__delay-1s">Servicios Médicos Siloé</h2>
                <p class="animate__animated animate__fadeInUp animate__delay-2s opacity-75">Bienestar y salud en buenas manos</p>
            </div>
        </div>

        <!-- Right Side: Login Form -->
        <div class="auth-form-container">
            <div class="card-title-group animate-delay-1">
                <h3 class="card-title">Bienvenido</h3>
                <p class="text-muted">Ingrese a su cuenta</p>
            </div>

            <form id="loginForm" action="php/auth/login.php" method="POST" class="animate-delay-2">
                
                <div class="form-floating-group mb-4">
                    <i class="bi bi-person input-icon"></i>
                    <input type="text" class="form-control form-control-styled" id="usuario" name="usuario" placeholder="Usuario" required>
                </div>

                <div class="form-floating-group mb-4">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" class="form-control form-control-styled" id="password" name="password" placeholder="Contraseña" required>
                    <button type="button" class="btn-toggle-password" id="togglePassword">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>

                <?php if(isset($_GET['error'])): ?>
                <div class="alert alert-danger animate__animated animate__shakeX d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <div>Credenciales incorrectas</div>
                </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-submit">
                    <i class="bi bi-box-arrow-in-right me-2"></i> INGRESAR
                </button>

            </form>

            <div class="copyright-text animate-delay-3">
                &copy; <?php echo date('Y'); ?> CM Siloé. Todos los derechos reservados.
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/login-reengineered.js"></script>

</body>
</html>