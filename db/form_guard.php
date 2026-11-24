<?php
/**
 * Sharclean – Filtros anti-spam para formularios públicos (cotización, contacto)
 *
 *  · Campo trampa ("honeypot"): un input oculto que una persona nunca llena; los bots sí.
 *  · Tiempo mínimo: una persona tarda más de unos segundos en llenar el formulario;
 *    el momento en que se mostró la página se guarda en la sesión (formGuardMarcar()).
 *  · Dominio del correo: debe tener servidor de correo (registro MX o A). Detecta errores
 *    de dedo como "gmial.con" y dominios inventados. NO puede saber si el buzón existe:
 *    eso solo se sabe enviando un correo.
 */

declare(strict_types=1);

const FORM_GUARD_CAMPO      = 'sitio_web';   // nombre del campo trampa
const FORM_GUARD_MIN_SEG    = 4;             // segundos mínimos entre mostrar y enviar

/** Llamar al mostrar la página que contiene los formularios. */
function formGuardMarcar(): void
{
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['form_guard_t'] = time();
}

/** Input oculto del campo trampa (va dentro de cada <form>; $form distingue el id). */
function formGuardCampo(string $form): string
{
    $id = FORM_GUARD_CAMPO . '-' . preg_replace('/[^a-z0-9-]/', '', $form);
    return '<div class="hp-field" aria-hidden="true">'
         . '<label for="' . $id . '">Sitio web</label>'
         . '<input type="text" id="' . $id . '" name="' . FORM_GUARD_CAMPO . '" tabindex="-1" autocomplete="off">'
         . '</div>';
}

/**
 * true si el envío parece de un bot (campo trampa lleno o enviado demasiado rápido).
 * A un bot se le responde "éxito" sin guardar nada, para no darle pistas.
 */
function formGuardEsBot(): bool
{
    if (trim((string)($_POST[FORM_GUARD_CAMPO] ?? '')) !== '') return true;
    if (session_status() === PHP_SESSION_NONE) session_start();
    $t = (int)($_SESSION['form_guard_t'] ?? 0);
    return $t === 0 || (time() - $t) < FORM_GUARD_MIN_SEG;
}

/** true si el dominio del correo puede recibir correos (MX, o A como respaldo según RFC 5321). */
function correoDominioRecibe(string $email): bool
{
    $dom = strtolower(substr(strrchr($email, '@') ?: '', 1));
    if ($dom === '' || !function_exists('checkdnsrr')) return true;   // sin DNS disponible no se bloquea
    $ascii = function_exists('idn_to_ascii') ? (idn_to_ascii($dom) ?: $dom) : $dom;
    return checkdnsrr($ascii . '.', 'MX') || checkdnsrr($ascii . '.', 'A');
}
