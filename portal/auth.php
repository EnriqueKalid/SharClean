<?php
/**
 * Sharclean Portal del Empleado – Auth guard + helpers
 * Include at the top of every página del portal (excepto login.php).
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../db/config.php';

if (empty($_SESSION['portal_empleado_id'])) {
    header('Location: login.php');
    exit;
}

function getPortalDB(): mysqli
{
    return getDB();
}

/**
 * Relee el empleado de BD en cada request (refleja cambios del admin,
 * p.ej. baja o desactivación del portal). Cierra la sesión si ya no
 * puede usar el portal.
 */
function portalEmpleado(): array
{
    $db = getPortalDB();
    $id = (int)($_SESSION['portal_empleado_id'] ?? 0);
    $row = $db->query("SELECT * FROM empleados WHERE id=$id LIMIT 1")->fetch_assoc();
    $db->close();

    if (!$row || $row['estatus'] === 'baja_definitiva' || (int)$row['portal_activo'] !== 1) {
        session_unset();
        session_destroy();
        header('Location: login.php?motivo=inactivo');
        exit;
    }

    return $row;
}
