<?php
/**
 * Sharclean Admin – Restablecer contraseña vía token
 * Página pública (no requiere sesión). Acceso solo con token válido.
 */
declare(strict_types=1);

require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../db/app_config.php';

$db    = getDB();
$token = trim($_GET['token'] ?? '');
$error = '';
$done  = false;

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ── Validar token ──────────────────────────────────────────────────
$tokenRow = null;
if ($token !== '') {
    $t = $db->real_escape_string($token);
    $res = $db->query(
        "SELECT rt.id, rt.user_id, rt.expires_at, rt.used, u.nombre, u.email, u.username
         FROM admin_reset_tokens rt
         JOIN admin_usuarios u ON u.id = rt.user_id
         WHERE rt.token = '$t' LIMIT 1"
    );
    $tokenRow = $res ? $res->fetch_assoc() : null;
}

$tokenValid = $tokenRow
    && (int)$tokenRow['used'] === 0
    && strtotime($tokenRow['expires_at']) > time();

// ── POST: cambiar contraseña ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $pass1 = $_POST['pass1'] ?? '';
    $pass2 = $_POST['pass2'] ?? '';

    if (strlen($pass1) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($pass1 !== $pass2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $hash = $db->real_escape_string(password_hash($pass1, PASSWORD_DEFAULT));
        $uid  = (int)$tokenRow['user_id'];
        $rid  = (int)$tokenRow['id'];

        $db->query("UPDATE admin_usuarios SET password_hash='$hash', activo=1 WHERE id=$uid");
        $db->query("UPDATE admin_reset_tokens SET used=1 WHERE id=$rid");
        $done = true;
    }
}

$empresa = appConfig('empresa_nombre', 'Sharclean');
$color   = appConfig('sistema_color',  '#0284C7');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Restablecer contraseña – <?= h($empresa) ?></title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:system-ui,-apple-system,sans-serif;background:#f4f7fb;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .card{background:#fff;border-radius:14px;box-shadow:0 4px 24px rgba(0,0,0,.08);width:100%;max-width:420px;overflow:hidden}
    .card-header{background:<?= h($color) ?>;padding:28px 32px}
    .card-header h1{color:#fff;font-size:20px;font-weight:700;margin-bottom:4px}
    .card-header p{color:rgba(255,255,255,.8);font-size:13px}
    .card-body{padding:32px}
    .field{margin-bottom:18px}
    .field label{display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em}
    .field input{width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;font-family:inherit;transition:border-color .15s}
    .field input:focus{outline:none;border-color:<?= h($color) ?>;box-shadow:0 0 0 3px <?= h($color) ?>22}
    .btn{width:100%;padding:11px;border:none;border-radius:8px;background:<?= h($color) ?>;color:#fff;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit;transition:opacity .15s}
    .btn:hover{opacity:.9}
    .alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:13px}
    .alert-error{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}
    .alert-invalid{background:#fef9c3;color:#713f12;border:1px solid #fde047}
    .alert-success{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0;font-size:15px;text-align:center;padding:20px}
    .hint{font-size:11px;color:#94a3b8;margin-top:5px}
    .back{display:block;text-align:center;margin-top:18px;font-size:13px;color:#64748b;text-decoration:none}
    .back:hover{color:#0284C7}
    strong.user{font-family:monospace;background:#f1f5f9;padding:1px 6px;border-radius:4px}
  </style>
</head>
<body>

<div class="card">
  <div class="card-header">
    <h1>🔑 Restablecer contraseña</h1>
    <p><?= h($empresa) ?> &middot; Panel administrativo</p>
  </div>
  <div class="card-body">

  <?php if ($done): ?>
    <div class="alert alert-success">
      <p style="font-size:20px;margin-bottom:8px">✓</p>
      <strong>¡Contraseña actualizada!</strong><br>
      Ya puedes iniciar sesión con tu nueva contraseña.
    </div>
    <a href="login.php" class="btn" style="display:block;text-align:center;text-decoration:none;padding:11px">
      Ir al inicio de sesión
    </a>

  <?php elseif (!$tokenValid): ?>
    <div class="alert alert-invalid">
      <?php if (!$token): ?>
        ⚠️ No se proporcionó ningún token de reseteo.
      <?php elseif (!$tokenRow): ?>
        ⚠️ El enlace no es válido o no existe.
      <?php elseif ((int)$tokenRow['used'] === 1): ?>
        ⚠️ Este enlace ya fue utilizado. Solicita uno nuevo.
      <?php else: ?>
        ⚠️ El enlace ha expirado (válido por 1 hora). Solicita uno nuevo.
      <?php endif ?>
    </div>
    <a href="login.php" class="back">← Volver al inicio de sesión</a>

  <?php else: ?>
    <?php if ($error): ?>
      <div class="alert alert-error">✗ <?= h($error) ?></div>
    <?php endif ?>

    <p style="font-size:14px;color:#475569;margin-bottom:22px">
      Estás restableciendo la contraseña de
      <strong class="user"><?= h($tokenRow['username']) ?></strong>.
      El enlace expira a las <?= date('H:i', strtotime($tokenRow['expires_at'])) ?>.
    </p>

    <form method="post" action="reset_password.php?token=<?= urlencode($token) ?>">
      <div class="field">
        <label>Nueva contraseña</label>
        <input type="password" name="pass1" required autofocus placeholder="Mínimo 8 caracteres">
        <span class="hint">Usa letras, números y símbolos para mayor seguridad.</span>
      </div>
      <div class="field">
        <label>Confirmar contraseña</label>
        <input type="password" name="pass2" required placeholder="Repite la contraseña">
      </div>
      <button type="submit" class="btn">Guardar nueva contraseña</button>
    </form>
    <a href="login.php" class="back">← Volver al inicio de sesión</a>

  <?php endif ?>

  </div>
</div>

</body>
</html>
