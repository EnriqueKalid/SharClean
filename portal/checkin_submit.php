<?php
/**
 * Sharclean Portal – Registrar check-in / check-out del empleado
 * Recibe: lat, lng, foto (archivo JPEG; o data URL base64 de versiones anteriores), face_score (0-100 o vacío)
 * Responde SIEMPRE JSON (también si la sesión venció o falla la BD), para que la pantalla
 * muestre el motivo real en vez de "No se pudo conectar con el servidor".
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/_boot.php';
if (empty($_SESSION['portal_empleado_id'])) {      // auth.php redirigiría al login (HTML)
    http_response_code(401);
    echo json_encode(['type' => 'error', 'code' => 'sesion', 'msg' => 'Tu sesión se cerró. Vuelve a entrar para registrar tu asistencia.']);
    exit;
}
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db/csrf.php';
require_once __DIR__ . '/../db/asistencia_calc.php';

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['type' => 'error', 'msg' => 'Token de seguridad inválido. Recarga la página e intenta de nuevo.']);
    exit;
}

$emp   = portalEmpleado();
$empId = (int)$emp['id'];

/* ── Ubicación ──────────────────────────────────────────────── */
$lat = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float)$_POST['lat'] : null;
$lng = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float)$_POST['lng'] : null;
if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    echo json_encode(['type' => 'error', 'msg' => 'No se pudo obtener tu ubicación. Activa el GPS e intenta de nuevo.']);
    exit;
}

