<?php
/**
 * Sharclean – App Config Helper
 * Lee la tabla `config` una sola vez por request (static cache).
 * Requiere que getDB() ya esté disponible (db/config.php).
 */

declare(strict_types=1);

function appConfig(string $key, string $default = ''): string
{
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        try {
            $db  = getDB();
            $res = $db->query('SELECT clave, valor FROM config');
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $cache[$row['clave']] = (string)($row['valor'] ?? '');
                }
            }
        } catch (Throwable $e) {
            // Si la tabla aún no existe devuelve defaults sin romper
        }
    }

    return $cache[$key] ?? $default;
}

function setAppConfig(string $key, string $value): bool
{
    $db  = getDB();
    $k   = $db->real_escape_string($key);
    $v   = $db->real_escape_string($value);
    return (bool) $db->query("UPDATE config SET valor='$v' WHERE clave='$k'");
}
