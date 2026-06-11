<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/multitenant.php';

$id_hospital = (int) ($_SESSION['id_hospital'] ?? 0);
$id_encamamiento = isset($_GET['id']) ? intval($_GET['id']) : 0;

header('Content-Type: text/plain');

if ($id_encamamiento == 0) {
    die("No ID provided");
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    echo "Session:\n";
    echo "  id_hospital: " . ($id_hospital ?: 'NOT SET') . "\n";
    echo "  user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n\n";

    // Encamamiento
    $stmt = $conn->prepare("SELECT e.*, pac.nombre as nombre_paciente, pac.apellido as apellido_paciente FROM encamamientos e INNER JOIN pacientes pac ON e.id_paciente = pac.id_paciente WHERE e.id_encamamiento = ?");
    $stmt->execute([$id_encamamiento]);
    $enc = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($enc) {
        echo "Encamamiento: FOUND\n";
        echo "  Paciente: " . $enc['nombre_paciente'] . " " . $enc['apellido_paciente'] . "\n";
        echo "  id_hospital in record: " . $enc['id_hospital'] . "\n";
        echo "  Session matches: " . ($enc['id_hospital'] == $id_hospital ? 'YES' : 'NO') . "\n\n";
    } else {
        echo "Encamamiento: NOT FOUND\n\n";
    }

    // Cuenta
    $stmt_c = $conn->prepare("SELECT * FROM cuenta_hospitalaria WHERE id_encamamiento = ?");
    $stmt_c->execute([$id_encamamiento]);
    $cuenta = $stmt_c->fetch(PDO::FETCH_ASSOC);
    if ($cuenta) {
        echo "Cuenta: FOUND\n";
        echo "  id_cuenta: " . $cuenta['id_cuenta'] . "\n";
        echo "  total_general: " . $cuenta['total_general'] . "\n";
        echo "  subtotal_habitacion: " . $cuenta['subtotal_habitacion'] . "\n";
        echo "  id_hospital in record: " . $cuenta['id_hospital'] . "\n\n";
    } else {
        echo "Cuenta: NOT FOUND\n\n";
    }

    // Cargos
    if ($cuenta) {
        $stmt_cargos = $conn->prepare("SELECT COUNT(*) FROM cargos_hospitalarios WHERE id_cuenta = ? AND cancelado = FALSE");
        $stmt_cargos->execute([$cuenta['id_cuenta']]);
        echo "Cargos (no filter by hospital): " . $stmt_cargos->fetchColumn() . "\n";

        $stmt_cargos2 = $conn->prepare("SELECT COUNT(*) FROM cargos_hospitalarios WHERE id_cuenta = ? AND cancelado = FALSE AND id_hospital = ?");
        $stmt_cargos2->execute([$cuenta['id_cuenta'], $id_hospital]);
        echo "Cargos (filtered by hospital=" . $id_hospital . "): " . $stmt_cargos2->fetchColumn() . "\n";
    }

    // What the page's if($cuenta) would evaluate to
    echo "\n--- Page rendering simulation ---\n";
    echo "if (\$cuenta): " . ($cuenta && $cuenta['id_hospital'] == $id_hospital ? 'TRUE (content shown)' : 'FALSE (warning shown)') . "\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
