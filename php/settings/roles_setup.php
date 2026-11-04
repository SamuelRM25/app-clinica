<?php
/**
 * settings/roles_setup.php
 * Instalación de los tipos de usuario Laboratorio (lab), Quirófano (qx) y Farmacia (farm)
 * y creación de los usuarios iniciales del hospital.
 *
 * - Solo administradores.
 * - Se puede ejecutar varias veces: no duplica usuarios ni repite cambios.
 * - Las contraseñas temporales se muestran UNA sola vez, al crearlas.
 */
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

start_app_session();
verify_session();

if (($_SESSION['tipoUsuario'] ?? '') !== 'admin') {
    header('Location: ../dashboard/index.php');
    exit;
}

$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);
$clinica = 'Centro Médico Dr. Roberto Morales';

// Usuarios iniciales (los nombres se pueden editar después en Configuración > Usuarios)
$usuarios_iniciales = [
    ['usuario' => 'turno1',          'nombre' => 'Médico',          'apellido' => 'de turno 1', 'tipo' => 'doc',   'especialidad' => 'Médico de turno'],
    ['usuario' => 'turno2',          'nombre' => 'Médico',          'apellido' => 'de turno 2', 'tipo' => 'doc',   'especialidad' => 'Médico de turno'],
    ['usuario' => 'turno3',          'nombre' => 'Médico',          'apellido' => 'de turno 3', 'tipo' => 'doc',   'especialidad' => 'Médico de turno'],
    ['usuario' => 'laboratorio',     'nombre' => 'Laboratorista',   'apellido' => 'CMRM',       'tipo' => 'lab',   'especialidad' => 'Laboratorio clínico'],
    ['usuario' => 'quirofano',       'nombre' => 'Quirófano',       'apellido' => 'CMRM',       'tipo' => 'qx',    'especialidad' => 'Quirófano'],
    ['usuario' => 'farmacia',        'nombre' => 'Farmacia',        'apellido' => 'CMRM',       'tipo' => 'farm',  'especialidad' => 'Farmacia'],
    ['usuario' => 'administracion1', 'nombre' => 'Administración',  'apellido' => '1',          'tipo' => 'admin', 'especialidad' => 'Administración'],
    ['usuario' => 'administracion2', 'nombre' => 'Administración',  'apellido' => '2',          'tipo' => 'admin', 'especialidad' => 'Administración'],
];

/** Contraseña temporal legible que cumple validate_password_strength(). */
function temp_password(): string {
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lower = 'abcdefghijkmnpqrstuvwxyz';
    $digit = '23456789';
    $all = $upper . $lower . $digit;
    $chars = [
        $upper[random_int(0, strlen($upper) - 1)],
        $lower[random_int(0, strlen($lower) - 1)],
        $digit[random_int(0, strlen($digit) - 1)],
    ];
    while (count($chars) < 10) {
        $chars[] = $all[random_int(0, strlen($all) - 1)];
    }
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }
    return implode('', $chars);
}

