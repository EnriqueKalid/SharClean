<?php
/**
 * Sharclean Portal – Autorregistro del empleado (liga general)
 *
 * El empleado YA dado de alta por RRHH activa su cuenta desde el celular:
 *   1. Se identifica: número de empleado + teléfono o correo de su ficha (ambos deben coincidir).
 *   2. Si su ficha tiene correo, confirma con un código de 6 dígitos enviado ahí.
 *   3. Captura sus datos personales y domicilio, su selfie de referencia y su usuario/contraseña.
 * Queda registro_estado='pendiente' hasta que RRHH aprueba la selfie (admin/empleados.php).
 *
 * Seguridad: la liga solo sirve con registro_activo=1 y la clave vigente; 10 intentos de
 * identificación por IP cada 15 min; mensajes genéricos (no revela qué empleados existen);
 * solo activa cuentas sin acceso previo al portal; el estado vive en la sesión del servidor.
 */
declare(strict_types=1);

require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/../db/registro.php';
require_once __DIR__ . '/../db/foto_upload.php';
require_once __DIR__ . '/../db/mailer.php';

const REG_TTL        = 1800;   // 30 min para completar el registro
const REG_CODE_TTL   = 900;    // el código por correo vence en 15 min
const REG_CODE_TRIES = 5;
const REG_CODE_SENDS = 3;

$marca  = portalMarca();
$errors = [];

/* ── Liga vigente ─────────────────────────────────────────── */
$claveVigente = appConfig('registro_clave');
$k = (string)($_GET['k'] ?? '');
if ($k !== '' && ($_SESSION['reg']['k'] ?? '') !== $k) {
    $_SESSION['reg'] = ['k' => $k, 'step' => 'identificar', 't' => time()];
}
/* Liga personal de una invitación (&n=EMP-0001): solo pre-escribe el número de empleado */
if ($k !== '' && isset($_GET['n']) && preg_match('/^[A-Za-z0-9-]{1,20}$/', (string)$_GET['n'])) {
    $_SESSION['reg']['n'] = strtoupper((string)$_GET['n']);
}
$reg = $_SESSION['reg'] ?? null;
$ligaValida = registroActivo() && $reg !== null && hash_equals($claveVigente, (string)$reg['k']);
if ($ligaValida && time() - (int)$reg['t'] > REG_TTL) {
    $reg = $_SESSION['reg'] = ['k' => $reg['k'], 'step' => 'identificar', 't' => time()];
    $errors[] = 'Tu sesión de registro venció. Vuelve a identificarte.';
}
$self = 'registro.php?k=' . rawurlencode((string)($reg['k'] ?? ''));

/** Empleado elegible: existe, no está de baja y aún no tiene acceso al portal. */
function regEmpleado(mysqli $db, int $id): ?array
{
    $row = $db->query("SELECT * FROM empleados WHERE id=$id LIMIT 1")->fetch_assoc();
    return $row ?: null;
}
function regElegible(array $e): bool
{
    return $e['estatus'] !== 'baja_definitiva'
        && empty($e['portal_password_hash'])
        && empty($e['portal_pin_hash'])           // con PIN ya tiene acceso: no se le bloquea por un registro pendiente
        && in_array($e['registro_estado'], [null, 'rechazado'], true);
}
/** PIN trivial: todos iguales, consecutivos (1234 / 4321), o parte del teléfono o del número de empleado. */
function pinDebil(string $pin, string $numeroEmpleado, string $telefono): bool
{
    if (preg_match('/^(\d)\1+$/', $pin)) return true;
    if (str_contains('01234567890', $pin) || str_contains('09876543210', $pin)) return true;
    $tel = preg_replace('/\D/', '', $telefono);
    $num = preg_replace('/\D/', '', $numeroEmpleado);
    return ($tel !== '' && str_contains($tel, $pin)) || ($num !== '' && str_contains(str_pad($num, strlen($pin), '0', STR_PAD_LEFT), $pin));
}
function regEnviarCodigo(array $e, string $email, string $empresa): bool
{
    $code = (string)random_int(100000, 999999);
    $_SESSION['reg']['code']  = password_hash($code, PASSWORD_DEFAULT);
    $_SESSION['reg']['exp']   = time() + REG_CODE_TTL;
    $_SESSION['reg']['tries'] = 0;
    $_SESSION['reg']['sends'] = (int)($_SESSION['reg']['sends'] ?? 0) + 1;
    $_SESSION['reg']['last']  = time();
    $_SESSION['reg']['to']    = enmascararCorreo($email);
    $nombre = htmlspecialchars($e['nombre'], ENT_QUOTES, 'UTF-8');
    // Solo el código, sin enlaces: así nadie puede imitar este correo para mandar a otra página
    $html = emailLayout('Código de verificación', <<<HTML
<p>Hola <strong>$nombre</strong>,</p>
<p>Tu código para activar tu cuenta del portal de empleados es:</p>
<p style="font-size:32px;font-weight:800;letter-spacing:8px;margin:20px 0;">$code</p>
<p style="font-size:13px;color:#64748b;">Vence en 15 minutos. Escríbelo en la misma pantalla donde lo pediste.
Nadie de la empresa te lo pedirá por teléfono ni por WhatsApp. Si no fuiste tú, ignora este correo.</p>
HTML);
    return sendMail($email, "Tu código de verificación – $empresa", $html);
}

