<?php
// test_save.php - Diagnostic script to test save_test.php
session_start();

// Simulate admin session
$_SESSION['user_id'] = 1;
$_SESSION['tipoUsuario'] = 'admin';
$_SESSION['nombre'] = 'Test Admin';

// Simulate POST data
$_POST['nombre'] = 'Hematologia completa';
$_POST['codigo'] = 'HEM-01';
$_POST['categoria'] = 'Hematologia';
$_POST['descripcion'] = 'Prueba';
$_POST['precio'] = '150';
$_POST['muestra_requerida'] = 'Sangre';
$_POST['tiempo_procesamiento_horas'] = '3';

// Set request method
$_SERVER['REQUEST_METHOD'] = 'POST';

echo "Testing save_test.php with the following data:\n";
print_r($_POST);
echo "\n\n";

// Include the save script
ob_start();
include 'save_test.php';
$output = ob_get_clean();

echo "Response from save_test.php:\n";
echo $output;
echo "\n";
?>