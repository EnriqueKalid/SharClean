<?php
/**
 * _boot.php – Arranque común de las páginas del portal del empleado:
 * sesión segura, cabeceras de seguridad (con cámara y GPS permitidos) y helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/../db/security.php';
startSecureSession();
sendSecurityHeaders(true);
header('X-Robots-Tag: noindex, nofollow');

require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../db/app_config.php';
require_once __DIR__ . '/../db/csrf.php';

if (!function_exists('h')) {
    function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

/** Marca de la empresa para el portal (nombre, logo y color validado: va dentro de <style>). */
function portalMarca(): array
{
    $color = appConfig('sistema_color', '#0284C7');
    return [
        'empresa' => appConfig('empresa_nombre', 'Sharclean'),
        'logo'    => '../' . appConfig('sistema_logo', 'images/logo.png'),
        'color'   => preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#0284C7',
    ];
}

/** El empleado puede usar el portal: no está de baja, acceso habilitado y, si se autorregistró, que RRHH ya haya aprobado su selfie. */
function portalPuedeEntrar(array $row): bool
{
    return $row['estatus'] !== 'baja_definitiva'
        && (int)$row['portal_activo'] === 1
        && ($row['registro_estado'] ?? null) !== 'pendiente';   // rechazado ya no tiene credenciales; si RRHH le asigna unas, puede entrar
}
