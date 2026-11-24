<?php
/**
 * Sharclean RRHH – Autorregistro de empleados (helpers compartidos admin + portal)
 *
 * Liga general: {APP_URL}/portal/registro.php?k={registro_clave}
 *  · Solo funciona con registro_activo=1 y la clave vigente (RRHH puede generar
 *    una nueva para invalidar la anterior si se compartió de más).
 *  · Solo activa empleados YA dados de alta por RRHH, sin acceso al portal todavía.
 * Requiere db/config.php, db/app_config.php y db/security.php cargados.
 */

declare(strict_types=1);

function registroDisponible(mysqli $db): bool
{
    $res = $db->query("SHOW COLUMNS FROM empleados LIKE 'registro_estado'");
    return $res && $res->num_rows > 0;
}

function registroActivo(): bool
{
    return appConfig('registro_activo') === '1' && strlen(appConfig('registro_clave')) === 32;
}

function registroUrl(string $clave): string
{
    return appBaseUrl() . '/portal/registro.php?k=' . rawurlencode($clave);
}

function registroNuevaClave(): string
{
    $clave = bin2hex(random_bytes(16));
    setAppConfig('registro_clave', $clave);
    return $clave;
}

function registroPendientes(mysqli $db): int
{
    $res = $db->query("SELECT COUNT(*) FROM empleados WHERE registro_estado='pendiente'");
    return $res ? (int)$res->fetch_row()[0] : 0;
}

/** Últimos 10 dígitos (quita lada +52, espacios, guiones). '' si no hay 10 dígitos. */
function normalizarTelefono(string $tel): string
{
    $d = preg_replace('/\D+/', '', $tel);
    return strlen($d) >= 10 ? substr($d, -10) : '';
}

/** e****@gmail.com — para mostrar a dónde se envió el código sin revelar el correo completo. */
function enmascararCorreo(string $email): string
{
    [$u, $dom] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($u, 0, 1) . str_repeat('•', max(3, min(8, mb_strlen($u) - 1))) . '@' . $dom;
}

/* ── Invitaciones individuales (correo / WhatsApp) ─────────── */

/**
 * Empleados que pueden activar su cuenta: no están de baja, no tienen contraseña ni PIN
 * y no tienen un registro en revisión. Mismo criterio que regElegible() en portal/registro.php.
 */
const REGISTRO_ELEGIBLE_SQL = "e.estatus <> 'baja_definitiva'
    AND e.portal_password_hash IS NULL AND e.portal_pin_hash IS NULL
    AND (e.registro_estado IS NULL OR e.registro_estado = 'rechazado')";

/** ¿Ya se ejecutó migrate_invitaciones_v1.sql? (sin ella se envía igual, solo no se registra cuándo) */
function invitacionDisponible(mysqli $db): bool
{
    $res = $db->query("SHOW COLUMNS FROM empleados LIKE 'invitacion_fecha'");
    return $res && $res->num_rows > 0;
}

/** Liga general con el número de empleado ya escrito (la identificación por teléfono/correo sigue igual). */
function registroUrlPersonal(string $clave, string $numeroEmpleado): string
{
    return registroUrl($clave) . '&n=' . rawurlencode($numeroEmpleado);
}

/** Primer correo válido de la ficha (personal, luego corporativo). */
function empleadoCorreo(array $e): string
{
    foreach ([$e['email'] ?? '', $e['email_corporativo'] ?? ''] as $m) {
        if ($m && filter_var($m, FILTER_VALIDATE_EMAIL)) return (string)$m;
    }
    return '';
}

/** wa.me con el número en formato internacional (México por defecto: 52 + 10 dígitos). '' si no hay teléfono válido. */
function whatsappUrl(string $telefono, string $texto): string
{
    $d = preg_replace('/\D+/', '', $telefono);
    if (strlen($d) === 10)                                   $d = '52' . $d;
    elseif (strlen($d) === 13 && str_starts_with($d, '521')) $d = '52' . substr($d, 3);   // formato viejo de celular
    elseif (strlen($d) < 11 || strlen($d) > 15)              return '';
    return 'https://wa.me/' . $d . '?text=' . rawurlencode($texto);
}

function invitacionTextoWhatsapp(array $e, string $url, string $empresa): string
{
    return "Hola {$e['nombre']}, te escribimos de Recursos Humanos de $empresa.\n\n"
         . "Activa tu cuenta del portal de empleados para checar tu asistencia desde tu celular. "
         . "Solo necesitas tomarte una selfie y crear tu contraseña o un PIN de números.\n\n"
         . "Tu número de empleado: *{$e['numero_empleado']}*\n"
         . "Te pediremos también el teléfono o correo que diste al contratarte.\n\n"
         . "Entra aquí: $url";
}

function invitacionCorreoHtml(array $e, string $url, string $empresa): string
{
    $hx     = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $color  = function_exists('appConfig') ? appConfig('sistema_color', '#0284C7') : '#0284C7';
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) $color = '#0284C7';
    $nombre = $hx($e['nombre']);
    $numero = $hx($e['numero_empleado']);
    $link   = $hx($url);
    $emp    = $hx($empresa);
    return emailLayout('Activa tu cuenta', <<<HTML
<p>Hola <strong>$nombre</strong>,</p>
<p>Recursos Humanos de $emp te invita a activar tu cuenta del <strong>portal de empleados</strong>,
desde donde podrás checar tu entrada y salida con tu celular.</p>
<p>Es rápido, solo necesitas:</p>
<ul style="padding-left:20px;margin:8px 0 16px;">
  <li>Tu número de empleado: <strong style="font-size:16px;">$numero</strong></li>
  <li>El teléfono o correo que diste al contratarte</li>
  <li>Tomarte una selfie (con ella se verifica tu identidad al checar)</li>
  <li>Crear tu contraseña y/o un PIN de 4 a 6 números</li>
</ul>
<p style="text-align:center;margin:28px 0;">
  <a href="$link" style="background:$color;color:#fff;text-decoration:none;font-weight:700;padding:14px 28px;border-radius:10px;display:inline-block;">Activar mi cuenta</a>
</p>
<p style="font-size:12px;color:#64748b;">Si el botón no funciona, copia esta dirección en tu navegador:<br>
<span style="word-break:break-all;">$link</span></p>
<p style="font-size:12px;color:#64748b;">Nadie de la empresa te pedirá tu contraseña o PIN. Si no trabajas en $emp, ignora este correo.</p>
HTML);
}

/** Registra que se invitó (sin migración no hace nada). $canal: correo | whatsapp */
function invitacionMarcar(mysqli $db, int $id, string $canal): void
{
    if (!in_array($canal, ['correo', 'whatsapp'], true)) return;
    $db->query("UPDATE empleados SET invitacion_fecha = NOW(),
                  invitacion_canal = CASE
                    WHEN invitacion_canal IS NULL OR invitacion_canal = '$canal' THEN '$canal'
                    ELSE 'correo+whatsapp' END
                WHERE id = $id");
}
