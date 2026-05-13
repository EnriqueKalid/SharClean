<?php
/**
 * admin/setup.php – Configuración inicial de primer usuario.
 * Solo accesible cuando no existen usuarios en admin_usuarios.
 * Debe eliminarse o protegerse tras el primer uso.
 */
declare(strict_types=1);

require_once __DIR__ . '/../db/config.php';
require_once __DIR__ . '/../db/app_config.php';

$db    = getDB();
$error = '';
$done  = false;

/* Verificar tabla y contar usuarios */
try {
    $count = (int)$db->query('SELECT COUNT(*) FROM admin_usuarios')->fetch_row()[0];
} catch (Throwable $e) {
    die('<p style="font-family:sans-serif;padding:40px;color:#dc2626"><strong>Error:</strong> La tabla admin_usuarios no existe.<br>Ejecute primero <code>db/migrate_usuarios_v1.sql</code> en su servidor.</p>');
}

if ($count > 0) {
    header('Location: login.php');
    exit;
}

/* Obtener perfil super_admin */
$perfilRow = $db->query("SELECT id FROM admin_perfiles WHERE slug='super_admin' LIMIT 1")->fetch_assoc();
if (!$perfilRow) {
    die('<p style="font-family:sans-serif;padding:40px;color:#dc2626"><strong>Error:</strong> Perfil super_admin no encontrado.<br>Ejecute <code>db/migrate_usuarios_v1.sql</code>.</p>');
}
$superAdminPerfil = (int)$perfilRow['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre']   ?? '');
    $email    = trim($_POST['email']    ?? '');
    $username = trim($_POST['username'] ?? '');
    $pass     = $_POST['pass']     ?? '';
    $pass2    = $_POST['pass2']    ?? '';

    if (!$nombre || !$username || !$pass) {
        $error = 'Nombre, usuario y contraseña son obligatorios.';
    } elseif (strlen($username) < 3) {
        $error = 'El nombre de usuario debe tener al menos 3 caracteres.';
    } elseif (strlen($pass) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
    } elseif ($pass !== $pass2) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $n    = $db->real_escape_string($nombre);
        $e    = $db->real_escape_string($email);
        $u    = $db->real_escape_string($username);
        $h    = $db->real_escape_string($hash);
        $ok   = $db->query(
            "INSERT INTO admin_usuarios (nombre, email, username, password_hash, perfil_id)
             VALUES ('$n', '$e', '$u', '$h', $superAdminPerfil)"
        );
        if ($ok) {
            $done = true;
        } else {
            $error = $db->error ?: 'Error al crear el usuario.';
        }
    }
}

$empresa = appConfig('empresa_nombre', 'Admin');
$logo    = appConfig('sistema_logo',   'images/logo.png');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Configuración inicial – <?= htmlspecialchars($empresa, ENT_QUOTES, 'UTF-8') ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh}
.card{background:#fff;border-radius:14px;padding:40px;box-shadow:0 4px 24px rgba(0,0,0,.1);width:100%;max-width:420px}
.logo{text-align:center;margin-bottom:28px}
.logo img{height:44px;margin-bottom:10px}
.logo h1{font-size:20px;font-weight:800;color:#0284C7}
.logo p{font-size:13px;color:#64748b;margin-top:4px}
.badge{display:inline-block;background:#fff7ed;color:#c2410c;border:1px solid #fed7aa;padding:8px 14px;border-radius:7px;font-size:12px;margin-bottom:20px;line-height:1.5}
label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;text-transform:uppercase;letter-spacing:.04em}
input{width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:14px;font-family:inherit;outline:none;transition:border-color .15s;margin-bottom:16px}
input:focus{border-color:#0284C7;box-shadow:0 0 0 3px rgba(2,132,199,.1)}
.btn{width:100%;padding:11px;background:#0284C7;color:#fff;border:none;border-radius:7px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit}
.btn:hover{background:#0369a1}
.err{background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:7px;font-size:13px;margin-bottom:16px;border:1px solid #fecaca}
.ok{background:#dcfce7;color:#15803d;padding:16px;border-radius:7px;font-size:14px;margin-bottom:16px;border:1px solid #bbf7d0}
.ok a{color:#0284C7;font-weight:700}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <img src="../<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') ?>" alt="" onerror="this.style.display='none'">
    <h1><?= htmlspecialchars($empresa, ENT_QUOTES, 'UTF-8') ?></h1>
    <p>Configuración inicial del sistema</p>
  </div>

  <?php if ($done): ?>
    <div class="ok">
      ✓ <strong>Primer usuario creado con éxito.</strong><br>
      <a href="login.php">Ir al inicio de sesión →</a>
    </div>
    <p style="font-size:12px;color:#94a3b8;text-align:center;margin-top:12px">
      Por seguridad, puede eliminar <code>admin/setup.php</code> del servidor.
    </p>
  <?php else: ?>
    <div class="badge">
      🔐 Este asistente solo está disponible cuando no hay usuarios registrados.<br>
      Crea la primera cuenta de <strong>Super Administrador</strong>.
    </div>

    <?php if ($error): ?>
      <div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif ?>

    <form method="post">
      <label for="nombre">Nombre completo</label>
      <input type="text" id="nombre" name="nombre" required value="<?= htmlspecialchars($_POST['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <label for="email">Email (opcional)</label>
      <input type="email" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <label for="username">Nombre de usuario</label>
      <input type="text" id="username" name="username" required autocomplete="off" value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

      <label for="pass">Contraseña (mín. 8 caracteres)</label>
      <input type="password" id="pass" name="pass" required autocomplete="new-password">

      <label for="pass2">Confirmar contraseña</label>
      <input type="password" id="pass2" name="pass2" required autocomplete="new-password">

      <button type="submit" class="btn">Crear Super Administrador</button>
    </form>
  <?php endif ?>
</div>
</body>
</html>
