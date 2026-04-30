<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['admin_logged_in'])) {
    header('Location: index.php');
    exit;
}

// Load .env via config (it populates $_ENV)
require_once __DIR__ . '/../db/config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user      = $_POST['user'] ?? '';
    $pass      = $_POST['pass'] ?? '';
    $adminUser = $_ENV['ADMIN_USER'] ?? 'admin';
    $adminPass = $_ENV['ADMIN_PASS'] ?? '';

    if (hash_equals($adminUser, $user) && hash_equals($adminPass, $pass)) {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    }
    $error = 'Usuario o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin – Sharclean</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f1f5f9;display:flex;align-items:center;justify-content:center;min-height:100vh;}
.card{background:#fff;border-radius:12px;padding:40px;box-shadow:0 4px 24px rgba(0,0,0,.10);width:100%;max-width:380px;}
.logo{text-align:center;margin-bottom:32px;}
.logo h1{color:#0284C7;font-size:26px;font-weight:800;}
.logo p{color:#64748b;font-size:13px;margin-top:4px;}
label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;}
input{width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:14px;outline:none;transition:border-color .15s;}
input:focus{border-color:#0284C7;}
.fg{margin-bottom:20px;}
.btn{width:100%;padding:11px;background:#0284C7;color:#fff;border:none;border-radius:6px;font-size:15px;font-weight:600;cursor:pointer;transition:background .15s;}
.btn:hover{background:#0369a1;}
.err{background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:6px;font-size:13px;margin-bottom:16px;}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <h1>Sharclean</h1>
    <p>Panel de administración</p>
  </div>

  <?php if ($error): ?>
    <div class="err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>

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
