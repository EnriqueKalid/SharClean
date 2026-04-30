<?php
/**
 * Sharclean – HTML Mail Helper
 * Sends multipart (text + HTML) emails with branded template.
 */

declare(strict_types=1);

function sendMail(string $to, string $subject, string $htmlBody, string $replyTo = ''): bool
{
    $boundary = 'SHARCLEAN_' . md5(uniqid('', true));
    // Plain-text fallback
    $plain = trim(strip_tags(preg_replace(
        ['/<br\s*\/?>/i', '/<\/p>/i', '/<\/h[1-6]>/i', '/<\/li>/i'],
        "\n", $htmlBody
    )));

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    $headers .= "From: Sharclean <noreply@sharclean.com.mx>\r\n";
    if ($replyTo) $headers .= "Reply-To: $replyTo\r\n";
    $headers .= "X-Mailer: Sharclean-Site/1.0\r\n";

    $body  = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($plain)) . "\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
    $body .= "--$boundary--";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    return @mail($to, $encodedSubject, $body, $headers);
}

function emailLayout(string $title, string $content): string
{
    $year = date('Y');
    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"><title>$title</title></head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1e293b;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr><td align="center" style="padding:32px 16px;">
  <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">
    <tr><td style="background:#0284C7;padding:24px 32px;border-radius:12px 12px 0 0;">
      <p style="margin:0;font-size:22px;font-weight:700;color:#fff;">Sharclean</p>
      <p style="margin:4px 0 0;font-size:12px;color:rgba(255,255,255,0.75);">Limpieza y Mantenimiento Profesional</p>
    </td></tr>
    <tr><td style="background:#fff;padding:32px;border-radius:0 0 12px 12px;line-height:1.75;">
      $content
    </td></tr>
    <tr><td style="padding:20px;text-align:center;">
      <p style="margin:0;font-size:12px;color:#94a3b8;">© $year Sharclean &middot; informacion@sharclean.com.mx</p>
    </td></tr>
  </table>
</td></tr>
</table>
</body></html>
HTML;
}
