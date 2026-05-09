<?php
/**
 * index.php - Panel de Super Administrador
 */
session_start();

define('ADMIN_USER', 'superadmin');
define('ADMIN_PASS', 'root');

// Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action'])) {
    if ($_POST['admin_user'] === ADMIN_USER && $_POST['admin_pass'] === ADMIN_PASS) {
        $_SESSION['superadmin'] = true;
    } else {
        $login_error = 'Credenciales incorrectas.';
    }
}

// Logout
if (isset($_GET['logout'])) {
    $_SESSION['superadmin'] = false;
    unset($_SESSION['superadmin']);
    header("Location: index.php");
    exit;
}

$logged_in = !empty($_SESSION['superadmin']);

$hospitales  = [];
$solicitudes = [];
$db_error    = null;

if ($logged_in) {
    $db_file = __DIR__ . '/base/config/database.php';
    
    if (!file_exists($db_file)) {
        $db_error = "El archivo de configuración de base de datos no se encuentra en el servidor. Ruta buscada: " . $db_file;
    } else {
        try {
            require_once $db_file;
            $db   = new Database();
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

        // ── MANEJO DE ACCIONES API (POST) ──────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
            header('Content-Type: application/json');
            ob_clean(); // Limpiar cualquier output previo
            $action = $_POST['action'];

            try {
                if ($action === 'approve') {
                    $id = (int)$_POST['id_solicitud'];
                    $nota = trim($_POST['nota'] ?? '');
                    $fecha_venc = $_POST['fecha_vencimiento'] ?: null;
                    $s = $conn->prepare("SELECT * FROM solicitudes_modulos WHERE id_solicitud = ?");
                    $s->execute([$id]);
                    $sol = $s->fetch();
                    if (!$sol) throw new Exception("Solicitud no encontrada");
                    $mods = json_decode($sol['modulos_solicitados'], true);
                    if (!in_array('core', $mods)) array_unshift($mods, 'core');
                    $conn->prepare("UPDATE hospitales SET modulos_activos=?, tipo_suscripcion=?, estado_suscripcion='Activo', fecha_vencimiento=? WHERE id_hospital=?")
                         ->execute([json_encode($mods), $sol['tipo_suscripcion'], ($sol['tipo_suscripcion']==='De por vida'?null:$fecha_venc), $sol['id_hospital']]);
                    $conn->prepare("UPDATE solicitudes_modulos SET estado='Aprobada', nota_admin=?, fecha_respuesta=NOW() WHERE id_solicitud=?")
                         ->execute([$nota, $id]);
                    echo json_encode(['status'=>'success', 'message'=>'Aprobada correctamente']);
                } elseif ($action === 'reject') {
                    $id = (int)$_POST['id_solicitud'];
                    $nota = trim($_POST['nota'] ?? '');
                    $conn->prepare("UPDATE solicitudes_modulos SET estado='Rechazada', nota_admin=?, fecha_respuesta=NOW() WHERE id_solicitud=?")
                         ->execute([$nota, $id]);
                    echo json_encode(['status'=>'success', 'message'=>'Rechazada']);
                } elseif ($action === 'update_modules') {
                    $id_h = (int)$_POST['id_hospital'];
                    $mods = json_decode($_POST['modulos'] ?? '[]', true);
                    if (!in_array('core', $mods)) array_unshift($mods, 'core');
                    $conn->prepare("UPDATE hospitales SET modulos_activos=?, tipo_suscripcion=?, estado_suscripcion=?, fecha_vencimiento=? WHERE id_hospital=?")
                         ->execute([json_encode($mods), $_POST['tipo_suscripcion'], $_POST['estado'], ($_POST['fecha_vencimiento']?:null), $id_h]);
                    echo json_encode(['status'=>'success', 'message'=>'Actualizado']);
                } elseif ($action === 'create_hospital') {
                    $nombre = trim($_POST['nombre']);
                    $codigo = trim($_POST['codigo']);
                    $stmt = $conn->prepare("INSERT INTO hospitales (nombre, codigo_hospital, modulos_activos, estado_suscripcion) VALUES (?, ?, '[\"core\"]', 'Prueba')");
                    $stmt->execute([$nombre, $codigo]);
                    echo json_encode(['status'=>'success', 'message'=>'Hospital creado']);
                } elseif ($action === 'create_user') {
                    $id_h = (int)$_POST['id_hospital'];
                    $user = trim($_POST['usuario']);
                    $pass = trim($_POST['password']);
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
                    $stmt->execute([$id_h, $user, $pass, $nombre, $apellido, $especialidad, $tipo, $clinica_nombre, $telefono, $email]);
                    echo json_encode(['status'=>'success', 'message'=>'Usuario creado']);
                } elseif ($action === 'get_users') {
                    $id_h = (int)$_GET['id_hospital'];
                    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id_hospital = ?");
                    $stmt->execute([$id_h]);
                    echo json_encode($stmt->fetchAll());
                } elseif ($action === 'update_user') {
                    $id_u = (int)$_POST['id_usuario'];
                    $stmt = $conn->prepare("UPDATE usuarios SET usuario=?, password=?, nombre=?, apellido=?, especialidad=?, tipoUsuario=?, telefono=?, email=? WHERE idUsuario=?");
                    $stmt->execute([$_POST['usuario'], $_POST['password'], $_POST['nombre'], $_POST['apellido'], $_POST['especialidad'], $_POST['tipoUsuario'], $_POST['telefono'], $_POST['email'], $id_u]);
                    echo json_encode(['status'=>'success', 'message'=>'Usuario actualizado']);
                } elseif ($action === 'delete_user') {
                    $id_u = (int)$_POST['id_usuario'];
                    $conn->prepare("DELETE FROM usuarios WHERE idUsuario = ?")->execute([$id_u]);
                    echo json_encode(['status'=>'success', 'message'=>'Usuario eliminado']);
                } elseif ($action === 'delete_hospital') {
                    $id_h = (int)$_POST['id_hospital'];
                    // Primero borrar usuarios de ese hospital
                    $conn->prepare("DELETE FROM usuarios WHERE id_hospital = ?")->execute([$id_h]);
                    // Luego borrar hospital
                    $conn->prepare("DELETE FROM hospitales WHERE id_hospital = ?")->execute([$id_h]);
                    echo json_encode(['status'=>'success', 'message'=>'Hospital eliminado']);
                }
            } catch (Exception $e) {
                echo json_encode(['status'=>'error', 'message'=>$e->getMessage()]);
            }
            exit;
        }

        } catch (Exception $e) {
            $db_error = "Error de conexión: " . $e->getMessage();
        }
    }
}

