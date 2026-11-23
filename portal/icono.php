<?php
/**
 * icono.php – Ícono cuadrado de la app del portal, generado desde el logo de Configuración.
 * Usage: icono.php?s=192|180|512   (&m=1 = "maskable": más margen para recortes circulares)
 */
declare(strict_types=1);
require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../db/app_config.php';

$size     = in_array((int)($_GET['s'] ?? 192), [180, 192, 512], true) ? (int)$_GET['s'] : 192;
$maskable = isset($_GET['m']);

// El logo debe ser una imagen dentro del proyecto (la ruta viene de Configuración)
$root = realpath(__DIR__ . '/..');
$logo = realpath($root . '/' . appConfig('sistema_logo', 'images/logo.png')) ?: realpath($root . '/images/logo.png');
$info = $logo && str_starts_with($logo, $root . DIRECTORY_SEPARATOR) ? @getimagesize($logo) : false;

$loaders = [IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
if (!$info || !isset($loaders[$info[2]]) || !function_exists('imagecreatetruecolor') || !function_exists($loaders[$info[2]])) {
    http_response_code(404);
    exit;
}

$src = $loaders[$info[2]]($logo);
$dst = imagecreatetruecolor($size, $size);
imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));

$box   = $size * ($maskable ? 0.62 : 0.80);           // área útil para el logo
$scale = min($box / $info[0], $box / $info[1]);
$w = (int)round($info[0] * $scale);
$h = (int)round($info[1] * $scale);
imagecopyresampled($dst, $src, (int)(($size - $w) / 2), (int)(($size - $h) / 2), 0, 0, $w, $h, $info[0], $info[1]);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
imagepng($dst);
