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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <?php include 'includes/theme_head.php'; ?>
  <style>
    body {
      background-color: var(--color-bg);
      color: var(--color-text);
      font-family: var(--font-family);
      transition: background-color var(--transition-base), color var(--transition-base);
      min-height: 100vh;
    }

    .login-wrap {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      position: relative;
      z-index: 10;
    }

    .login-card {
      background: rgba(var(--color-card-rgb), 0.7);
      border: 1px solid var(--color-border);
      border-radius: 1.25rem;
      padding: 2.5rem;
      width: 100%;
      max-width: 400px;
      box-shadow: var(--shadow-xl);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
    }

    .login-card h2 {
      color: var(--color-primary);
      font-weight: 700;
    }

    .sidebar-glass {
      width: var(--sidebar-width);
      background: rgba(var(--color-card-rgb), 0.7) !important;
      backdrop-filter: blur(16px) saturate(180%);
      -webkit-backdrop-filter: blur(16px) saturate(180%);
      border-right: 1px solid var(--color-border);
      box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.1);
      padding: 1.5rem 1rem;
      min-height: 100vh;
      position: fixed;
      top: 0;
      left: 0;
      z-index: 100;
      display: flex;
      flex-direction: column;
      transition: background-color var(--transition-base), border-color var(--transition-base);
    }

    .sidebar-glass .brand {
      color: var(--color-primary);
      font-weight: 700;
      font-size: 1.2rem;
      padding: .5rem;
      margin-bottom: 2rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .sidebar-glass .nav-link {
      color: var(--color-text-secondary);
      border-radius: var(--radius-md);
      padding: .75rem 1rem;
      font-size: .95rem;
      font-weight: 500;
      transition: all var(--transition-base);
    }

    .sidebar-glass .nav-link:hover {
      background: rgba(var(--color-primary-rgb), 0.1);
      color: var(--color-primary);
      transform: translateX(4px);
    }

    .sidebar-glass .nav-link.active {
      background: var(--color-primary);
      color: white;
      box-shadow: 0 4px 12px rgba(var(--color-primary-rgb), 0.2);
    }

    .main-content-glass {
      margin-left: var(--sidebar-width);
      padding: var(--space-xl);
      min-height: 100vh;
      transition: all var(--transition-base);
    }

    .stat-card-glass {
      background: rgba(var(--color-card-rgb), 0.65);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-lg);
      padding: var(--space-lg);
      box-shadow: var(--shadow-md);
      transition: transform var(--transition-base), box-shadow var(--transition-base);
    }

    .stat-card-glass:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 40px 0 rgba(var(--color-primary-rgb), 0.1);
    }

    .stat-card-glass h6 {
      color: var(--color-text-secondary);
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 0.5rem;
    }

    .stat-card-glass .value {
      font-size: 2rem;
      font-weight: 700;
      color: var(--color-text);
    }

    .badge-mod {
      font-size: .7rem;
      padding: .25em .55em;
      border-radius: .3rem;
      background: rgba(var(--color-primary-rgb), 0.1);
      color: var(--color-primary);
      margin: .1rem;
      display: inline-block;
      font-weight: 600;
    }

    .badge-mod.active {
      background: rgba(var(--color-success-rgb), 0.1);
      color: var(--color-success);
    }

    .section-title {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--color-text);
      margin-bottom: 1.5rem;
    }

    .card-dark-glass {
      background: rgba(var(--color-card-rgb), 0.65);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-md);
      padding: 1.5rem;
    }

    .tab-dark .nav-link {
      color: var(--color-text-secondary);
      border: 0;
      border-bottom: 2px solid transparent;
      border-radius: 0;
      padding: .75rem 1rem;
      font-weight: 500;
      transition: all var(--transition-base);
    }

    .tab-dark .nav-link.active {
      color: var(--color-primary);
      border-bottom-color: var(--color-primary);
      background: transparent;
    }

    @media (max-width: 991.98px) {
      .sidebar-glass {
        width: 100%;
        height: auto;
        position: relative;
        min-height: auto;
        border-right: none;
        border-bottom: 1px solid var(--color-border);
        padding: 1rem;
      }

      .main-content-glass {
        margin-left: 0;
        padding: var(--space-md);
      }
    }
  </style>
</head>

