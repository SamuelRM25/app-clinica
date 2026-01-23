<?php
// laboratory/api/save_results.php - Save clinical laboratory results
header('Content-Type: application/json');
session_start();
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

verify_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id_orden = $_POST['id_orden'] ?? null;
$results_data = $_POST['results'] ?? []; // Format: [id_orden_prueba][id_parametro] => value

if (!$id_orden) {
    echo json_encode(['success' => false, 'message' => 'ID de orden no proporcionado']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    // Get patient info for range calculation
    $stmt = $conn->prepare("
        SELECT p.genero, p.fecha_nacimiento 
        FROM ordenes_laboratorio ol 
        JOIN pacientes p ON ol.id_paciente = p.id_paciente 
        WHERE ol.id_orden = ?
    ");
    $stmt->execute([$id_orden]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    $genero = $patient['genero'];
    $edad = date_diff(date_create($patient['fecha_nacimiento']), date_create('today'))->y;

    $conn->beginTransaction();

    // Prepare statements
    $stmt_param = $conn->prepare("SELECT * FROM parametros_pruebas WHERE id_parametro = ?");
    $stmt_upsert = $conn->prepare("
        INSERT INTO resultados_laboratorio 
        (id_orden_prueba, id_parametro, valor_resultado, valor_numerico, fuera_rango, fecha_resultado, procesado_por)
        VALUES (?, ?, ?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE 
        valor_resultado = VALUES(valor_resultado),
        valor_numerico = VALUES(valor_numerico),
        fuera_rango = VALUES(fuera_rango),
        fecha_resultado = NOW(),
        procesado_por = VALUES(procesado_por)
    ");

    $stmt_status = $conn->prepare("UPDATE orden_pruebas SET estado = 'En_Proceso' WHERE id_orden_prueba = ?");

    foreach ($results_data as $id_orden_prueba => $params) {
        foreach ($params as $id_parametro => $valor) {
            if ($valor === '')
                continue;

            // Get parameter reference values
            $stmt_param->execute([$id_parametro]);
            $p = $stmt_param->fetch(PDO::FETCH_ASSOC);

            // Calculate flag
            $fuera_rango = 'Normal';
            $valor_num = is_numeric($valor) ? (float) $valor : null;

            if ($valor_num !== null) {
                $min = 0;
                $max = 0;
                if ($edad <= 12) {
                    $min = $p['valor_ref_pediatrico_min'];
                    $max = $p['valor_ref_pediatrico_max'];
                } elseif ($genero === 'Masculino') {
                    $min = $p['valor_ref_hombre_min'];
                    $max = $p['valor_ref_hombre_max'];
                } else {
                    $min = $p['valor_ref_mujer_min'];
                    $max = $p['valor_ref_mujer_max'];
                }

                if ($min !== null && $max !== null) {
                    if ($valor_num < $min)
                        $fuera_rango = 'Bajo';
                    elseif ($valor_num > $max)
                        $fuera_rango = 'Alto';
                }
            }

            $stmt_upsert->execute([
                $id_orden_prueba,
                $id_parametro,
                $valor,
                $valor_num,
                $fuera_rango,
                $_SESSION['user_id']
            ]);
        }

        // Update test status
        $stmt_status->execute([$id_orden_prueba]);
    }

    // Update order status if it was Muestra_Recibida
    $stmt = $conn->prepare("UPDATE ordenes_laboratorio SET estado = 'En_Proceso' WHERE id_orden = ? AND estado = 'Muestra_Recibida'");
    $stmt->execute([$id_orden]);

    // Start file upload handling
    if (isset($_FILES['archivo_resultados']) && $_FILES['archivo_resultados']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../../uploads/results/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileInfo = pathinfo($_FILES['archivo_resultados']['name']);
        $extension = strtolower($fileInfo['extension']);
        $newFileName = 'orden_' . $id_orden . '_' . uniqid() . '.' . $extension;
        $targetPath = $uploadDir . $newFileName;

        $allowedExts = ['pdf', 'jpg', 'jpeg', 'png'];

        if (in_array($extension, $allowedExts)) {
            if (move_uploaded_file($_FILES['archivo_resultados']['tmp_name'], $targetPath)) {
                // Save relative path to DB
                $dbPath = '../../uploads/results/' . $newFileName;
                $stmt_file = $conn->prepare("UPDATE ordenes_laboratorio SET archivo_resultados = ? WHERE id_orden = ?");
                $stmt_file->execute([$dbPath, $id_orden]);
            }
        }
    }
    // End file upload handling

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Resultados guardados correctamente']);

} catch (Exception $e) {
    if (isset($conn))
        $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
