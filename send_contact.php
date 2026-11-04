<?php
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Campo trampa: los bots lo llenan, las personas no lo ven
if (!empty($_POST['website'])) {
    echo json_encode(['success' => true]);
    exit;
}

// Una sola línea y con largo máximo (evita inyectar encabezados en el correo)
function campo_linea(string $key, int $max): string {
    $v = trim((string) ($_POST[$key] ?? ''));
    $v = preg_replace('/[\r\n\t]+/', ' ', $v);
    return mb_substr($v, 0, $max);
}

$name    = campo_linea('name', 120);
$email   = campo_linea('email', 160);
$phone   = campo_linea('phone', 40);
$clinica = campo_linea('clinica', 160);
$plan    = campo_linea('plan', 120);
$message = mb_substr(trim((string) ($_POST['message'] ?? '')), 0, 5000);

if (empty($name) || empty($email) || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Campos obligatorios faltantes']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Email inválido']);
    exit;
}

$to      = 'samuel.ramirez25prs@gmail.com';
$subject = "Nuevo contacto desde ClinicApp — $name";
$body    = "Has recibido un nuevo mensaje desde la página de presentación de ClinicApp/Centro Médico RS.\n\n";
$body   .= "━ NUEVO CONTACTO ━━━━━━━━━━━━━━━━━━━━━━━\n\n";
$body   .= "Nombre:     $name\n";
$body   .= "Email:      $email\n";
$body   .= "Teléfono:   " . ($phone ?: 'No especificado') . "\n";
$body   .= "Clínica:    " . ($clinica ?: 'No especificada') . "\n";
$body   .= "Plan:       " . ($plan ?: 'No especificado') . "\n";
$body   .= "\n━ MENSAJE ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
$body   .= wordwrap($message, 72) . "\n";
$body   .= "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
$body   .= "Enviado desde: clinicapp.gt\n";
$body   .= "Fecha: " . date('d/m/Y H:i:s') . "\n";
$body   .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Desconocida') . "\n";

$headers   = [];
$headers[] = 'From: Centro Médico RS <no-reply@clinicamgrs.com>';
$headers[] = 'Reply-To: ' . $email;
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';

$success = mail($to, $subject, $body, implode("\r\n", $headers));

if ($success) {
    $confirm_subject = "Recibimos tu mensaje — Centro Médico RS";
    $confirm_body    = "Hola $name,\n\n";
    $confirm_body   .= "Gracias por contactarnos a través de nuestra página.\n";
    $confirm_body   .= "Hemos recibido tu mensaje y te responderemos a la brevedad posible.\n\n";
    $confirm_body   .= "Resumen de tu mensaje:\n";
    $confirm_body   .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $confirm_body   .= wordwrap($message, 72) . "\n";
    $confirm_body   .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    $confirm_body   .= "Saludos,\nEquipo Centro Médico RS\nRS SOLUTIONS";
    $confirm_headers   = [];
    $confirm_headers[] = 'From: Centro Médico RS <no-reply@clinicamgrs.com>';
    $confirm_headers[] = 'MIME-Version: 1.0';
    $confirm_headers[] = 'Content-Type: text/plain; charset=UTF-8';
    @mail($email, $confirm_subject, $confirm_body, implode("\r\n", $confirm_headers));

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al enviar el correo']);
}
exit;