<body>

  <?php if (!$logged_in): ?>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect" style="opacity: 0.85;"></div>
    <!-- ═══════════════ LOGIN ═══════════════ -->
    <div class="login-wrap">
      <div class="login-card">
        <div class="text-center mb-4">
          <div
            style="width:60px;height:60px;background:var(--color-primary);border-radius:1.25rem;display:flex;align-items:center;justify-content:center;font-size:1.75rem;margin:0 auto 1rem;box-shadow: 0 8px 24px rgba(var(--color-primary-rgb), 0.25);">
            🏥</div>
          <h2>ClinicApp Admin</h2>
          <p class="text-secondary small">Panel de Súper Administrador</p>
        </div>
        <?php if (!empty($login_error)): ?>
          <div class="alert alert-danger py-2"><?php echo $login_error; ?></div>
        <?php endif; ?>
        <form method="POST">
          <input type="hidden" name="login_action" value="1">
          <?php echo csrf_field(); ?>
          <div class="mb-3">
            <label class="form-label small text-secondary">Usuario</label>
            <input type="text" name="admin_user" class="form-control" required autofocus placeholder="Nombre de usuario">
          </div>
          <div class="mb-4">
            <label class="form-label small text-secondary">Contraseña</label>
            <input type="password" name="admin_pass" class="form-control" required placeholder="••••••••">
          </div>
          <button class="btn btn-primary w-100 fw-semibold py-2">Iniciar Sesión</button>
        </form>
      </div>
    </div>

  <?php else: ?>
    <!-- Efecto de mármol animado -->
    <div class="marble-effect"></div>
    <!-- ═══════════════ PANEL ADMIN ═══════════════ -->
    <!-- Sidebar -->
    <div class="sidebar sidebar-glass">
      <div class="brand"><i class="bi bi-shield-lock me-2"></i>ClinicApp Admin</div>
      <nav class="nav flex-column gap-2">
        <a href="#dashboard" class="nav-link active" onclick="showTab('dashboard',this)"><i
            class="bi bi-grid me-2"></i>Dashboard</a>
        <a href="#hospitals" class="nav-link" onclick="showTab('hospitals',this)"><i
            class="bi bi-building me-2"></i>Hospitales</a>
        <a href="#requests" class="nav-link" onclick="showTab('requests',this)">
          <i class="bi bi-bell me-2"></i>Solicitudes
          <?php $pend = array_filter($solicitudes, fn($s) => $s['estado'] === 'Pendiente'); ?>
          <?php if (count($pend) > 0): ?>
            <span class="badge bg-danger ms-1"><?php echo count($pend); ?></span>
          <?php endif; ?>
        </a>
      </nav>
      <div class="mt-auto pt-4 d-flex flex-column gap-3">
        <!-- Theme Toggle -->
        <div class="d-flex align-items-center justify-content-between px-2">
          <span class="small text-secondary fw-semibold">Apariencia</span>
          <div class="theme-toggle">
            <button id="themeSwitch" class="theme-btn" aria-label="Cambiar tema claro/oscuro">
              <i class="bi bi-sun theme-icon sun-icon"></i>
              <i class="bi bi-moon theme-icon moon-icon"></i>
            </button>
          </div>
        </div>
        <a href="?logout=1" class="nav-link text-danger"><i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión</a>
      </div>
    </div>

    <!-- Main -->
    <div class="main main-content-glass">

      <!-- ── TAB: Dashboard ── -->
      <div id="tab-dashboard">
        <?php if ($db_error): ?>
          <div class="alert alert-danger mb-4">
            <strong>Error de Base de Datos:</strong> <?php echo htmlspecialchars($db_error); ?>
          </div>
        <?php endif; ?>
        <h4 class="section-title mb-4">Resumen General</h4>
        <div class="row g-3 mb-4">
          <div class="col-md-3">
            <div class="stat-card-glass">
              <h6>Hospitales</h6>
              <div class="value"><?php echo count($hospitales); ?></div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-card-glass">
              <h6>Solicitudes Pendientes</h6>
              <div class="value text-warning"><?php echo count($pend); ?></div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-card-glass">
              <h6>Activos</h6>
              <div class="value text-success">
                <?php echo count(array_filter($hospitales, fn($h) => $h['estado_suscripcion'] === 'Activo')); ?></div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-card-glass">
              <h6>Vencidos / Inactivos</h6>
              <div class="value text-danger">
                <?php echo count(array_filter($hospitales, fn($h) => in_array($h['estado_suscripcion'], ['Vencido', 'Inactivo']))); ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Solicitudes recientes -->
        <?php if (count($pend) > 0): ?>
          <div class="card-dark-glass p-4 mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
              <h6 class="fw-bold mb-0 text-warning"><i class="bi bi-bell-fill me-2"></i>Solicitudes Pendientes</h6>
              <button class="btn btn-sm btn-outline-primary"
                onclick="showTab('requests', document.querySelector('[onclick*=requests]'))">Ver todas</button>
            </div>
            <?php foreach ($pend as $s): ?>
              <div class="d-flex align-items-center justify-content-between p-3 mb-2"
                style="background: rgba(var(--color-card-rgb), 0.4); border: 1px solid var(--color-border); border-radius:.5rem;">
                <div>
                  <strong><?php echo htmlspecialchars($s['hospital_nombre']); ?></strong>
                  <span class="text-secondary small ms-2"><?php echo htmlspecialchars($s['tipo_suscripcion'] ?? ''); ?></span>
                  <div class="small text-secondary"><?php echo date('d/m/Y H:i', strtotime($s['fecha_solicitud'])); ?></div>
                </div>
                <div class="d-flex gap-2">
                  <button class="btn btn-sm btn-success"
                    onclick="openApprove(<?php echo $s['id_solicitud']; ?>, '<?php echo htmlspecialchars($s['hospital_nombre']); ?>', '<?php echo htmlspecialchars($s['tipo_suscripcion'] ?? ''); ?>')"><i
                      class="bi bi-check2"></i></button>
                  <button class="btn btn-sm btn-danger" onclick="rejectReq(<?php echo $s['id_solicitud']; ?>)"><i
                      class="bi bi-x"></i></button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- ── TAB: Hospitales ── -->
      <div id="tab-hospitals" style="display:none;">
        <div class="d-flex align-items-center justify-content-between mb-4">
          <h4 class="section-title mb-0">Gestión de Hospitales</h4>
          <button class="btn btn-primary btn-sm" onclick="openNewHospital()"><i class="bi bi-plus-lg me-1"></i>Nuevo
            Hospital</button>
        </div>

        <div class="table-responsive">
          <table class="table data-table">
            <thead>
              <tr>
                <th>Hospital</th>
                <th>Código</th>
                <th>Suscripción</th>
                <th>Vencimiento</th>
                <th>Módulos Activos</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($hospitales as $h): ?>
                <tr>
                  <td class="fw-semibold"><?php echo htmlspecialchars($h['nombre']); ?></td>
                  <td><code class="text-info"><?php echo htmlspecialchars($h['codigo_hospital'] ?? ''); ?></code></td>
                  <td>
                    <?php
                    $c = match ($h['estado_suscripcion']) {
                      'Activo' => 'success', 'Vencido' => 'warning', 'Inactivo' => 'danger', default => 'secondary'
                    };
                    ?>
                    <span
                      class="status-badge <?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($h['estado_suscripcion']); ?></span>
                    <span
                      class="badge bg-secondary ms-1"><?php echo htmlspecialchars($h['tipo_suscripcion'] ?? '—'); ?></span>
                  </td>
                  <td class="small">
                    <?php echo ($h['tipo_suscripcion'] === 'De por vida') ? '♾ Permanente' : htmlspecialchars($h['fecha_vencimiento'] ?? '—'); ?>
                  </td>
                  <td>
                    <?php foreach ($h['modulos_activos'] as $m): ?>
                      <span class="badge-mod active"><?php echo htmlspecialchars($module_labels[$m] ?? $m); ?></span>
                    <?php endforeach; ?>
                  </td>
                  <td>
                    <div class="d-flex gap-1">
                      <button class="btn btn-sm btn-outline-primary"
                        onclick='openEditHospital(<?php echo $h["id_hospital"]; ?>, <?php echo htmlspecialchars(json_encode($h), ENT_QUOTES); ?>)'
                        title="Editar Hospital">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-success"
                        onclick="openCreateUser(<?php echo $h['id_hospital']; ?>, '<?php echo htmlspecialchars($h['nombre']); ?>')"
                        title="Crear Usuario">
                        <i class="bi bi-person-plus"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-info"
                        onclick="viewUsers(<?php echo $h['id_hospital']; ?>, '<?php echo htmlspecialchars($h['nombre']); ?>')"
                        title="Ver Usuarios">
                        <i class="bi bi-people"></i>
                      </button>
                      <button class="btn btn-sm btn-outline-danger"
                        onclick="deleteHospital(<?php echo $h['id_hospital']; ?>, '<?php echo htmlspecialchars($h['nombre']); ?>')"
                        title="Eliminar Hospital">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── TAB: Solicitudes ── -->
      <div id="tab-requests" style="display:none;">
        <h4 class="section-title mb-4">Solicitudes de Módulos</h4>

        <!-- Filtro por estado -->
        <div class="d-flex gap-2 mb-3">
          <button class="btn btn-sm btn-warning" onclick="filterReqs('Pendiente')">Pendientes</button>
          <button class="btn btn-sm btn-success" onclick="filterReqs('Aprobada')">Aprobadas</button>
          <button class="btn btn-sm btn-danger" onclick="filterReqs('Rechazada')">Rechazadas</button>
          <button class="btn btn-sm btn-secondary" onclick="filterReqs('')">Todas</button>
        </div>

        <div class="table-responsive">
          <table class="table data-table" id="reqTable">
            <thead>
              <tr>
                <th>Hospital</th>
                <th>Módulos Solicitados</th>
                <th>Tipo</th>
                <th>Mensaje</th>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($solicitudes as $s): ?>
                <tr data-estado="<?php echo htmlspecialchars($s['estado']); ?>">
                  <td class="fw-semibold"><?php echo htmlspecialchars($s['hospital_nombre']); ?></td>
                  <td>
                    <?php foreach ($s['modulos_solicitados'] as $m): ?>
                      <span class="badge-mod"><?php echo htmlspecialchars($module_labels[$m] ?? $m); ?></span>
                    <?php endforeach; ?>
                  </td>
                  <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($s['tipo_suscripcion']); ?></span>
                  </td>
                  <td class="small text-secondary"><?php echo htmlspecialchars($s['mensaje'] ?? '—'); ?></td>
                  <td class="small"><?php echo date('d/m/Y H:i', strtotime($s['fecha_solicitud'])); ?></td>
                  <td>
                    <?php
                    $c2 = match ($s['estado']) { 'Aprobada' => 'success', 'Rechazada' => 'danger', default => 'warning'};
                    echo "<span class='status-badge " . htmlspecialchars($c2) . "'>" . htmlspecialchars($s['estado']) . "</span>";
                    ?>
                  </td>
                  <td>
                    <?php if ($s['estado'] === 'Pendiente'): ?>
                      <div class="d-flex gap-1">
                        <button class="btn btn-xs btn-success btn-sm"
                          onclick="openApprove(<?php echo $s['id_solicitud']; ?>, '<?php echo htmlspecialchars($s['hospital_nombre']); ?>', '<?php echo htmlspecialchars($s['tipo_suscripcion']); ?>')"><i
                            class="bi bi-check2"></i> Aprobar</button>
                        <button class="btn btn-xs btn-danger  btn-sm"
                          onclick="rejectReq(<?php echo $s['id_solicitud']; ?>)"><i class="bi bi-x"></i> Rechazar</button>
                      </div>
                    <?php else: ?>
                      <span
                        class="text-secondary small"><?php echo htmlspecialchars($s['nota_admin'] ? substr($s['nota_admin'], 0, 40) . '…' : '—'); ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div><!-- /main -->

      <!-- ══ MODALS ══ -->

      <!-- Aprobar Solicitud -->
      <div class="modal fade" id="approveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title text-success fw-bold"><i class="bi bi-check2-circle me-2"></i>Aprobar Solicitud</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <p class="text-secondary small">Hospital: <strong id="approveHospName" class="text-white"></strong></p>
              <input type="hidden" id="approveSolId">
              <div class="mb-3">
                <label class="form-label small text-secondary">Fecha de Vencimiento</label>
                <input type="date" id="approveFechaVenc" class="form-control"
                  value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                <small class="text-secondary">Déjalo vacío si es "De por vida".</small>
              </div>
              <div>
                <label class="form-label small text-secondary">Nota para el hospital (opcional)</label>
                <textarea id="approveNota" class="form-control" rows="2"
                  placeholder="Ej: Activado correctamente. Gracias por su preferencia."></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button class="btn btn-success px-4 fw-semibold" onclick="submitApprove()"><i
                  class="bi bi-check2-circle me-1"></i>Aprobar</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Editar Hospital -->
      <div class="modal fade" id="editHospModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title fw-bold text-primary" id="hospModalTitle"><i
                  class="bi bi-pencil-square me-2"></i>Editar Hospital</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" id="editHospId">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Nombre</label>
                  <input type="text" id="editNombre" class="form-control">
                </div>
                <div class="col-md-6" id="codigoHospWrap">
                  <label class="form-label small text-secondary">Código del Hospital</label>
                  <input type="text" id="editCodigo" class="form-control" placeholder="Ej: HOSP01">
                </div>
                <div class="col-md-3 edit-only">
                  <label class="form-label small text-secondary">Estado</label>
                  <select id="editEstado" class="form-select">
                    <option>Activo</option>
                    <option>Inactivo</option>
                    <option>Vencido</option>
                    <option>Prueba</option>
                  </select>
                </div>
                <div class="col-md-3 edit-only">
                  <label class="form-label small text-secondary">Tipo Suscripción</label>
                  <select id="editTipo" class="form-select">
                    <option>Mensual</option>
                    <option>Anual</option>
                    <option>De por vida</option>
                  </select>
                </div>
                <div class="col-md-4 edit-only">
                  <label class="form-label small text-secondary">Fecha Vencimiento</label>
                  <input type="date" id="editVenc" class="form-control">
                </div>
                <div class="col-12 edit-only">
                  <label class="form-label small text-secondary mb-2">Módulos Activos</label>
                  <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($all_modules as $m): ?>
                      <div class="form-check">
                        <input class="form-check-input edit-mod-check" type="checkbox" value="<?php echo $m; ?>"
                          id="em_<?php echo $m; ?>" <?php if ($m === 'core')
                               echo 'checked disabled'; ?>>
                        <label class="form-check-label small text-secondary"
                          for="em_<?php echo $m; ?>"><?php echo $module_labels[$m]; ?></label>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button class="btn btn-primary px-4 fw-semibold" onclick="submitHosp()"><i
                  class="bi bi-save me-1"></i>Guardar</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Crear Usuario -->
      <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title fw-bold text-success"><i class="bi bi-person-plus me-2"></i>Crear Nuevo Usuario</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" id="userHospId">
              <p class="text-secondary small mb-4">Hospital: <strong id="userHospName" class="text-white"></strong></p>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Usuario (Login)</label>
                  <input type="text" id="newUserLogin" class="form-control" placeholder="ej: admin1">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Contraseña</label>
                  <input type="text" id="newUserPass" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Nombre</label>
                  <input type="text" id="newUserName" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Apellido</label>
                  <input type="text" id="newUserApe" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Tipo de Usuario</label>
                  <select id="newUserTipo" class="form-select">
                    <option value="admin">Administrador</option>
                    <option value="doc">Doctor</option>
                    <option value="user">Usuario Regular</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Especialidad (opcional)</label>
                  <input type="text" id="newUserEsp" class="form-control" placeholder="ej: Pediatría">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Teléfono</label>
                  <input type="text" id="newUserTel" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Email (opcional)</label>
                  <input type="email" id="newUserEmail" class="form-control">
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button class="btn btn-success px-4 fw-semibold" onclick="submitCreateUser()">Guardar Usuario</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Ver Usuarios -->
      <div class="modal fade" id="viewUsersModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-xl">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title fw-bold text-info"><i class="bi bi-people me-2"></i>Usuarios del Hospital</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <p class="text-secondary small">Lista de usuarios registrados en: <strong id="viewUsersHospName"
                  class="text-white"></strong></p>
              <div class="table-responsive">
                <table class="table data-table border-secondary small mt-2">
                  <thead>
                    <tr>
                      <th>Usuario</th>
                      <th>Nombre Completo</th>
                      <th>Tipo</th>
                      <th>Especialidad</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody id="viewUsersBody">
                    <!-- Cargado via JS -->
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Editar Usuario -->
      <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title fw-bold text-warning"><i class="bi bi-pencil me-2"></i>Editar Usuario</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" id="editUserId">
              <input type="hidden" id="editUserHospId">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Usuario</label>
                  <input type="text" id="editUserLogin" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Contraseña</label>
                  <input type="text" id="editUserPass" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Nombre</label>
                  <input type="text" id="editUserName" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Apellido</label>
                  <input type="text" id="editUserApe" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Tipo</label>
                  <select id="editUserTipo" class="form-select">
                    <option value="admin">Administrador</option>
                    <option value="doc">Doctor</option>
                    <option value="user">Usuario Regular</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Especialidad</label>
                  <input type="text" id="editUserEsp" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Teléfono</label>
                  <input type="text" id="editUserTel" class="form-control">
                </div>
                <div class="col-md-6">
                  <label class="form-label small text-secondary">Email</label>
                  <input type="email" id="editUserEmail" class="form-control">
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button class="btn btn-warning px-4 fw-semibold" onclick="submitUpdateUser()">Guardar Cambios</button>
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
          document.querySelectorAll('[id^="tab-"]').forEach(t => t.style.display = 'none');
          document.getElementById('tab-' + name).style.display = '';
          document.querySelectorAll('.sidebar .nav-link').forEach(l => l.classList.remove('active'));
          if (el) el.classList.add('active');
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
                <td><span class="badge bg-secondary">${esc(u.tipoUsuario)}</span></td>
                <td>${esc(u.especialidad || '-')}</td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick='openEditUserById(${u.idUsuario})'><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="deleteUser(${u.idUsuario}, ${u.id_hospital})"><i class="bi bi-trash"></i></button>
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