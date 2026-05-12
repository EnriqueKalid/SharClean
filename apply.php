<?php
/**
 * apply.php — Manejador de solicitudes de empleo
 * Sharclean – Bolsa de trabajo
 */
header('Content-Type: application/json; charset=utf-8');

require_once 'db/config.php';
require_once 'db/csrf.php';
require_once 'db/ratelimit.php';

checkRateLimit('apply', 60);

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['type' => 'error', 'msg' => 'Token de seguridad inválido. Recargue la página e intente de nuevo.']);
    exit;
}

/* ── Campos requeridos ─────────────────────────────────────── */
$required = ['apply-name', 'apply-phone', 'apply-email', 'apply-position'];
$labels   = [
    'apply-name'     => 'Nombre completo',
    'apply-phone'    => 'Teléfono',
    'apply-email'    => 'Correo electrónico',
    'apply-position' => 'Puesto de interés',
];

$missing = [];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        $missing[] = $labels[$field];
    }
}

if (!empty($missing)) {
    echo json_encode(['type' => 'error', 'msg' => 'Por favor complete los campos: ' . implode(', ', $missing) . '.']);
    exit;
}

if (filter_var($_POST['apply-email'], FILTER_VALIDATE_EMAIL) === false) {
    echo json_encode(['type' => 'error', 'msg' => 'Por favor ingrese un correo electrónico válido.']);
    exit;
}

/* ── Validar y mover CV si se adjuntó ──────────────────────── */
$cvFilename = null;
if (!empty($_FILES['apply-cv']['name'])) {
    $allowedTypes = ['application/pdf', 'application/msword',
                     'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $maxSize = 5 * 1024 * 1024; // 5 MB

    if (!in_array($_FILES['apply-cv']['type'], $allowedTypes)) {
        echo json_encode(['type' => 'error', 'msg' => 'Solo se aceptan archivos PDF o Word (.doc, .docx).']);
        exit;
    }

    if ($_FILES['apply-cv']['size'] > $maxSize) {
        echo json_encode(['type' => 'error', 'msg' => 'El archivo no debe superar 5 MB.']);
        exit;
    }

    $uploadDir = __DIR__ . '/uploads/cvs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0750, true);
    }

    $ext        = pathinfo($_FILES['apply-cv']['name'], PATHINFO_EXTENSION);
    $cvFilename = uniqid('cv_', true) . '.' . strtolower($ext);
    $destPath   = $uploadDir . $cvFilename;

    if (!move_uploaded_file($_FILES['apply-cv']['tmp_name'], $destPath)) {
        echo json_encode(['type' => 'error', 'msg' => 'No se pudo guardar el archivo. Intente de nuevo.']);
        exit;
    }
}

/* ── Sanitizar campos ──────────────────────────────────────── */
$name       = htmlspecialchars(strip_tags($_POST['apply-name']));
$age        = isset($_POST['apply-age']) ? (int) $_POST['apply-age'] : null;
$phone      = htmlspecialchars(strip_tags($_POST['apply-phone']));
$email      = htmlspecialchars(strip_tags($_POST['apply-email']));
$position   = htmlspecialchars(strip_tags($_POST['apply-position']));
$experience = isset($_POST['apply-experience'])
              ? htmlspecialchars(strip_tags($_POST['apply-experience']))
              : '';
$vacanteId  = (int)($_POST['vacante_id'] ?? 0) ?: null;

/* ── Guardar en base de datos ──────────────────────────────── */
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
try {
    $db   = getDB();
    $stmt = $db->prepare(
        "INSERT INTO aplicaciones_empleo (nombre, edad, telefono, email, puesto_interes, experiencia, cv_filename, ip_address, vacante_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('siisssssi', $name, $age, $phone, $email, $position, $experience, $cvFilename, $ip, $vacanteId);
    $stmt->execute();
    $stmt->close();
    $db->close();
} catch (Exception $e) {
    error_log('apply.php DB error: ' . $e->getMessage());
    // Continue even if DB save fails — still send email
}

/* ── Notificación por correo ───────────────────────────────── */
require_once __DIR__ . '/db/mailer.php';

$cvInfo = $cvFilename
    ? "<p><strong>CV:</strong> Guardado como <code>$cvFilename</code> en <code>uploads/cvs/</code></p>"
    : '<p><strong>CV:</strong> No adjuntado</p>';

$expInfo = !empty($experience)
    ? "<p style='background:#f8fafc;border-left:4px solid #0284C7;padding:14px 16px;border-radius:0 6px 6px 0;margin:0;'>" . nl2br(htmlspecialchars($experience, ENT_QUOTES, 'UTF-8')) . "</p>"
    : '<p style="color:#94a3b8;">No especificada</p>';

$notifyHtml = emailLayout("Nueva solicitud de empleo – $position", "
<h2 style='margin:0 0 20px;color:#0284C7;font-size:20px;'>Nueva solicitud de empleo</h2>
<table style='width:100%;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:20px;'>
  <tr><td style='padding:8px 12px;font-weight:600;color:#475569;background:#f8fafc;width:140px;border-bottom:1px solid #e2e8f0;'>Nombre</td><td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</td></tr>
  <tr><td style='padding:8px 12px;font-weight:600;color:#475569;background:#f8fafc;border-bottom:1px solid #e2e8f0;'>Edad</td><td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'>" . ($age ? "$age años" : '—') . "</td></tr>
  <tr><td style='padding:8px 12px;font-weight:600;color:#475569;background:#f8fafc;border-bottom:1px solid #e2e8f0;'>Teléfono</td><td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . "</td></tr>
  <tr><td style='padding:8px 12px;font-weight:600;color:#475569;background:#f8fafc;border-bottom:1px solid #e2e8f0;'>Email</td><td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'><a href='mailto:$email' style='color:#0284C7;'>$email</a></td></tr>
  <tr><td style='padding:8px 12px;font-weight:600;color:#475569;background:#f8fafc;'>Puesto</td><td style='padding:8px 12px;'>" . htmlspecialchars($position, ENT_QUOTES, 'UTF-8') . "</td></tr>
</table>
<p style='margin:0 0 8px;'><strong>Experiencia previa:</strong></p>
$expInfo
<br>$cvInfo
");
sendMail('informacion@sharclean.com.mx', "Nueva solicitud de empleo – $position | Sharclean", $notifyHtml, $email);

echo json_encode([
    'type' => 'success',
    'msg'  => '¡Gracias ' . $name . '! Hemos recibido tu solicitud. Te contactaremos pronto.',
]);
