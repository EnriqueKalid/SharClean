<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../db/config.php';

$error = '';

/* ── Detectar si hay usuarios en BD o usar fallback .env ── */
function dbUsersExist(mysqli $db): bool
{
    try {
        $r = $db->query('SELECT COUNT(*) FROM admin_usuarios WHERE activo=1');
        return $r && (int)$r->fetch_row()[0] > 0;
    } catch (Throwable $e) {
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['user'] ?? '');
    $pass = trim($_POST['pass'] ?? '');
    $db   = getDB();

    if (dbUsersExist($db)) {
        /* ── Autenticación por BD ── */
        $u = $db->real_escape_string($user);
        $row = $db->query(
            "SELECT u.*, p.permisos, p.nombre AS perfil_nombre, p.slug AS perfil_slug
             FROM admin_usuarios u
             JOIN admin_perfiles p ON p.id = u.perfil_id
             WHERE u.username = '$u' AND u.activo = 1
             LIMIT 1"
        )->fetch_assoc();

        if ($row && password_verify($pass, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in']    = true;
            $_SESSION['admin_user_id']      = (int)$row['id'];
            $_SESSION['admin_user_name']    = $row['nombre'];
            $_SESSION['admin_perfil']       = $row['perfil_nombre'];
            $_SESSION['admin_perfil_slug']  = $row['perfil_slug'];
            $_SESSION['admin_permisos']     = json_decode($row['permisos'], true) ?? [];

            $id = (int)$row['id'];
            $db->query("UPDATE admin_usuarios SET ultimo_login=NOW() WHERE id=$id");
            $db->close();
            header('Location: index.php');
            exit;
        }
        $error = 'Usuario o contraseña incorrectos.';
    } else {
        /* ── Fallback .env (sin usuarios en BD) ── */
        $adminUser = $_ENV['ADMIN_USER'] ?? 'admin';
        $adminPass = $_ENV['ADMIN_PASS'] ?? '';
        if (hash_equals($adminUser, $user) && hash_equals($adminPass, $pass)) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $db->close();
            header('Location: index.php');
            exit;
        }
        $error = 'Usuario o contraseña incorrectos.';
    }
    if (isset($db)) $db->close();
}

$logoPath = 'images/logo.png';
try {
    require_once __DIR__ . '/../db/app_config.php';
    $logoPath = appConfig('sistema_logo', 'images/logo.png');
    $empresa  = appConfig('empresa_nombre', 'Admin');
} catch (Throwable $e) {
    $empresa = 'Admin';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Iniciar sesión – <?= htmlspecialchars($empresa, ENT_QUOTES, 'UTF-8') ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh;}
.card{background:#fff;border-radius:14px;padding:40px;box-shadow:0 4px 24px rgba(0,0,0,.10);width:100%;max-width:380px;}
.logo{text-align:center;margin-bottom:32px}
.logo img{height:48px;margin-bottom:10px}
.logo h1{color:#0284C7;font-size:22px;font-weight:800}
.logo p{color:#64748b;font-size:13px;margin-top:4px}
label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px;text-transform:uppercase;letter-spacing:.04em}
input{width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:7px;font-size:14px;font-family:inherit;outline:none;transition:border-color .15s}
input:focus{border-color:#0284C7;box-shadow:0 0 0 3px rgba(2,132,199,.1)}
.fg{margin-bottom:18px}
.btn{width:100%;padding:11px;background:#0284C7;color:#fff;border:none;border-radius:7px;font-size:15px;font-weight:600;cursor:pointer;transition:background .15s;font-family:inherit}
.btn:hover{background:#0369a1}
.err{background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:7px;font-size:13px;margin-bottom:16px;border:1px solid #fecaca}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <img src="../<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="" onerror="this.style.display='none'">
    <h1><?= htmlspecialchars($empresa, ENT_QUOTES, 'UTF-8') ?></h1>
    <p>Panel de administración</p>
  </div>

  <?php if ($error): ?>
    <div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif ?>

  <form method="post" autocomplete="off">
    <div class="fg">
      <label for="user">Usuario</label>
      <input type="text" id="user" name="user" required autofocus autocomplete="username">
    </div>
    <div class="fg">
      <label for="pass">Contraseña</label>
      <input type="password" id="pass" name="pass" required autocomplete="current-password">
    </div>
    <button type="submit" class="btn">Iniciar sesión</button>
  </form>
</div>
</body>
</html>
