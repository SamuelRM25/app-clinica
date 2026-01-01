<?php
// inventory/index.php - Módulo de Ventas - Centro Médico Herrera Saenz
// Versión: 3.0 - Diseño Minimalista con Modo Noche y Efecto Mármol
session_start();

// Verificar sesión activa
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer zona horaria
date_default_timezone_set('America/Guatemala');

verify_session();

try {
    // Conectar a la base de datos
    $database = new Database();
    $conn = $database->getConnection();

    // Crear tabla de reservas si no existe
    $conn->exec("CREATE TABLE IF NOT EXISTS reservas_inventario (
        id_reserva INT AUTO_INCREMENT PRIMARY KEY,
        id_inventario INT NOT NULL,
        cantidad INT NOT NULL,
        session_id VARCHAR(255) NOT NULL,
        fecha_reserva TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (id_inventario),
        INDEX (session_id)
    )");

    // Limpiar reservas antiguas (> 60 minutos)
    $conn->exec("DELETE FROM reservas_inventario WHERE fecha_reserva < (NOW() - INTERVAL 1 HOUR)");
    
    // Obtener items de inventario para venta, restando items reservados
    $stmt = $conn->prepare("
        SELECT i.id_inventario, i.nom_medicamento, i.mol_medicamento, 
               i.presentacion_med, i.casa_farmaceutica, i.cantidad_med,
               (i.cantidad_med - COALESCE((SELECT SUM(cantidad) FROM reservas_inventario WHERE id_inventario = i.id_inventario), 0)) as disponible
        FROM inventario i
        WHERE i.cantidad_med > 0 AND i.estado != 'Pendiente'
        ORDER BY i.nom_medicamento
    ");
    $stmt->execute();
    $inventario = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Información del usuario
    $user_name = $_SESSION['nombre'];
    $user_type = $_SESSION['tipoUsuario'];
    $user_specialty = $_SESSION['especialidad'] ?? 'Profesional Médico';
    
    // Título de la página
    $page_title = "Ventas - Centro Médico Herrera Saenz";
    
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../../assets/img/Logo.png">
    
    <!-- Google Fonts - Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
    /* 
     * Módulo de Ventas - Centro Médico Herrera Saenz
     * Diseño: Fondo blanco, colores pastel, efecto mármol
     * Versión: 3.0
     */
    
    /* Variables CSS para modo claro y oscuro */
    :root {
        /* Modo claro (predeterminado) - Colores pastel */
        --color-background: #f8fafc;
        --color-surface: #ffffff;
        --color-primary: #7c90db;
        --color-primary-light: #a3b1e8;
        --color-primary-dark: #5a6fca;
        --color-secondary: #8dd7bf;
        --color-secondary-light: #b2e6d5;
        --color-accent: #f8b195;
        --color-text: #1e293b;
        --color-text-light: #64748b;
        --color-text-muted: #94a3b8;
        --color-border: #e2e8f0;
        --color-border-light: #f1f5f9;
        --color-error: #f87171;
        --color-warning: #fbbf24;
        --color-success: #34d399;
        --color-info: #38bdf8;
        
        /* Efecto mármol */
        --marble-bg: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        --marble-pattern: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23e2e8f0' fill-opacity='0.2' fill-rule='evenodd'/%3E%3C/svg%3E");
        
        /* Sombras sutiles */
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.07);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.08);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        
        /* Bordes redondeados */
        --radius-sm: 8px;
        --radius-md: 12px;
        --radius-lg: 16px;
        --radius-xl: 20px;
        
        /* Transiciones */
        --transition-fast: 150ms ease;
        --transition-normal: 250ms ease;
        --transition-slow: 350ms ease;
    }
    
    /* Variables para modo oscuro */
    [data-theme="dark"] {
        --color-background: #0f172a;
        --color-surface: #1e293b;
        --color-primary: #7c90db;
        --color-primary-light: #a3b1e8;
        --color-primary-dark: #5a6fca;
        --color-secondary: #8dd7bf;
        --color-secondary-light: #b2e6d5;
        --color-accent: #f8b195;
        --color-text: #f1f5f9;
        --color-text-light: #cbd5e1;
        --color-text-muted: #94a3b8;
        --color-border: #334155;
        --color-border-light: #1e293b;
        --color-error: #f87171;
        --color-warning: #fbbf24;
        --color-success: #34d399;
        --color-info: #38bdf8;
        
        /* Efecto mármol oscuro */
        --marble-bg: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        --marble-pattern: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%23334155' fill-opacity='0.2' fill-rule='evenodd'/%3E%3C/svg%3E");
        
        /* Sombras más sutiles en modo oscuro */
        --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.2);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.4);
        --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
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
        transition: background-color var(--transition-normal), color var(--transition-normal);
        line-height: 1.5;
        position: relative;
        overflow-x: hidden;
    }
    
    /* Fondo con efecto mármol sutil */
    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-image: var(--marble-pattern), var(--marble-bg);
        background-size: 300px, cover;
        background-attachment: fixed;
        z-index: -1;
        opacity: 0.8;
    }
    
    /* Contenedor principal */
    .dashboard-container {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        position: relative;
    }
    
    /* ============ HEADER SUPERIOR ============ */
    .dashboard-header {
        background: var(--color-surface);
        border-bottom: 1px solid var(--color-border);
        padding: 1rem 2rem;
        position: sticky;
        top: 0;
        z-index: 100;
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        animation: slideDown 0.4s ease-out;
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
    
    .header-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        max-width: 1400px;
        margin: 0 auto;
    }
    
    /* Logo y marca */
    .brand-container {
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .brand-logo {
        height: 48px;
        width: auto;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        transition: transform var(--transition-normal);
    }
    
    .brand-logo:hover {
        transform: scale(1.05);
    }
    
    .brand-text {
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
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--color-primary);
        letter-spacing: 0.5px;
    }
    
    /* Control de tema y usuario */
    .header-controls {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    
    /* Botón de cambio de tema */
    .theme-toggle {
        position: relative;
    }
    
    .theme-btn {
        background: transparent;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        width: 44px;
        height: 44px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all var(--transition-normal);
        color: var(--color-text);
        position: relative;
        overflow: hidden;
    }
    
    .theme-btn:hover {
        background: var(--color-primary-light);
        color: white;
        border-color: var(--color-primary);
        transform: rotate(15deg);
    }
    
    .theme-icon {
        width: 20px;
        height: 20px;
        transition: opacity var(--transition-normal), transform var(--transition-normal);
    }
    
    .sun-icon {
        color: var(--color-warning);
    }
    
    .moon-icon {
        color: var(--color-primary-light);
    }
    
    [data-theme="light"] .moon-icon {
        display: none;
    }
    
    [data-theme="dark"] .sun-icon {
        display: none;
    }
    
    /* Información del usuario */
    .user-info {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.5rem;
        border-radius: var(--radius-md);
        transition: background-color var(--transition-normal);
    }
    
    .user-info:hover {
        background: var(--color-border-light);
    }
    
    .user-avatar {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: white;
        font-size: 16px;
        flex-shrink: 0;
    }
    
    .user-details {
        display: flex;
        flex-direction: column;
    }
    
    .user-name {
        font-weight: 600;
        color: var(--color-text);
        font-size: 0.95rem;
    }
    
    .user-role {
        font-size: 0.8rem;
        color: var(--color-text-light);
    }
    
    /* ============ BARRA LATERAL ============ */
    .sidebar {
        width: 260px;
        background: var(--color-surface);
        border-right: 1px solid var(--color-border);
        position: fixed;
        top: 81px; /* Altura del header */
        left: 0;
        bottom: 0;
        z-index: 90;
        padding: 1.5rem;
        overflow-y: auto;
        transition: transform var(--transition-normal), width var(--transition-normal);
        animation: slideInLeft 0.5s ease-out;
    }
    
    @keyframes slideInLeft {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .sidebar.collapsed {
        width: 80px;
    }
    
    .sidebar.collapsed .nav-text {
        display: none;
    }
    
    /* Navegación */
    .nav-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    
    .nav-item {
        margin-bottom: 0.5rem;
    }
    
    .nav-link {
        display: flex;
        align-items: center;
        padding: 0.875rem 1rem;
        color: var(--color-text);
        text-decoration: none;
        border-radius: var(--radius-md);
        transition: all var(--transition-normal);
        font-weight: 500;
        position: relative;
        overflow: hidden;
    }
    
    .nav-link:hover {
        background: var(--color-border-light);
        color: var(--color-primary);
        transform: translateX(4px);
    }
    
    .sidebar.collapsed .nav-link:hover {
        transform: scale(1.05);
    }
    
    .nav-link.active {
        background: var(--color-primary);
        color: white;
        box-shadow: var(--shadow-md);
    }
    
    .nav-icon {
        font-size: 1.25rem;
        margin-right: 1rem;
        width: 24px;
        text-align: center;
        flex-shrink: 0;
    }
    
    .sidebar.collapsed .nav-icon {
        margin-right: 0;
        font-size: 1.35rem;
    }
    
    .nav-text {
        font-size: 0.95rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    /* Contenido principal */
    .main-content {
        margin-left: 260px;
        padding: 2rem;
        min-height: calc(100vh - 81px);
        transition: margin-left var(--transition-normal);
        max-width: 1400px;
        margin-right: auto;
        margin-left: auto;
        width: calc(100% - 260px);
    }
    
    .sidebar.collapsed ~ .main-content {
        margin-left: 80px;
        width: calc(100% - 80px);
    }
    
    /* ============ PUNTO DE VENTA ============ */
    .pos-container {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 1.5rem;
        height: calc(100vh - 180px);
        margin-top: 1.5rem;
        animation: fadeIn 0.6s ease-out;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }
    
    /* Área de búsqueda y selección */
    .pos-selection-area {
        overflow-y: auto;
        padding-right: 0.5rem;
    }
    
    .selection-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        height: 100%;
        transition: all var(--transition-normal);
    }
    
    .selection-card:hover {
        box-shadow: var(--shadow-lg);
    }
    
    .section-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--color-text);
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .section-title-icon {
        color: var(--color-primary);
    }
    
    /* Búsqueda */
    .search-container {
        position: relative;
        margin-bottom: 1rem;
    }
    
    .search-input {
        width: 100%;
        padding: 0.875rem 1rem 0.875rem 3rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface);
        color: var(--color-text);
        font-size: 0.95rem;
        transition: all var(--transition-normal);
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%2394a3b8' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: 1rem center;
        background-size: 1rem;
    }
    
    .search-input:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(124, 144, 219, 0.25);
    }
    
    /* Resultados de búsqueda */
    .search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        z-index: 200;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        box-shadow: var(--shadow-xl);
        max-height: 400px;
        overflow-y: auto;
        display: none;
        margin-top: 0.5rem;
        animation: slideDown 0.3s ease-out;
    }
    
    .search-result-item {
        padding: 1rem;
        border-bottom: 1px solid var(--color-border);
        cursor: pointer;
        transition: background-color var(--transition-fast);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .search-result-item:hover {
        background: var(--color-border-light);
    }
    
    .search-result-item:last-child {
        border-bottom: none;
    }
    
    .search-item-info {
        flex: 1;
    }
    
    .search-item-name {
        font-weight: 600;
        color: var(--color-text);
        margin-bottom: 0.25rem;
    }
    
    .search-item-details {
        font-size: 0.875rem;
        color: var(--color-text-light);
    }
    
    .search-item-stock {
        text-align: right;
    }
    
    .stock-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        background: rgba(52, 211, 153, 0.1);
        color: var(--color-success);
    }
    
    .stock-badge.warning {
        background: rgba(251, 191, 36, 0.1);
        color: var(--color-warning);
    }
    
    .stock-badge.danger {
        background: rgba(248, 113, 113, 0.1);
        color: var(--color-error);
    }
    
    /* Detalles de selección */
    .selection-details {
        background: var(--color-border-light);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-top: 1.5rem;
        display: none;
        animation: fadeIn 0.5s ease-out;
    }
    
    .selected-product {
        margin-bottom: 1.5rem;
    }
    
    .selected-product-name {
        font-size: 1.125rem;
        font-weight: 600;
        color: var(--color-text);
        margin-bottom: 0.25rem;
    }
    
    .selected-product-details {
        font-size: 0.875rem;
        color: var(--color-text-light);
    }
    
    /* Formulario de cantidad y precio */
    .selection-form {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        align-items: end;
    }
    
    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--color-text);
    }
    
    .form-input {
        padding: 0.75rem 1rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        background: var(--color-surface);
        color: var(--color-text);
        font-size: 0.95rem;
        transition: all var(--transition-normal);
    }
    
    .form-input:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px rgba(124, 144, 219, 0.25);
    }
    
    .form-input-group {
        display: flex;
        align-items: center;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        overflow: hidden;
    }
    
    .form-input-group .form-input {
        flex: 1;
        border: none;
        border-radius: 0;
    }
    
    .form-input-group .input-addon {
        padding: 0.75rem 1rem;
        background: var(--color-border-light);
        color: var(--color-text-light);
        font-size: 0.875rem;
        white-space: nowrap;
    }
    
    /* Botón agregar */
    .add-button {
        grid-column: span 2;
        padding: 0.875rem;
        background: var(--color-primary);
        color: white;
        border: none;
        border-radius: var(--radius-md);
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all var(--transition-normal);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .add-button:hover {
        background: var(--color-primary-dark);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    /* Área del carrito */
    .pos-cart-area {
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    
    .cart-card {
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        height: 100%;
        display: flex;
        flex-direction: column;
        transition: all var(--transition-normal);
    }
    
    .cart-card:hover {
        box-shadow: var(--shadow-lg);
    }
    
    /* Encabezado del carrito */
    .cart-header {
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--color-border);
    }
    
    .cart-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--color-text);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    /* Formulario del cliente */
    .client-form {
        margin-bottom: 1.5rem;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    
    /* Lista de items del carrito */
    .cart-items {
        flex: 1;
        overflow-y: auto;
        margin-bottom: 1.5rem;
        min-height: 200px;
    }
    
    .cart-items-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .cart-items-table th {
        text-align: left;
        padding: 0.75rem;
        font-weight: 600;
        color: var(--color-text-light);
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid var(--color-border);
        background: var(--color-border-light);
    }
    
    .tab-button.active {
        color: var(--color-primary);
        background: rgba(124, 144, 219, 0.1);
    }

    .cart-items-table td {
        padding: 1rem 0.75rem;
        border-bottom: 1px solid var(--color-border);
        color: var(--color-text);
        transition: background-color var(--transition-normal);
    }
    
    .cart-items-table tbody tr:hover td {
        background: var(--color-border-light);
    }
    
    .cart-item-product {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .cart-item-name {
        font-weight: 600;
        color: var(--color-text);
    }
    
    .cart-item-details {
        font-size: 0.875rem;
        color: var(--color-text-light);
    }
    
    .cart-item-quantity {
        text-align: center;
    }
    
    .quantity-badge {
        display: inline-block;
        padding: 0.375rem 0.75rem;
        background: var(--color-border-light);
        color: var(--color-text);
        border-radius: var(--radius-md);
        font-weight: 600;
        border: 1px solid var(--color-border);
    }
    
    .cart-item-price {
        text-align: right;
        font-weight: 600;
    }
    
    .cart-item-actions {
        text-align: right;
    }
    
    .remove-button {
        background: none;
        border: none;
        color: var(--color-error);
        cursor: pointer;
        padding: 0.5rem;
        border-radius: var(--radius-sm);
        transition: all var(--transition-normal);
        font-size: 0.875rem;
    }
    
    .remove-button:hover {
        background: rgba(248, 113, 113, 0.1);
        color: var(--color-error);
    }
    
    /* Carrito vacío */
    .empty-cart {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        text-align: center;
        color: var(--color-text-light);
    }
    
    .empty-cart-icon {
        font-size: 3rem;
        color: var(--color-border);
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    
    /* Total y acciones */
    .cart-footer {
        margin-top: auto;
    }
    
    .cart-total {
        background: var(--color-primary);
        color: white;
        padding: 1.5rem;
        border-radius: var(--radius-lg);
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow-md);
    }
    
    .total-label {
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        opacity: 0.9;
        margin-bottom: 0.5rem;
    }
    
    .total-amount {
        font-size: 2rem;
        font-weight: 700;
    }
    
    .cart-actions {
        display: flex;
        gap: 1rem;
    }
    
    .checkout-button {
        flex: 1;
        padding: 1rem;
        background: var(--color-success);
        color: white;
        border: none;
        border-radius: var(--radius-md);
        font-weight: 600;
        font-size: 1rem;
        cursor: pointer;
        transition: all var(--transition-normal);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }
    
    .checkout-button:hover {
        background: #10b981;
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    
    .notification {
        padding: 1rem;
        border-radius: var(--radius-md);
        margin-bottom: 1.5rem;
        font-weight: 500;
        animation: slideDown 0.4s ease-out;
    }

    .notification.error {
        background: rgba(248, 113, 113, 0.1);
        color: var(--color-text);
        border-left: 4px solid var(--color-error);
    }

    .notification.success {
        background: rgba(52, 211, 153, 0.1);
        color: var(--color-text);
        border-left: 4px solid var(--color-success);
    }
    
    .clear-button {
        padding: 1rem 1.5rem;
        background: var(--color-surface);
        color: var(--color-text);
        border: 1px solid var(--color-border);
        border-radius: var(--radius-md);
        font-weight: 600;
        font-size: 0.95rem;
        cursor: pointer;
        transition: all var(--transition-normal);
    }
    
    .clear-button:hover {
        background: var(--color-border-light);
    }
    
    /* ============ BOTÓN TOGGLE SIDEBAR ============ */
    .sidebar-toggle {
        position: fixed;
        bottom: 2rem;
        left: 280px;
        width: 40px;
        height: 40px;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 95;
        transition: all var(--transition-normal);
        box-shadow: var(--shadow-md);
        color: var(--color-text);
    }
    
    .sidebar-toggle:hover {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
        transform: scale(1.1);
    }
    
    .sidebar.collapsed ~ .sidebar-toggle {
        left: 100px;
    }
    
    /* ============ RESPONSIVE DESIGN ============ */
    @media (max-width: 1200px) {
        .main-content {
            padding: 1.5rem;
        }
        
        .pos-container {
            grid-template-columns: 1fr;
            height: auto;
        }
        
        .pos-selection-area {
            height: 500px;
        }
        
        .pos-cart-area {
            height: auto;
            min-height: 500px;
        }
    }
    
    @media (max-width: 992px) {
        .sidebar {
            transform: translateX(-100%);
            width: 280px;
        }
        
        .sidebar.show {
            transform: translateX(0);
        }
        
        .main-content {
            margin-left: 0;
            width: 100%;
        }
        
        .sidebar-toggle {
            display: none;
        }
        
        /* Botón móvil para mostrar sidebar */
        .mobile-sidebar-toggle {
            display: block;
            position: fixed;
            top: 1.5rem;
            left: 1.5rem;
            z-index: 101;
            width: 44px;
            height: 44px;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-md);
            color: var(--color-text);
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow-md);
        }
    }
    
    @media (min-width: 993px) {
        .mobile-sidebar-toggle {
            display: none;
        }
    }
    
    @media (max-width: 768px) {
        .dashboard-header {
            padding: 1rem;
        }
        
        .header-content {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
        }
        
        .header-controls {
            width: 100%;
            justify-content: space-between;
        }
        
        .main-content {
            padding: 1rem;
        }
        
        .selection-form {
            grid-template-columns: 1fr;
        }
        
        .add-button {
            grid-column: span 1;
        }
        
        .cart-actions {
            flex-direction: column;
        }
    }
    
    @media (max-width: 480px) {
        .selection-card {
            padding: 1.25rem;
        }
        
        .cart-card {
            padding: 1.25rem;
        }
        
        .section-title {
            font-size: 1.125rem;
        }
    }
    
    /* ============ EFECTOS DE MÁRMOL ANIMADOS ============ */
    .marble-effect {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        pointer-events: none;
        z-index: -1;
        opacity: 0.3;
        background-image: 
            radial-gradient(circle at 20% 30%, rgba(124, 144, 219, 0.05) 0%, transparent 30%),
            radial-gradient(circle at 80% 70%, rgba(141, 215, 191, 0.05) 0%, transparent 30%),
            radial-gradient(circle at 40% 80%, rgba(248, 177, 149, 0.05) 0%, transparent 30%);
        animation: marbleFloat 20s ease-in-out infinite;
    }
    
    @keyframes marbleFloat {
        0%, 100% {
            transform: translate(0, 0) rotate(0deg);
        }
        25% {
            transform: translate(10px, 5px) rotate(0.5deg);
        }
        50% {
            transform: translate(5px, 10px) rotate(-0.5deg);
        }
        75% {
            transform: translate(-5px, 5px) rotate(0.3deg);
        }
    }
    
    /* ============ PREFERENCIAS DE MOVIMIENTO REDUCIDO ============ */
    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
        
        .marble-effect {
            display: none;
        }
    }
    </style>
</head>
<body>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>
    
    <!-- Botón móvil para mostrar/ocultar sidebar -->
    <button class="mobile-sidebar-toggle" id="mobileSidebarToggle" aria-label="Mostrar/ocultar menú">
        <i class="bi bi-list"></i>
    </button>
    
    <div class="dashboard-container">
        <!-- Header superior -->
        <header class="dashboard-header">
            <div class="header-content">
                <!-- Logo y marca -->
                <div class="brand-container">
                    <img src="../../assets/img/herrerasaenz.png" alt="Centro Médico Herrera Saenz" class="brand-logo">
                </div>
                
                <!-- Controles del header -->
                <div class="header-controls">
                    <!-- Control de tema -->
                    <div class="theme-toggle">
                        <button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema claro/oscuro">
                            <i class="bi bi-sun theme-icon sun-icon"></i>
                            <i class="bi bi-moon theme-icon moon-icon"></i>
                        </button>
                    </div>
                    
                    <!-- Información del usuario -->
                    <div class="user-info">
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                        <div class="user-details">
                            <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                            <span class="user-role"><?php echo htmlspecialchars($user_specialty); ?></span>
                        </div>
                    </div>
                    
                    <!-- Botón de cerrar sesión -->
                    <a href="../auth/logout.php" class="action-btn logout-btn" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right"></i>
                        <span class="d-none d-md-inline">Salir</span>
                    </a>
                </div>
            </div>
        </header>
        
        <!-- Sidebar de navegación -->
        <nav class="sidebar" id="sidebar">
            <ul class="nav-menu">
                <?php $role = $user_type; ?>
                
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="../dashboard/index.php" class="nav-link">
                        <i class="bi bi-grid-1x2-fill nav-icon"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                </li>
                
                <!-- Pacientes -->
                <?php if (in_array($role, ['admin', 'doc', 'user'])): ?>
                <li class="nav-item">
                    <a href="../patients/index.php" class="nav-link">
                        <i class="bi bi-person-vcard nav-icon"></i>
                        <span class="nav-text">Pacientes</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Citas (admin y user) -->
                <?php if (in_array($role, ['admin', 'user'])): ?>
                <li class="nav-item">
                    <a href="../appointments/index.php" class="nav-link">
                        <i class="bi bi-calendar-heart nav-icon"></i>
                        <span class="nav-text">Citas</span>
                    </a>
                </li>
                
                <!-- Procedimientos menores -->
                <li class="nav-item">
                    <a href="../minor_procedures/index.php" class="nav-link">
                        <i class="bi bi-bandaid nav-icon"></i>
                        <span class="nav-text">Proc. Menores</span>
                    </a>
                </li>
                
                <!-- Exámenes -->
                <li class="nav-item">
                    <a href="../examinations/index.php" class="nav-link">
                        <i class="bi bi-clipboard2-pulse nav-icon"></i>
                        <span class="nav-text">Exámenes</span>
                    </a>
                </li>
                
                <!-- Dispensario -->
                <li class="nav-item">
                    <a href="../sales/index.php" class="nav-link active">
                        <i class="bi bi-capsule nav-icon"></i>
                        <span class="nav-text">Ventas</span>
                    </a>
                </li>
                
                <!-- Inventario -->
                <li class="nav-item">
                    <a href="../inventory/index.php" class="nav-link">
                        <i class="bi bi-box-seam nav-icon"></i>
                        <span class="nav-text">Inventario</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Compras, Ventas, Reportes (solo admin) -->
                <?php if ($role === 'admin'): ?>
                <li class="nav-item">
                    <a href="../purchases/index.php" class="nav-link">
                        <i class="bi bi-cart-check nav-icon"></i>
                        <span class="nav-text">Compras</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a href="../reports/index.php" class="nav-link">
                        <i class="bi bi-graph-up-arrow nav-icon"></i>
                        <span class="nav-text">Reportes</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <!-- Cobros (admin y user) -->
                <?php if (in_array($role, ['admin', 'user'])): ?>
                <li class="nav-item">
                    <a href="../billing/index.php" class="nav-link">
                        <i class="bi bi-credit-card-2-front nav-icon"></i>
                        <span class="nav-text">Cobros</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        
        <!-- Botón para colapsar/expandir sidebar (escritorio) -->
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Colapsar/expandir menú">
            <i class="bi bi-chevron-left" id="sidebarToggleIcon"></i>
        </button>
        
        <!-- Contenido principal -->
        <main class="main-content">
            <!-- Bienvenida -->
            <div class="stat-card mb-4" style="border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 1.5rem;">
                <div class="stat-header">
                    <div>
                        <h2 style="font-size: 1.75rem; margin-bottom: 0.5rem;">
                            <span id="greeting-text">Buenos días</span>, <?php echo htmlspecialchars($user_name); ?>
                        </h2>
                        <p class="text-muted">
                            <i class="bi bi-capsule me-1"></i> Módulo de Ventas
                            <span class="mx-2">•</span>
                            <i class="bi bi-calendar-check me-1"></i> <?php echo date('d/m/Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="bi bi-clock me-1"></i> <span id="current-time"><?php echo date('H:i'); ?></span>
                        </p>
                    </div>
                    <div class="d-none d-md-block">
                        <i class="bi bi-cart4 text-primary" style="font-size: 3rem; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            
            <!-- Punto de Venta -->
            <div class="pos-container">
                <!-- Panel izquierdo: Búsqueda y selección -->
                <div class="pos-selection-area">
                    <div class="selection-card">
                        <h3 class="section-title">
                            <i class="bi bi-search section-title-icon"></i>
                            Buscar Medicamentos
                        </h3>
                        
                        <!-- Búsqueda -->
                        <div class="search-container">
                            <input type="text" 
                                   class="search-input" 
                                   id="searchMedication" 
                                   placeholder="Escriba el nombre o molécula del medicamento..."
                                   autocomplete="off">
                            <div class="search-results" id="searchResults"></div>
                        </div>
                        
                        <!-- Detalles de selección -->
                        <div class="selection-details" id="selectionDetails">
                            <div class="selected-product">
                                <h4 class="selected-product-name" id="selectedProductName">---</h4>
                                <p class="selected-product-details" id="selectedProductDetails">---</p>
                            </div>
                            
                            <form class="selection-form" id="addToCartForm">
                                <div class="form-group">
                                    <label class="form-label">Precio Unitario</label>
                                    <div class="form-input-group">
                                        <span class="input-addon">Q</span>
                                        <input type="number" 
                                               class="form-input" 
                                               id="unitPrice" 
                                               step="0.01" 
                                               min="0" 
                                               required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Cantidad</label>
                                    <div class="form-input-group">
                                        <input type="number" 
                                               class="form-input" 
                                               id="quantity" 
                                               min="1" 
                                               value="1" 
                                               required>
                                        <span class="input-addon">
                                            Disp: <span id="availableStock" class="ms-1 fw-bold text-primary">0</span>
                                        </span>
                                    </div>
                                </div>
                                
                                <button type="button" class="add-button" id="addToCartBtn">
                                    <i class="bi bi-cart-plus"></i>
                                    Agregar al Carrito
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Panel derecho: Carrito de compras -->
                <div class="pos-cart-area">
                    <div class="cart-card">
                        <!-- Encabezado del carrito -->
                        <div class="cart-header">
                            <h3 class="cart-title">
                                <i class="bi bi-cart4"></i>
                                Carrito de Ventas
                            </h3>
                            
                            <!-- Datos del cliente -->
                            <div class="client-form">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label">Nombre del Cliente</label>
                                        <input type="text" 
                                               class="form-input" 
                                               id="clientName" 
                                               placeholder="Nombre completo del cliente...">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Método de Pago</label>
                                        <select class="form-input" id="paymentMethod">
                                            <option value="Efectivo">Efectivo</option>
                                            <option value="Tarjeta">Tarjeta</option>
                                            <option value="Transferencia">Transferencia</option>
                                            <option value="Seguro">Seguro Médico</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Lista de items -->
                        <div class="cart-items">
                            <div class="empty-cart" id="emptyCart">
                                <div class="empty-cart-icon">
                                    <i class="bi bi-cart-x"></i>
                                </div>
                                <h4 class="text-muted mb-2">Carrito Vacío</h4>
                                <p class="text-muted mb-3">Busque y agregue productos para realizar una venta.</p>
                            </div>
                            
                            <table class="cart-items-table" id="cartTable" style="display: none;">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th style="text-align: center;">Cant.</th>
                                        <th style="text-align: right;">Subtotal</th>
                                        <th style="width: 40px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="cartItemsBody">
                                    <!-- Items se insertarán aquí dinámicamente -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Total y acciones -->
                        <div class="cart-footer">
                            <div class="cart-total">
                                <div class="total-label">Total a Pagar</div>
                                <div class="total-amount" id="cartTotal">Q0.00</div>
                            </div>
                            
                            <div class="cart-actions">
                                <button class="clear-button" id="clearCartBtn">
                                    <i class="bi bi-trash"></i>
                                    Vaciar Carrito
                                </button>
                                
                                <button class="checkout-button" id="checkoutBtn">
                                    <i class="bi bi-printer-fill"></i>
                                    Procesar Venta
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- JavaScript -->
    <script>
    // Módulo de Ventas - Centro Médico Herrera Saenz
    // JavaScript para funcionalidades del punto de venta
    
    document.addEventListener('DOMContentLoaded', function() {
        // ============ REFERENCIAS A ELEMENTOS ============
        const themeSwitch = document.getElementById('themeSwitch');
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarToggleIcon = document.getElementById('sidebarToggleIcon');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const greetingElement = document.getElementById('greeting-text');
        const currentTimeElement = document.getElementById('current-time');
        
        // Elementos del POS
        const searchMedication = document.getElementById('searchMedication');
        const searchResults = document.getElementById('searchResults');
        const selectionDetails = document.getElementById('selectionDetails');
        const selectedProductName = document.getElementById('selectedProductName');
        const selectedProductDetails = document.getElementById('selectedProductDetails');
        const unitPrice = document.getElementById('unitPrice');
        const quantity = document.getElementById('quantity');
        const availableStock = document.getElementById('availableStock');
        const addToCartBtn = document.getElementById('addToCartBtn');
        const clientName = document.getElementById('clientName');
        const paymentMethod = document.getElementById('paymentMethod');
        const emptyCart = document.getElementById('emptyCart');
        const cartTable = document.getElementById('cartTable');
        const cartItemsBody = document.getElementById('cartItemsBody');
        const cartTotal = document.getElementById('cartTotal');
        const clearCartBtn = document.getElementById('clearCartBtn');
        const checkoutBtn = document.getElementById('checkoutBtn');
        
        // ============ DATOS GLOBALES ============
        let cartItems = [];
        let currentInventory = <?php echo json_encode($inventario); ?>;
        let selectedItem = null;
        
        // ============ FUNCIONALIDAD DEL TEMA ============
        
        // Inicializar tema desde localStorage o preferencias del sistema
        function initializeTheme() {
            const savedTheme = localStorage.getItem('dashboard-theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
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
            localStorage.setItem('dashboard-theme', newTheme);
            
            // Animación sutil en el botón
            themeSwitch.style.transform = 'rotate(180deg)';
            setTimeout(() => {
                themeSwitch.style.transform = 'rotate(0)';
            }, 300);
        }
        
        // ============ FUNCIONALIDAD DEL SIDEBAR ============
        
        // Restaurar estado del sidebar desde localStorage
        function initializeSidebar() {
            const sidebarCollapsed = localStorage.getItem('sidebar-collapsed');
            
            if (sidebarCollapsed === 'true') {
                sidebar.classList.add('collapsed');
                sidebarToggleIcon.classList.remove('bi-chevron-left');
                sidebarToggleIcon.classList.add('bi-chevron-right');
            }
        }
        
        // Colapsar/expandir sidebar
        function toggleSidebar() {
            const isCollapsed = sidebar.classList.toggle('collapsed');
            
            // Cambiar icono
            if (isCollapsed) {
                sidebarToggleIcon.classList.remove('bi-chevron-left');
                sidebarToggleIcon.classList.add('bi-chevron-right');
            } else {
                sidebarToggleIcon.classList.remove('bi-chevron-right');
                sidebarToggleIcon.classList.add('bi-chevron-left');
            }
            
            // Guardar estado
            localStorage.setItem('sidebar-collapsed', isCollapsed);
        }
        
        // Mostrar/ocultar sidebar en móvil
        function toggleMobileSidebar() {
            sidebar.classList.toggle('show');
            
            // Cerrar sidebar al hacer clic fuera en móvil
            if (sidebar.classList.contains('show')) {
                document.addEventListener('click', closeSidebarOnClickOutside);
            } else {
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }
        
        // Cerrar sidebar al hacer clic fuera (solo móvil)
        function closeSidebarOnClickOutside(event) {
            if (!sidebar.contains(event.target) && 
                !mobileSidebarToggle.contains(event.target) && 
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        }
        
        // ============ SALUDO DINÁMICO ============
        
        // Actualizar saludo según hora del día
        function updateGreeting() {
            const hour = new Date().getHours();
            let greeting = '';
            
            if (hour < 12) {
                greeting = 'Buenos días';
            } else if (hour < 19) {
                greeting = 'Buenas tardes';
            } else {
                greeting = 'Buenas noches';
            }
            
            if (greetingElement) {
                greetingElement.textContent = greeting;
            }
        }
        
        // ============ RELOJ EN TIEMPO REAL ============
        
        // Actualizar hora actual
        function updateCurrentTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-GT', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            
            if (currentTimeElement) {
                currentTimeElement.textContent = timeString;
            }
        }
        
        // ============ FUNCIONALIDAD DEL PUNTO DE VENTA ============
        
        // Búsqueda de medicamentos en tiempo real
        function performSearch(searchTerm) {
            searchResults.innerHTML = '';
            
            if (searchTerm.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            const term = searchTerm.toLowerCase();
            const results = currentInventory.filter(item => 
                item.nom_medicamento.toLowerCase().includes(term) || 
                item.mol_medicamento.toLowerCase().includes(term)
            ).slice(0, 10);
            
            if (results.length > 0) {
                searchResults.style.display = 'block';
                
                results.forEach(item => {
                    const resultItem = document.createElement('div');
                    resultItem.className = 'search-result-item';
                    
                    // Verificar si ya está en el carrito
                    const inCart = cartItems.some(cartItem => cartItem.id === item.id_inventario);
                    
                    // Determinar clase de stock
                    let stockClass = 'success';
                    if (item.disponible <= 0) stockClass = 'danger';
                    else if (item.disponible <= 5) stockClass = 'warning';
                    
                    resultItem.innerHTML = `
                        <div class="search-item-info">
                            <div class="search-item-name">${item.nom_medicamento}</div>
                            <div class="search-item-details">${item.mol_medicamento} • ${item.presentacion_med}</div>
                        </div>
                        <div class="search-item-stock">
                            <span class="stock-badge ${stockClass}">${item.disponible} disp.</span>
                        </div>
                    `;
                    
                    resultItem.addEventListener('click', () => selectProduct(item));
                    searchResults.appendChild(resultItem);
                });
            } else {
                searchResults.style.display = 'block';
                searchResults.innerHTML = '<div class="search-result-item text-muted text-center">No se encontraron resultados</div>';
            }
        }
        
        // Seleccionar producto
        function selectProduct(item) {
            selectedItem = item;
            
            // Actualizar interfaz
            selectedProductName.textContent = `${item.nom_medicamento} (${item.presentacion_med})`;
            selectedProductDetails.textContent = `${item.mol_medicamento} • ${item.casa_farmaceutica}`;
            availableStock.textContent = item.disponible;
            quantity.max = item.disponible;
            quantity.value = 1;
            
            // Obtener precio de venta
            getSalePrice(item.id_inventario).then(price => {
                unitPrice.value = price.toFixed(2);
            });
            
            // Mostrar detalles de selección
            selectionDetails.style.display = 'block';
            searchResults.style.display = 'none';
            searchMedication.value = item.nom_medicamento;
            
            // Enfocar en cantidad
            quantity.focus();
        }
        
        // Obtener precio de venta del producto
        async function getSalePrice(idInventario) {
            try {
                const response = await fetch(`get_precio.php?id_inventario=${idInventario}`);
                const data = await response.json();
                return data.status === 'success' ? parseFloat(data.precio_venta) : 0;
            } catch (error) {
                console.error('Error al obtener precio:', error);
                return 0;
            }
        }
        
        // Agregar producto al carrito
        function addToCart() {
            if (!selectedItem) return;
            
            const price = parseFloat(unitPrice.value);
            const qty = parseInt(quantity.value);
            const stock = parseInt(availableStock.textContent);
            
            // Validaciones
            if (isNaN(price) || price <= 0) {
                showAlert('Precio inválido', 'error');
                return;
            }
            
            if (isNaN(qty) || qty <= 0 || qty > stock) {
                showAlert('Cantidad inválida o insuficiente stock', 'error');
                return;
            }
            
            // Verificar si ya está en el carrito
            const existingIndex = cartItems.findIndex(item => item.id === selectedItem.id_inventario);
            
            if (existingIndex !== -1) {
                // Actualizar cantidad existente
                const newQty = cartItems[existingIndex].quantity + qty;
                if (newQty > stock) {
                    showAlert('La cantidad total excede el stock disponible', 'error');
                    return;
                }
                cartItems[existingIndex].quantity = newQty;
                cartItems[existingIndex].subtotal = newQty * price;
            } else {
                // Agregar nuevo item
                cartItems.push({
                    id: selectedItem.id_inventario,
                    name: selectedItem.nom_medicamento,
                    details: `${selectedItem.mol_medicamento} • ${selectedItem.presentacion_med}`,
                    price: price,
                    quantity: qty,
                    subtotal: price * qty
                });
            }
            
            // Actualizar interfaz del carrito
            updateCartDisplay();
            
            // Reservar stock
            reserveStock(selectedItem.id_inventario, 
                cartItems.find(item => item.id === selectedItem.id_inventario).quantity);
            
            // Resetear selección
            resetSelection();
            
            // Mostrar confirmación
            showAlert('Producto agregado al carrito', 'success');
        }
        
        // Reservar stock en el servidor
        async function reserveStock(idInventario, cantidad) {
            try {
                await fetch('reserve_item.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_inventario: idInventario, cantidad: cantidad })
                });
            } catch (error) {
                console.error('Error al reservar stock:', error);
            }
        }
        
        // Liberar stock del servidor
        async function releaseStock(idInventario) {
            try {
                await fetch('release_item.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_inventario: idInventario })
                });
            } catch (error) {
                console.error('Error al liberar stock:', error);
            }
        }
        
        // Actualizar display del carrito
        function updateCartDisplay() {
            // Actualizar tabla
            cartItemsBody.innerHTML = '';
            
            if (cartItems.length === 0) {
                emptyCart.style.display = 'flex';
                cartTable.style.display = 'none';
                cartTotal.textContent = 'Q0.00';
            } else {
                emptyCart.style.display = 'none';
                cartTable.style.display = 'table';
                
                let total = 0;
                
                cartItems.forEach((item, index) => {
                    total += item.subtotal;
                    
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>
                            <div class="cart-item-product">
                                <div class="cart-item-name">${item.name}</div>
                                <div class="cart-item-details">${item.details}</div>
                            </div>
                        </td>
                        <td class="cart-item-quantity">
                            <span class="quantity-badge">${item.quantity}</span>
                        </td>
                        <td class="cart-item-price">Q${item.subtotal.toFixed(2)}</td>
                        <td class="cart-item-actions">
                            <button class="remove-button" data-index="${index}">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    `;
                    
                    cartItemsBody.appendChild(row);
                });
                
                // Actualizar total
                cartTotal.textContent = `Q${total.toFixed(2)}`;
                
                // Agregar event listeners a botones de eliminar
                document.querySelectorAll('.remove-button').forEach(button => {
                    button.addEventListener('click', function() {
                        const index = parseInt(this.getAttribute('data-index'));
                        removeFromCart(index);
                    });
                });
            }
        }
        
        // Remover item del carrito
        function removeFromCart(index) {
            const removedItem = cartItems[index];
            
            // Liberar stock
            releaseStock(removedItem.id);
            
            // Remover del array
            cartItems.splice(index, 1);
            
            // Actualizar display
            updateCartDisplay();
            
            showAlert('Producto removido del carrito', 'info');
        }
        
        // Vaciar carrito
        function clearCart() {
            if (cartItems.length === 0) return;
            
            // Liberar todo el stock reservado
            cartItems.forEach(item => {
                releaseStock(item.id);
            });
            
            // Limpiar array
            cartItems = [];
            
            // Actualizar display
            updateCartDisplay();
            
            showAlert('Carrito vaciado', 'info');
        }
        
        // Procesar venta
        async function processSale() {
            // Validaciones
            if (cartItems.length === 0) {
                showAlert('El carrito está vacío', 'error');
                return;
            }
            
            if (!clientName.value.trim()) {
                showAlert('Ingrese el nombre del cliente', 'error');
                clientName.focus();
                return;
            }
            
            // Preparar datos de la venta
            const saleData = {
                nombre_cliente: clientName.value.trim(),
                tipo_pago: paymentMethod.value,
                total: cartItems.reduce((sum, item) => sum + item.subtotal, 0),
                estado: 'Pagado',
                items: cartItems.map(item => ({
                    id_inventario: item.id,
                    nombre: item.name,
                    cantidad: item.quantity,
                    precio_unitario: item.price,
                    subtotal: item.subtotal
                }))
            };
            
            // Mostrar carga
            Swal.fire({
                title: 'Procesando venta...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            
            try {
                // Enviar al servidor
                const response = await fetch('save_venta.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(saleData)
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Venta completada!',
                        text: 'Redirigiendo al comprobante...',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        // Abrir recibo en nueva pestaña
                        window.open(`print_receipt.php?id=${data.id_venta}`, '_blank');
                        
                        // Recargar página
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    });
                } else {
                    showAlert(data.message || 'Error al procesar la venta', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error de conexión con el servidor', 'error');
            }
        }
        
        // Resetear selección
        function resetSelection() {
            selectedItem = null;
            selectionDetails.style.display = 'none';
            searchMedication.value = '';
            searchResults.style.display = 'none';
            unitPrice.value = '';
            quantity.value = 1;
            availableStock.textContent = '0';
        }
        
        // Mostrar alerta
        function showAlert(message, type = 'info') {
            const colors = {
                success: '#34d399',
                error: '#f87171',
                warning: '#fbbf24',
                info: '#38bdf8'
            };
            
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: type,
                title: message,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                background: 'var(--color-surface)',
                color: 'var(--color-text)'
            });
        }
        
        // ============ INICIALIZACIÓN ============
        
        // Inicializar componentes
        initializeTheme();
        initializeSidebar();
        updateGreeting();
        updateCurrentTime();
        
        // Configurar intervalo para el reloj
        setInterval(updateCurrentTime, 60000);
        
        // ============ EVENT LISTENERS ============
        
        // Tema
        themeSwitch.addEventListener('click', toggleTheme);
        
        // Sidebar (escritorio)
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
        }
        
        // Sidebar (móvil)
        if (mobileSidebarToggle) {
            mobileSidebarToggle.addEventListener('click', toggleMobileSidebar);
        }
        
        // Búsqueda
        searchMedication.addEventListener('input', function() {
            performSearch(this.value);
        });
        
        // Cerrar resultados al hacer clic fuera
        document.addEventListener('click', function(event) {
            if (!searchMedication.contains(event.target) && !searchResults.contains(event.target)) {
                searchResults.style.display = 'none';
            }
        });
        
        // Agregar al carrito
        addToCartBtn.addEventListener('click', addToCart);
        
        // Permitir Enter en cantidad para agregar
        quantity.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addToCart();
            }
        });
        
        // Vaciar carrito
        clearCartBtn.addEventListener('click', clearCart);
        
        // Procesar venta
        checkoutBtn.addEventListener('click', processSale);
        
        // Cerrar sidebar al cambiar tamaño de ventana
        window.addEventListener('resize', function() {
            if (window.innerWidth > 992 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                document.removeEventListener('click', closeSidebarOnClickOutside);
            }
        });
        
        // ============ CONSOLA DE DESARROLLO ============
        
        console.log('Módulo de Ventas - Centro Médico Herrera Saenz');
        console.log('Versión: 3.0 - Diseño Minimalista con Efecto Mármol');
        console.log('Usuario: <?php echo htmlspecialchars($user_name); ?>');
        console.log('Productos disponibles: <?php echo count($inventario); ?>');
    });
    </script>
</body>
</html>