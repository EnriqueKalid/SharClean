<?php
/**
 * Sharclean Admin – Auth guard + helpers
 * Include at the top of every admin page.
 *
 * Además del guard de sesión aplica, para TODO el panel:
 *   · cookie de sesión segura y cabeceras de seguridad (db/security.php)
 *   · expiración por inactividad (ADMIN_IDLE_TIMEOUT)
 *   · revalidación del usuario en cada petición: si fue desactivado/eliminado
 *     se cierra su sesión; cambios de perfil/permisos aplican de inmediato
 *   · CSRF obligatorio en todo POST (campo csrf_token o cabecera X-CSRF-Token)
 */
declare(strict_types=1);

require_once __DIR__ . '/../db/security.php';
startSecureSession();
sendSecurityHeaders();

require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../db/app_config.php';
require_once __DIR__ . '/../db/csrf.php';

const ADMIN_IDLE_TIMEOUT = 7200;   // 2 h sin actividad → cerrar sesión

function adminLogoutAndRedirect(string $query = ''): never
{
    $_SESSION = [];
    session_destroy();
    header('Location: login.php' . ($query !== '' ? '?' . $query : ''));
    exit;
}

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['admin_last_activity']) && time() - (int)$_SESSION['admin_last_activity'] > ADMIN_IDLE_TIMEOUT) {
    adminLogoutAndRedirect('expired=1');
}
$_SESSION['admin_last_activity'] = time();

function getAdminDB(): mysqli
{
    return getDB();
}

/* ── Revalidar usuario contra la BD (modo usuarios; el modo legacy .env tiene id 0) ── */
if (!empty($_SESSION['admin_user_id'])) {
    $_uid = (int)$_SESSION['admin_user_id'];
    $_db  = getAdminDB();
    $_sql = "SELECT u.activo, u.nombre, %s p.permisos, p.nombre AS perfil_nombre, p.slug AS perfil_slug
             FROM admin_usuarios u JOIN admin_perfiles p ON p.id = u.perfil_id
             WHERE u.id = $_uid LIMIT 1";
    $_res = $_db->query(sprintf($_sql, 'u.foto,'));
    if ($_res === false) $_res = $_db->query(sprintf($_sql, ''));   // migrate_admin_v2.sql aún no ejecutada
    if ($_res !== false) {
        $_row = $_res->fetch_assoc();
        if (!$_row || !(int)$_row['activo']) adminLogoutAndRedirect('disabled=1');
        $_SESSION['admin_user_name']   = $_row['nombre'];
        $_SESSION['admin_perfil']      = $_row['perfil_nombre'];
        $_SESSION['admin_perfil_slug'] = $_row['perfil_slug'];
        $_SESSION['admin_permisos']    = json_decode($_row['permisos'], true) ?? [];
        $_SESSION['admin_user_foto']   = (string)($_row['foto'] ?? '');
    }
    unset($_uid, $_db, $_sql, $_res, $_row);
}

/* ── CSRF global para POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        http_response_code(413);
        exit('El archivo enviado excede el tamaño máximo permitido por el servidor.');
    }
    $_tok = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verifyCsrfToken((string)$_tok)) {
        http_response_code(403);
        exit('Token de seguridad inválido o vencido. Recargue la página e intente de nuevo.');
    }
    unset($_tok);
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
        'foto'   => (string)($_SESSION['admin_user_foto'] ?? ''),
    ];
}