$all_modules = ['core','pharmacy','hospitalization','laboratory','inventory','imaging','purchases','sales','finances','reports'];
$module_labels = [
    'core'=>'Core','pharmacy'=>'Farmacia','hospitalization'=>'Hospitalización',
    'laboratory'=>'Laboratorio','inventory'=>'Inventario','imaging'=>'Imagenología',
    'purchases'=>'Compras','sales'=>'Ventas','finances'=>'Finanzas','reports'=>'Reportes'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Panel Administrativo — ClinicApp</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
  body { background:#0f172a; color:#e2e8f0; font-family:system-ui,sans-serif; min-height:100vh; }
  .login-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; }
  .login-card { background:#1e293b; border:1px solid #334155; border-radius:1rem; padding:2.5rem; width:100%; max-width:400px; }
  .login-card h2 { color:#3b82f6; font-weight:700; }
  .sidebar { width:240px; background:#1e293b; min-height:100vh; position:fixed; top:0; left:0; border-right:1px solid #334155; padding:1.5rem 1rem; z-index:100; }
  .sidebar .brand { color:#3b82f6; font-weight:700; font-size:1.1rem; padding:.5rem; margin-bottom:1.5rem; }
  .sidebar .nav-link { color:#94a3b8; border-radius:.5rem; padding:.6rem .8rem; font-size:.9rem; }
  .sidebar .nav-link:hover, .sidebar .nav-link.active { background:#334155; color:#e2e8f0; }
  .main { margin-left:240px; padding:2rem; }
  .stat-card { background:#1e293b; border:1px solid #334155; border-radius:.875rem; padding:1.5rem; }
  .stat-card h6 { color:#94a3b8; font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; margin-bottom:.5rem; }
  .stat-card .value { font-size:2rem; font-weight:700; color:#e2e8f0; }
  .table-dark-custom { background:#1e293b; color:#e2e8f0; border-radius:.75rem; overflow:hidden; }
  .table-dark-custom th { background:#334155; color:#94a3b8; font-size:.75rem; text-transform:uppercase; border:0; }
  .table-dark-custom td { border-color:#334155; vertical-align:middle; }
  .badge-mod { font-size:.7rem; padding:.25em .55em; border-radius:.3rem; background:#1d4ed8; color:#bfdbfe; margin:.1rem; display:inline-block; }
  .badge-mod.active { background:#166534; color:#bbf7d0; }
  .section-title { font-size:1.25rem; font-weight:700; color:#e2e8f0; margin-bottom:1rem; }
  .form-control, .form-select { background:#0f172a; border-color:#334155; color:#e2e8f0; }
  .form-control:focus, .form-select:focus { background:#0f172a; border-color:#3b82f6; color:#e2e8f0; box-shadow:0 0 0 .2rem rgba(59,130,246,.25); }
  .card-dark { background:#1e293b; border:1px solid #334155; border-radius:.875rem; }
  .tab-dark .nav-link { color:#94a3b8; border:0; border-bottom:2px solid transparent; border-radius:0; padding:.75rem 1rem; }
  .tab-dark .nav-link.active { color:#3b82f6; border-bottom-color:#3b82f6; background:transparent; }
</style>
</head>
<body>

<?php if (!$logged_in): ?>
<!-- ═══════════════ LOGIN ═══════════════ -->
<div class="login-wrap">
  <div class="login-card">
    <div class="text-center mb-4">
      <div style="width:60px;height:60px;background:#1d4ed8;border-radius:1rem;display:flex;align-items:center;justify-content:center;font-size:1.75rem;margin:0 auto 1rem;">🏥</div>
      <h2>ClinicApp Admin</h2>
      <p class="text-secondary small">Panel de Super Administrador</p>
    </div>
    <?php if (!empty($login_error)): ?>
      <div class="alert alert-danger py-2"><?php echo $login_error; ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="login_action" value="1">
      <div class="mb-3">
        <label class="form-label small text-secondary">Usuario</label>
        <input type="text" name="admin_user" class="form-control" required autofocus>
      </div>
      <div class="mb-4">
        <label class="form-label small text-secondary">Contraseña</label>
        <input type="password" name="admin_pass" class="form-control" required>
      </div>
      <button class="btn btn-primary w-100 fw-semibold">Iniciar Sesión</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ═══════════════ PANEL ADMIN ═══════════════ -->
<!-- Sidebar -->
<div class="sidebar">
  <div class="brand"><i class="bi bi-hospital me-2"></i>ClinicApp Admin</div>
  <nav class="nav flex-column gap-1">
    <a href="#dashboard" class="nav-link active" onclick="showTab('dashboard',this)"><i class="bi bi-grid me-2"></i>Dashboard</a>
    <a href="#hospitals"  class="nav-link"        onclick="showTab('hospitals',this)"><i class="bi bi-building me-2"></i>Hospitales</a>
    <a href="#requests"   class="nav-link"        onclick="showTab('requests',this)">
      <i class="bi bi-bell me-2"></i>Solicitudes
      <?php $pend = array_filter($solicitudes, fn($s) => $s['estado'] === 'Pendiente'); ?>
      <?php if (count($pend) > 0): ?>
        <span class="badge bg-danger ms-1"><?php echo count($pend); ?></span>
      <?php endif; ?>
    </a>
  </nav>
  <div class="mt-auto pt-4">
    <a href="?logout=1" class="nav-link text-danger"><i class="bi bi-box-arrow-left me-2"></i>Cerrar Sesión</a>
  </div>
</div>

<!-- Main -->
<div class="main">

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
      <div class="stat-card">
        <h6>Hospitales</h6>
        <div class="value"><?php echo count($hospitales); ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card">
        <h6>Solicitudes Pendientes</h6>
        <div class="value text-warning"><?php echo count($pend); ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card">
        <h6>Activos</h6>
        <div class="value text-success"><?php echo count(array_filter($hospitales, fn($h)=>$h['estado_suscripcion']==='Activo')); ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="stat-card">
        <h6>Vencidos / Inactivos</h6>
        <div class="value text-danger"><?php echo count(array_filter($hospitales, fn($h)=>in_array($h['estado_suscripcion'],['Vencido','Inactivo']))); ?></div>
      </div>
    </div>
  </div>

  <!-- Solicitudes recientes -->
  <?php if (count($pend) > 0): ?>
  <div class="card-dark p-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h6 class="fw-bold mb-0 text-warning"><i class="bi bi-bell-fill me-2"></i>Solicitudes Pendientes</h6>
      <button class="btn btn-sm btn-outline-primary" onclick="showTab('requests', document.querySelector('[onclick*=requests]'))">Ver todas</button>
    </div>
    <?php foreach ($pend as $s): ?>
    <div class="d-flex align-items-center justify-content-between p-2 mb-1" style="background:#0f172a;border-radius:.5rem;">
      <div>
        <strong><?php echo htmlspecialchars($s['hospital_nombre']); ?></strong>
        <span class="text-secondary small ms-2"><?php echo $s['tipo_suscripcion']; ?></span>
        <div class="small text-secondary"><?php echo date('d/m/Y H:i', strtotime($s['fecha_solicitud'])); ?></div>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-sm btn-success" onclick="openApprove(<?php echo $s['id_solicitud']; ?>, '<?php echo htmlspecialchars($s['hospital_nombre']); ?>', '<?php echo $s['tipo_suscripcion']; ?>')"><i class="bi bi-check2"></i></button>
        <button class="btn btn-sm btn-danger"  onclick="rejectReq(<?php echo $s['id_solicitud']; ?>)"><i class="bi bi-x"></i></button>
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
    <button class="btn btn-primary btn-sm" onclick="openNewHospital()"><i class="bi bi-plus-lg me-1"></i>Nuevo Hospital</button>
  </div>

  <table class="table table-dark-custom">
    <thead>
      <tr>
        <th>Hospital</th><th>Código</th><th>Suscripción</th><th>Vencimiento</th><th>Módulos Activos</th><th>Acciones</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($hospitales as $h): ?>
      <tr>
        <td class="fw-semibold"><?php echo htmlspecialchars($h['nombre']); ?></td>
        <td><code class="text-info"><?php echo $h['codigo_hospital']; ?></code></td>
        <td>
          <?php
          $c = match($h['estado_suscripcion']) {
            'Activo' => 'success', 'Vencido' => 'warning', 'Inactivo' => 'danger', default => 'secondary'
          };
          ?>
          <span class="badge bg-<?php echo $c; ?>"><?php echo $h['estado_suscripcion']; ?></span>
          <span class="badge bg-secondary ms-1"><?php echo $h['tipo_suscripcion'] ?? '—'; ?></span>
        </td>
        <td class="small"><?php echo ($h['tipo_suscripcion']==='De por vida') ? '♾ Permanente' : ($h['fecha_vencimiento'] ?? '—'); ?></td>
        <td>
          <?php foreach ($h['modulos_activos'] as $m): ?>
            <span class="badge-mod active"><?php echo $module_labels[$m] ?? $m; ?></span>
          <?php endforeach; ?>
        </td>
        <td>
          <div class="d-flex gap-1">
            <button class="btn btn-sm btn-outline-primary" onclick='openEditHospital(<?php echo $h["id_hospital"]; ?>, <?php echo htmlspecialchars(json_encode($h), ENT_QUOTES); ?>)' title="Editar Hospital">
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-success" onclick="openCreateUser(<?php echo $h['id_hospital']; ?>, '<?php echo htmlspecialchars($h['nombre']); ?>')" title="Crear Usuario">
              <i class="bi bi-person-plus"></i>
            </button>
            <button class="btn btn-sm btn-outline-info" onclick="viewUsers(<?php echo $h['id_hospital']; ?>, '<?php echo htmlspecialchars($h['nombre']); ?>')" title="Ver Usuarios">
              <i class="bi bi-people"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteHospital(<?php echo $h['id_hospital']; ?>, '<?php echo htmlspecialchars($h['nombre']); ?>')" title="Eliminar Hospital">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- ── TAB: Solicitudes ── -->
<div id="tab-requests" style="display:none;">
  <h4 class="section-title mb-4">Solicitudes de Módulos</h4>

  <!-- Filtro por estado -->
  <div class="d-flex gap-2 mb-3">
    <button class="btn btn-sm btn-warning" onclick="filterReqs('Pendiente')">Pendientes</button>
    <button class="btn btn-sm btn-success" onclick="filterReqs('Aprobada')">Aprobadas</button>
    <button class="btn btn-sm btn-danger"  onclick="filterReqs('Rechazada')">Rechazadas</button>
    <button class="btn btn-sm btn-secondary" onclick="filterReqs('')">Todas</button>
  </div>

  <table class="table table-dark-custom" id="reqTable">
    <thead><tr><th>Hospital</th><th>Módulos Solicitados</th><th>Tipo</th><th>Mensaje</th><th>Fecha</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($solicitudes as $s): ?>
      <tr data-estado="<?php echo $s['estado']; ?>">
        <td class="fw-semibold"><?php echo htmlspecialchars($s['hospital_nombre']); ?></td>
        <td>
          <?php foreach ($s['modulos_solicitados'] as $m): ?>
            <span class="badge-mod"><?php echo $module_labels[$m] ?? $m; ?></span>
          <?php endforeach; ?>
        </td>
        <td><span class="badge bg-info text-dark"><?php echo $s['tipo_suscripcion']; ?></span></td>
        <td class="small text-secondary"><?php echo htmlspecialchars($s['mensaje'] ?? '—'); ?></td>
        <td class="small"><?php echo date('d/m/Y H:i', strtotime($s['fecha_solicitud'])); ?></td>
        <td>
          <?php
          $c2 = match($s['estado']) { 'Aprobada'=>'success','Rechazada'=>'danger',default=>'warning' };
          echo "<span class='badge bg-{$c2}'>{$s['estado']}</span>";
          ?>
        </td>
        <td>
          <?php if ($s['estado'] === 'Pendiente'): ?>
          <div class="d-flex gap-1">
            <button class="btn btn-xs btn-success btn-sm" onclick="openApprove(<?php echo $s['id_solicitud']; ?>, '<?php echo htmlspecialchars($s['hospital_nombre']); ?>', '<?php echo $s['tipo_suscripcion']; ?>')"><i class="bi bi-check2"></i> Aprobar</button>
            <button class="btn btn-xs btn-danger  btn-sm" onclick="rejectReq(<?php echo $s['id_solicitud']; ?>)"><i class="bi bi-x"></i> Rechazar</button>
          </div>
          <?php else: ?>
          <span class="text-secondary small"><?php echo $s['nota_admin'] ? substr($s['nota_admin'],0,40).'…' : '—'; ?></span>
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
    <div class="modal-content" style="background:#1e293b;border:1px solid #334155;border-radius:1rem;">
      <div class="modal-header border-0">
        <h5 class="modal-title text-success fw-bold"><i class="bi bi-check2-circle me-2"></i>Aprobar Solicitud</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-secondary small">Hospital: <strong id="approveHospName" class="text-white"></strong></p>
        <input type="hidden" id="approveSolId">
        <div class="mb-3">
          <label class="form-label small text-secondary">Fecha de Vencimiento</label>
          <input type="date" id="approveFechaVenc" class="form-control" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
          <small class="text-secondary">Déjalo vacío si es "De por vida".</small>
        </div>
        <div>
          <label class="form-label small text-secondary">Nota para el hospital (opcional)</label>
          <textarea id="approveNota" class="form-control" rows="2" placeholder="Ej: Activado correctamente. Gracias por su preferencia."></textarea>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success px-4 fw-semibold" onclick="submitApprove()"><i class="bi bi-check2-circle me-1"></i>Aprobar</button>
      </div>
    </div>
  </div>
</div>

<!-- Editar Hospital -->
<div class="modal fade" id="editHospModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background:#1e293b;border:1px solid #334155;border-radius:1rem;">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold text-primary" id="hospModalTitle"><i class="bi bi-pencil-square me-2"></i>Editar Hospital</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
              <option>Activo</option><option>Inactivo</option><option>Vencido</option><option>Prueba</option>
            </select>
          </div>
          <div class="col-md-3 edit-only">
            <label class="form-label small text-secondary">Tipo Suscripción</label>
            <select id="editTipo" class="form-select">
              <option>Mensual</option><option>Anual</option><option>De por vida</option>
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
                <input class="form-check-input edit-mod-check" type="checkbox" value="<?php echo $m; ?>" id="em_<?php echo $m; ?>" <?php if ($m==='core') echo 'checked disabled'; ?>>
                <label class="form-check-label small text-secondary" for="em_<?php echo $m; ?>"><?php echo $module_labels[$m]; ?></label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer border-0">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary px-4 fw-semibold" onclick="submitHosp()"><i class="bi bi-save me-1"></i>Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- Crear Usuario -->
<div class="modal fade" id="createUserModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" style="background:#1e293b;border:1px solid #334155;border-radius:1rem;">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold text-success"><i class="bi bi-person-plus me-2"></i>Crear Nuevo Usuario</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
      <div class="modal-footer border-0">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success px-4 fw-semibold" onclick="submitCreateUser()">Guardar Usuario</button>
      </div>
    </div>
  </div>
</div>

<!-- Ver Usuarios -->
<div class="modal fade" id="viewUsersModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content" style="background:#1e293b;border:1px solid #334155;border-radius:1rem;">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold text-info"><i class="bi bi-people me-2"></i>Usuarios del Hospital</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-secondary small">Lista de usuarios registrados en: <strong id="viewUsersHospName" class="text-white"></strong></p>
        <div class="table-responsive">
          <table class="table table-dark table-hover border-secondary small mt-2">
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
    <div class="modal-content" style="background:#1e293b;border:1px solid #334155;border-radius:1rem;">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold text-warning"><i class="bi bi-pencil me-2"></i>Editar Usuario</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="editUserId">
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
      <div class="modal-footer border-0">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-warning px-4 fw-semibold" onclick="submitUpdateUser()">Guardar Cambios</button>
      </div>
    </div>
  </div>
</div>

<script>
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
    const r = await fetch('index.php', {method:'POST', body:fd});
    const j = await r.json();
    bootstrap.Modal.getInstance(document.getElementById('approveModal')).hide();
    Swal.fire(j.status==='success'?'Aprobado':'Error', j.message, j.status==='success'?'success':'error')
        .then(()=>location.reload());
}

async function rejectReq(id) {
    const {value: nota, isConfirmed} = await Swal.fire({
        title:'Rechazar Solicitud',
        input:'textarea', inputLabel:'Motivo (opcional)',
        showCancelButton:true, confirmButtonText:'Rechazar', cancelButtonText:'Cancelar',
        confirmButtonColor:'#ef4444'
    });
    if (!isConfirmed) return;
    const fd = new FormData();
    fd.append('action','reject'); fd.append('id_solicitud',id); fd.append('nota', nota||'');
    const r = await fetch('index.php',{method:'POST',body:fd});
    const j = await r.json();
    Swal.fire(j.status==='success'?'Rechazada':'Error', j.message, j.status).then(()=>location.reload());
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
    document.getElementById('editHospId').value   = id;
    document.getElementById('editNombre').value   = data.nombre;
    document.getElementById('editCodigo').value   = data.codigo_hospital || '';
    document.getElementById('editEstado').value   = data.estado_suscripcion;
    document.getElementById('editTipo').value     = data.tipo_suscripcion || 'Mensual';
    document.getElementById('editVenc').value     = data.fecha_vencimiento || '';
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
        fd.append('action','update_modules');
        fd.append('id_hospital', id);
        fd.append('modulos', JSON.stringify(mods));
        fd.append('tipo_suscripcion', document.getElementById('editTipo').value);
        fd.append('estado', document.getElementById('editEstado').value);
        fd.append('fecha_vencimiento', document.getElementById('editVenc').value);
    } else {
        // CREATE
        fd.append('action','create_hospital');
        fd.append('nombre', document.getElementById('editNombre').value);
        fd.append('codigo', document.getElementById('editCodigo').value);
    }
    
    const r = await fetch('index.php',{method:'POST',body:fd});
    const j = await r.json();
    bootstrap.Modal.getInstance(document.getElementById('editHospModal')).hide();
    Swal.fire(j.status==='success'?'Guardado':'Error', j.message, j.status).then(()=>location.reload());
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
    
    const r = await fetch(location.pathname,{method:'POST',body:fd});
    const j = await r.json();
    bootstrap.Modal.getInstance(document.getElementById('createUserModal')).hide();
    Swal.fire(j.status==='success'?'Creado':'Error', j.message, j.status);
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
        body.innerHTML = users.length ? '' : '<tr><td colspan="5" class="text-center text-secondary">No hay usuarios</td></tr>';
        users.forEach(u => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${u.usuario}</td>
                <td>${u.nombre} ${u.apellido}</td>
                <td><span class="badge bg-secondary">${u.tipoUsuario}</span></td>
                <td>${u.especialidad || '-'}</td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick='openEditUser(${JSON.stringify(u)})'><i class="bi bi-pencil"></i></button>
                    <button class="btn btn-sm btn-danger" onclick="deleteUser(${u.idUsuario}, ${u.id_hospital})"><i class="bi bi-trash"></i></button>
                </td>
            `;
            body.appendChild(tr);
        });
    } catch(e) {
        body.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error al cargar usuarios</td></tr>';
    }
}

function openEditUser(u) {
    document.getElementById('editUserId').value = u.idUsuario;
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
    
    const r = await fetch(location.pathname, {method:'POST', body:fd});
    const j = await r.json();
    bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
    Swal.fire(j.status==='success'?'Actualizado':'Error', j.message, j.status);
    // Recargar lista si es posible o esperar a que cierre modal
}

async function deleteUser(id, hospId) {
    if (!confirm('¿Estás seguro de eliminar este usuario?')) return;
    const fd = new FormData();
    fd.append('action', 'delete_user');
    fd.append('id_usuario', id);
    const r = await fetch(location.pathname, {method:'POST', body:fd});
    const j = await r.json();
    if (j.status === 'success') refreshUserList(hospId);
    else Swal.fire('Error', j.message, 'error');
}

async function deleteHospital(id, name) {
    const {isConfirmed} = await Swal.fire({
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
    const r = await fetch(location.pathname, {method:'POST', body:fd});
    const j = await r.json();
    Swal.fire(j.status==='success'?'Eliminado':'Error', j.message, j.status).then(()=>location.reload());
}
</script>
<?php endif; ?>
</body>
</html>
