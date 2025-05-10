<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Clínica'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons - Updated CDN link -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    
    <!-- Preload Bootstrap Icons font to improve loading performance -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/fonts/bootstrap-icons.woff2" as="font" type="font/woff2" crossorigin>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../../assets/css/style.css">
    
    <style>
        /* Font display swap to handle slow network conditions */
        @font-face {
            font-family: 'Bootstrap Icons';
            font-display: swap;
            src: url("https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/fonts/bootstrap-icons.woff2") format("woff2");
        }
        
        /* Ensure icons have a fallback */
        .bi::before {
            font-family: 'Bootstrap Icons', sans-serif;
        }
        
        /* Add any additional custom styles here */
    </style>
</head>
<body>
