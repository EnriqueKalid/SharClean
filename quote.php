<?php
/**
 * Sharclean – Quote Form Handler
 * Endpoint: POST /quote.php
 * Returns: JSON {type, msg}
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['type' => 'error', 'msg' => 'Método no permitido.']);
    exit;
}

require_once __DIR__ . '/db/config.php';
require_once __DIR__ . '/db/app_config.php';
require_once __DIR__ . '/db/csrf.php';
require_once __DIR__ . '/db/ratelimit.php';

checkRateLimit('quote', 60);

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['type' => 'error', 'msg' => 'Token de seguridad inválido. Recargue la página e intente de nuevo.']);
    exit;
}

/* ── Sanitize helper ───────────────────────────────────────── */
function sanitize(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

/* ── Collect & sanitize inputs ─────────────────────────────── */
$name        = sanitize($_POST['quote-name']        ?? '');
$company     = sanitize($_POST['quote-company']     ?? '');
$phone       = sanitize($_POST['quote-phone']       ?? '');
$email       = sanitize($_POST['quote-email']       ?? '');
$service     = sanitize($_POST['quote-service']     ?? '');
$description = sanitize($_POST['quote-description'] ?? '');
$area        = sanitize($_POST['quote-area']        ?? '');
$message     = sanitize($_POST['quote-message']     ?? '');

/* ── Validation ─────────────────────────────────────────────── */
$errors = [];

if (empty($name))    $errors[] = 'El nombre es requerido.';
if (empty($phone))   $errors[] = 'El teléfono es requerido.';
if (empty($email))   $errors[] = 'El correo electrónico es requerido.';
if (empty($service)) $errors[] = 'Seleccione un tipo de servicio.';

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'El formato del correo electrónico no es válido.';
}

if (!empty($phone) && !preg_match('/^\+?[\d\s\-().]{10,}$/', $phone)) {
    $errors[] = 'El teléfono debe tener al menos 10 dígitos.';
}

if (!empty($area) && (!is_numeric($area) || (float)$area <= 0)) {
    $errors[] = 'La superficie debe ser un número positivo.';
}

if (!empty($errors)) {
    echo json_encode(['type' => 'error', 'msg' => implode(' ', $errors)]);
    exit;
}

// Normalize area
$areaNormalized = !empty($area) ? (float)$area : null;

/* ── Save to database ───────────────────────────────────────── */
try {
    $db = getDB();

    $stmt = $db->prepare(
        'INSERT INTO cotizaciones
            (nombre, empresa, telefono, email, servicio, descripcion, area_m2, mensaje, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        throw new RuntimeException('Error preparando consulta: ' . $db->error);
    }

    $estado = 'pending';
    $stmt->bind_param(
        'ssssssdss',
        $name, $company, $phone, $email,
        $service, $description, $areaNormalized,
        $message, $estado
    );

    if (!$stmt->execute()) {
        throw new RuntimeException('Error ejecutando consulta: ' . $stmt->error);
    }

    $stmt->close();
    $db->close();
} catch (RuntimeException $e) {
    error_log('[Sharclean Quote] DB Error: ' . $e->getMessage());
    echo json_encode(['type' => 'error', 'msg' => 'Error al guardar su solicitud. Inténtelo de nuevo o contáctenos directamente.']);
    exit;
}

/* ── Send email notification ────────────────────────────────── */
require_once __DIR__ . '/db/mailer.php';
$cfgEmpresa        = appConfig('empresa_nombre',          'Sharclean');
$cfgEmailComercial = appConfig('empresa_email_comercial', 'comercializacion@sharclean.com.mx');
$cfgEmailInfo      = appConfig('empresa_email_info',      'informacion@sharclean.com.mx');
$cfgTelefono       = appConfig('empresa_telefono',        '+52 55-8945-4415');

function row(string $label, string $value): string {
    return "<tr>
      <td style='padding:8px 12px;font-weight:600;color:#475569;background:#f8fafc;width:140px;border-bottom:1px solid #e2e8f0;'>$label</td>
      <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "</td>
    </tr>";
}

$notifyHtml = emailLayout('Nueva solicitud de cotización', "
<h2 style='margin:0 0 20px;color:#0284C7;font-size:20px;'>Nueva solicitud de cotización</h2>
<p style='margin:0 0 16px;color:#64748b;font-size:13px;'>Recibida el " . date('d/m/Y H:i:s') . "</p>
<h3 style='margin:0 0 8px;font-size:14px;color:#64748b;text-transform:uppercase;letter-spacing:.05em;'>Datos del solicitante</h3>
<table style='width:100%;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:20px;'>
" . row('Nombre', $name) . row('Empresa', $company ?: 'No especificada') . row('Teléfono', $phone) . row('Email', $email) . "
</table>
<h3 style='margin:0 0 8px;font-size:14px;color:#64748b;text-transform:uppercase;letter-spacing:.05em;'>Detalles del servicio</h3>
<table style='width:100%;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:20px;'>
" . row('Servicio', $service) . row('Inmueble', $description ?: 'No especificado') . row('Superficie', $areaNormalized ? number_format($areaNormalized, 0) . ' m²' : 'No especificada') . "
</table>
" . ($message ? "<p><strong>Mensaje adicional:</strong></p><p style='background:#f8fafc;border-left:4px solid #0284C7;padding:14px 16px;border-radius:0 6px 6px 0;margin:0;'>" . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . "</p>" : '') . "
");

$mailSent = sendMail($cfgEmailComercial, "Nueva solicitud de cotización – $name | $cfgEmpresa", $notifyHtml, $email);
if (!$mailSent) error_log('[Sharclean Quote] Failed to send notification email for: ' . $email);

/* ── Auto-reply to client ───────────────────────────────────── */
$replyHtml = emailLayout('Recibimos tu solicitud', "
<h2 style='margin:0 0 16px;color:#0284C7;font-size:20px;'>¡Solicitud recibida!</h2>
<p>Estimado/a <strong>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</strong>,</p>
<p>Hemos recibido su solicitud de cotización para el servicio de <strong>" . htmlspecialchars($service, ENT_QUOTES, 'UTF-8') . "</strong>.</p>
<p>Un asesor se pondrá en contacto con usted en las próximas <strong>24 horas hábiles</strong> con una propuesta personalizada.</p>
<p>Si necesita atención urgente:</p>
<ul style='line-height:2.2;padding-left:20px;'>
  <li>Email: <a href='mailto:$cfgEmailInfo' style='color:#0284C7;'>$cfgEmailInfo</a></li>
  <li>Teléfono / WhatsApp: $cfgTelefono</li>
</ul>
<p style='color:#64748b;font-size:13px;'>Horario: Lun–Vie 8am–6pm | Sáb 9am–2pm</p>
");
sendMail($email, "Recibimos tu solicitud de cotización – $cfgEmpresa", $replyHtml);

/* ── Success response ───────────────────────────────────────── */
echo json_encode([
    'type' => 'success',
    'msg'  => '¡Solicitud enviada correctamente! Un asesor se pondrá en contacto con usted en menos de 24 horas. También recibirá una confirmación en su correo.',
]);
