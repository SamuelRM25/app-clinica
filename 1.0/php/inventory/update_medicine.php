<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_inventario'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        $stmt = $conn->prepare("UPDATE inventario SET 
                                nom_medicamento = ?, 
                                mol_medicamento = ?, 
                                presentacion_med = ?, 
                                casa_farmaceutica = ?, 
                                cantidad_med = ?, 
                                fecha_adquisicion = ?, 
                                fecha_vencimiento = ? 
                                WHERE id_inventario = ?");
        
        $result = $stmt->execute([
            $_POST['nom_medicamento'],
            $_POST['mol_medicamento'],
            $_POST['presentacion_med'],
            $_POST['casa_farmaceutica'],
            $_POST['cantidad_med'],
            $_POST['fecha_adquisicion'],
            $_POST['fecha_vencimiento'],
            $_POST['id_inventario']
        ]);

        if ($result) {
            $_SESSION['inventory_message'] = 'Medicamento actualizado correctamente';
            $_SESSION['inventory_status'] = 'success';
        } else {
            $_SESSION['inventory_message'] = 'Error al actualizar el medicamento';
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