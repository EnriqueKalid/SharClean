<?php
/**
 * Sharclean RRHH – Autorregistro de empleados (helpers compartidos admin + portal)
 *
 * Liga general: {APP_URL}/portal/registro.php?k={registro_clave}
 *  · Solo funciona con registro_activo=1 y la clave vigente (RRHH puede generar
 *    una nueva para invalidar la anterior si se compartió de más).
 *  · Solo activa empleados YA dados de alta por RRHH, sin acceso al portal todavía.
 * Requiere db/config.php, db/app_config.php y db/security.php cargados.
 */

declare(strict_types=1);

function registroDisponible(mysqli $db): bool
{
    $res = $db->query("SHOW COLUMNS FROM empleados LIKE 'registro_estado'");
    return $res && $res->num_rows > 0;
}

function registroActivo(): bool
{
    return appConfig('registro_activo') === '1' && strlen(appConfig('registro_clave')) === 32;
}

function registroUrl(string $clave): string
{
    return appBaseUrl() . '/portal/registro.php?k=' . rawurlencode($clave);
}

function registroNuevaClave(): string
{
    $clave = bin2hex(random_bytes(16));
    setAppConfig('registro_clave', $clave);
    return $clave;
}

function registroPendientes(mysqli $db): int
{
    $res = $db->query("SELECT COUNT(*) FROM empleados WHERE registro_estado='pendiente'");
    return $res ? (int)$res->fetch_row()[0] : 0;
}

/** Últimos 10 dígitos (quita lada +52, espacios, guiones). '' si no hay 10 dígitos. */
function normalizarTelefono(string $tel): string
{
    $d = preg_replace('/\D+/', '', $tel);
    return strlen($d) >= 10 ? substr($d, -10) : '';
}

/** e****@gmail.com — para mostrar a dónde se envió el código sin revelar el correo completo. */
function enmascararCorreo(string $email): string
{
    [$u, $dom] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($u, 0, 1) . str_repeat('•', max(3, min(8, mb_strlen($u) - 1))) . '@' . $dom;
}
