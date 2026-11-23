<?php
declare(strict_types=1);

require_once __DIR__ . '/_boot.php';

if (!empty($_SESSION['portal_empleado_id'])) {
    header('Location: checkin.php');
    exit;
}

$error = '';
$modo  = ($_POST['modo'] ?? $_GET['modo'] ?? 'password') === 'pin' ? 'pin' : 'password';

if (($_GET['motivo'] ?? '') === 'inactivo') {
    $error = 'Tu acceso al portal no está disponible. Contacta a tu supervisor.';
}

/* ── Anti fuerza bruta (en BD, no en sesión): 20 fallos por IP y 5 por cuenta en 15 min ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido. Recarga la página.';
    } else {
        $db     = getDB();
        $row    = null;
        $cuenta = $modo === 'password'
            ? mb_strtolower(trim($_POST['portal_username'] ?? ''))
            : strtoupper(trim($_POST['numero_empleado'] ?? ''));
        $kIp     = 'ip:' . clientIp() . ':portal';
        $kCuenta = 'cuenta:' . $cuenta;

        if (intentosExcedidos($db, $kIp, 20) || ($cuenta !== '' && intentosExcedidos($db, $kCuenta, 5))) {
            $error = 'Demasiados intentos fallidos. Espera 15 minutos e intenta de nuevo.';
        } else {
            if ($modo === 'password') {
                $pass = $_POST['portal_password'] ?? '';
                if ($cuenta !== '' && $pass !== '') {
                    $u = $db->real_escape_string($cuenta);
                    $cand = $db->query("SELECT * FROM empleados WHERE portal_username='$u' LIMIT 1")->fetch_assoc();
                    if ($cand && !empty($cand['portal_password_hash']) && password_verify($pass, $cand['portal_password_hash'])) {
                        $row = $cand;
                    }
                }
            } else {
                $pin = trim($_POST['portal_pin'] ?? '');
                if ($cuenta !== '' && $pin !== '') {
                    $n = $db->real_escape_string($cuenta);
                    $cand = $db->query("SELECT * FROM empleados WHERE numero_empleado='$n' LIMIT 1")->fetch_assoc();
                    if ($cand && !empty($cand['portal_pin_hash']) && password_verify($pin, $cand['portal_pin_hash'])) {
                        $row = $cand;
                    }
                }
            }

            if ($row && portalPuedeEntrar($row)) {
                limpiarIntentos($db, $kCuenta);
                session_regenerate_id(true);
                $_SESSION['portal_empleado_id'] = (int)$row['id'];
                $db->close();
                header('Location: checkin.php');
                exit;
            }

            if ($row && ($row['registro_estado'] ?? null) === 'pendiente') {
                $error = 'Tu registro está en revisión por Recursos Humanos. Podrás entrar en cuanto lo aprueben.';
            } elseif ($row) {
                $error = 'Tu acceso al portal no está disponible. Contacta a tu supervisor.';
            } else {
                registrarIntento($db, $kIp, $kCuenta);
                $error = $modo === 'password'
                    ? 'Usuario o contraseña incorrectos.'
                    : 'Número de empleado o PIN incorrectos.';
            }
        }
        $db->close();
    }
}

$token = generateCsrfToken();
$marca = portalMarca();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Check-in de empleados – <?= h($marca['empresa']) ?></title>
<?php require __DIR__ . '/_head.php'; ?>
<style>
:root{--brand:<?= $marca['color'] ?>}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f0f9ff;min-height:100vh;color:#1e293b;-webkit-font-smoothing:antialiased}
.header{background:var(--brand);color:#fff;padding:16px 24px;display:flex;align-items:center;gap:16px}
.header img{height:36px;background:#fff;border-radius:8px;padding:3px}
.header h1{font-size:18px;font-weight:800}
.header p{font-size:13px;opacity:.85}
.container{max-width:420px;margin:32px auto;padding:0 16px 60px}
.card{background:#fff;border-radius:18px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:28px}
.card-title{font-size:20px;font-weight:800;margin-bottom:6px;color:var(--brand)}
.card-sub{font-size:14px;color:#64748b;margin-bottom:22px}
label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
.inp{width:100%;padding:13px 14px;border:1.5px solid #d1d5db;border-radius:12px;font-size:16px;font-family:inherit;transition:.15s}
.inp:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px color-mix(in srgb,var(--brand) 15%,transparent)}
.form-group{margin-bottom:18px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:14px 28px;border-radius:12px;border:none;cursor:pointer;font-size:16px;font-weight:700;font-family:inherit;transition:.15s;width:100%}
.btn-primary{background:var(--brand);color:#fff}.btn-primary:hover{filter:brightness(.92)}
.btn-ghost{background:#fff;color:var(--brand);border:1.5px solid color-mix(in srgb,var(--brand) 35%,transparent)}
.error{background:#fee2e2;color:#991b1b;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:600}
.tabs{display:flex;gap:6px;margin-bottom:22px;background:#f1f5f9;border-radius:12px;padding:4px}
.tabs a{flex:1;text-align:center;padding:10px;border-radius:9px;font-size:13px;font-weight:700;color:#64748b;text-decoration:none}
.tabs a.active{background:#fff;color:var(--brand);box-shadow:0 1px 3px rgba(0,0,0,.08)}
.tip{font-size:13px;color:#64748b;margin-top:18px;text-align:center;line-height:1.6}
.install{margin-top:16px}
.install[hidden],#ios-tip[hidden]{display:none}
#ios-tip{margin-top:14px;font-size:13px;color:#475569;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px;padding:12px;text-align:center;line-height:1.5}
</style>
</head>
<body>

<div class="header">
  <img src="<?= h($marca['logo']) ?>" alt="" onerror="this.remove()">
  <div>
    <h1>Check-in de empleados</h1>
    <p><?= h($marca['empresa']) ?> – Portal del colaborador</p>
  </div>
</div>

<div class="container">
  <div class="card">
    <div class="card-title">Iniciar sesión</div>
    <div class="card-sub">Marca tu entrada y salida desde tu celular.</div>

    <div class="tabs">
      <a href="?modo=password" class="<?= $modo === 'password' ? 'active' : '' ?>">Usuario y contraseña</a>
      <a href="?modo=pin" class="<?= $modo === 'pin' ? 'active' : '' ?>">N° empleado y PIN</a>
    </div>

    <?php if ($error): ?>
      <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
      <input type="hidden" name="modo" value="<?= $modo ?>">

      <?php if ($modo === 'password'): ?>
        <div class="form-group">
          <label for="portal_username">Usuario</label>
          <input type="text" id="portal_username" name="portal_username" class="inp" required autofocus autocomplete="username" autocapitalize="none">
        </div>
        <div class="form-group">
          <label for="portal_password">Contraseña</label>
          <input type="password" id="portal_password" name="portal_password" class="inp" required autocomplete="current-password">
        </div>
      <?php else: ?>
        <div class="form-group">
          <label for="numero_empleado">Número de empleado</label>
          <input type="text" id="numero_empleado" name="numero_empleado" class="inp" required autofocus placeholder="EMP-0001" autocapitalize="characters">
        </div>
        <div class="form-group">
          <label for="portal_pin">PIN</label>
          <input type="password" id="portal_pin" name="portal_pin" class="inp" required inputmode="numeric" maxlength="6" autocomplete="off">
        </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary">Entrar</button>
    </form>

    <div class="install" id="install" hidden>
      <button type="button" class="btn btn-ghost" id="btn-install">📲 Instalar en mi celular</button>
    </div>
    <p id="ios-tip" hidden>Para tenerlo como app en tu iPhone: toca <strong>Compartir</strong> y luego <strong>“Agregar a inicio”</strong>.</p>

    <p class="tip">¿Primera vez? Usa la liga de registro que te envió Recursos Humanos.<br>¿Olvidaste tu contraseña? Pide a tu supervisor que la restablezca.</p>
  </div>
</div>

<script>
/* Instalar como app: Android/Chrome muestra el botón; iPhone, las instrucciones */
(function () {
  const box = document.getElementById('install');
  let aviso = null;
  addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); aviso = e; box.hidden = false; });
  document.getElementById('btn-install').addEventListener('click', async function () {
    if (!aviso) return;
    aviso.prompt();
    await aviso.userChoice;
    aviso = null;
    box.hidden = true;
  });
  const standalone = navigator.standalone || matchMedia('(display-mode: standalone)').matches;
  if (/iphone|ipad|ipod/i.test(navigator.userAgent) && !standalone) document.getElementById('ios-tip').hidden = false;
})();
</script>
</body>
</html>
