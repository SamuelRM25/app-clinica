<?php
// laboratory/api/save_test.php - Save or update a clinical test
header('Content-Type: application/json');
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/multitenant.php';

if (!in_array($_SESSION['tipoUsuario'] ?? '', ['admin', 'lab'], true)) {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id_prueba = (!empty($_POST['id_prueba'])) ? $_POST['id_prueba'] : null;
$nombre = $_POST['nombre'] ?? '';
$codigo = $_POST['codigo'] ?? '';
$categoria = $_POST['categoria'] ?? '';
$precio = (float) ($_POST['precio'] ?? 0);
// Costo externo: un solo costo por prueba. Se guarda en las dos columnas anteriores
// (precio_medilab y precio_la_esperanza) para que los reportes de costo sigan funcionando.
if (isset($_POST['costo_externo'])) {
    $precio_medilab = $precio_la_esperanza = max(0, (float) $_POST['costo_externo']);
} else {
    $precio_medilab = (float) ($_POST['precio_medilab'] ?? 0);
    $precio_la_esperanza = (float) ($_POST['precio_la_esperanza'] ?? 0);
}
$muestra = $_POST['muestra_requerida'] ?? ''; // Fixed mapping
$tiempo = (int) ($_POST['tiempo_procesamiento_horas'] ?? 0); // Fixed mapping
$notas = $_POST['descripcion'] ?? ''; // Map description to notas
$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);

if (empty($nombre) || empty($codigo)) {
    echo json_encode(['success' => false, 'message' => 'El nombre y el código son obligatorios']);
    exit;
}

try {
    // CSRF validation
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        throw new Exception('Token CSRF inválido');
    }

    $database = new Database();
    $conn = $database->getConnection();

    if ($id_prueba) {
        // Fetch old data for audit trail
        $stmt_old = $conn->prepare("SELECT nombre_prueba, codigo_prueba, categoria, precio, precio_medilab, precio_la_esperanza, muestra_requerida, tiempo_procesamiento_horas, notas FROM catalogo_pruebas WHERE id_prueba = ? AND id_hospital = ?");
        $stmt_old->execute([$id_prueba, $id_hospital]);
        $oldData = $stmt_old->fetch(PDO::FETCH_ASSOC);

        // Update
        $stmt = $conn->prepare("
            UPDATE catalogo_pruebas 
            SET nombre_prueba = ?, codigo_prueba = ?, categoria = ?, precio = ?, precio_medilab = ?, precio_la_esperanza = ?, muestra_requerida = ?, tiempo_procesamiento_horas = ?, notas = ?
            WHERE id_prueba = ? AND id_hospital = ?
        ");
        $stmt->execute([$nombre, $codigo, $categoria, $precio, $precio_medilab, $precio_la_esperanza, $muestra, $tiempo, $notas, $id_prueba, $id_hospital]);
        $message = 'Prueba actualizada correctamente';

        audit_log('update', 'laboratory', "Prueba actualizada: $nombre ($codigo)", [
            'table_name' => 'catalogo_pruebas',
            'record_id' => (int)$id_prueba,
            'old_data' => $oldData,
            'new_data' => [
                'nombre_prueba' => $nombre,
                'codigo_prueba' => $codigo,
                'categoria' => $categoria,
                'precio' => $precio,
                'precio_medilab' => $precio_medilab,
                'precio_la_esperanza' => $precio_la_esperanza,
                'muestra_requerida' => $muestra,
                'tiempo_procesamiento_horas' => $tiempo,
                'notas' => $notas,
            ]
        ]);
    } else {
        // Create - Check for duplicate code first
        $checkStmt = $conn->prepare("SELECT id_prueba FROM catalogo_pruebas WHERE codigo_prueba = ? AND id_hospital = ?");
        $checkStmt->execute([$codigo, $id_hospital]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe una prueba con el código ' . $codigo]);
            exit;
        }

        $stmt = $conn->prepare("
            INSERT INTO catalogo_pruebas (nombre_prueba, codigo_prueba, categoria, precio, precio_medilab, precio_la_esperanza, muestra_requerida, tiempo_procesamiento_horas, notas, id_hospital)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$nombre, $codigo, $categoria, $precio, $precio_medilab, $precio_la_esperanza, $muestra, $tiempo, $notas, $id_hospital]);
        $id_prueba = $conn->lastInsertId();
        $message = 'Prueba creada correctamente';

        audit_log('create', 'laboratory', "Prueba creada: $nombre ($codigo)", [
            'table_name' => 'catalogo_pruebas',
            'record_id' => (int)$id_prueba,
            'new_data' => [
                'nombre_prueba' => $nombre,
                'codigo_prueba' => $codigo,
                'categoria' => $categoria,
                'precio' => $precio,
                'precio_medilab' => $precio_medilab,
                'precio_la_esperanza' => $precio_la_esperanza,
                'muestra_requerida' => $muestra,
                'tiempo_procesamiento_horas' => $tiempo,
                'notas' => $notas,
            ]
        ]);
    }

    // Plantilla de resultados (si ya está activada)
    if (array_key_exists('id_plantilla', $_POST) && lab_tables_ready($conn)) {
        $id_tpl = (int) $_POST['id_plantilla'];
        if ($id_tpl) {
            $chk = $conn->prepare("SELECT COUNT(*) FROM lab_plantillas WHERE id_plantilla = ? AND id_hospital = ?");
            $chk->execute([$id_tpl, $id_hospital]);
            if (!$chk->fetchColumn()) $id_tpl = 0;
        }
        $conn->prepare("UPDATE catalogo_pruebas SET id_plantilla = ? WHERE id_prueba = ? AND id_hospital = ?")->execute([$id_tpl ?: null, $id_prueba, $id_hospital]);
    }

    echo json_encode(['success' => true, 'message' => $message]);

} catch (PDOException $e) {
    // Handle database-specific errors
    if ($e->getCode() == 23000) { // Integrity constraint violation
        echo json_encode(['success' => false, 'message' => 'El código de prueba ya existe. Por favor use un código diferente.']);
    } else {
        error_log('Error PDO en laboratory/api/save_test.php: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
} catch (Exception $e) {
    error_log('Error en laboratory/api/save_test.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
