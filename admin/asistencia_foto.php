<?php
/**
 * Sharclean Admin – Ver selfie de check-in/check-out
 * Usage: admin/asistencia_foto.php?a=123&tipo=checkin|checkout
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requirePermiso('asistencia');

$asistId = (int)($_GET['a'] ?? 0);
$tipo    = $_GET['tipo'] ?? '';

if ($asistId <= 0 || !in_array($tipo, ['checkin', 'checkout'], true)) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$db = getAdminDB();
$col = $tipo === 'checkin' ? 'checkin_foto' : 'checkout_foto';
$row = $db->query("SELECT $col AS foto FROM asistencia WHERE id=$asistId LIMIT 1")->fetch_assoc();
$db->close();

$filename = basename($row['foto'] ?? '');

if (!$filename || !preg_match('/^chk_[a-zA-Z0-9._-]+\.(jpe?g|png)$/i', $filename)) {
    http_response_code(404);
    exit('Foto no encontrada.');
}

$path = realpath(__DIR__ . '/../uploads/checkin/' . $filename);
$base = realpath(__DIR__ . '/../uploads/checkin/');

if (!$path || !$base || !str_starts_with($path, $base)) {
    http_response_code(404);
    exit('Foto no encontrada.');
}

$ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
