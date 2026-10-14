<?php
/**
 * Sharclean Portal – Sirve únicamente la foto de referencia del
 * empleado autenticado (para que face-api.js la compare en el navegador).
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$emp = portalEmpleado();

$filename = basename($emp['foto_referencia'] ?? '');

if (!$filename || !preg_match('/^ref_[a-zA-Z0-9._-]+\.(jpe?g|png)$/i', $filename)) {
    http_response_code(404);
    exit('Sin foto de referencia.');
}

$path = realpath(__DIR__ . '/../uploads/empleados/' . $filename);
$base = realpath(__DIR__ . '/../uploads/empleados/');

if (!$path || !$base || !str_starts_with($path, $base)) {
    http_response_code(404);
    exit('Sin foto de referencia.');
}

$ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Cache-Control: private, no-store');
header('Content-Length: ' . filesize($path));
readfile($path);