/* ── Selfie: archivo (actual) o data URL base64 (pantalla en caché de la versión anterior) ── */
$binary = false;
if (isset($_FILES['foto']) && is_array($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK
    && is_uploaded_file($_FILES['foto']['tmp_name'])) {
    $binary = file_get_contents($_FILES['foto']['tmp_name']);
} else {
    $fotoData = (string)($_POST['foto'] ?? '');
    if (preg_match('#^data:image/(jpe?g|png);base64,#i', $fotoData)) {
        $binary = base64_decode(substr($fotoData, strpos($fotoData, ',') + 1), true);
    }
}
if ($binary === false || $binary === '') {
    echo json_encode(['type' => 'error', 'msg' => 'No se recibió la foto. Tómala de nuevo.']);
    exit;
}
if (strlen($binary) < 500 || strlen($binary) > 5 * 1024 * 1024) {
    echo json_encode(['type' => 'error', 'msg' => 'La foto no es válida o es demasiado grande.']);
    exit;
}
$info   = @getimagesizefromstring($binary);
$extMap = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
$ext    = $info ? ($extMap[$info[2]] ?? null) : null;
if (!$ext) {
    echo json_encode(['type' => 'error', 'msg' => 'La foto no es válida.']);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/checkin/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0750, true);
$filename = uniqid('chk_', true) . '.' . $ext;
if (!file_put_contents($uploadDir . $filename, $binary)) {
    echo json_encode(['type' => 'error', 'msg' => 'No se pudo guardar la foto. Intenta de nuevo.']);
    exit;
}

/* ── Score de verificación facial (calculado en el navegador) ── */
$faceScoreRaw   = $_POST['face_score'] ?? '';
$faceScore      = ($faceScoreRaw !== '' && is_numeric($faceScoreRaw)) ? max(0.0, min(100.0, (float)$faceScoreRaw)) : null;
const FACE_MIN_SCORE = 45;   // igual que MATCH_DIST 0.55 en checkin.php
if ($faceScore !== null && $faceScore < FACE_MIN_SCORE) {   // rostro que no coincide: no se registra
    unlink($uploadDir . $filename);
    echo json_encode(['type' => 'error', 'msg' => 'No pudimos confirmar que eres tú. Avisa a tu supervisor o a Recursos Humanos para que registren tu asistencia.']);
    exit;
}
// Sin verificación (celular sin soporte o foto de registro inservible): se acepta, pero queda para revisión
$revisionFlag   = $faceScore === null ? 1 : 0;

/* Error de BD (mysqli lanza excepciones en PHP 8.1+): se responde JSON y se borra la foto huérfana */
set_exception_handler(function (Throwable $t) use ($uploadDir, $filename) {
    error_log('[Sharclean checkin] ' . get_class($t) . ': ' . $t->getMessage() . ' en ' . $t->getFile() . ':' . $t->getLine());
    if (is_file($uploadDir . $filename)) unlink($uploadDir . $filename);
    http_response_code(500);
    echo json_encode(['type' => 'error', 'msg' => 'No se pudo guardar tu registro por un problema del sistema. Intenta de nuevo en un momento; si continúa, avisa a Recursos Humanos.']);
});

/* ── Determinar si es entrada o salida ─────────────────────── */
$db    = getPortalDB();
$today = date('Y-m-d');
$row   = $db->query("SELECT * FROM asistencia WHERE empleado_id=$empId AND fecha='$today' LIMIT 1")->fetch_assoc();

if ($row && $row['hora_entrada'] && $row['hora_salida']) {
    $db->close();
    echo json_encode(['type' => 'error', 'msg' => 'Ya registraste tu entrada y salida de hoy.']);
    exit;
}

if (!$row || !$row['hora_entrada']) {
    /* ── Entrada ──────────────────────────────────────────── */
    $horaAhora = date('H:i:s');
    [$retardo, $minRetardo] = calcRetardo($horaAhora, $emp['hora_entrada_esperada'] ?? '08:00:00', (int)($emp['tolerancia_min'] ?? 10));
    $retardoI = $retardo ? 1 : 0;

    if ($row) {
        $s = $db->prepare(
            "UPDATE asistencia SET
               tipo='normal', hora_entrada=?, capturado_por='Empleado',
               retardo=?, minutos_retardo=?,
               checkin_lat=?, checkin_lng=?, checkin_foto=?, checkin_face_score=?, revision_manual=?
             WHERE id=?"
        );
        $s->bind_param('siiddsdii', $horaAhora, $retardoI, $minRetardo, $lat, $lng, $filename, $faceScore, $revisionFlag, $row['id']);
    } else {
        $s = $db->prepare(
            "INSERT INTO asistencia
               (empleado_id, fecha, tipo, hora_entrada, capturado_por, retardo, minutos_retardo,
                checkin_lat, checkin_lng, checkin_foto, checkin_face_score, revision_manual)
             VALUES (?, ?, 'normal', ?, 'Empleado', ?, ?, ?, ?, ?, ?, ?)"
        );
        $s->bind_param('issiiddsdi', $empId, $today, $horaAhora, $retardoI, $minRetardo, $lat, $lng, $filename, $faceScore, $revisionFlag);
    }
    if (!$s->execute()) throw new RuntimeException('INSERT/UPDATE entrada: ' . $s->error);
    $db->close();
    echo json_encode(['type' => 'success', 'msg' => '¡Entrada registrada a las ' . substr($horaAhora, 0, 5) . '!', 'accion' => 'entrada']);
    exit;
}

/* ── Salida ─────────────────────────────────────────────────── */
$horaAhora        = date('H:i:s');
$horasTrabajadas  = calcHorasTrabajadas($row['hora_entrada'], $horaAhora);
$horasExtra       = calcHorasExtra($horasTrabajadas, (int)($emp['jornada_horas'] ?? 8));

$s = $db->prepare(
    "UPDATE asistencia SET
       hora_salida=?, horas_trabajadas=?, horas_extra=?,
       checkout_lat=?, checkout_lng=?, checkout_foto=?, checkout_face_score=?,
       revision_manual=GREATEST(revision_manual, ?)
     WHERE id=?"
);
$s->bind_param('sddddsdii', $horaAhora, $horasTrabajadas, $horasExtra, $lat, $lng, $filename, $faceScore, $revisionFlag, $row['id']);
if (!$s->execute()) throw new RuntimeException('UPDATE salida: ' . $s->error);
$db->close();

echo json_encode(['type' => 'success', 'msg' => '¡Salida registrada a las ' . substr($horaAhora, 0, 5) . '! Horas trabajadas: ' . number_format((float)$horasTrabajadas, 1) . 'h', 'accion' => 'salida']);
