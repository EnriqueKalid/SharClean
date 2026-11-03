<?php
/**
 * Sharclean Admin – Foto de perfil de un usuario del panel
 * Usage: admin/usuario_foto.php?id=3
 * Cada usuario puede ver la suya; ver la de otros requiere permiso 'usuarios'.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$id = (int)($_GET['id'] ?? 0);
$me = adminUser();
if ($id <= 0 || ($id !== $me['id'] && !hasPermiso('usuarios'))) {
    http_response_code(403);
    exit;
}

$db  = getAdminDB();
$res = $db->query("SELECT foto FROM admin_usuarios WHERE id=$id LIMIT 1");
$row = $res ? $res->fetch_assoc() : null;
$db->close();

$filename = basename((string)($row['foto'] ?? ''));
if (!preg_match('/^u\d+_[a-f0-9]{24}\.(jpg|png|webp)$/', $filename)) {
    http_response_code(404);
    exit;
}

$base = realpath(__DIR__ . '/../uploads/usuarios/');
$path = realpath(__DIR__ . '/../uploads/usuarios/' . $filename);
if (!$path || !$base || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit;
}

$types = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
header('Content-Type: ' . $types[pathinfo($path, PATHINFO_EXTENSION)]);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline');
header("Content-Security-Policy: default-src 'none'; sandbox");
// El nombre cambia en cada subida (va en ?v=), así que se puede cachear en el navegador
header('Cache-Control: private, max-age=604800, immutable');
readfile($path);
