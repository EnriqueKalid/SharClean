<?php
/**
 * Sharclean Admin – Ver / descargar un documento del expediente del empleado
 * Usage: admin/empleado_documento.php?id=45        (ver en el navegador)
 *        admin/empleado_documento.php?id=45&dl=1   (descargar)
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requirePermiso('empleados');
require_once __DIR__ . '/../db/expediente.php';

$docId = (int)($_GET['id'] ?? 0);
if ($docId <= 0) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$db  = getAdminDB();
$res = $db->query("SELECT empleado_id, archivo, nombre_original FROM empleado_documentos WHERE id=$docId LIMIT 1");
$row = $res ? $res->fetch_assoc() : null;
$db->close();

$archivo = basename((string)($row['archivo'] ?? ''));
if (!$row || !preg_match('/^doc_[a-f0-9]{24}\.(pdf|jpg|png|webp)$/', $archivo)) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

$base = realpath(expedienteDir((int)$row['empleado_id']));
$path = realpath(expedienteDir((int)$row['empleado_id']) . '/' . $archivo);
if (!$path || !$base || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('Documento no encontrado.');
}

$ext   = pathinfo($archivo, PATHINFO_EXTENSION);
$types = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

// Nombre de descarga: el original, sin caracteres que rompan la cabecera, con la extensión real
$nombre = preg_replace('/[^\p{L}\p{N} ._()-]+/u', '_', pathinfo((string)$row['nombre_original'], PATHINFO_FILENAME)) ?: 'documento';
$nombre .= '.' . $ext;
$ascii   = preg_replace('/[^A-Za-z0-9 ._()-]/', '_', $nombre);

if ($ext === 'pdf') {
    header_remove('Content-Security-Policy');   // el visor PDF del navegador no funciona con la CSP del panel
} else {
    header("Content-Security-Policy: default-src 'none'; sandbox");
}
header('Content-Type: ' . $types[$ext]);
header('Content-Disposition: ' . (isset($_GET['dl']) ? 'attachment' : 'inline')
     . '; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
readfile($path);
