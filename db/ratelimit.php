<?php
/**
 * Sharclean – Session-based rate limiting
 * Usage: checkRateLimit('contact', 60);  // max 1 submission per 60 s
 */

declare(strict_types=1);

/**
 * Aborts with a JSON error if the form was submitted too recently.
 *
 * @param string $key      Unique key per form (e.g. 'contact', 'quote', 'apply')
 * @param int    $seconds  Minimum seconds required between submissions
 */
function checkRateLimit(string $key, int $seconds = 60): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();

    $sessionKey = 'rl_' . $key;
    $lastSubmit = $_SESSION[$sessionKey] ?? 0;
    $remaining  = $seconds - (time() - $lastSubmit);

    if ($lastSubmit > 0 && $remaining > 0) {
        http_response_code(429);
        if (ob_get_level()) ob_clean();
        echo json_encode([
            'type' => 'error',
            'msg'  => "Por favor espere $remaining segundo(s) antes de enviar de nuevo.",
        ]);
        exit;
    }

    $_SESSION[$sessionKey] = time();
}
