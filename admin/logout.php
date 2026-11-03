<?php
declare(strict_types=1);
require_once __DIR__ . '/../db/security.php';
require_once __DIR__ . '/../db/csrf.php';
startSecureSession();

// Solo POST con token: un enlace o <img> externo no puede cerrar la sesión
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header('Location: dashboard.php');
    exit;
}

$_SESSION = [];
$p = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600, 'path' => $p['path'], 'domain' => $p['domain'],
    'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'] ?: 'Lax',
]);
session_destroy();
header('Location: login.php');
exit;
