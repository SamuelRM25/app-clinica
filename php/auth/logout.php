<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';
start_app_session();

$user_id = $_SESSION['user_id'] ?? null;
$user_nombre = $_SESSION['nombre'] ?? null;

audit_log_auth('logout', 'Usuario: ' . ($user_nombre ?? 'unknown') . ' - Sesión cerrada');

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: ../../index.php");
exit;
?>