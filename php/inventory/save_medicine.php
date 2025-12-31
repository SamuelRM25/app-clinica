<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Establecer la zona horaria correcta
date_default_timezone_set('America/Guatemala');


verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        $stmt = $conn->prepare("INSERT INTO inventario (nom_medicamento, mol_medicamento, presentacion_med, casa_farmaceutica, cantidad_med, fecha_adquisicion, fecha_vencimiento) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $result = $stmt->execute([
            $_POST['nom_medicamento'],
            $_POST['mol_medicamento'],
            $_POST['presentacion_med'],
            $_POST['casa_farmaceutica'],
            $_POST['cantidad_med'],
            $_POST['fecha_adquisicion'],
            $_POST['fecha_vencimiento']
        ]);

        if ($result) {
            $_SESSION['inventory_message'] = 'Medicamento agregado correctamente';
            $_SESSION['inventory_status'] = 'success';
        } else {
            $_SESSION['inventory_message'] = 'Error al agregar el medicamento';
            $_SESSION['inventory_status'] = 'error';
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        $_SESSION['inventory_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['inventory_status'] = 'error';
    }
    
    // Redirect back to inventory page
    header('Location: index.php');
    exit;
}