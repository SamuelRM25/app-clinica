<?php
// laboratory/configuracion.php - Configuración específica del laboratorio
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

verify_session();

// En esta versión, redirigimos a la configuración global para centralizar la gestión
header("Location: ../settings/index.php");
exit;
?>
