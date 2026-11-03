<?php
/**
 * Sharclean – Helpers de seguridad compartidos (admin)
 *
 *   startSecureSession()  — cookie de sesión HttpOnly + SameSite + Secure (en HTTPS)
 *   sendSecurityHeaders() — CSP, anti-clickjacking, nosniff, no-cache
 *   appBaseUrl()          — URL raíz del sitio para enlaces en correos (anti host-header poisoning)
 */

declare(strict_types=1);

function isHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function startSecureSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) return;
    ini_set('session.use_strict_mode', '1');     // rechaza IDs de sesión inventados por el cliente
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isHttps(),
        'httponly' => true,                      // JS no puede leer la cookie (mitiga robo por XSS)
        'samesite' => 'Lax',                     // no se envía en POST desde otros sitios
    ]);
    session_start();
}

/**
 * Cabeceras para páginas del panel. 'unsafe-inline' es necesario porque las
 * páginas usan <script>/<style> y onclick inline; aun así la política bloquea
 * scripts, formularios y conexiones hacia dominios externos.
 */
function sendSecurityHeaders(): void
{
    if (headers_sent()) return;
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; "
         . "style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; "
         . "connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header('Cache-Control: no-store, private');   // al cerrar sesión, "Atrás" no muestra datos
}

/**
 * URL raíz del sitio (sin "/" final). Usa APP_URL del .env si existe; si no,
 * el Host de la petición validado. Para enlaces en correos SIEMPRE conviene
 * definir APP_URL: el Host lo controla el cliente y podría apuntar a un
 * dominio de phishing.
 */
function appBaseUrl(): string
{
    $env = rtrim($_ENV['APP_URL'] ?? '', '/');
    if ($env !== '' && preg_match('#^https?://[a-z0-9.-]+(:\d+)?(/[\w./-]*)?$#i', $env)) {
        return $env;
    }
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!preg_match('/^[a-z0-9.-]+(:\d{1,5})?$/i', $host)) $host = 'localhost';
    $dir  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $root = rtrim(preg_replace('#/(admin|portal)$#', '', $dir), '/');
    return (isHttps() ? 'https' : 'http') . '://' . $host . $root;
}
