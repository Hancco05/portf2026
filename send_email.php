<?php
// ============================================================
// CONFIGURACIÓN - ¡CAMBIA ESTOS DATOS!
// ============================================================
$recipient = "tu-email@dominio.com";       // Correo donde recibirás los mensajes
$sender_name = "Hancco Portafolio";
$sender_email = "noreply@tudominio.com";

// Configuración SMTP (ejemplo con Gmail)
$smtp_host = 'smtp.gmail.com';
$smtp_port = 587;
$smtp_auth = true;
$smtp_user = 'tu-correo@gmail.com';
$smtp_pass = 'tu-contraseña-app';
$smtp_secure = 'tls';

// ============================================================
// SEGURIDAD: Rate Limiting (5 intentos por hora)
// ============================================================
define('RATE_LIMIT', 5);
define('RATE_WINDOW', 3600);

function isRateLimited($ip) {
    $logFile = __DIR__ . '/rate_limit.log';
    $now = time();
    $windowStart = $now - RATE_WINDOW;
    if (!file_exists($logFile)) {
        file_put_contents($logFile, '');
        return false;
    }
    $entries = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $validEntries = [];
    $count = 0;
    foreach ($entries as $entry) {
        list($timestamp, $entryIp) = explode('|', $entry);
        if ($entryIp === $ip && $timestamp >= $windowStart) {
            $validEntries[] = $entry;
            $count++;
        }
    }
    file_put_contents($logFile, implode("\n", $validEntries) . "\n");
    if ($count >= RATE_LIMIT) return true;
    file_put_contents($logFile, "$now|$ip\n", FILE_APPEND);
    return false;
}

// ============================================================
// CSRF
// ============================================================
session_start();
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================================
// PROCESAR POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
    exit;
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (isRateLimited($clientIp)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Has excedido el límite de envíos. Espera un momento.',
        'retry_after' => RATE_WINDOW
    ]);
    exit;
}

$name = trim(filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING));
$email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
$message = trim(filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING));

if (empty($name) || empty($email) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'El correo no es válido.']);
    exit;
}
if (preg_match("/[\r\n]/", $name) || preg_match("/[\r\n]/", $email)) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos.']);
    exit;
}

// ============================================================
// ENVÍO CON PHPMailer (ajusta las rutas según tu instalación)
// ============================================================
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';
require 'PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $smtp_host;
    $mail->SMTPAuth   = $smtp_auth;
    $mail->Username   = $smtp_user;
    $mail->Password   = $smtp_pass;
    $mail->SMTPSecure = $smtp_secure === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = $smtp_port;

    $mail->setFrom($sender_email, $sender_name);
    $mail->addAddress($recipient, 'Hancco');
    $mail->addReplyTo($email, $name);

    $mail->isHTML(false);
    $mail->Subject = "Contacto desde portafolio: $name";
    $body = "Has recibido un nuevo mensaje desde el portafolio de Hancco.\n\n";
    $body .= "Nombre: $name\n";
    $body .= "Correo: $email\n";
    $body .= "Mensaje:\n$message\n";
    $mail->Body = $body;

    $mail->send();

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $newCsrf = $_SESSION['csrf_token'];

    echo json_encode([
        'success' => true,
        'message' => 'Correo enviado correctamente.',
        'new_csrf' => $newCsrf
    ]);

} catch (Exception $e) {
    error_log("Error al enviar correo: " . $mail->ErrorInfo);
    echo json_encode([
        'success' => false,
        'message' => 'Error al enviar el correo. Intenta nuevamente más tarde.'
    ]);
}