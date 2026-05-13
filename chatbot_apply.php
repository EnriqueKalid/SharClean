<?php
/**
 * chatbot_apply.php — Recibe postulaciones del chatbot de reclutamiento.
 *
 * SQL requerido (ejecutar una sola vez en la BD):
 *
 * CREATE TABLE chatbot_candidatos (
 *   id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *   nombre           VARCHAR(150) NOT NULL,
 *   telefono         VARCHAR(30)  NOT NULL,
 *   estado           VARCHAR(60),
 *   edad             TINYINT UNSIGNED,
 *   experiencia      VARCHAR(80),
 *   traslado         VARCHAR(60),
 *   condicion_salud  TINYINT(1)   DEFAULT 0,
 *   dependientes     TINYINT(1)   DEFAULT 0,
 *   disponibilidad   VARCHAR(60),
 *   ip_address       VARCHAR(45),
 *   created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
 */

header('Content-Type: application/json; charset=utf-8');

require_once 'db/config.php';
require_once 'db/csrf.php';
require_once 'db/ratelimit.php';

checkRateLimit('chatbot', 300);

/* ── CSRF ──────────────────────────────────────────────────── */
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['type' => 'error', 'msg' => 'Token de seguridad inválido. Recarga la página e intenta de nuevo.']);
    exit;
}

/* ── Sanitizar campos ──────────────────────────────────────── */
$nombre        = trim(htmlspecialchars(strip_tags($_POST['nombre']        ?? '')));
$telefono      = trim(htmlspecialchars(strip_tags($_POST['telefono']      ?? '')));
$estado        = trim(htmlspecialchars(strip_tags($_POST['estado']        ?? '')));
$edad          = (int) ($_POST['edad'] ?? 0);
$experiencia   = trim(htmlspecialchars(strip_tags($_POST['experiencia']   ?? '')));
$traslado      = trim(htmlspecialchars(strip_tags($_POST['traslado']      ?? '')));
$salud_raw     = trim($_POST['salud']        ?? '');
$dep_raw       = trim($_POST['dependientes'] ?? '');
$disponibilidad= trim(htmlspecialchars(strip_tags($_POST['disponibilidad'] ?? '')));

$condicion_salud = ($salud_raw === 'Sí') ? 1 : 0;
$dependientes    = ($dep_raw   === 'Sí') ? 1 : 0;

/* ── Validación mínima ─────────────────────────────────────── */
if (empty($nombre) || empty($telefono)) {
    http_response_code(400);
    echo json_encode(['type' => 'error', 'msg' => 'Faltan datos obligatorios.']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? null;

/* ── Guardar en BD ─────────────────────────────────────────── */
try {
    $db   = getDB();
    $stmt = $db->prepare(
        "INSERT INTO chatbot_candidatos
         (nombre, telefono, estado, edad, experiencia, traslado, condicion_salud, dependientes, disponibilidad, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param(
        'sssissiiss',
        $nombre, $telefono, $estado, $edad,
        $experiencia, $traslado,
        $condicion_salud, $dependientes,
        $disponibilidad, $ip
    );
    $stmt->execute();
    $stmt->close();
    $db->close();
} catch (Exception $e) {
    error_log('chatbot_apply.php DB error: ' . $e->getMessage());
}

/* ── Notificación por correo ───────────────────────────────── */
require_once __DIR__ . '/db/app_config.php';
require_once __DIR__ . '/db/mailer.php';
$cfgEmpresa   = appConfig('empresa_nombre',     'Sharclean');
$cfgEmailInfo = appConfig('empresa_email_info', 'informacion@sharclean.com.mx');

$salud_txt = $condicion_salud ? 'Sí' : 'No';
$dep_txt   = $dependientes    ? 'Sí' : 'No';
$edad_txt  = $edad > 0        ? "$edad años" : '—';

$html = emailLayout("Nuevo candidato vía Chatbot – $nombre", "
<h2 style='margin:0 0 20px;color:#0284C7;font-size:20px;'>Nuevo candidato – Chatbot de Reclutamiento</h2>
<table style='width:100%;border-collapse:collapse;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;margin-bottom:20px;'>
  <tr style='background:#f8fafc;'>
    <td style='padding:8px 12px;font-weight:600;color:#475569;width:160px;border-bottom:1px solid #e2e8f0;'>Nombre</td>
    <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') . "</td>
  </tr>
  <tr>
    <td style='padding:8px 12px;font-weight:600;color:#475569;background:#f8fafc;border-bottom:1px solid #e2e8f0;'>Teléfono</td>
    <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($telefono, ENT_QUOTES, 'UTF-8') . "</td>
  </tr>
  <tr style='background:#f8fafc;'>
    <td style='padding:8px 12px;font-weight:600;color:#475569;border-bottom:1px solid #e2e8f0;'>Estado</td>
    <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($estado, ENT_QUOTES, 'UTF-8') . "</td>
  </tr>
  <tr>
    <td style='padding:8px 12px;font-weight:600;color:#475569;background:#f8fafc;border-bottom:1px solid #e2e8f0;'>Edad</td>
    <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'>$edad_txt</td>
  </tr>
  <tr style='background:#f8fafc;'>
    <td style='padding:8px 12px;font-weight:600;color:#475569;border-bottom:1px solid #e2e8f0;'>Experiencia en limpieza</td>
    <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($experiencia, ENT_QUOTES, 'UTF-8') . "</td>
  </tr>
  <tr>
    <td style='padding:8px 12px;font-weight:600;color:#475569;background:#f8fafc;border-bottom:1px solid #e2e8f0;'>Tiempo de traslado</td>
    <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'>" . htmlspecialchars($traslado, ENT_QUOTES, 'UTF-8') . "</td>
  </tr>
  <tr style='background:#f8fafc;'>
    <td style='padding:8px 12px;font-weight:600;color:#475569;border-bottom:1px solid #e2e8f0;'>Condición de salud</td>
    <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'>$salud_txt</td>
  </tr>
  <tr>
    <td style='padding:8px 12px;font-weight:600;color:#475569;background:#f8fafc;border-bottom:1px solid #e2e8f0;'>Dependientes económicos</td>
    <td style='padding:8px 12px;border-bottom:1px solid #e2e8f0;'>$dep_txt</td>
  </tr>
  <tr style='background:#f8fafc;'>
    <td style='padding:8px 12px;font-weight:600;color:#475569;'>Disponibilidad</td>
    <td style='padding:8px 12px;'>" . htmlspecialchars($disponibilidad, ENT_QUOTES, 'UTF-8') . "</td>
  </tr>
</table>
<p style='margin:0;font-size:12px;color:#94a3b8;'>Recibido desde el chatbot de reclutamiento – " . date('d/m/Y H:i') . "</p>
");

sendMail($cfgEmailInfo, "Nuevo candidato Chatbot – $nombre | $cfgEmpresa", $html);

echo json_encode(['type' => 'success']);