/* ── POST ─────────────────────────────────────────────────── */
if ($ligaValida && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de seguridad inválido. Recarga la página.';
    } else {
        $db   = getDB();
        $paso = $_POST['paso'] ?? '';
        $_SESSION['reg']['t'] = time();

        if ($paso === 'identificar' && $reg['step'] === 'identificar') {
            $kIp      = 'ip:' . clientIp() . ':registro';
            $numero   = strtoupper(trim($_POST['numero_empleado'] ?? ''));
            $contacto = trim($_POST['contacto'] ?? '');
            $generico = 'Los datos no coinciden con ningún empleado pendiente de activar. Revisa tu número de empleado y el teléfono o correo que diste al contratarte.';

            if (intentosExcedidos($db, $kIp, 10)) {
                $errors[] = 'Demasiados intentos. Espera 15 minutos e intenta de nuevo.';
            } else {
                $n = $db->real_escape_string($numero);
                $e = $numero !== '' ? $db->query("SELECT * FROM empleados WHERE numero_empleado='$n' LIMIT 1")->fetch_assoc() : null;
                $coincide = false;
                if ($e) {
                    if (str_contains($contacto, '@')) {
                        $c = mb_strtolower($contacto);
                        $coincide = in_array($c, array_map('mb_strtolower', array_filter([(string)$e['email'], (string)$e['email_corporativo']])), true);
                    } else {
                        $t = normalizarTelefono($contacto);
                        $coincide = $t !== '' && $t === normalizarTelefono((string)$e['telefono']);
                    }
                }

                if (!$e || !$coincide) {
                    registrarIntento($db, $kIp);
                    $errors[] = $generico;
                } elseif (!regElegible($e)) {
                    $errors[] = $e['registro_estado'] === 'pendiente'
                        ? 'Tu registro ya está en revisión por Recursos Humanos.'
                        : 'Tu cuenta ya tiene acceso al portal. Entra con tu usuario y contraseña, o pide ayuda a RRHH.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['reg']['emp'] = (int)$e['id'];
                    $correo = '';
                    foreach ([$e['email'], $e['email_corporativo']] as $m) {
                        if ($m && filter_var($m, FILTER_VALIDATE_EMAIL)) { $correo = $m; break; }
                    }
                    if ($correo === '') {
                        $_SESSION['reg']['step'] = 'datos';     // sin correo: lo confirma RRHH al aprobar
                    } elseif (regEnviarCodigo($e, $correo, $marca['empresa'])) {
                        $_SESSION['reg']['step']  = 'codigo';
                        $_SESSION['reg']['email'] = $correo;
                    } else {
                        unset($_SESSION['reg']['emp']);
                        $errors[] = 'No pudimos enviar el código a tu correo. Intenta más tarde o avisa a Recursos Humanos.';
                    }
                }
            }
        } elseif ($paso === 'codigo' && $reg['step'] === 'codigo') {
            $code = preg_replace('/\D/', '', $_POST['codigo'] ?? '');
            $_SESSION['reg']['tries'] = (int)$reg['tries'] + 1;
            if (time() > (int)$reg['exp']) {
                $errors[] = 'El código venció. Pide uno nuevo.';
            } elseif ($_SESSION['reg']['tries'] > REG_CODE_TRIES) {
                $_SESSION['reg'] = ['k' => $reg['k'], 'step' => 'identificar', 't' => time()];
                $errors[] = 'Demasiados intentos con el código. Vuelve a identificarte.';
            } elseif (strlen($code) === 6 && password_verify($code, (string)$reg['code'])) {
                $_SESSION['reg']['step'] = 'datos';
                unset($_SESSION['reg']['code']);
            } else {
                $errors[] = 'El código no es correcto.';
            }
        } elseif ($paso === 'reenviar' && $reg['step'] === 'codigo') {
            $e = regEmpleado($db, (int)$reg['emp']);
            if ((int)$reg['sends'] >= REG_CODE_SENDS || time() - (int)$reg['last'] < 60) {
                $errors[] = 'Espera un minuto antes de pedir otro código (máximo ' . REG_CODE_SENDS . ').';
            } elseif ($e && regEnviarCodigo($e, (string)$reg['email'], $marca['empresa'])) {
                $_SESSION['reg']['aviso'] = 'Te enviamos un código nuevo.';
            } else {
                $errors[] = 'No pudimos enviar el código. Intenta más tarde.';
            }
        } elseif ($paso === 'datos' && $reg['step'] === 'datos') {
            $e = regEmpleado($db, (int)$reg['emp']);
            if (!$e || !regElegible($e)) {
                $_SESSION['reg'] = ['k' => $reg['k'], 'step' => 'identificar', 't' => time()];
                $errors[] = 'Esta cuenta ya no está disponible para registro.';
            } else {
                /* ── Validación ── */
                $p = fn(string $k) => trim((string)($_POST[$k] ?? ''));
                $fn    = $p('fecha_nacimiento');
                $curp  = strtoupper($p('curp'));
                $rfc   = mb_strtoupper($p('rfc'));
                $nss   = preg_replace('/\D/', '', $p('nss'));
                $tel   = $p('telefono');
                $email = $p('email');
                $modo  = in_array($_POST['acceso'] ?? '', ['password', 'pin', 'ambos'], true) ? $_POST['acceso'] : 'password';
                $usaPw = $modo !== 'pin';
                $usaPin= $modo !== 'password';
                $user  = $usaPw ? mb_strtolower($p('portal_username')) : '';
                $pass  = $usaPw ? (string)($_POST['portal_password'] ?? '') : '';
                $pin   = $usaPin ? trim((string)($_POST['portal_pin'] ?? '')) : '';

                $edad = $fn !== '' && ($d = DateTime::createFromFormat('Y-m-d', $fn)) ? (int)$d->diff(new DateTime())->y : -1;
                if ($edad < 15 || $edad > 90)                                  $errors[] = 'Revisa tu fecha de nacimiento.';
                if (!preg_match('/^[A-Z]{4}\d{6}[HMX][A-Z]{5}[A-Z0-9]\d$/', $curp)) $errors[] = 'La CURP no es válida (18 caracteres).';
                if ($rfc !== '' && !preg_match('/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/u', $rfc)) $errors[] = 'El RFC no es válido (13 caracteres).';
                if ($nss !== '' && strlen($nss) !== 11)                        $errors[] = 'El NSS debe tener 11 dígitos.';
                if (normalizarTelefono($tel) === '')                           $errors[] = 'Escribe un teléfono de 10 dígitos.';
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'El correo no es válido.';
                if ($p('direccion') === '' || $p('colonia') === '' || $p('ciudad') === '' || $p('estado_residencia') === '')
                                                                                $errors[] = 'Completa tu domicilio (calle, colonia, ciudad y estado).';
                if (!preg_match('/^\d{5}$/', $p('cp')))                        $errors[] = 'El código postal debe tener 5 dígitos.';
                if ($usaPw) {
                    if (!preg_match('/^[a-z0-9._-]{4,30}$/', $user))               $errors[] = 'El usuario debe tener de 4 a 30 caracteres: letras, números, punto o guion.';
                    if (strlen($pass) < 8)                                         $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
                    elseif ($pass !== (string)($_POST['portal_password2'] ?? ''))  $errors[] = 'Las contraseñas no coinciden.';
                    elseif (mb_strtolower($pass) === $user)                        $errors[] = 'La contraseña no puede ser igual a tu usuario.';
                }
                if ($usaPin) {
                    if (!preg_match('/^\d{4,6}$/', $pin))                          $errors[] = 'El PIN debe tener de 4 a 6 números.';
                    elseif ($pin !== trim((string)($_POST['portal_pin2'] ?? '')))  $errors[] = 'Los PIN no coinciden.';
                    elseif (pinDebil($pin, (string)$e['numero_empleado'], $tel))   $errors[] = 'Ese PIN es muy fácil de adivinar (números repetidos, seguidos o parte de tu teléfono o número de empleado). Elige otro.';
                }
                if (empty($_POST['acepto']))                                   $errors[] = 'Debes aceptar el aviso de privacidad.';
                if (empty($_FILES['foto_referencia']['name']))                 $errors[] = 'Toma tu selfie de referencia.';

                if (!$errors && $usaPw) {
                    $u   = $db->real_escape_string($user);
                    $dup = $db->query("SELECT 1 FROM empleados WHERE portal_username='$u' AND id<>{$e['id']} LIMIT 1");
                    if ($dup && $dup->fetch_row()) $errors[] = 'Ese usuario ya está ocupado. Elige otro.';
                }

                $foto = null;
                if (!$errors) {
                    $r = procesarFotoReferencia($_FILES['foto_referencia'], __DIR__ . '/../uploads/empleados');
                    $r['ok'] ? $foto = $r['filename'] : $errors[] = 'Selfie: ' . $r['error'];
                }

                if (!$errors) {
                    $sexo = in_array($_POST['sexo'] ?? '', ['M', 'F', 'NB'], true) ? $_POST['sexo'] : null;
                    $civ  = in_array($_POST['estado_civil'] ?? '', ['soltero', 'casado', 'union_libre', 'divorciado', 'viudo'], true) ? $_POST['estado_civil'] : null;
                    $hash    = $usaPw  ? password_hash($pass, PASSWORD_DEFAULT) : null;
                    $pinHash = $usaPin ? password_hash($pin, PASSWORD_DEFAULT)  : null;
                    $id      = (int)$e['id'];
                    $nil  = fn($v) => $v === '' ? null : $v;

                    // Solo si sigue sin acceso (evita que dos personas activen la misma cuenta a la vez)
                    $s = $db->prepare(
                        "UPDATE empleados SET
                           fecha_nacimiento=?, sexo=?, estado_civil=?, curp=?, rfc=?, nss=?,
                           telefono=?, email=?, direccion=?, colonia=?, ciudad=?, estado_residencia=?, cp=?,
                           portal_username=?, portal_password_hash=?, portal_pin_hash=?, portal_activo=1, foto_referencia=?,
                           registro_estado='pendiente', registro_fecha=NOW(), registro_revisado_por=NULL
                         WHERE id=? AND portal_password_hash IS NULL AND portal_pin_hash IS NULL
                           AND (registro_estado IS NULL OR registro_estado='rechazado')"
                    );
                    $vals = [$fn, $sexo, $civ, $curp, $nil($rfc), $nil($nss), $tel, $nil($email) ?? $e['email'],
                             mb_substr($p('direccion'), 0, 200), mb_substr($p('colonia'), 0, 120), mb_substr($p('ciudad'), 0, 100),
                             mb_substr($p('estado_residencia'), 0, 60), $p('cp'), $usaPw ? $user : null, $hash, $pinHash, $foto, $id];
                    $s->bind_param('sssssssssssssssssi', ...$vals);

                    if ($s->execute() && $s->affected_rows === 1) {
                        $vieja = basename((string)$e['foto_referencia']);
                        if ($vieja !== '' && $vieja !== $foto && is_file(__DIR__ . "/../uploads/empleados/$vieja")) {
                            unlink(__DIR__ . "/../uploads/empleados/$vieja");
                        }
                        $_SESSION['reg'] = ['k' => $reg['k'], 'step' => 'listo', 't' => time(), 'nombre' => $e['nombre'],
                                            'user' => $usaPw ? $user : '', 'pin' => $usaPin, 'numero' => $e['numero_empleado']];
                    } else {
                        unlink(__DIR__ . "/../uploads/empleados/$foto");
                        $errors[] = 'No se pudo guardar tu registro. Intenta de nuevo o avisa a Recursos Humanos.';
                    }
                }
            }
        }
        $db->close();

        if (!$errors) {           // PRG: evita reenviar el formulario al recargar
            header('Location: ' . $self);
            exit;
        }
    }
}

