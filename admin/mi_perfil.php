<?php
/**
 * Sharclean Admin – Mi perfil: foto del usuario que se muestra en el menú lateral.
 * Disponible para cualquier usuario autenticado (no requiere permiso de módulo).
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db/foto_upload.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$me      = adminUser();
$csrf    = generateCsrfToken();
$db      = getAdminDB();
$errors  = [];
$fotoDir = __DIR__ . '/../uploads/usuarios';

$res = $me['id'] > 0 ? $db->query("SELECT u.username, u.email, u.foto FROM admin_usuarios u WHERE u.id={$me['id']} LIMIT 1") : false;
$row = $res ? $res->fetch_assoc() : null;
$disponible = $row !== null;   // false en modo legacy .env o sin migrate_admin_v2.sql

if ($disponible && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion   = $_POST['accion'] ?? '';
    $anterior = basename((string)($row['foto'] ?? ''));

    if ($accion === 'subir') {
        $r = procesarFotoPerfil($_FILES['foto'] ?? [], $fotoDir, 'u' . $me['id'] . '_');
        if ($r['ok']) {
            $f = $db->real_escape_string($r['filename']);
            $db->query("UPDATE admin_usuarios SET foto='$f' WHERE id={$me['id']}");
            if ($anterior !== '' && is_file("$fotoDir/$anterior")) unlink("$fotoDir/$anterior");
            header('Location: mi_perfil.php?ok=1');
            exit;
        }
        $errors[] = $r['error'];
    } elseif ($accion === 'quitar') {
        $db->query("UPDATE admin_usuarios SET foto=NULL WHERE id={$me['id']}");
        if ($anterior !== '' && is_file("$fotoDir/$anterior")) unlink("$fotoDir/$anterior");
        header('Location: mi_perfil.php?ok=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mi perfil — Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-size:14px}
.body{padding:28px;max-width:720px;margin:0 auto}
.card{background:#fff;border:1px solid #e6e9f0;border-radius:16px;padding:28px 32px;margin-bottom:20px}
.profile{display:flex;align-items:center;gap:24px;flex-wrap:wrap}
.profile-av{width:112px;height:112px;border-radius:50%;object-fit:cover;flex:0 0 112px;
  box-shadow:0 0 0 4px #fff,0 0 0 6px var(--brand-soft);display:grid;place-items:center;
  background:var(--brand);color:#fff;font-size:38px;font-weight:700}
.profile-av[hidden]{display:none}
.profile h2{font-size:20px;font-weight:700;margin-bottom:4px}
.profile p{color:#64748b;font-size:13px;line-height:1.6}
.card-title{font-size:15px;font-weight:700;margin-bottom:16px}
.drop{display:flex;align-items:center;gap:14px;padding:16px;border:1.5px dashed #cbd5e1;border-radius:12px;background:#f8fafc}
.drop input{font:inherit;font-size:13px}
.hint{font-size:12px;color:#94a3b8;margin-top:8px}
.actions{display:flex;gap:10px;justify-content:flex-end;margin-top:18px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;padding:9px 18px;border-radius:10px;border:none;cursor:pointer;font:inherit;font-weight:600;font-size:14px;transition:filter .15s,background .15s}
.btn-primary{background:var(--brand);color:#fff}.btn-primary:hover{filter:brightness(.92)}
.btn-ghost{background:#fff;color:#b91c1c;border:1px solid #fecaca}.btn-ghost:hover{background:#fef2f2}
.alert{padding:12px 16px;border-radius:10px;margin-bottom:20px;font-size:13px}
.alert-success{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0}
.alert-error{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}
</style>
<link rel="stylesheet" href="assets/admin.css?v=3">
</head>
<body>
<?php $navTitle = 'Mi perfil'; $activeModule = 'mi_perfil'; require __DIR__ . '/_nav.php'; ?>

<div class="body">
  <?php if (isset($_GET['ok'])): ?>
    <div class="alert alert-success">✓ Foto de perfil actualizada.</div>
  <?php endif ?>
  <?php foreach ($errors as $e): ?>
    <div class="alert alert-error">✗ <?= h($e) ?></div>
  <?php endforeach ?>

  <div class="card profile">
    <?php if (!empty($row['foto'])): ?>
      <img class="profile-av" id="av-preview" src="usuario_foto.php?id=<?= $me['id'] ?>&amp;v=<?= substr(md5($row['foto']), 0, 8) ?>" alt="Foto de perfil">
    <?php else: ?>
      <span class="profile-av" id="av-initials"><?= h($_initials ?: 'A') ?></span>
      <img class="profile-av" id="av-preview" alt="" hidden>
    <?php endif ?>
    <div>
      <h2><?= h($me['nombre']) ?></h2>
      <p>
        <?= h($me['perfil'] !== '' ? $me['perfil'] : 'Administrador') ?>
        <?php if ($disponible): ?><br>Usuario: <strong><?= h($row['username']) ?></strong><?= $row['email'] ? ' · ' . h($row['email']) : '' ?><?php endif ?>
      </p>
    </div>
  </div>

  <?php if (!$disponible): ?>
    <div class="alert alert-error">
      La foto de perfil requiere una cuenta de usuario en la base de datos y la migración
      <code>db/migrate_admin_v2.sql</code>.
    </div>
  <?php else: ?>
  <div class="card">
    <div class="card-title">Foto de perfil</div>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="accion" value="subir">
      <label class="drop">
        <input type="file" name="foto" id="foto" accept="image/*" data-compress="1024" required>
      </label>
      <p class="hint">JPG, PNG o WEBP (también fotos del celular; se comprimen antes de subir) · se recorta en cuadrado. Solo los usuarios del panel pueden verla.</p>
      <div class="actions">
        <button type="submit" class="btn btn-primary">Guardar foto</button>
      </div>
    </form>
    <?php if (!empty($row['foto'])): ?>
    <form method="post" onsubmit="return confirm('¿Quitar tu foto de perfil?')">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="accion" value="quitar">
      <div class="actions" style="margin-top:8px"><button type="submit" class="btn btn-ghost">Quitar foto</button></div>
    </form>
    <?php endif ?>
  </div>
  <?php endif ?>
</div>

<script src="assets/img-compress.js?v=1"></script>
<script>
// Vista previa local (no sube nada hasta pulsar Guardar)
document.getElementById('foto')?.addEventListener('change', function () {
  const file = this.files && this.files[0];
  if (!file || !file.type.startsWith('image/')) return;
  const img = document.getElementById('av-preview');
  img.src = URL.createObjectURL(file);
  img.hidden = false;
  document.getElementById('av-initials')?.remove();
});
</script>
</body>
</html>
