<?php
/**
 * Sharclean Admin – Auth guard + helpers
 * Include at the top of every admin page.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../db/app_config.php';

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

function getAdminDB(): mysqli
{
    return getDB();
}

/**
 * Comprueba si el usuario activo tiene permiso para un módulo.
 * Si no hay permisos en sesión (modo legacy .env) → acceso total.
 */
function hasPermiso(string $modulo): bool
{
    if (!isset($_SESSION['admin_permisos'])) return true;
    $perms = (array)$_SESSION['admin_permisos'];
    return in_array('*', $perms) || in_array($modulo, $perms);
}

/**
 * Redirige a index.php si el usuario no tiene el permiso indicado.
 */
function requirePermiso(string $modulo): void
{
    if (!hasPermiso($modulo)) {
        header('Location: index.php?forbidden=1');
        exit;
    }
}

/**
 * Devuelve los datos del usuario activo de la sesión.
 */
function adminUser(): array
{
    return [
        'id'     => (int)($_SESSION['admin_user_id']    ?? 0),
        'nombre' => (string)($_SESSION['admin_user_name'] ?? 'Admin'),
        'perfil' => (string)($_SESSION['admin_perfil']    ?? ''),
        'slug'   => (string)($_SESSION['admin_perfil_slug'] ?? ''),
    ];
}