/** Lee cómo está definida la columna tipoUsuario. */
function columna_tipo($conn): ?array {
    $st = $conn->query("SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_MAXIMUM_LENGTH
                        FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'tipoUsuario'");
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/** Valores de un ENUM: "enum('a','b')" -> ['a','b'] */
function enum_valores(string $column_type): array {
    preg_match_all("/'((?:[^'\\\\]|\\\\.|'')*)'/", $column_type, $m);
    return array_map(fn($v) => str_replace("''", "'", $v), $m[1]);
}

/** ¿Qué cambio necesita la columna? Devuelve el SQL o null si no hace falta. */
function sql_cambio_columna(?array $col): ?string {
    if (!$col) return null;
    $tipo = strtolower($col['DATA_TYPE']);
    $nulo = $col['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL';
    $def  = $col['COLUMN_DEFAULT'];
    $def_sql = ($def === null) ? '' : " DEFAULT " . (preg_match("/^'.*'$/", $def) ? $def : "'" . str_replace("'", "''", $def) . "'");

    if ($tipo === 'enum') {
        $actuales = enum_valores($col['COLUMN_TYPE']);
        $faltan = array_diff(array_keys(APP_ROLES), $actuales);
        if (!$faltan) return null;
        $todos = array_values(array_unique(array_merge($actuales, array_keys(APP_ROLES))));
        $lista = implode(',', array_map(fn($v) => "'" . str_replace("'", "''", $v) . "'", $todos));
        return "ALTER TABLE usuarios MODIFY tipoUsuario ENUM($lista) $nulo$def_sql";
    }
    if (in_array($tipo, ['varchar', 'char'], true) && (int) $col['CHARACTER_MAXIMUM_LENGTH'] < 5) {
        return "ALTER TABLE usuarios MODIFY tipoUsuario VARCHAR(20) $nulo$def_sql";
    }
    return null;
}

$errores = [];
$creados = [];
$existentes = [];
$columna_actualizada = false;

try {
    $conn = (new Database())->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_token();

        // 1. Permitir los tipos nuevos en la base de datos (si hace falta)
        $sql = sql_cambio_columna(columna_tipo($conn));
        if ($sql) {
            $conn->exec($sql);
            $columna_actualizada = true;
        }

        // 2. Crear los usuarios que todavía no existen
        $check = $conn->prepare("SELECT idUsuario FROM usuarios WHERE usuario = ? AND id_hospital = ?");
        $insert = $conn->prepare("INSERT INTO usuarios (usuario, password, nombre, apellido, tipoUsuario, especialidad, clinica, telefono, email, id_hospital)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, '0000', NULL, ?)");
        foreach ($usuarios_iniciales as $u) {
            $check->execute([$u['usuario'], $id_hospital]);
            if ($check->fetch()) {
                $existentes[] = $u;
                continue;
            }
            $clave = temp_password();
            try {
                $insert->execute([$u['usuario'], password_hash($clave, PASSWORD_DEFAULT), $u['nombre'], $u['apellido'],
                                  $u['tipo'], $u['especialidad'], $clinica, $id_hospital]);
                $creados[] = $u + ['clave' => $clave];
            } catch (PDOException $e) {
                $errores[] = 'No se pudo crear "' . $u['usuario'] . '": ' . $e->getMessage();
            }
        }

        if ($creados) {
            audit_log('create', 'settings', 'Usuarios iniciales por rol creados: ' . implode(', ', array_column($creados, 'usuario')));
        }
    }

    // Estado actual (para mostrarlo antes y después)
    $col = columna_tipo($conn);
    $cambio_pendiente = sql_cambio_columna($col);
    $ya = $conn->prepare("SELECT usuario FROM usuarios WHERE id_hospital = ?");
    $ya->execute([$id_hospital]);
    $usuarios_actuales = array_column($ya->fetchAll(PDO::FETCH_ASSOC), 'usuario');
} catch (Throwable $e) {
    error_log('roles_setup: ' . $e->getMessage());
    $errores[] = 'Error con la base de datos: ' . $e->getMessage();
    $col = null;
    $cambio_pendiente = null;
    $usuarios_actuales = [];
}

$por_crear = array_filter($usuarios_iniciales, fn($u) => !in_array($u['usuario'], $usuarios_actuales, true));
?>
<!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roles y usuarios iniciales | Configuración</title>
    <link rel="icon" type="image/png" href="../../assets/img/favicon-cmrm.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/global_dashboard.css">
    <?php include '../../includes/theme_head.php'; ?>
    <style>
        body { background: var(--color-bg); }
        .setup { max-width: 920px; margin: 0 auto; padding: 2rem 1.25rem 4rem; }
        .setup-card { background: var(--color-card); border: 1px solid var(--color-border); border-radius: 16px; padding: 1.5rem; margin-top: 1.25rem; }
        .creds td, .creds th { vertical-align: middle; }
        .creds code { font-size: 1rem; color: var(--color-text); background: var(--color-surface); padding: .2rem .5rem; border-radius: 6px; user-select: all; }
        .role-pill { font-size: .78rem; font-weight: 600; padding: .2rem .6rem; border-radius: 999px; background: var(--color-surface); border: 1px solid var(--color-border); }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .setup-card { border: 0; padding: 0; }
        }
    </style>
</head>
<body>
<div class="setup">
    <a href="index.php" class="no-print text-decoration-none"><i class="bi bi-arrow-left"></i> Volver a Configuración</a>
    <h1 class="h3 mt-3 mb-1">Roles y usuarios iniciales</h1>
    <p class="text-muted mb-0">Activa los tipos de usuario <strong>Laboratorio</strong>, <strong>Quirófano</strong> y <strong>Farmacia</strong>, y crea los usuarios del hospital que todavía no existen.</p>

    <?php foreach ($errores as $err): ?>
        <div class="alert alert-danger mt-3 mb-0"><?php echo htmlspecialchars($err); ?></div>
    <?php endforeach; ?>

    <?php if ($creados): ?>
        <div class="setup-card">
            <div class="alert alert-warning">
                <strong>Guarde estas contraseñas ahora.</strong> Es la única vez que se muestran. Entréguelas a cada persona y pídales que la cambien,
                o cámbielas usted en Configuración &gt; Usuarios.
            </div>
            <div class="table-responsive">
                <table class="table creds mb-3">
                    <thead><tr><th>Usuario</th><th>Contraseña temporal</th><th>Tipo</th><th>Nombre</th></tr></thead>
                    <tbody>
                    <?php foreach ($creados as $u): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($u['usuario']); ?></code></td>
                            <td><code><?php echo htmlspecialchars($u['clave']); ?></code></td>
                            <td><span class="role-pill"><?php echo htmlspecialchars(role_label($u['tipo'])); ?></span></td>
                            <td><?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <button class="btn btn-outline-primary no-print" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir credenciales</button>
            <?php if ($existentes): ?>
                <p class="text-muted small mt-3 mb-0">Ya existían y no se tocaron: <?php echo htmlspecialchars(implode(', ', array_column($existentes, 'usuario'))); ?>.</p>
            <?php endif; ?>
        </div>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errores): ?>
        <div class="alert alert-success mt-3">Todo estaba listo: no hacía falta crear usuarios<?php echo $columna_actualizada ? '' : ' ni modificar la base de datos'; ?>.</div>
    <?php endif; ?>

    <div class="setup-card no-print">
        <h2 class="h5">Estado</h2>
        <ul class="list-unstyled mb-3">
            <li class="mb-2">
                <?php if ($col === null): ?>
                    <i class="bi bi-exclamation-triangle text-warning"></i> No se pudo leer la columna <code>usuarios.tipoUsuario</code>.
                <?php elseif ($cambio_pendiente): ?>
                    <i class="bi bi-circle text-warning"></i> La base de datos todavía no acepta los tipos nuevos
                    <small class="text-muted">(columna actual: <?php echo htmlspecialchars($col['COLUMN_TYPE']); ?>)</small>.
                <?php else: ?>
                    <i class="bi bi-check-circle-fill text-success"></i> La base de datos acepta todos los tipos de usuario
                    <small class="text-muted">(<?php echo htmlspecialchars($col['COLUMN_TYPE']); ?>)</small>.
                <?php endif; ?>
            </li>
            <li>
                <?php if ($por_crear): ?>
                    <i class="bi bi-circle text-warning"></i> Faltan <?php echo count($por_crear); ?> de <?php echo count($usuarios_iniciales); ?> usuarios iniciales.
                <?php else: ?>
                    <i class="bi bi-check-circle-fill text-success"></i> Los <?php echo count($usuarios_iniciales); ?> usuarios iniciales ya existen.
                <?php endif; ?>
            </li>
        </ul>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-3">
                <thead><tr><th>Usuario</th><th>Tipo</th><th>Nombre</th><th>Estado</th></tr></thead>
                <tbody>
                <?php foreach ($usuarios_iniciales as $u): $existe = in_array($u['usuario'], $usuarios_actuales, true); ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($u['usuario']); ?></code></td>
                        <td><span class="role-pill"><?php echo htmlspecialchars(role_label($u['tipo'])); ?></span></td>
                        <td><?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?></td>
                        <td><?php echo $existe ? '<span class="text-success">Creado</span>' : '<span class="text-muted">Por crear</span>'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($cambio_pendiente || $por_crear): ?>
            <form method="post" onsubmit="this.querySelector('button').disabled = true;">
                <?php echo csrf_field(); ?>
                <button type="submit" class="btn btn-primary"><i class="bi bi-person-check"></i> Activar tipos y crear usuarios</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
