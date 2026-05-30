<?php
/**
 * index.php - Panel de Super Administrador
 */
session_start();

require_once __DIR__ . '/includes/functions.php';

// Buscar .env en múltiples ubicaciones (local, Hostinger, etc.)
$envPaths = [
  __DIR__ . '/.env',             // Local XAMPP (project root / public_html)
  __DIR__ . '/../.env',          // Hostinger: fuera de public_html/
  __DIR__ . '/../../.env',       // Hostinger: dos niveles arriba/
];
$envFile = null;
foreach ($envPaths as $path) {
  if (file_exists($path)) {
    $envFile = $path;
    break;
  }
}

if ($envFile) {
  $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    if (strpos(trim($line), '#') === 0)
      continue;
    putenv(trim($line));
  }
}

define('ADMIN_USER', getenv('ADMIN_USER') ?: 'superadmin');
define('ADMIN_PASS', getenv('ADMIN_PASS') ?: '');
define('ADMIN_PASS_HASH', getenv('ADMIN_PASS_HASH') ?: '');

csrf_token(); // Ensure CSRF token exists in session

// Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action'])) {
  verify_csrf_token();
  $valid = false;
  if (empty(ADMIN_USER)) {
    $login_error = 'Usuario administrador no configurado.';
  } elseif ($_POST['admin_user'] === ADMIN_USER) {
    if (!empty(ADMIN_PASS) && $_POST['admin_pass'] === ADMIN_PASS) {
      $valid = true;
    } elseif (!empty(ADMIN_PASS_HASH) && password_verify($_POST['admin_pass'], ADMIN_PASS_HASH)) {
      $valid = true;
    }
  }
  if ($valid) {
    session_regenerate_id(true);
    $_SESSION['superadmin'] = true;
  } else {
    $login_error = $login_error ?? 'Credenciales incorrectas.';
  }
}

// logout
if (isset($_GET['logout'])) {
  $_SESSION['superadmin'] = false;
  unset($_SESSION['superadmin']);
  header("Location: index.php");
  exit;
}

$logged_in = !empty($_SESSION['superadmin']);

$hospitales = [];
$solicitudes = [];
$db_error = null;

if ($logged_in) {
  $db_file = __DIR__ . '/config/database.php';

  if (!file_exists($db_file)) {
    $db_error = "El archivo de configuración de base de datos no se encuentra en el servidor. Ruta buscada: " . $db_file;
  } else {
    try {
      require_once $db_file;
      $db = new Database();
      $conn = $db->getConnection();

      // Cargar hospitales
      $hospitales = $conn->query("SELECT * FROM hospitales ORDER BY nombre")->fetchAll();

      // Cargar solicitudes
      $solicitudes = $conn->query("
                SELECT s.*, h.nombre AS hospital_nombre
                FROM solicitudes_modulos s
                JOIN hospitales h ON s.id_hospital = h.id_hospital
                ORDER BY s.estado ASC, s.fecha_solicitud DESC
            ")->fetchAll();

      // Decodificar módulos
      foreach ($hospitales as &$h) {
        $h['modulos_activos'] = json_decode($h['modulos_activos'], true) ?: ['core'];
      }
      unset($h);
      foreach ($solicitudes as &$s) {
        $s['modulos_solicitados'] = json_decode($s['modulos_solicitados'], true) ?: [];
      }
      unset($s);

      // ── MANEJO DE ACCIONES API (POST/GET) ──────────────────────────────
      $action = $_POST['action'] ?? $_GET['action'] ?? null;
      if ($action) {
        header('Content-Type: application/json');
        ob_clean(); // Limpiar cualquier output previo

        // CSRF validation for state-changing actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
          $csrf_token = $_POST['csrf_token'] ?? '';
          if (empty($csrf_token) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrf_token)) {
            echo json_encode(['status' => 'error', 'message' => 'Token CSRF inválido. Recargue la página.']);
            exit;
          }
        }

        try {
          if ($action === 'approve') {
            $id = (int) $_POST['id_solicitud'];
            $nota = trim($_POST['nota'] ?? '');
            $fecha_venc = $_POST['fecha_vencimiento'] ?: null;
            $s = $conn->prepare("SELECT * FROM solicitudes_modulos WHERE id_solicitud = ?");
            $s->execute([$id]);
            $sol = $s->fetch();
            if (!$sol)
              throw new Exception("Solicitud no encontrada");
            $mods = json_decode($sol['modulos_solicitados'], true);
            if (!in_array('core', $mods))
              array_unshift($mods, 'core');
            $conn->prepare("UPDATE hospitales SET modulos_activos=?, tipo_suscripcion=?, estado_suscripcion='Activo', fecha_vencimiento=? WHERE id_hospital=?")
              ->execute([json_encode($mods), $sol['tipo_suscripcion'], ($sol['tipo_suscripcion'] === 'De por vida' ? null : $fecha_venc), $sol['id_hospital']]);
            $conn->prepare("UPDATE solicitudes_modulos SET estado='Aprobada', nota_admin=?, fecha_respuesta=NOW() WHERE id_solicitud=?")
              ->execute([$nota, $id]);
            echo json_encode(['status' => 'success', 'message' => 'Aprobada correctamente']);
          } elseif ($action === 'reject') {
            $id = (int) $_POST['id_solicitud'];
            $nota = trim($_POST['nota'] ?? '');
            $conn->prepare("UPDATE solicitudes_modulos SET estado='Rechazada', nota_admin=?, fecha_respuesta=NOW() WHERE id_solicitud=?")
              ->execute([$nota, $id]);
            echo json_encode(['status' => 'success', 'message' => 'Rechazada']);
          } elseif ($action === 'update_modules') {
            $id_h = (int) $_POST['id_hospital'];
            $mods = json_decode($_POST['modulos'] ?? '[]', true);
            if (!in_array('core', $mods))
              array_unshift($mods, 'core');
            $conn->prepare("UPDATE hospitales SET modulos_activos=?, tipo_suscripcion=?, estado_suscripcion=?, fecha_vencimiento=? WHERE id_hospital=?")
              ->execute([json_encode($mods), $_POST['tipo_suscripcion'], $_POST['estado'], ($_POST['fecha_vencimiento'] ?: null), $id_h]);
            echo json_encode(['status' => 'success', 'message' => 'Actualizado']);
          } elseif ($action === 'create_hospital') {
            $nombre = trim($_POST['nombre']);
            $codigo = trim($_POST['codigo']);
            $stmt = $conn->prepare("INSERT INTO hospitales (nombre, codigo_hospital, modulos_activos, estado_suscripcion) VALUES (?, ?, '[\"core\"]', 'Prueba')");
            $stmt->execute([$nombre, $codigo]);
            echo json_encode(['status' => 'success', 'message' => 'Hospital creado']);
          } elseif ($action === 'create_user') {
            $id_h = (int) $_POST['id_hospital'];
            $user = trim($_POST['usuario']);
            $pass = trim($_POST['password']);
            $hashedPass = password_hash($pass, PASSWORD_DEFAULT);
            $nombre = trim($_POST['nombre']);
            $apellido = trim($_POST['apellido']);
            $especialidad = trim($_POST['especialidad'] ?? '');
            $tipo = $_POST['tipoUsuario'];
            $telefono = trim($_POST['telefono']);
            $email = trim($_POST['email'] ?? '');

            $h_stmt = $conn->prepare("SELECT nombre FROM hospitales WHERE id_hospital = ?");
            $h_stmt->execute([$id_h]);
            $clinica_nombre = $h_stmt->fetchColumn() ?: 'Clínica';

            $stmt = $conn->prepare("
                        INSERT INTO usuarios (id_hospital, usuario, password, nombre, apellido, especialidad, tipoUsuario, clinica, telefono, email) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
            $stmt->execute([$id_h, $user, $hashedPass, $nombre, $apellido, $especialidad, $tipo, $clinica_nombre, $telefono, $email]);
            echo json_encode(['status' => 'success', 'message' => 'Usuario creado']);
          } elseif ($action === 'get_users') {
            $id_h = (int) $_GET['id_hospital'];
            $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id_hospital = ?");
            $stmt->execute([$id_h]);
            echo json_encode($stmt->fetchAll());
          } elseif ($action === 'update_user') {
            $id_u = (int) $_POST['id_usuario'];
            $pass = trim($_POST['password'] ?? '');
            if ($pass !== '') {
              $hashedPass = password_hash($pass, PASSWORD_DEFAULT);
              $stmt = $conn->prepare("UPDATE usuarios SET usuario=?, password=?, nombre=?, apellido=?, especialidad=?, tipoUsuario=?, telefono=?, email=? WHERE idUsuario=?");
              $stmt->execute([$_POST['usuario'], $hashedPass, $_POST['nombre'], $_POST['apellido'], $_POST['especialidad'], $_POST['tipoUsuario'], $_POST['telefono'], $_POST['email'], $id_u]);
            } else {
              $stmt = $conn->prepare("UPDATE usuarios SET usuario=?, nombre=?, apellido=?, especialidad=?, tipoUsuario=?, telefono=?, email=? WHERE idUsuario=?");
              $stmt->execute([$_POST['usuario'], $_POST['nombre'], $_POST['apellido'], $_POST['especialidad'], $_POST['tipoUsuario'], $_POST['telefono'], $_POST['email'], $id_u]);
            }
            echo json_encode(['status' => 'success', 'message' => 'Usuario actualizado']);
          } elseif ($action === 'delete_user') {
            $id_u = (int) $_POST['id_usuario'];
            $conn->prepare("DELETE FROM usuarios WHERE idUsuario = ?")->execute([$id_u]);
            echo json_encode(['status' => 'success', 'message' => 'Usuario eliminado']);
          } elseif ($action === 'delete_hospital') {
            $id_h = (int) $_POST['id_hospital'];
            // Primero borrar usuarios de ese hospital
            $conn->prepare("DELETE FROM usuarios WHERE id_hospital = ?")->execute([$id_h]);
            // Luego borrar hospital
            $conn->prepare("DELETE FROM hospitales WHERE id_hospital = ?")->execute([$id_h]);
            echo json_encode(['status' => 'success', 'message' => 'Hospital eliminado']);
          }
        } catch (Exception $e) {
          error_log("ADMIN API Error: " . $e->getMessage());
          echo json_encode(['status' => 'error', 'message' => 'Error al procesar la solicitud.']);
        }
        exit;
      }

    } catch (Exception $e) {
      error_log("ADMIN DB Error: " . $e->getMessage());
      $db_error = "Error de conexión con la base de datos.";
    }
  }
}

