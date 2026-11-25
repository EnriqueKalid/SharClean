<?php
/**
 * Sharclean – HTML Mail Helper
 * Sends multipart (text + HTML) emails with branded template.
 */

declare(strict_types=1);

function sendMail(string $to, string $subject, string $htmlBody, string $replyTo = ''): bool
{
    if (function_exists('appConfig')) {
        $fromName    = appConfig('correo_from_name',    'Sharclean');
        $fromAddress = appConfig('correo_from_address', 'noreply@sharclean.com.mx');
    } else {
        $fromName    = 'Sharclean';
        $fromAddress = 'noreply@sharclean.com.mx';
    }

    $boundary = 'MAIL_' . md5(uniqid('', true));
    $plain = trim(strip_tags(preg_replace(
        ['/<br\s*\/?>/i', '/<\/p>/i', '/<\/h[1-6]>/i', '/<\/li>/i'],
        "\n", $htmlBody
    )));

    /* Nada que venga de Configuración o de un formulario puede inyectar encabezados */
    $limpio      = fn(string $s): string => trim(str_replace(["\r", "\n"], '', $s));
    $fromName    = $limpio($fromName);
    $fromAddress = $limpio($fromAddress);
    $replyTo     = $limpio($replyTo);
    if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) $fromAddress = 'noreply@sharclean.com.mx';
    if ($replyTo !== '' && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $replyTo = '';
    $dominio = substr(strrchr($fromAddress, '@'), 1);

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    // Nombre codificado (acentos/ñ) para que Gmail no lo considere encabezado mal formado
    $headers .= 'From: =?UTF-8?B?' . base64_encode($fromName) . "?= <$fromAddress>\r\n";
    if ($replyTo) $headers .= "Reply-To: $replyTo\r\n";
    $headers .= 'Message-ID: <' . bin2hex(random_bytes(16)) . "@$dominio>\r\n";
    $headers .= "X-Mailer: AppMailer/1.0\r\n";

    $body  = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($plain)) . "\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
    $body .= "--$boundary--";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($limpio($subject)) . '?=';

    /* -f = remitente técnico (Return-Path) con el MISMO dominio del "De:". Sin esto el hosting usa
       usuario@servidor y Gmail no puede validar SPF/DMARC de sharclean.com.mx → rechazo o spam. */
    $ok = @mail($limpio($to), $encodedSubject, $body, $headers, '-f' . $fromAddress);
    if (!$ok) error_log('[Sharclean mail] mail() falló hacia ' . $limpio($to));
    return $ok;
}

function emailLayout(string $title, string $content): string
{
    $year    = date('Y');
    $empresa = function_exists('appConfig') ? appConfig('empresa_nombre',  'Sharclean')                          : 'Sharclean';
    $giro    = function_exists('appConfig') ? appConfig('empresa_giro',    'Limpieza y Mantenimiento Profesional'): 'Limpieza y Mantenimiento Profesional';
    $emailPie= function_exists('appConfig') ? appConfig('empresa_email_info','informacion@sharclean.com.mx')     : 'informacion@sharclean.com.mx';
    $color   = function_exists('appConfig') ? appConfig('sistema_color',   '#0284C7')                            : '#0284C7';

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"><title>$title</title></head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1e293b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr><td align="center" style="padding:32px 16px;">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">
    <tr><td style="background:$color;padding:24px 32px;border-radius:12px 12px 0 0;">
      <p style="margin:0;font-size:22px;font-weight:700;color:#fff;">$empresa</p>
      <p style="margin:4px 0 0;font-size:12px;color:rgba(255,255,255,0.75);">$giro</p>
    </td></tr>
    <tr><td style="background:#fff;padding:32px;border-radius:0 0 12px 12px;line-height:1.75;">
      $content
    </td></tr>
    <tr><td style="padding:20px;text-align:center;">
      <p style="margin:0;font-size:12px;color:#94a3b8;">© $year $empresa &middot; $emailPie</p>
    </td></tr>
  </table>
</td></tr>
</table>
</body></html>
HTML;
}
