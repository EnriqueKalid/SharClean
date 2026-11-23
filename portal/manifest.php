<?php
/**
 * manifest.php – Web App Manifest del portal (permite "Agregar a pantalla de inicio").
 * Dinámico para tomar nombre y color de Configuración.
 */
declare(strict_types=1);
require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../db/app_config.php';

$color = appConfig('sistema_color', '#0284C7');
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#0284C7';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo json_encode([
    'name'             => 'Check-in ' . appConfig('empresa_nombre', 'Sharclean'),
    'short_name'       => 'Check-in',
    'description'      => 'Registro de entrada y salida del personal',
    'start_url'        => 'login.php?app=1',
    'scope'            => './',
    'display'          => 'standalone',
    'orientation'      => 'portrait',
    'background_color' => '#f0f9ff',
    'theme_color'      => $color,
    'lang'             => 'es-MX',
    'icons'            => [
        ['src' => 'icono.php?s=192', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'icono.php?s=512', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'icono.php?s=512&m=1', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
