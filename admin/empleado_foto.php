<?php
/**
 * Sharclean Admin – Ver foto de referencia del empleado
 * Usage: admin/empleado_foto.php?emp=123
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requirePermiso('empleados');

$empId = (int)($_GET['emp'] ?? 0);
if ($empId <= 0) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$db  = getAdminDB();
$row = $db->query("SELECT foto_referencia AS foto FROM empleados WHERE id=$empId LIMIT 1")->fetch_assoc();
$db->close();

$filename = basename($row['foto'] ?? '');

if (!$filename || !preg_match('/^ref_[a-zA-Z0-9._-]+\.(jpe?g|png)$/i', $filename)) {
    http_response_code(404);
    exit('Foto no encontrada.');
}

$path = realpath(__DIR__ . '/../uploads/empleados/' . $filename);
$base = realpath(__DIR__ . '/../uploads/empleados/');

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
