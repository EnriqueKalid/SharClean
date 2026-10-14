<?php
/**
 * Sharclean Portal – Registrar check-in / check-out del empleado
 * Recibe: lat, lng, foto (data URL base64), face_score (0-100 o vacío)
 * Responde JSON.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

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

/* ── Selfie (data URL base64) ──────────────────────────────── */
$fotoData = $_POST['foto'] ?? '';
if (!preg_match('#^data:image/(jpe?g|png);base64,#i', $fotoData)) {
    echo json_encode(['type' => 'error', 'msg' => 'No se recibió una foto válida. Intenta de nuevo.']);
    exit;
}
$binary = base64_decode(substr($fotoData, strpos($fotoData, ',') + 1), true);
if ($binary === false || strlen($binary) < 500 || strlen($binary) > 5 * 1024 * 1024) {
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
$revisionFlag   = ($faceScore === null || $faceScore < 50) ? 1 : 0;

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
    $s->execute();
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
$s->execute();
$db->close();

echo json_encode(['type' => 'success', 'msg' => '¡Salida registrada a las ' . substr($horaAhora, 0, 5) . '! Horas trabajadas: ' . number_format((float)$horasTrabajadas, 1) . 'h', 'accion' => 'salida']);
