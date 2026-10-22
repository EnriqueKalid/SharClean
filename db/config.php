<?php
/**
 * Sharclean – Database Configuration
 * Usage: require_once __DIR__ . '/db/config.php'; $db = getDB();
 */

declare(strict_types=1);

/* ── Load .env ─────────────────────────────────────────────── */
$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

define('DB_HOST',    $_ENV['DB_HOST']    ?? 'localhost');
define('DB_USER',    $_ENV['DB_USER']    ?? '');
define('DB_PASS',    $_ENV['DB_PASS']    ?? '');
define('DB_NAME',    $_ENV['DB_NAME']    ?? '');
define('DB_PORT',    (int)($_ENV['DB_PORT'] ?? 3306));
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a connected MySQLi instance.
 * On failure, outputs JSON error and exits.
 */
function getDB(): mysqli
{
    /* Desde PHP 8.1, mysqli lanza mysqli_sql_exception por defecto en errores
       de query/prepare. Todo el proyecto asume el comportamiento clásico
       (false/null en error, sin excepciones) — se restaura explícitamente
       para no romper ese patrón tras el upgrade de PHP 8.0 a 8.4. */
    mysqli_report(MYSQLI_REPORT_OFF);

    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    if ($mysqli->connect_error) {
        error_log('[Sharclean DB] Connection failed: ' . $mysqli->connect_error);
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'type' => 'error',
            'msg'  => 'Error de conexión a la base de datos. Por favor inténtelo más tarde.',
        ]);
        exit;
    }

    $mysqli->set_charset(DB_CHARSET);

    return $mysqli;
}
