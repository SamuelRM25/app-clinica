<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'Clínica'); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- Preload fonts -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/fonts/bootstrap-icons.woff2" as="font" type="font/woff2" crossorigin>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <link rel="stylesheet" href="../../assets/css/themes.css">
    <?php
    $theme_head_path = __DIR__ . '/theme_head.php';
    if (file_exists($theme_head_path)) {
        include_once $theme_head_path;
    }
    ?>

    <!-- Mantenimiento de sesión (Global) -->
    <?php if (function_exists('output_keep_alive_script'))
        output_keep_alive_script(); ?>

    <!-- Seguridad y Protección de Código -->
    <?php
    $path_to_security = 'assets/js/security.js';
    $prefix = '';
    for ($i = 0; $i < 3; $i++) {
        if (file_exists($prefix . $path_to_security)) {
            $final_path = $prefix . $path_to_security;
            break;
        }
        $prefix .= '../';
    }
    ?>
    <script src="<?php echo $final_path ?? 'assets/js/security.js'; ?>"></script>
</head>

<body>