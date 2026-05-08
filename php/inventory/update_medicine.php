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
                                codigo_barras = ?,
                                nom_medicamento = ?, 
                                mol_medicamento = ?, 
                                presentacion_med = ?, 
                                casa_farmaceutica = ?, 
                                cantidad_med = ?, 
                                fecha_adquisicion = ?, 
                                fecha_vencimiento = ?,
                                precio_venta = ?,
                                precio_compra = ?,
                                precio_hospital = ?,
                                precio_medico = ?,
                                stock_hospital = ?
                                WHERE id_inventario = ?");

        $result = $stmt->execute([
            $_POST['codigo_barras'] ?? null,
            $_POST['nom_medicamento'],
            $_POST['mol_medicamento'],
            $_POST['presentacion_med'],
            $_POST['casa_farmaceutica'],
            $_POST['cantidad_med'],
            $_POST['fecha_adquisicion'],
            $_POST['fecha_vencimiento'],
            $_POST['precio_venta'] ?? 0.00,
            $_POST['precio_compra'] ?? 0.00,
            $_POST['precio_hospital'] ?? 0.00,
            $_POST['precio_medico'] ?? 0.00,
            $_POST['stock_hospital'] ?? 0,
            $_POST['id_inventario']
        ]);

        if ($result) {
            // Requerimiento 4: Actualizar unit_cost en purchase_items si el inventario se actualiza
            $stmt_pi = $conn->prepare("SELECT id_purchase_item FROM inventario WHERE id_inventario = ?");
            $stmt_pi->execute([$_POST['id_inventario']]);
            $inv_row = $stmt_pi->fetch(PDO::FETCH_ASSOC);

            if ($inv_row && !empty($inv_row['id_purchase_item'])) {
                $stmt_update_pi = $conn->prepare("UPDATE purchase_items SET unit_cost = ? WHERE id = ?");
                $stmt_update_pi->execute([$_POST['precio_compra'] ?? 0.00, $inv_row['id_purchase_item']]);
            }

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