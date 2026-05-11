<?php
/**
 * Sharclean – Contact Form Handler
 * Endpoint: POST /contact.php
 * Returns: JSON {type, msg}
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['type' => 'error', 'msg' => 'Método no permitido.']);
    exit;
}

require_once __DIR__ . '/db/config.php';
require_once __DIR__ . '/db/csrf.php';
require_once __DIR__ . '/db/ratelimit.php';

checkRateLimit('contact', 60);

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['type' => 'error', 'msg' => 'Token de seguridad inválido. Recargue la página e intente de nuevo.']);
    exit;
}

function sanitize(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

$name    = sanitize($_POST['contact-name']    ?? '');
$email   = sanitize($_POST['contact-email']   ?? '');
$message = sanitize($_POST['contact-message'] ?? '');

$errors = [];
if (empty($name))    $errors[] = 'El nombre es requerido.';
if (empty($email))   $errors[] = 'El correo electrónico es requerido.';
if (empty($message)) $errors[] = 'El mensaje es requerido.';
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'El formato del correo no es válido.';
}

if (!empty($errors)) {
    echo json_encode(['type' => 'error', 'msg' => implode(' ', $errors)]);
    exit;
}

// Save to DB
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
try {
    $db   = getDB();
    $stmt = $db->prepare('INSERT INTO contactos (nombre, email, mensaje, ip_address) VALUES (?, ?, ?, ?)');
    if (!$stmt) throw new RuntimeException($db->error);
    $stmt->bind_param('ssss', $name, $email, $message, $ip);
    if (!$stmt->execute()) throw new RuntimeException($stmt->error);
    $stmt->close();
    $db->close();
} catch (RuntimeException $e) {
    error_log('[Sharclean Contact] DB Error: ' . $e->getMessage());
    echo json_encode(['type' => 'error', 'msg' => 'Error al guardar el mensaje. Intente de nuevo o escríbanos directamente.']);
    exit;
}

// Send notification email
require_once __DIR__ . '/db/mailer.php';

$notifyHtml = emailLayout('Nuevo mensaje de contacto', "
<h2 style='margin:0 0 20px;color:#0284C7;font-size:20px;'>Nuevo mensaje de contacto</h2>
<p style='margin:6px 0;'><strong>Fecha:</strong> " . date('d/m/Y H:i:s') . "</p>
<p style='margin:6px 0;'><strong>Nombre:</strong> " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</p>
<p style='margin:6px 0;'><strong>Email:</strong> <a href='mailto:$email' style='color:#0284C7;'>$email</a></p>
<hr style='border:none;border-top:1px solid #e2e8f0;margin:20px 0;'>
<p style='margin:0 0 8px;'><strong>Mensaje:</strong></p>
<p style='background:#f8fafc;border-left:4px solid #0284C7;padding:14px 16px;border-radius:0 6px 6px 0;margin:0;'>" . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . "</p>
");
sendMail('informacion@sharclean.com.mx', "Nuevo mensaje de contacto – $name | Sharclean", $notifyHtml, $email);

// Auto-reply to sender
$replyHtml = emailLayout('Hemos recibido tu mensaje', "
<h2 style='margin:0 0 16px;color:#0284C7;font-size:20px;'>¡Gracias por escribirnos!</h2>
<p>Estimado/a <strong>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
<p>Hemos recibido su mensaje y le responderemos a la brevedad posible.</p>
<p>Si su consulta es urgente, puede contactarnos directamente:</p>
<ul style='line-height:2.2;padding-left:20px;'>
  <li>Email: <a href='mailto:informacion@sharclean.com.mx' style='color:#0284C7;'>informacion@sharclean.com.mx</a></li>
  <li>Teléfono / WhatsApp: +52 55-8945-4415</li>
</ul>
<p style='color:#64748b;font-size:13px;'>Horario de atención: Lun–Vie 8am–6pm | Sáb 9am–2pm</p>
");
sendMail($email, 'Hemos recibido tu mensaje – Sharclean', $replyHtml);

echo json_encode([
    'type' => 'success',
    'msg'  => '¡Mensaje enviado! Nos pondremos en contacto con usted a la brevedad. Gracias por escribirnos.',
]);
