<?php
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function verify_session() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /appClinica/index.php");
        exit();
    }
}
?>