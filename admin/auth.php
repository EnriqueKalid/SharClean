<?php
/**
 * Sharclean Admin – Auth guard
 * Include at the top of every admin page.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../db/config.php'; // loads .env into $_ENV and defines getDB()

if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

function getAdminDB(): mysqli
{
    return getDB();
}