$all_modules = ['core', 'pharmacy', 'hospitalization', 'laboratory', 'inventory', 'imaging', 'purchases', 'sales', 'finances', 'reports'];
$module_labels = [
  'core' => 'Core',
  'pharmacy' => 'Farmacia',
  'hospitalization' => 'Hospitalización',
  'laboratory' => 'Laboratorio',
  'inventory' => 'Inventario',
  'imaging' => 'Imagenología',
  'purchases' => 'Compras',
  'sales' => 'Ventas',
  'finances' => 'Finanzas',
  'reports' => 'Reportes'
];
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Panel Administrativo — ClinicApp</title>
  <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <?php include 'includes/theme_head.php'; ?>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --s-sidebar: 250px;
      --s-radius: 12px;
      --s-radius-sm: 8px;
      --s-transition: 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    body {
      background: var(--color-bg);
      color: var(--color-text);
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      min-height: 100vh;
      overflow-x: hidden;
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
    }
    a { text-decoration: none; color: inherit; }

    /* ════════ SCROLLBAR ════════ */
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: var(--color-border); border-radius: 10px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--color-text-secondary); }

    /* ════════ LOGIN ════════ */
    .login-page {
      background: linear-gradient(135deg, #0b1120 0%, #1a2332 50%, #0f1a2e 100%);
      display: flex; align-items: center; justify-content: center;
      position: relative;
    }
    .login-page::before {
      content: ''; position: fixed; inset: 0;
      background:
        radial-gradient(ellipse at 20% 50%, rgba(59,130,246,0.08) 0%, transparent 60%),
        radial-gradient(ellipse at 80% 50%, rgba(139,92,246,0.06) 0%, transparent 60%);
      pointer-events: none;
    }
    .login-wrap {
      width: 100%; max-width: 410px; padding: 1.5rem; position: relative; z-index: 1;
    }
    .login-card {
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(255,255,255,0.07);
      border-radius: 16px;
      padding: 2.5rem 2rem;
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      box-shadow: 0 25px 60px -12px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.05);
    }
    .login-icon {
      width: 60px; height: 60px; margin: 0 auto 1.25rem;
      background: linear-gradient(135deg, #3b82f6, #8b5cf6);
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem; box-shadow: 0 8px 24px rgba(59,130,246,0.25);
    }
    .login-card h1 { font-size: 1.45rem; font-weight: 800; color: #f1f5f9; text-align: center; letter-spacing: -0.02em; }
    .login-card .sub { color: #64748b; font-size: 0.85rem; text-align: center; margin-bottom: 2rem; }
    .login-card .field { margin-bottom: 1.25rem; }
    .login-card .field label { display: block; font-size: 0.7rem; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem; }
    .login-card .field input {
      width: 100%; padding: 0.75rem 1rem;
      background: rgba(255,255,255,0.04); border: 1.5px solid rgba(255,255,255,0.08);
      border-radius: 10px; color: #f1f5f9; font-size: 0.9rem;
      transition: border-color 0.2s, box-shadow 0.2s; outline: none;
    }
    .login-card .field input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); background: rgba(255,255,255,0.06); }
    .login-card .field input::placeholder { color: #475569; }
    .btn-login {
      width: 100%; padding: 0.8rem; border: none; border-radius: 10px;
      background: linear-gradient(135deg, #3b82f6, #2563eb); color: #fff;
      font-size: 0.9rem; font-weight: 700; cursor: pointer;
      transition: transform 0.2s, box-shadow 0.2s;
      box-shadow: 0 4px 16px rgba(59,130,246,0.3);
    }
    .btn-login:hover { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(59,130,246,0.4); }
    .login-error {
      background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.15);
      border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 1.25rem;
      color: #fca5a5; font-size: 0.85rem;
    }

    /* ════════ LAYOUT ════════ */
    .app { display: flex; min-height: 100vh; }

    .sidebar {
      width: var(--s-sidebar); position: fixed; top: 0; left: 0; bottom: 0; z-index: 100;
      background: var(--color-bg);
      border-right: 1px solid var(--color-border);
      display: flex; flex-direction: column;
      transition: transform var(--s-transition);
    }
    .sidebar-brand {
      display: flex; align-items: center; gap: 0.75rem;
      padding: 1.25rem 1.25rem 1.75rem;
    }
    .sidebar-brand .brand-icon {
      width: 38px; height: 38px; border-radius: 10px;
      background: linear-gradient(135deg, var(--color-primary), #8b5cf6);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem; color: #fff;
      box-shadow: 0 4px 12px rgba(var(--color-primary-rgb), 0.25);
      flex-shrink: 0;
    }
    .sidebar-brand .brand-name { font-weight: 800; font-size: 1rem; color: var(--color-text); line-height: 1.2; }
    .sidebar-brand .brand-role { font-size: 0.6rem; text-transform: uppercase; letter-spacing: 0.06em; color: var(--color-text-secondary); opacity: 0.6; }
    .sidebar-nav { flex: 1; padding: 0 0.75rem; }
    .sidebar-nav .nav-label {
      font-size: 0.6rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;
      color: var(--color-text-secondary); padding: 1rem 0.5rem 0.5rem; opacity: 0.4;
    }
    .sidebar-nav a {
      display: flex; align-items: center; gap: 0.7rem;
      padding: 0.65rem 0.75rem; margin-bottom: 2px;
      border-radius: var(--s-radius-sm);
      color: var(--color-text-secondary); font-size: 0.85rem; font-weight: 500;
      transition: all 0.2s; position: relative;
    }
    .sidebar-nav a i { font-size: 1.05rem; width: 20px; text-align: center; flex-shrink: 0; }
    .sidebar-nav a:hover { background: rgba(var(--color-primary-rgb), 0.06); color: var(--color-primary); }
    .sidebar-nav a.active {
      background: rgba(var(--color-primary-rgb), 0.1);
      color: var(--color-primary); font-weight: 600;
    }
    .sidebar-nav a.active::before {
      content: ''; position: absolute; left: -0.75rem; top: 50%; transform: translateY(-50%);
      width: 3px; height: 18px; border-radius: 3px;
      background: var(--color-primary); box-shadow: 0 0 8px rgba(var(--color-primary-rgb), 0.4);
    }
    .sidebar-nav a .count {
      margin-left: auto; font-size: 0.6rem; font-weight: 700;
      background: #ef4444; color: #fff; padding: 0.15rem 0.45rem; border-radius: 50px;
      min-width: 20px; text-align: center;
    }
    .sidebar-footer {
      padding: 1rem 0.75rem 1.25rem;
      border-top: 1px solid var(--color-border);
    }
    .sidebar-footer .theme-row {
      display: flex; align-items: center; justify-content: space-between;
      padding: 0.4rem 0.5rem; font-size: 0.75rem; color: var(--color-text-secondary);
    }
    .theme-btn {
      width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--color-border);
      background: transparent; cursor: pointer; display: flex; align-items: center; justify-content: center;
      color: var(--color-text-secondary); transition: all 0.2s;
    }
    .theme-btn:hover { background: rgba(var(--color-primary-rgb), 0.06); color: var(--color-primary); }
    [data-theme="dark"] .sun-icon { display: none; }
    [data-theme="dark"] .moon-icon { display: inline !important; }
    .btn-logout {
      display: flex; align-items: center; gap: 0.6rem;
      padding: 0.5rem 0.75rem; margin-top: 0.5rem; border-radius: var(--s-radius-sm);
      font-size: 0.8rem; font-weight: 500; color: var(--color-text-secondary);
      transition: all 0.2s; cursor: pointer; border: none; background: none; width: 100%;
    }
    .btn-logout:hover { background: rgba(239,68,68,0.08); color: #ef4444; }

    .main {
      flex: 1; margin-left: var(--s-sidebar); padding: 2rem 2.5rem;
      min-height: 100vh; transition: margin-left var(--s-transition);
    }

    /* ════════ TOP BAR ════════ */
    .topbar {
      display: none; align-items: center; justify-content: space-between;
      padding: 0.75rem 1rem;
      background: var(--color-bg);
      border-bottom: 1px solid var(--color-border);
      position: sticky; top: 0; z-index: 99;
    }
    .topbar-brand {
      display: flex; align-items: center; gap: 0.6rem; font-weight: 700; font-size: 0.95rem;
    }
    .topbar-brand i { color: var(--color-primary); font-size: 1.2rem; }
    .menu-btn {
      width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--color-border);
      background: transparent; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.2rem; color: var(--color-text);
    }

    /* ════════ TABS ════════ */
    .tab { display: none; }

    /* ════════ SECTION HEADER ════════ */
    .section-hdr {
      display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.75rem; gap: 1rem;
    }
    .section-hdr h2 {
      font-size: 1.3rem; font-weight: 800; color: var(--color-text);
      display: flex; align-items: center; gap: 0.6rem; letter-spacing: -0.02em;
    }
    .section-hdr h2 i { color: var(--color-primary); }
    .section-hdr .actions { display: flex; gap: 0.5rem; flex-shrink: 0; }

    /* ════════ STATS GRID ════════ */
    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
    .stat {
      background: var(--color-card); border: 1px solid var(--color-border);
      border-radius: var(--s-radius); padding: 1.5rem;
      transition: transform 0.25s, box-shadow 0.25s;
      position: relative; overflow: hidden;
    }
    .stat::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
      background: linear-gradient(90deg, var(--color-primary), transparent);
      opacity: 0; transition: opacity 0.3s;
    }
    .stat:hover { transform: translateY(-3px); box-shadow: 0 8px 30px rgba(0,0,0,0.06); }
    .stat:hover::before { opacity: 1; }
    .stat .stat-label { font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text-secondary); margin-bottom: 0.4rem; }
    .stat .stat-val { font-size: 1.75rem; font-weight: 800; color: var(--color-text); letter-spacing: -0.02em; line-height: 1.1; }

    /* ════════ CARDS ════════ */
    .card {
      background: var(--color-card); border: 1px solid var(--color-border);
      border-radius: var(--s-radius); padding: 1.5rem;
    }

    /* ════════ BUTTONS ════════ */
    .btn {
      display: inline-flex; align-items: center; gap: 0.4rem;
      padding: 0.5rem 1rem; border-radius: var(--s-radius-sm);
      font-size: 0.8rem; font-weight: 600; border: none; cursor: pointer;
      transition: all 0.2s; line-height: 1.4;
    }
    .btn-primary { background: var(--color-primary); color: #fff; }
    .btn-primary:hover { filter: brightness(1.1); transform: translateY(-1px); }
    .btn-success { background: #10b981; color: #fff; }
    .btn-success:hover { filter: brightness(1.1); transform: translateY(-1px); }
    .btn-danger { background: #ef4444; color: #fff; }
    .btn-warning { background: #f59e0b; color: #fff; }
    .btn-ghost {
      background: transparent; color: var(--color-text-secondary);
      border: 1px solid var(--color-border);
    }
    .btn-ghost:hover { background: rgba(var(--color-primary-rgb), 0.05); color: var(--color-primary); border-color: var(--color-primary); }
    .btn-sm { padding: 0.35rem 0.65rem; font-size: 0.7rem; }
    .btn-xs { padding: 0.25rem 0.5rem; font-size: 0.65rem; border-radius: 6px; }

    /* ════════ TABLE ════════ */
    .table-wrap { overflow-x: auto; border: 1px solid var(--color-border); border-radius: var(--s-radius); }
    table { width: 100%; border-collapse: collapse; }
    table thead th {
      padding: 0.7rem 1rem; font-size: 0.65rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: 0.05em; color: var(--color-text-secondary);
      border-bottom: 2px solid var(--color-border); text-align: left; white-space: nowrap;
    }
    table tbody td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--color-border); font-size: 0.85rem; color: var(--color-text); vertical-align: middle; }
    table tbody tr:last-child td { border-bottom: none; }
    table tbody tr:hover { background: rgba(var(--color-primary-rgb), 0.02); }

    /* ════════ BADGES ════════ */
    .badge {
      display: inline-flex; align-items: center; padding: 0.2rem 0.65rem; border-radius: 50px;
      font-size: 0.65rem; font-weight: 700;
    }
    .badge-green { background: rgba(16,185,129,0.1); color: #10b981; }
    .badge-yellow { background: rgba(245,158,11,0.1); color: #f59e0b; }
    .badge-red { background: rgba(239,68,68,0.1); color: #ef4444; }
    .badge-gray { background: rgba(100,116,139,0.1); color: var(--color-text-secondary); }

    .badge-mod {
      display: inline-block; padding: 0.2em 0.55em; margin: 0.1rem;
      font-size: 0.6rem; font-weight: 600; border-radius: 6px;
      background: rgba(var(--color-primary-rgb), 0.07); color: var(--color-primary);
      border: 1px solid rgba(var(--color-primary-rgb), 0.12);
    }
    .badge-mod.on { background: rgba(16,185,129,0.08); color: #10b981; border-color: rgba(16,185,129,0.15); }

    /* ════════ FORM CONTROLS ════════ */
    .frm { margin-bottom: 1rem; }
    .frm label { display: block; font-size: 0.7rem; font-weight: 600; color: var(--color-text-secondary); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.35rem; }
    .frm input, .frm select, .frm textarea {
      width: 100%; padding: 0.6rem 0.85rem; font-size: 0.85rem; font-family: inherit;
      background: var(--color-card); border: 1.5px solid var(--color-border);
      border-radius: var(--s-radius-sm); color: var(--color-text);
      transition: border-color 0.2s, box-shadow 0.2s; outline: none;
    }
    .frm input:focus, .frm select:focus, .frm textarea:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb), 0.1); }

    /* ════════ MODALS ════════ */
    .modal-content.custom {
      background: var(--color-card); border: 1px solid var(--color-border);
      border-radius: 14px; box-shadow: 0 25px 60px -12px rgba(0,0,0,0.3);
    }
    .modal-content.custom .modal-header { border-bottom: 1px solid var(--color-border); padding: 1.25rem 1.5rem; }
    .modal-content.custom .modal-title { font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; }
    .modal-content.custom .modal-body { padding: 1.5rem; }
    .modal-content.custom .modal-footer { border-top: 1px solid var(--color-border); padding: 1rem 1.5rem; }

    /* ════════ ALERT ════════ */
    .alert {
      padding: 0.85rem 1.1rem; border-radius: var(--s-radius-sm);
      font-size: 0.85rem; margin-bottom: 1rem;
    }
    .alert-red { background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.12); color: var(--color-text); }

    /* ════════ EMPTY ════════ */
    .empty {
      text-align: center; padding: 3rem 1rem; color: var(--color-text-secondary);
    }
    .empty i { font-size: 2.5rem; opacity: 0.15; display: block; margin-bottom: 0.75rem; }
    .empty p { font-size: 0.9rem; }

    /* ════════ REQUEST LIST ════════ */
    .req-item {
      display: flex; align-items: center; justify-content: space-between;
      padding: 0.9rem 1.25rem; margin-bottom: 0.4rem;
      border: 1px solid var(--color-border); border-radius: var(--s-radius-sm);
      transition: border-color 0.2s, background 0.2s;
    }
    .req-item:hover { border-color: rgba(var(--color-primary-rgb), 0.2); background: rgba(var(--color-primary-rgb), 0.02); }

    /* ════════ ACTION GROUP ════════ */
    .act-group { display: flex; gap: 0.25rem; flex-wrap: nowrap; }

    /* ════════ FILTER GROUP ════════ */
    .filter-bar { display: flex; gap: 0.35rem; flex-wrap: wrap; margin-bottom: 1rem; }

    /* ════════ CHECKBOX GRID ════════ */
    .check-grid { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .check-grid label { display: flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; cursor: pointer; }

    /* ════════ RESPONSIVE ════════ */
    @media (max-width: 991.98px) {
      .sidebar { transform: translateX(-100%); }
      .sidebar.open { transform: translateX(0); }
      .main { margin-left: 0; padding: 1.25rem; }
      .topbar { display: flex; }
    }
    @media (max-width: 767px) {
      .main { padding: 1rem; }
      .stats { grid-template-columns: 1fr 1fr; gap: 0.75rem; }
      .stat { padding: 1rem; }
      .stat .stat-val { font-size: 1.35rem; }
      .section-hdr { flex-direction: column; align-items: flex-start; }
      .req-item { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
      table thead th, table tbody td { padding: 0.5rem 0.6rem; font-size: 0.75rem; }
      .section-hdr h2 { font-size: 1.1rem; }
    }

    /* ════════ UTILITIES ════════ */
    .text-center { text-align: center; }
    .text-secondary { color: var(--color-text-secondary); }
    .fw-bold { font-weight: 700; }
    .fw-semibold { font-weight: 600; }
    .small { font-size: 0.75rem; }
    .d-flex { display: flex; }
    .gap-2 { gap: 0.5rem; }
    .align-items-center { align-items: center; }
    .justify-content-between { justify-content: space-between; }
    .mb-3 { margin-bottom: 0.75rem; }
    .mb-4 { margin-bottom: 1rem; }
    .me-1 { margin-right: 0.25rem; }
    .me-2 { margin-right: 0.5rem; }
    .ms-1 { margin-left: 0.25rem; }
    .ms-2 { margin-left: 0.5rem; }
    .mt-1 { margin-top: 0.25rem; }
    .mt-2 { margin-top: 0.5rem; }

    /* ════════ ANIMATIONS ════════ */
    @keyframes fadeUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .anim { animation: fadeUp 0.35s ease-out forwards; }
    .d1 { animation-delay: 0.05s; } .d2 { animation-delay: 0.1s; } .d3 { animation-delay: 0.15s; } .d4 { animation-delay: 0.2s; }
  </style>
</head>

<body class="<?php echo !$logged_in ? 'login-page' : ''; ?>">

<?php if (!$logged_in): ?>

  <!-- ════════ LOGIN ════════ -->
  <div class="login-wrap">
    <div class="login-card">
      <div class="login-icon">🔐</div>
      <h1>ClinicApp Admin</h1>
      <p class="sub">Panel de Súper Administrador</p>
      <?php if (!empty($login_error)): ?>
        <div class="login-error"><strong>Error:</strong> <?php echo $login_error; ?></div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="login_action" value="1">
        <?php echo csrf_field(); ?>
        <div class="field">
          <label>Usuario</label>
          <input type="text" name="admin_user" required autofocus placeholder="Ingrese su usuario">
        </div>
        <div class="field">
          <label>Contraseña</label>
          <input type="password" name="admin_pass" required placeholder="••••••••">
        </div>
        <button class="btn-login">Iniciar Sesión</button>
      </form>
    </div>
  </div>

<?php else: ?>

  <!-- ════════ TOP BAR (mobile) ════════ -->
  <div class="topbar">
    <button class="menu-btn" onclick="document.querySelector('.sidebar').classList.toggle('open')"><i class="bi bi-list"></i></button>
    <div class="topbar-brand"><i class="bi bi-shield-lock"></i>ClinicApp</div>
    <div></div>
  </div>

  <!-- ════════ SIDEBAR ════════ -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon"><i class="bi bi-shield-lock"></i></div>
      <div>
        <div class="brand-name">ClinicApp</div>
        <div class="brand-role">Admin Panel</div>
      </div>
    </div>

    <div class="sidebar-nav">
      <div class="nav-label">Navegación</div>
      <a href="#" class="active" data-tab="dashboard" onclick="return showTab('dashboard',this)"><i class="bi bi-grid"></i>Dashboard</a>
      <a href="#" data-tab="hospitals" onclick="return showTab('hospitals',this)"><i class="bi bi-building"></i>Hospitales</a>
      <?php $pend = array_filter($solicitudes, fn($s) => $s['estado'] === 'Pendiente'); ?>
      <a href="#" data-tab="requests" onclick="return showTab('requests',this)">
        <i class="bi bi-bell"></i><span>Solicitudes</span>
        <?php if (count($pend) > 0): ?><span class="count"><?php echo count($pend); ?></span><?php endif; ?>
      </a>
    </div>

    <div class="sidebar-footer">
      <div class="theme-row">
        <span><i class="bi bi-palette"></i>&nbsp; Apariencia</span>
        <button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema">
          <i class="bi bi-sun theme-icon sun-icon"></i>
          <i class="bi bi-moon theme-icon moon-icon" style="display:none"></i>
        </button>
      </div>
      <button class="btn-logout" onclick="location.href='?logout=1'"><i class="bi bi-box-arrow-left"></i>Cerrar Sesión</button>
    </div>
  </aside>

  <!-- ════════ MAIN ════════ -->
  <main class="main">

    <!-- ── TAB: DASHBOARD ── -->
    <div id="tab-dashboard" class="tab" style="display:block">
      <?php if ($db_error): ?>
        <div class="alert alert-red"><strong>Error de BD:</strong> <?php echo htmlspecialchars($db_error); ?></div>
      <?php endif; ?>

      <div class="section-hdr">
        <h2><i class="bi bi-speedometer2"></i>Resumen General</h2>
      </div>

      <div class="stats">
        <div class="stat anim d1">
          <div class="stat-label">Hospitales</div>
          <div class="stat-val"><?php echo count($hospitales); ?></div>
        </div>
        <div class="stat anim d2">
          <div class="stat-label">Pendientes</div>
          <div class="stat-val" style="color:#f59e0b"><?php echo count($pend); ?></div>
        </div>
        <div class="stat anim d3">
          <div class="stat-label">Activos</div>
          <div class="stat-val" style="color:#10b981"><?php echo count(array_filter($hospitales, fn($h) => $h['estado_suscripcion'] === 'Activo')); ?></div>
        </div>
        <div class="stat anim d4">
          <div class="stat-label">Vencidos / Inactivos</div>
          <div class="stat-val" style="color:#ef4444"><?php echo count(array_filter($hospitales, fn($h) => in_array($h['estado_suscripcion'], ['Vencido', 'Inactivo']))); ?></div>
        </div>
      </div>

      <?php if (count($pend) > 0): ?>
      <div class="card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
          <strong style="color:#f59e0b;font-size:0.85rem"><i class="bi bi-bell-fill"></i>&nbsp; Solicitudes Pendientes</strong>
          <button class="btn btn-ghost btn-sm" onclick="return showTab('requests',document.querySelector('[data-tab=requests]'))">Ver todas</button>
        </div>
        <?php foreach ($pend as $s): ?>
        <div class="req-item">
          <div>
            <strong><?php echo htmlspecialchars($s['hospital_nombre']); ?></strong>
            <span style="color:var(--color-text-secondary);font-size:0.75rem;margin-left:0.5rem"><?php echo htmlspecialchars($s['tipo_suscripcion'] ?? ''); ?></span>
            <div style="font-size:0.75rem;color:var(--color-text-secondary)"><?php echo date('d/m/Y H:i', strtotime($s['fecha_solicitud'])); ?></div>
          </div>
          <div style="display:flex;gap:0.35rem">
            <button class="btn btn-success btn-xs" onclick="openApprove(<?php echo $s['id_solicitud']; ?>,'<?php echo htmlspecialchars($s['hospital_nombre']); ?>','<?php echo htmlspecialchars($s['tipo_suscripcion'] ?? ''); ?>')"><i class="bi bi-check2"></i></button>
            <button class="btn btn-danger btn-xs" onclick="rejectReq(<?php echo $s['id_solicitud']; ?>)"><i class="bi bi-x"></i></button>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── TAB: HOSPITALES ── -->
    <div id="tab-hospitals" class="tab anim">
      <div class="section-hdr">
        <h2><i class="bi bi-building"></i>Hospitales Registrados</h2>
        <div class="actions">
          <button class="btn btn-primary" onclick="openNewHospital()" <?php echo $db_error ? 'disabled' : ''; ?>><i class="bi bi-plus-lg"></i>Agregar</button>
        </div>
      </div>

      <?php if ($db_error): ?>
        <div class="alert alert-red"><?php echo htmlspecialchars($db_error); ?></div>
      <?php elseif (empty($hospitales)): ?>
        <div class="empty"><i class="bi bi-building"></i><p>No hay hospitales registrados aún.</p></div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>ID</th><th>Nombre</th><th>Contacto</th><th>Teléfono</th><th>Suscripción</th><th>Acciones</th></tr>
          </thead>
          <tbody>
            <?php foreach ($hospitales as $h): ?>
            <tr>
              <td><?php echo $h['id_hospital']; ?></td>
              <td><strong><?php echo htmlspecialchars($h['nombre']); ?></strong></td>
              <td><?php echo htmlspecialchars($h['correo'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($h['telefono'] ?? '-'); ?></td>
              <td>
                <?php $st = $h['estado_suscripcion'] ?? 'Inactivo';
                $bc = match($st){'Activo'=>'badge-green','Vencido'=>'badge-red','Pendiente'=>'badge-yellow',default=>'badge-gray'}; ?>
                <span class="badge <?php echo $bc; ?>"><?php echo $st; ?></span>
              </td>
              <td>
                <div class="act-group">
                  <button class="btn btn-ghost btn-xs" title="Editar" onclick="openEditHospital(<?php echo $h['id_hospital']; ?>)"><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-success btn-xs" title="Suscripción" onclick="openSubscription(<?php echo $h['id_hospital']; ?>)"><i class="bi bi-credit-card"></i></button>
                  <button class="btn btn-ghost btn-xs" title="Dispensarios" onclick="window.location.href='php/dispensary/index.php?hospital_id=<?php echo $h['id_hospital']; ?>'"><i class="bi bi-shop"></i></button>
                  <button class="btn btn-warning btn-xs" title="Historial" onclick="openHistory(<?php echo $h['id_hospital']; ?>,'<?php echo htmlspecialchars($h['nombre'],ENT_QUOTES); ?>')"><i class="bi bi-clock-history"></i></button>
                  <button class="btn btn-danger btn-xs" title="Eliminar" onclick="deleteHospital(<?php echo $h['id_hospital']; ?>,'<?php echo htmlspecialchars($h['nombre'],ENT_QUOTES); ?>')"><i class="bi bi-trash"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div style="margin-top:1.5rem"></div>

      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Hospital</th><th>Código</th><th>Suscripción</th><th>Vencimiento</th><th>Módulos</th><th>Acciones</th></tr>
          </thead>
          <tbody>
            <?php foreach ($hospitales as $h): ?>
            <tr>
              <td><strong><?php echo htmlspecialchars($h['nombre']); ?></strong></td>
              <td style="font-family:monospace;font-size:0.8rem;color:var(--color-primary)"><?php echo htmlspecialchars($h['codigo_hospital'] ?? ''); ?></td>
              <td>
                <?php $c = match($h['estado_suscripcion']){'Activo'=>'badge-green','Vencido'=>'badge-red','Inactivo'=>'badge-red',default=>'badge-gray'}; ?>
                <span class="badge <?php echo $c; ?>"><?php echo htmlspecialchars($h['estado_suscripcion']); ?></span>
                <span class="badge badge-gray"><?php echo htmlspecialchars($h['tipo_suscripcion'] ?? '—'); ?></span>
              </td>
              <td style="font-size:0.8rem"><?php echo ($h['tipo_suscripcion']==='De por vida') ? '♾ Permanente' : htmlspecialchars($h['fecha_vencimiento'] ?? '—'); ?></td>
              <td><?php foreach($h['modulos_activos'] as $m): ?><span class="badge-mod on"><?php echo htmlspecialchars($module_labels[$m]??$m); ?></span><?php endforeach; ?></td>
              <td>
                <div class="act-group">
                  <button class="btn btn-ghost btn-xs" onclick='openEditHospital(<?php echo $h["id_hospital"]; ?>,<?php echo htmlspecialchars(json_encode($h),ENT_QUOTES); ?>)' title="Editar"><i class="bi bi-pencil"></i></button>
                  <button class="btn btn-success btn-xs" onclick="openCreateUser(<?php echo $h['id_hospital']; ?>,'<?php echo htmlspecialchars($h['nombre']); ?>')" title="Crear Usuario"><i class="bi bi-person-plus"></i></button>
                  <button class="btn btn-ghost btn-xs" onclick="viewUsers(<?php echo $h['id_hospital']; ?>,'<?php echo htmlspecialchars($h['nombre']); ?>')" title="Ver Usuarios"><i class="bi bi-people"></i></button>
                  <button class="btn btn-danger btn-xs" onclick="deleteHospital(<?php echo $h['id_hospital']; ?>,'<?php echo htmlspecialchars($h['nombre']); ?>')" title="Eliminar"><i class="bi bi-trash"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── TAB: SOLICITUDES ── -->
    <div id="tab-requests" class="tab anim">
      <div class="section-hdr">
        <h2><i class="bi bi-inbox"></i>Solicitudes de Módulos</h2>
      </div>

      <div class="filter-bar">
        <button class="btn btn-warning btn-sm" onclick="filterReqs('Pendiente')">Pendientes</button>
        <button class="btn btn-success btn-sm" onclick="filterReqs('Aprobada')">Aprobadas</button>
        <button class="btn btn-danger btn-sm" onclick="filterReqs('Rechazada')">Rechazadas</button>
        <button class="btn btn-ghost btn-sm" onclick="filterReqs('')">Todas</button>
      </div>

      <?php if (empty($solicitudes)): ?>
        <div class="empty"><i class="bi bi-inbox"></i><p>No hay solicitudes de registro.</p></div>
      <?php else: ?>
      <div class="table-wrap">
        <table id="reqTable">
          <thead>
            <tr><th>Hospital</th><th>Módulos</th><th>Tipo</th><th>Mensaje</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr>
          </thead>
          <tbody>
            <?php foreach ($solicitudes as $s): ?>
            <tr data-estado="<?php echo htmlspecialchars($s['estado']); ?>">
              <td><strong><?php echo htmlspecialchars($s['hospital_nombre']); ?></strong></td>
              <td><?php foreach($s['modulos_solicitados'] as $m): ?><span class="badge-mod"><?php echo htmlspecialchars($module_labels[$m]??$m); ?></span><?php endforeach; ?></td>
              <td><span class="badge badge-gray"><?php echo htmlspecialchars($s['tipo_suscripcion']); ?></span></td>
              <td style="font-size:0.8rem;color:var(--color-text-secondary);max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo htmlspecialchars($s['mensaje'] ?? '—'); ?></td>
              <td style="font-size:0.8rem"><?php echo date('d/m/Y H:i', strtotime($s['fecha_solicitud'])); ?></td>
              <td><?php $bc=match($s['estado']){'Aprobada'=>'badge-green','Rechazada'=>'badge-red',default=>'badge-yellow'}; ?><span class="badge <?php echo $bc; ?>"><?php echo htmlspecialchars($s['estado']); ?></span></td>
              <td>
                <div class="act-group">
                  <?php if ($s['estado']==='Pendiente'): ?>
                    <button class="btn btn-success btn-xs" onclick="openApprove(<?php echo $s['id_solicitud']; ?>,'<?php echo htmlspecialchars($s['hospital_nombre']); ?>','<?php echo htmlspecialchars($s['tipo_suscripcion']); ?>')"><i class="bi bi-check2"></i></button>
                    <button class="btn btn-danger btn-xs" onclick="rejectReq(<?php echo $s['id_solicitud']; ?>)"><i class="bi bi-x"></i></button>
                  <?php else: ?>
                    <span style="font-size:0.75rem;color:var(--color-text-secondary)"><?php echo htmlspecialchars($s['nota_admin'] ? substr($s['nota_admin'],0,40).'…' : '—'); ?></span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- ════════ MODALS ════════ -->

    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom">
          <div class="modal-header"><h5 class="modal-title"><i class="bi bi-check2-circle"></i>Aprobar Solicitud</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <p style="font-size:0.85rem;color:var(--color-text-secondary)">Hospital: <strong id="approveHospName" style="color:var(--color-text)"></strong></p>
            <input type="hidden" id="approveSolId">
            <div class="frm">
              <label>Fecha de Vencimiento</label>
              <input type="date" id="approveFechaVenc" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
              <small style="color:var(--color-text-secondary);font-size:0.75rem">Déjalo vacío si es "De por vida".</small>
            </div>
            <div class="frm">
              <label>Nota para el hospital (opcional)</label>
              <textarea id="approveNota" rows="2" placeholder="Ej: Activado correctamente."></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-success" onclick="submitApprove()"><i class="bi bi-check2-circle"></i>Aprobar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Hospital Modal -->
    <div class="modal fade" id="editHospModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content custom">
          <div class="modal-header"><h5 class="modal-title" id="hospModalTitle"><i class="bi bi-pencil-square"></i>Editar Hospital</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <input type="hidden" id="editHospId">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
              <div class="frm"><label>Nombre</label><input type="text" id="editNombre"></div>
              <div class="frm" id="codigoHospWrap"><label>Código</label><input type="text" id="editCodigo" placeholder="Ej: HOSP01"></div>
              <div class="frm edit-only"><label>Estado</label><select id="editEstado"><option>Activo</option><option>Inactivo</option><option>Vencido</option><option>Prueba</option></select></div>
              <div class="frm edit-only"><label>Tipo Suscripción</label><select id="editTipo"><option>Mensual</option><option>Anual</option><option>De por vida</option></select></div>
              <div class="frm edit-only"><label>Fecha Vencimiento</label><input type="date" id="editVenc"></div>
            </div>
            <div class="frm edit-only">
              <label>Módulos Activos</label>
              <div class="check-grid">
                <?php foreach ($all_modules as $m): ?>
                <label><input class="edit-mod-check" type="checkbox" value="<?php echo $m; ?>" id="em_<?php echo $m; ?>" <?php if($m==='core') echo 'checked disabled'; ?>>&nbsp;<?php echo $module_labels[$m]; ?></label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-primary" onclick="submitHosp()"><i class="bi bi-save"></i>Guardar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content custom">
          <div class="modal-header"><h5 class="modal-title"><i class="bi bi-person-plus"></i>Crear Nuevo Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <input type="hidden" id="userHospId">
            <p style="font-size:0.85rem;color:var(--color-text-secondary);margin-bottom:1rem">Hospital: <strong id="userHospName" style="color:var(--color-text)"></strong></p>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
              <div class="frm"><label>Usuario</label><input type="text" id="newUserLogin" placeholder="ej: admin1"></div>
              <div class="frm"><label>Contraseña</label><input type="text" id="newUserPass"></div>
              <div class="frm"><label>Nombre</label><input type="text" id="newUserName"></div>
              <div class="frm"><label>Apellido</label><input type="text" id="newUserApe"></div>
              <div class="frm"><label>Tipo</label><select id="newUserTipo"><option value="admin">Administrador</option><option value="doc">Doctor</option><option value="user">Usuario Regular</option></select></div>
              <div class="frm"><label>Especialidad</label><input type="text" id="newUserEsp" placeholder="ej: Pediatría"></div>
              <div class="frm"><label>Teléfono</label><input type="text" id="newUserTel"></div>
              <div class="frm"><label>Email</label><input type="email" id="newUserEmail"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-success" onclick="submitCreateUser()">Guardar Usuario</button>
          </div>
        </div>
      </div>
    </div>

    <!-- View Users Modal -->
    <div class="modal fade" id="viewUsersModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content custom">
          <div class="modal-header"><h5 class="modal-title"><i class="bi bi-people"></i>Usuarios del Hospital</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <p style="font-size:0.85rem;color:var(--color-text-secondary);margin-bottom:1rem">Lista de usuarios en: <strong id="viewUsersHospName" style="color:var(--color-text)"></strong></p>
            <div class="table-wrap">
              <table style="font-size:0.8rem">
                <thead><tr><th>Usuario</th><th>Nombre</th><th>Tipo</th><th>Especialidad</th><th>Acciones</th></tr></thead>
                <tbody id="viewUsersBody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content custom">
          <div class="modal-header"><h5 class="modal-title"><i class="bi bi-pencil"></i>Editar Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <input type="hidden" id="editUserId"><input type="hidden" id="editUserHospId">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
              <div class="frm"><label>Usuario</label><input type="text" id="editUserLogin"></div>
              <div class="frm"><label>Contraseña</label><input type="text" id="editUserPass"></div>
              <div class="frm"><label>Nombre</label><input type="text" id="editUserName"></div>
              <div class="frm"><label>Apellido</label><input type="text" id="editUserApe"></div>
              <div class="frm"><label>Tipo</label><select id="editUserTipo"><option value="admin">Administrador</option><option value="doc">Doctor</option><option value="user">Usuario Regular</option></select></div>
              <div class="frm"><label>Especialidad</label><input type="text" id="editUserEsp"></div>
              <div class="frm"><label>Teléfono</label><input type="text" id="editUserTel"></div>
              <div class="frm"><label>Email</label><input type="email" id="editUserEmail"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-ghost" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-warning" onclick="submitUpdateUser()">Guardar Cambios</button>
          </div>
        </div>
      </div>
    </div>

      <script>
        // ── CSRF TOKEN HELPER ──────────────────────────────────────────────────────
        function getCsrfToken() {
          const meta = document.querySelector('meta[name="csrf-token"]');
          return meta ? meta.getAttribute('content') : '';
        }

        // ── TAB NAVIGATION ─────────────────────────────────────────────────────────
        function showTab(name, el) {
          document.querySelectorAll('.tab').forEach(t => t.style.display = 'none');
          document.getElementById('tab-' + name).style.display = 'block';
          document.querySelectorAll('.sidebar-nav a').forEach(l => l.classList.remove('active'));
          if (el) el.classList.add('active');
          // Close sidebar on mobile
          document.querySelector('.sidebar')?.classList.remove('open');
          return false;
        }

        // ── FILTER REQUESTS ────────────────────────────────────────────────────────
        function filterReqs(estado) {
          document.querySelectorAll('#reqTable tbody tr').forEach(r => {
            r.style.display = (!estado || r.dataset.estado === estado) ? '' : 'none';
          });
        }

        // ── APPROVE ────────────────────────────────────────────────────────────────
        function openApprove(id, name, tipo) {
          document.getElementById('approveSolId').value = id;
          document.getElementById('approveHospName').textContent = name;
          if (tipo === 'De por vida') document.getElementById('approveFechaVenc').value = '';
          new bootstrap.Modal(document.getElementById('approveModal')).show();
        }

        async function submitApprove() {
          const fd = new FormData();
          fd.append('action', 'approve');
          fd.append('id_solicitud', document.getElementById('approveSolId').value);
          fd.append('nota', document.getElementById('approveNota').value);
          fd.append('fecha_vencimiento', document.getElementById('approveFechaVenc').value);
          fd.append('csrf_token', getCsrfToken());
          const r = await fetch(location.pathname, { method: 'POST', body: fd });
          const j = await r.json();
          bootstrap.Modal.getInstance(document.getElementById('approveModal')).hide();
          Swal.fire(j.status === 'success' ? 'Aprobado' : 'Error', j.message, j.status === 'success' ? 'success' : 'error')
            .then(() => location.reload());
        }

        async function rejectReq(id) {
          const { value: nota, isConfirmed } = await Swal.fire({
            title: 'Rechazar Solicitud',
            input: 'textarea', inputLabel: 'Motivo (opcional)',
            showCancelButton: true, confirmButtonText: 'Rechazar', cancelButtonText: 'Cancelar',
            confirmButtonColor: '#ef4444'
          });
          if (!isConfirmed) return;
          const fd = new FormData();
          fd.append('action', 'reject'); fd.append('id_solicitud', id); fd.append('nota', nota || '');
          fd.append('csrf_token', getCsrfToken());
          const r = await fetch(location.pathname, { method: 'POST', body: fd });
          const j = await r.json();
          Swal.fire(j.status === 'success' ? 'Rechazada' : 'Error', j.message, j.status).then(() => location.reload());
        }

        // ── HOSPITALES ─────────────────────────────────────────────────────────────
        function openNewHospital() {
          document.getElementById('editHospId').value = '';
          document.getElementById('editNombre').value = '';
          document.getElementById('editCodigo').value = '';
          document.getElementById('hospModalTitle').innerHTML = '<i class="bi bi-plus-circle me-2"></i>Nuevo Hospital';
          document.querySelectorAll('.edit-only').forEach(el => el.style.display = 'none');
          new bootstrap.Modal(document.getElementById('editHospModal')).show();
        }

        function openEditHospital(id, data) {
          document.getElementById('editHospId').value = id;
          document.getElementById('editNombre').value = data.nombre;
          document.getElementById('editCodigo').value = data.codigo_hospital || '';
          document.getElementById('editEstado').value = data.estado_suscripcion;
          document.getElementById('editTipo').value = data.tipo_suscripcion || 'Mensual';
          document.getElementById('editVenc').value = data.fecha_vencimiento || '';
          document.getElementById('hospModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Editar Hospital';
          document.querySelectorAll('.edit-only').forEach(el => el.style.display = '');

          const mods = typeof data.modulos_activos === 'string' ? JSON.parse(data.modulos_activos) : data.modulos_activos;
          document.querySelectorAll('.edit-mod-check').forEach(c => {
            c.checked = mods.includes(c.value);
          });
          new bootstrap.Modal(document.getElementById('editHospModal')).show();
        }

        async function submitHosp() {
          const id = document.getElementById('editHospId').value;
          const fd = new FormData();

          if (id) {
            // UPDATE
            const mods = [];
            document.querySelectorAll('.edit-mod-check:checked').forEach(c => mods.push(c.value));
            fd.append('action', 'update_modules');
            fd.append('id_hospital', id);
            fd.append('modulos', JSON.stringify(mods));
            fd.append('tipo_suscripcion', document.getElementById('editTipo').value);
            fd.append('estado', document.getElementById('editEstado').value);
            fd.append('fecha_vencimiento', document.getElementById('editVenc').value);
          } else {
            // CREATE
            fd.append('action', 'create_hospital');
            fd.append('nombre', document.getElementById('editNombre').value);
            fd.append('codigo', document.getElementById('editCodigo').value);
          }

          fd.append('csrf_token', getCsrfToken());
          const r = await fetch(location.pathname, { method: 'POST', body: fd });
          const j = await r.json();
          bootstrap.Modal.getInstance(document.getElementById('editHospModal')).hide();
          Swal.fire(j.status === 'success' ? 'Guardado' : 'Error', j.message, j.status).then(() => location.reload());
        }

        // ── USUARIOS ───────────────────────────────────────────────────────────────
        function openCreateUser(id, name) {
          document.getElementById('userHospId').value = id;
          document.getElementById('userHospName').textContent = name;
          document.getElementById('newUserLogin').value = '';
          document.getElementById('newUserPass').value = '';
          new bootstrap.Modal(document.getElementById('createUserModal')).show();
        }

        async function submitCreateUser() {
          const fd = new FormData();
          fd.append('action', 'create_user');
          fd.append('id_hospital', document.getElementById('userHospId').value);
          fd.append('usuario', document.getElementById('newUserLogin').value);
          fd.append('password', document.getElementById('newUserPass').value);
          fd.append('nombre', document.getElementById('newUserName').value);
          fd.append('apellido', document.getElementById('newUserApe').value);
          fd.append('tipoUsuario', document.getElementById('newUserTipo').value);
          fd.append('especialidad', document.getElementById('newUserEsp').value);
          fd.append('telefono', document.getElementById('newUserTel').value);
          fd.append('email', document.getElementById('newUserEmail').value);
          fd.append('csrf_token', getCsrfToken());

          const r = await fetch(location.pathname, { method: 'POST', body: fd });
          const j = await r.json();
          bootstrap.Modal.getInstance(document.getElementById('createUserModal')).hide();
          Swal.fire(j.status === 'success' ? 'Creado' : 'Error', j.message, j.status);
        }

        // ── VER/EDITAR/BORRAR USUARIOS ─────────────────────────────────────────────
        async function viewUsers(id, name) {
          document.getElementById('viewUsersHospName').textContent = name;
          refreshUserList(id);
          new bootstrap.Modal(document.getElementById('viewUsersModal')).show();
        }

        async function refreshUserList(id) {
          const body = document.getElementById('viewUsersBody');
          body.innerHTML = '<tr><td colspan="5" class="text-center">Cargando...</td></tr>';

          try {
            const r = await fetch(location.pathname + '?action=get_users&id_hospital=' + id);
            const users = await r.json();
            _tempUsers = users; // Store for the edit by ID function
            body.innerHTML = users.length ? '' : '<tr><td colspan="5" class="text-center text-secondary">No hay usuarios</td></tr>';
            users.forEach(u => {
              const tr = document.createElement('tr');
              const esc = s => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };
              tr.innerHTML = `
                <td>${esc(u.usuario)}</td>
                <td>${esc(u.nombre)} ${esc(u.apellido)}</td>
                <td><span class="badge badge-gray">${esc(u.tipoUsuario)}</span></td>
                <td>${esc(u.especialidad || '-')}</td>
                <td>
                    <button class="btn btn-warning btn-xs" onclick='openEditUserById(${u.idUsuario})'><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-danger btn-xs" onclick="deleteUser(${u.idUsuario}, ${u.id_hospital})"><i class="bi bi-trash"></i></button>
                </td>
            `;
              body.appendChild(tr);
            });
          } catch (e) {
            body.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error al cargar usuarios</td></tr>';
          }
        }

        function openEditUser(u) {
          document.getElementById('editUserId').value = u.idUsuario;
          document.getElementById('editUserHospId').value = u.id_hospital;
          document.getElementById('editUserLogin').value = u.usuario;
          document.getElementById('editUserPass').value = u.password;
          document.getElementById('editUserName').value = u.nombre;
          document.getElementById('editUserApe').value = u.apellido;
          document.getElementById('editUserTipo').value = u.tipoUsuario;
          document.getElementById('editUserEsp').value = u.especialidad || '';
          document.getElementById('editUserTel').value = u.telefono || '';
          document.getElementById('editUserEmail').value = u.email || '';
          new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        let _tempUsers = []; // Global to store currently viewed hospital users

        async function openEditUserById(id) {
          const u = _tempUsers.find(x => x.idUsuario == id);
          if (u) openEditUser(u);
        }

        async function submitUpdateUser() {
          const fd = new FormData();
          fd.append('action', 'update_user');
          fd.append('id_usuario', document.getElementById('editUserId').value);
          fd.append('usuario', document.getElementById('editUserLogin').value);
          fd.append('password', document.getElementById('editUserPass').value);
          fd.append('nombre', document.getElementById('editUserName').value);
          fd.append('apellido', document.getElementById('editUserApe').value);
          fd.append('tipoUsuario', document.getElementById('editUserTipo').value);
          fd.append('especialidad', document.getElementById('editUserEsp').value);
          fd.append('telefono', document.getElementById('editUserTel').value);
          fd.append('email', document.getElementById('editUserEmail').value);
          fd.append('csrf_token', getCsrfToken());

          const r = await fetch(location.pathname, { method: 'POST', body: fd });
          const j = await r.json();
          bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
          if (j.status === 'success') {
            Swal.fire('Actualizado', j.message, 'success');
            refreshUserList(document.getElementById('editUserHospId').value);
          } else {
            Swal.fire('Error', j.message, 'error');
          }
        }

        async function deleteUser(id, hospId) {
          if (!confirm('¿Estás seguro de eliminar este usuario?')) return;
          const fd = new FormData();
          fd.append('action', 'delete_user');
          fd.append('id_usuario', id);
          fd.append('csrf_token', getCsrfToken());
          const r = await fetch(location.pathname, { method: 'POST', body: fd });
          const j = await r.json();
          if (j.status === 'success') refreshUserList(hospId);
          else Swal.fire('Error', j.message, 'error');
        }

        async function deleteHospital(id, name) {
          const { isConfirmed } = await Swal.fire({
            title: '¿Eliminar Hospital?',
            text: `Se borrará "${name}" y TODOS sus usuarios. Esta acción es irreversible.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar todo',
            confirmButtonColor: '#ef4444'
          });
          if (!isConfirmed) return;
          const fd = new FormData();
          fd.append('action', 'delete_hospital');
          fd.append('id_hospital', id);
          fd.append('csrf_token', getCsrfToken());
          const r = await fetch(location.pathname, { method: 'POST', body: fd });
          const j = await r.json();
          Swal.fire(j.status === 'success' ? 'Eliminado' : 'Error', j.message, j.status).then(() => location.reload());
        }
      </script>
    <?php endif; ?>
</body>

</html>