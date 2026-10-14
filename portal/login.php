<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['portal_empleado_id'])) {
    header('Location: checkin.php');
    exit;
}

require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../db/csrf.php';

$error = '';
$modo  = ($_POST['modo'] ?? $_GET['modo'] ?? 'password') === 'pin' ? 'pin' : 'password';

if (($_GET['motivo'] ?? '') === 'inactivo') {
    $error = 'Tu acceso al portal no está disponible. Contacta a tu supervisor.';
}

/* ── Bloqueo simple por intentos fallidos (anti fuerza bruta) ── */
$lockUntil = (int)($_SESSION['portal_lock_until'] ?? 0);
$locked    = $lockUntil > time();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido. Recarga la página.';
    } else {
        $db  = getDB();
        $row = null;

        if ($modo === 'password') {
            $user = trim($_POST['portal_username'] ?? '');
            $pass = $_POST['portal_password'] ?? '';
            if ($user !== '' && $pass !== '') {
                $u = $db->real_escape_string($user);
                $cand = $db->query(
                    "SELECT * FROM empleados WHERE portal_username='$u' LIMIT 1"
                )->fetch_assoc();
                if ($cand && !empty($cand['portal_password_hash']) && password_verify($pass, $cand['portal_password_hash'])) {
                    $row = $cand;
                }
            }
        } else {
            $num = strtoupper(trim($_POST['numero_empleado'] ?? ''));
            $pin = trim($_POST['portal_pin'] ?? '');
            if ($num !== '' && $pin !== '') {
                $n = $db->real_escape_string($num);
                $cand = $db->query(
                    "SELECT * FROM empleados WHERE numero_empleado='$n' LIMIT 1"
                )->fetch_assoc();
                if ($cand && !empty($cand['portal_pin_hash']) && password_verify($pin, $cand['portal_pin_hash'])) {
                    $row = $cand;
                }
            }
        }

        if ($row && $row['estatus'] !== 'baja_definitiva' && (int)$row['portal_activo'] === 1) {
            session_regenerate_id(true);
            $_SESSION['portal_empleado_id'] = (int)$row['id'];
            unset($_SESSION['portal_lock_until'], $_SESSION['portal_fail_count']);
            $db->close();
            header('Location: checkin.php');
            exit;
        }

        $fails = (int)($_SESSION['portal_fail_count'] ?? 0) + 1;
        $_SESSION['portal_fail_count'] = $fails;
        if ($fails >= 5) {
            $_SESSION['portal_lock_until'] = time() + 30;
            $_SESSION['portal_fail_count'] = 0;
            $error = 'Demasiados intentos. Espera 30 segundos e intenta de nuevo.';
        } else {
            $error = $modo === 'password'
                ? 'Usuario o contraseña incorrectos.'
                : 'Número de empleado o PIN incorrectos.';
        }
        $db->close();
    }
} elseif ($locked) {
    $error = 'Demasiados intentos. Espera unos segundos e intenta de nuevo.';
}

$token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Check-in de empleados – Sharclean</title>
<link rel="icon" href="../images/favicon.ico" type="image/x-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:#f0f9ff;min-height:100vh;color:#1e293b}
.header{background:#0284C7;color:#fff;padding:16px 24px;display:flex;align-items:center;gap:16px}
.header img{height:36px}
.header h1{font-size:18px;font-weight:800}
.header p{font-size:13px;opacity:.8}
.container{max-width:420px;margin:40px auto;padding:0 16px 60px}
.card{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:32px}
.card-title{font-size:20px;font-weight:800;margin-bottom:6px;color:#0284C7}
.card-sub{font-size:14px;color:#64748b;margin-bottom:24px}
label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
.inp{width:100%;padding:12px 14px;border:1.5px solid #d1d5db;border-radius:10px;font-size:15px;font-family:inherit;transition:.15s}
.inp:focus{outline:none;border-color:#0284C7;box-shadow:0 0 0 3px rgba(2,132,199,.12)}
.form-group{margin-bottom:18px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 28px;border-radius:10px;border:none;cursor:pointer;font-size:15px;font-weight:700;font-family:inherit;transition:.15s;width:100%}
.btn-primary{background:#0284C7;color:#fff}.btn-primary:hover{background:#0369a1}
.error{background:#fee2e2;color:#991b1b;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:600}
.tabs{display:flex;gap:6px;margin-bottom:22px;background:#f1f5f9;border-radius:10px;padding:4px}
.tabs a{flex:1;text-align:center;padding:9px;border-radius:8px;font-size:13px;font-weight:700;color:#64748b;text-decoration:none}
.tabs a.active{background:#fff;color:#0284C7;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.tip{font-size:12px;color:#94a3b8;margin-top:16px;text-align:center;line-height:1.6}
</style>
</head>
<body>

<div class="header">
  <img src="../images/logo.png" alt="Sharclean">
  <div>
    <h1>Check-in de empleados</h1>
    <p>Sharclean – Portal del colaborador</p>
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
      <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="modo" value="<?= $modo ?>">

      <?php if ($modo === 'password'): ?>
        <div class="form-group">
          <label for="portal_username">Usuario</label>
          <input type="text" id="portal_username" name="portal_username" class="inp" required autofocus autocomplete="username">
        </div>
        <div class="form-group">
          <label for="portal_password">Contraseña</label>
          <input type="password" id="portal_password" name="portal_password" class="inp" required autocomplete="current-password">
        </div>
      <?php else: ?>
        <div class="form-group">
          <label for="numero_empleado">Número de empleado</label>
          <input type="text" id="numero_empleado" name="numero_empleado" class="inp" required autofocus placeholder="EMP-0001">
        </div>
        <div class="form-group">
          <label for="portal_pin">PIN</label>
          <input type="text" id="portal_pin" name="portal_pin" class="inp" required inputmode="numeric" maxlength="6" autocomplete="off">
        </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-primary">Entrar</button>
    </form>

    <p class="tip">¿No tienes acceso? Pide a tu supervisor que configure tu usuario en el sistema.</p>
  </div>
</div>

</body>
</html>