$reg    = $_SESSION['reg'] ?? null;
$step   = $ligaValida ? ($reg['step'] ?? 'identificar') : 'cerrado';
$aviso  = $reg['aviso'] ?? '';
unset($_SESSION['reg']['aviso']);
$token  = generateCsrfToken();

$emp = null;
if ($step === 'datos') {
    $db  = getDB();
    $emp = regEmpleado($db, (int)$reg['emp']);
    $db->close();
}
/* Valores del formulario: lo que envió (si hubo error) o lo que ya hay en su ficha */
$val = fn(string $k) => h($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST[$k] ?? '') : ($emp[$k] ?? ''));
$pasos = ['identificar' => 1, 'codigo' => 2, 'datos' => 3, 'listo' => 4];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Activa tu cuenta – <?= h($marca['empresa']) ?></title>
<?php require __DIR__ . '/_head.php'; ?>
<style>
:root{--brand:<?= $marca['color'] ?>;--brand-soft:color-mix(in srgb,var(--brand) 12%,transparent)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f0f9ff;min-height:100vh;color:#1e293b;-webkit-font-smoothing:antialiased}
.header{background:var(--brand);color:#fff;padding:16px 20px;display:flex;align-items:center;gap:14px}
.header img{height:34px;background:#fff;border-radius:8px;padding:3px}
.header h1{font-size:17px;font-weight:800}.header p{font-size:13px;opacity:.85}
.container{max-width:520px;margin:24px auto;padding:0 16px 60px}
.card{background:#fff;border-radius:18px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:24px}
.steps{display:flex;gap:6px;margin-bottom:20px}
.steps span{flex:1;height:5px;border-radius:99px;background:#e2e8f0}
.steps span.on{background:var(--brand)}
h2{font-size:20px;font-weight:800;color:#0f172a;margin-bottom:6px}
.sub{font-size:14px;color:#64748b;margin-bottom:20px;line-height:1.5}
.section{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#64748b;margin:24px 0 12px}
label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
label small{font-weight:400;color:#94a3b8}
.inp{width:100%;padding:13px 14px;border:1.5px solid #d1d5db;border-radius:12px;font-size:16px;font-family:inherit;background:#fff}
.inp:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)}
.inp[readonly]{background:#f8fafc;color:#475569}
.fg{margin-bottom:16px}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:14px 22px;border-radius:12px;border:1.5px solid transparent;cursor:pointer;font-size:16px;font-weight:700;font-family:inherit;width:100%;text-decoration:none}
.btn-primary{background:var(--brand);color:#fff}.btn-primary:hover{filter:brightness(.92)}
.btn-ghost{background:#fff;color:var(--brand);border-color:color-mix(in srgb,var(--brand) 35%,transparent)}
.btn-link{background:none;border:none;color:var(--brand);font-size:14px;width:auto;padding:8px}
.btn[disabled]{opacity:.55}
.error{background:#fee2e2;color:#991b1b;border-radius:12px;padding:12px 16px;margin-bottom:16px;font-size:14px;font-weight:600;line-height:1.5}
.ok{background:#dcfce7;color:#166534;border-radius:12px;padding:12px 16px;margin-bottom:16px;font-size:14px;font-weight:600}
.code{font-size:28px;letter-spacing:12px;text-align:center;font-weight:800}
.check{display:flex;gap:10px;align-items:flex-start;font-size:13px;font-weight:500;color:#475569;line-height:1.5}
.check input{width:20px;height:20px;margin-top:1px;flex:0 0 20px}
.acceso-opts{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.acceso-opts label{margin:0;cursor:pointer;position:relative}
.acceso-opts input{position:absolute;opacity:0;pointer-events:none}
.acceso-opts span{display:flex;flex-direction:column;gap:2px;height:100%;padding:12px 10px;border:1.5px solid #d1d5db;border-radius:12px;text-align:center;font-size:14px}
.acceso-opts small{font-size:11px;font-weight:400;color:#64748b;line-height:1.3}
.acceso-opts input:checked + span{border-color:var(--brand);background:var(--brand-soft);color:var(--brand)}
.acceso-opts input:focus-visible + span{box-shadow:0 0 0 3px var(--brand-soft)}
[hidden]{display:none !important}
@media(max-width:400px){.acceso-opts{grid-template-columns:1fr}}
/* Selfie (mismo componente que la ficha del admin: admin/assets/foto-referencia.js) */
.ref-box{display:flex;flex-direction:column;align-items:center;gap:14px;padding:18px;border:1.5px dashed #cbd5e1;border-radius:16px;background:#f8fafc;text-align:center}
.ref-img{width:140px;height:140px;border-radius:50%;object-fit:cover;background:#e2e8f0;display:grid;place-items:center;font-size:44px}
.ref-img[hidden]{display:none}
.ref-actions{display:flex;flex-direction:column;gap:10px;width:100%}
.ref-actions p{font-size:13px;color:#64748b;line-height:1.5}
.ref-actions .btns{display:flex;flex-direction:column;gap:8px}
#ref-status{font-size:14px;font-weight:600}
#ref-status[data-kind=ok]{color:#15803d}#ref-status[data-kind=err]{color:#b91c1c}#ref-status[data-kind=warn]{color:#b45309}
dialog#cam-dialog{border:none;border-radius:18px;padding:0;width:min(520px,96vw);background:#0f172a;color:#fff;margin:auto}
dialog#cam-dialog::backdrop{background:rgba(15,23,42,.7)}
.cam-stage{position:relative;aspect-ratio:3/4;max-height:68vh;background:#000;overflow:hidden}
.cam-stage video{width:100%;height:100%;object-fit:cover}
.cam-stage video.mirror{transform:scaleX(-1)}
.cam-oval{position:absolute;left:50%;top:46%;width:62%;aspect-ratio:3/4;transform:translate(-50%,-50%);border:3px dashed rgba(255,255,255,.8);border-radius:50%;box-shadow:0 0 0 999px rgba(0,0,0,.35);pointer-events:none;transition:border-color .2s}
.cam-oval.rostro-ok{border-style:solid;border-color:#4ade80}
.cam-foot{padding:14px;display:flex;flex-direction:column;gap:12px}
#cam-msg{font-size:14px;color:#cbd5e1;min-height:20px;text-align:center}
.cam-btns{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.cam-btns #cam-capture{grid-column:1/-1}
.cam-btns .btn-ghost{background:transparent;color:#e2e8f0;border-color:#334155}
.done{text-align:center;padding:12px 0}
.done .ico{width:72px;height:72px;border-radius:50%;background:#dcfce7;color:#16a34a;display:grid;place-items:center;font-size:36px;margin:0 auto 16px}
@media(max-width:400px){.row2{grid-template-columns:1fr}}
</style>
</head>
<body>

<div class="header">
  <img src="<?= h($marca['logo']) ?>" alt="" onerror="this.remove()">
  <div><h1>Activa tu cuenta</h1><p><?= h($marca['empresa']) ?> – Portal del colaborador</p></div>
</div>

<div class="container">
  <div class="card">
    <?php if ($step !== 'cerrado' && $step !== 'listo'): ?>
      <div class="steps"><?php for ($i = 1; $i <= 3; $i++): ?><span class="<?= $pasos[$step] >= $i ? 'on' : '' ?>"></span><?php endfor ?></div>
    <?php endif ?>

    <?php foreach ($errors as $e): ?><div class="error"><?= h($e) ?></div><?php endforeach ?>
    <?php if ($aviso): ?><div class="ok"><?= h($aviso) ?></div><?php endif ?>

  <?php if ($step === 'cerrado'): ?>
    <h2>Liga no disponible</h2>
    <p class="sub">Esta liga de registro no es válida o ya no está activa. Pide a Recursos Humanos la liga vigente.</p>
    <a href="login.php" class="btn btn-ghost">Ir al inicio de sesión</a>

  <?php elseif ($step === 'identificar'): ?>
    <h2>Identifícate</h2>
    <p class="sub">Usa los datos que diste cuando te contrataron. Tu número de empleado te lo da Recursos Humanos.</p>
    <form method="post" action="<?= h($self) ?>" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
      <input type="hidden" name="paso" value="identificar">
      <div class="fg">
        <label for="numero_empleado">Número de empleado</label>
        <input class="inp" id="numero_empleado" name="numero_empleado" required placeholder="EMP-0001" autocapitalize="characters" value="<?= h($_POST['numero_empleado'] ?? ($reg['n'] ?? '')) ?>">
      </div>
      <div class="fg">
        <label for="contacto">Tu teléfono o correo <small>(el que está registrado)</small></label>
        <input class="inp" id="contacto" name="contacto" required placeholder="55 1234 5678 o correo@ejemplo.com" autocapitalize="none" value="<?= h($_POST['contacto'] ?? '') ?>">
      </div>
      <button class="btn btn-primary" type="submit">Continuar</button>
    </form>

  <?php elseif ($step === 'codigo'): ?>
    <h2>Revisa tu correo</h2>
    <p class="sub">Te enviamos un código de 6 dígitos a <strong><?= h($reg['to']) ?></strong>. Revisa también la carpeta de spam.</p>
    <form method="post" action="<?= h($self) ?>" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
      <input type="hidden" name="paso" value="codigo">
      <div class="fg">
        <input class="inp code" name="codigo" required inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" autofocus aria-label="Código de 6 dígitos">
      </div>
      <button class="btn btn-primary" type="submit">Verificar</button>
    </form>
    <form method="post" action="<?= h($self) ?>" style="text-align:center;margin-top:10px">
      <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
      <input type="hidden" name="paso" value="reenviar">
      <button class="btn btn-link" type="submit">No me llegó, enviar otro código</button>
    </form>

  <?php elseif ($step === 'datos' && $emp): ?>
    <h2>Hola, <?= h($emp['nombre']) ?></h2>
    <p class="sub">Completa tus datos, toma tu selfie y crea tu contraseña. Recursos Humanos revisará tu registro antes de activar tu acceso.</p>
    <form method="post" action="<?= h($self) ?>" enctype="multipart/form-data" id="reg-form">
      <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
      <input type="hidden" name="paso" value="datos">

      <div class="fg">
        <label>Nombre completo</label>
        <input class="inp" readonly value="<?= h(trim($emp['nombre'] . ' ' . $emp['apellido_paterno'] . ' ' . ($emp['apellido_materno'] ?? ''))) ?>">
        <label style="font-weight:400;color:#94a3b8;margin-top:6px">¿Está mal escrito? Avísale a Recursos Humanos.</label>
      </div>

      <div class="section">Datos personales</div>
      <div class="row2">
        <div class="fg"><label for="fecha_nacimiento">Fecha de nacimiento</label>
          <input class="inp" type="date" id="fecha_nacimiento" name="fecha_nacimiento" required value="<?= $val('fecha_nacimiento') ?>"></div>
        <div class="fg"><label for="sexo">Sexo</label>
          <select class="inp" id="sexo" name="sexo">
            <option value="">—</option>
            <?php foreach (['M' => 'Masculino', 'F' => 'Femenino', 'NB' => 'No binario'] as $k2 => $l): ?>
              <option value="<?= $k2 ?>" <?= $val('sexo') === $k2 ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach ?>
          </select></div>
      </div>
      <div class="fg"><label for="estado_civil">Estado civil</label>
        <select class="inp" id="estado_civil" name="estado_civil">
          <option value="">—</option>
          <?php foreach (['soltero' => 'Soltero/a', 'casado' => 'Casado/a', 'union_libre' => 'Unión libre', 'divorciado' => 'Divorciado/a', 'viudo' => 'Viudo/a'] as $k2 => $l): ?>
            <option value="<?= $k2 ?>" <?= $val('estado_civil') === $k2 ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach ?>
        </select></div>
      <div class="fg"><label for="curp">CURP</label>
        <input class="inp" id="curp" name="curp" required maxlength="18" pattern="[A-Za-z]{4}\d{6}[HMXhmx][A-Za-z]{5}[A-Za-z0-9]\d" autocapitalize="characters" style="text-transform:uppercase" value="<?= $val('curp') ?>"></div>
      <div class="row2">
        <div class="fg"><label for="rfc">RFC <small>(opcional)</small></label>
          <input class="inp" id="rfc" name="rfc" maxlength="13" autocapitalize="characters" style="text-transform:uppercase" value="<?= $val('rfc') ?>"></div>
        <div class="fg"><label for="nss">NSS <small>(opcional)</small></label>
          <input class="inp" id="nss" name="nss" maxlength="11" inputmode="numeric" value="<?= $val('nss') ?>"></div>
      </div>

      <div class="section">Contacto y domicilio</div>
      <div class="row2">
        <div class="fg"><label for="telefono">Teléfono celular</label>
          <input class="inp" type="tel" id="telefono" name="telefono" required value="<?= $val('telefono') ?>"></div>
        <div class="fg"><label for="email">Correo <small>(opcional)</small></label>
          <input class="inp" type="email" id="email" name="email" autocapitalize="none" value="<?= $val('email') ?>"></div>
      </div>
      <div class="fg"><label for="direccion">Calle y número</label>
        <input class="inp" id="direccion" name="direccion" required maxlength="200" value="<?= $val('direccion') ?>"></div>
      <div class="row2">
        <div class="fg"><label for="cp-input">Código postal</label>
          <input class="inp" id="cp-input" name="cp" required inputmode="numeric" maxlength="5" pattern="\d{5}" value="<?= $val('cp') ?>">
          <small id="cp-status" style="font-size:12px;color:#94a3b8"></small></div>
        <div class="fg" id="colonia-wrap"><label for="colonia-input">Colonia</label>
          <input class="inp" id="colonia-input" name="colonia" required maxlength="120" value="<?= $val('colonia') ?>"></div>
      </div>
      <div class="row2">
        <div class="fg"><label for="ciudad-input">Ciudad / Municipio</label>
          <input class="inp" id="ciudad-input" name="ciudad" required maxlength="100" value="<?= $val('ciudad') ?>"></div>
        <div class="fg"><label for="estado-input">Estado</label>
          <input class="inp" id="estado-input" name="estado_residencia" required maxlength="60" value="<?= $val('estado_residencia') ?>"></div>
      </div>

      <div class="section">Tu selfie para el check-in</div>
      <div class="ref-box" id="ref-photo" data-facing="user"
           data-faceapi="assets/face-api/face-api.min.js" data-weights="assets/face-api/weights">
        <span class="ref-img" id="ref-initials">🙂</span>
        <img class="ref-img" id="ref-preview" alt="" hidden>
        <div class="ref-actions">
          <p>De frente, sin lentes oscuros ni gorra, con buena luz y solo tú en la foto. Con esta foto se verifica tu identidad cada vez que checas.</p>
          <div class="btns">
            <button type="button" class="btn btn-primary" id="ref-cam-btn">📷 Tomar selfie</button>
            <label class="btn btn-ghost">Elegir de mis fotos<input type="file" id="ref-file" accept="image/*" hidden></label>
          </div>
          <span id="ref-status" aria-live="polite"></span>
          <input type="file" name="foto_referencia" id="foto_referencia" accept="image/jpeg" hidden>
        </div>
      </div>

      <div class="section">Tu acceso</div>
      <?php $acceso = in_array($_POST['acceso'] ?? '', ['password', 'pin', 'ambos'], true) ? $_POST['acceso'] : 'password'; ?>
      <div class="fg">
        <label>¿Cómo quieres entrar a checar?</label>
        <div class="acceso-opts" role="radiogroup">
          <label><input type="radio" name="acceso" value="password" <?= $acceso === 'password' ? 'checked' : '' ?>><span><b>Contraseña</b><small>Usuario y contraseña</small></span></label>
          <label><input type="radio" name="acceso" value="pin" <?= $acceso === 'pin' ? 'checked' : '' ?>><span><b>PIN</b><small>N° de empleado y 4–6 números</small></span></label>
          <label><input type="radio" name="acceso" value="ambos" <?= $acceso === 'ambos' ? 'checked' : '' ?>><span><b>Los dos</b><small>Entra con cualquiera</small></span></label>
        </div>
      </div>
      <div id="acc-password">
        <div class="fg"><label for="portal_username">Usuario <small>(minúsculas, sin espacios)</small></label>
          <input class="inp" id="portal_username" name="portal_username" required minlength="4" maxlength="30" pattern="[a-zA-Z0-9._\-]{4,30}" autocapitalize="none" autocomplete="username" value="<?= h($_POST['portal_username'] ?? '') ?>"></div>
        <div class="row2">
          <div class="fg"><label for="portal_password">Contraseña <small>(mín. 8)</small></label>
            <input class="inp" type="password" id="portal_password" name="portal_password" required minlength="8" autocomplete="new-password"></div>
          <div class="fg"><label for="portal_password2">Repite la contraseña</label>
            <input class="inp" type="password" id="portal_password2" name="portal_password2" required minlength="8" autocomplete="new-password"></div>
        </div>
      </div>
      <div id="acc-pin">
        <div class="row2">
          <div class="fg"><label for="portal_pin">PIN <small>(4 a 6 números)</small></label>
            <input class="inp" type="password" id="portal_pin" name="portal_pin" required inputmode="numeric" pattern="\d{4,6}" minlength="4" maxlength="6" autocomplete="off"></div>
          <div class="fg"><label for="portal_pin2">Repite el PIN</label>
            <input class="inp" type="password" id="portal_pin2" name="portal_pin2" required inputmode="numeric" pattern="\d{4,6}" minlength="4" maxlength="6" autocomplete="off"></div>
        </div>
        <p class="sub" style="margin:-6px 0 16px;font-size:13px">Para entrar con PIN usarás tu número de empleado <strong><?= h($emp['numero_empleado']) ?></strong>. No uses números repetidos o seguidos (1111, 1234) ni parte de tu teléfono.</p>
      </div>

      <label class="check fg"><input type="checkbox" name="acepto" value="1" required>
        <span>Acepto que <?= h($marca['empresa']) ?> trate mis datos personales para fines laborales conforme a su
        <a href="../index.php#footer" target="_blank" rel="noopener">Aviso de Privacidad</a>.</span></label>

      <button class="btn btn-primary" type="submit" id="btn-enviar">Enviar registro</button>
    </form>

    <dialog id="cam-dialog">
      <div class="cam-stage"><video id="cam-video" playsinline muted></video><div class="cam-oval"></div></div>
      <div class="cam-foot">
        <div id="cam-msg" aria-live="polite"></div>
        <div class="cam-btns">
          <button type="button" class="btn btn-primary" id="cam-capture">Capturar</button>
          <button type="button" class="btn btn-ghost" id="cam-switch">↺ Cambiar cámara</button>
          <button type="button" class="btn btn-ghost" id="cam-cancel">Cancelar</button>
        </div>
      </div>
    </dialog>

  <?php elseif ($step === 'listo'): ?>
    <div class="done">
      <div class="ico">✓</div>
      <h2>¡Listo, <?= h($reg['nombre'] ?? '') ?>!</h2>
      <?php
        $formas = [];
        if (!empty($reg['user'])) $formas[] = 'tu usuario <strong>' . h($reg['user']) . '</strong> y la contraseña que creaste';
        if (!empty($reg['pin']))  $formas[] = 'tu número de empleado <strong>' . h($reg['numero'] ?? '') . '</strong> y tu PIN';
      ?>
      <p class="sub">Recursos Humanos revisará tu registro. En cuanto lo aprueben podrás checar entrando con
        <?= implode(', o con ', $formas) ?>.</p>
      <p class="sub"><strong>Siguiente paso:</strong> instala el Check-in en tu celular. En la pantalla del portal verás cómo hacerlo en Android o iPhone.</p>
      <a href="login.php" class="btn btn-primary">Ir al portal e instalar</a>
    </div>
  <?php endif ?>
  </div>
</div>

<?php if ($step === 'datos'): ?>
<script src="../admin/assets/img-compress.js?v=2"></script>
<script src="../admin/assets/foto-referencia.js?v=5"></script>
<script>
/* La selfie es obligatoria y no se puede marcar "required" (el campo está oculto) */
document.getElementById('reg-form').addEventListener('submit', function (e) {
  const foto = document.getElementById('foto_referencia');
  if (!foto.files || !foto.files.length) {
    e.preventDefault();
    const st = document.getElementById('ref-status');
    st.textContent = 'Falta tu selfie: toma una foto antes de enviar.';
    st.dataset.kind = 'err';
    st.scrollIntoView({ behavior: 'smooth', block: 'center' });
    return;
  }
  document.getElementById('btn-enviar').disabled = true;
  document.getElementById('btn-enviar').textContent = 'Enviando…';
});
document.getElementById('curp').addEventListener('input', function () { this.value = this.value.toUpperCase(); });

/* Acceso: contraseña, PIN o ambos (los campos ocultos no se validan ni se envían) */
(function () {
  const pw = document.getElementById('acc-password'), pin = document.getElementById('acc-pin');
  function sync() {
    const v = document.querySelector('input[name=acceso]:checked').value;
    [[pw, v !== 'pin'], [pin, v !== 'password']].forEach(function ([box, on]) {
      box.hidden = !on;
      box.querySelectorAll('input').forEach(i => { i.disabled = !on; });
    });
  }
  document.querySelectorAll('input[name=acceso]').forEach(r => r.addEventListener('change', sync));
  sync();
})();

/* Autocompletar colonia/ciudad/estado desde el C.P. (catálogo local) */
(function () {
  const cp = document.getElementById('cp-input'), st = document.getElementById('cp-status');
  cp.addEventListener('blur', function () {
    if (!/^\d{5}$/.test(cp.value)) return;
    st.textContent = 'Buscando…';
    fetch('cp_lookup.php?cp=' + encodeURIComponent(cp.value)).then(r => r.json()).then(function (d) {
      if (!d.ok) { st.textContent = 'C.P. no encontrado; escribe tu colonia.'; return; }
      st.textContent = '';
      document.getElementById('ciudad-input').value = d.ciudad || d.municipio;
      document.getElementById('estado-input').value = d.estado;
      const actual = document.getElementById('colonia-input');
      if (d.colonias.length) {
        const sel = document.createElement('select');
        sel.className = 'inp'; sel.id = 'colonia-input'; sel.name = 'colonia'; sel.required = true;
        d.colonias.forEach(function (c) { const o = new Option(c, c, false, c === actual.value); sel.add(o); });
        actual.replaceWith(sel);
      }
    }).catch(() => { st.textContent = ''; });
  });
})();
</script>
<?php endif ?>
<script src="../admin/assets/fecha-legible.js?v=1" defer></script>
</body>
</html>
