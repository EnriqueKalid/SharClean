<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
requirePermiso('usuarios');
$db = getAdminDB();

function fetchAll($result): array {
    $rows = [];
    if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$me    = adminUser();
$saved = false;
$errors= [];
$tab   = in_array($_GET['tab'] ?? '', ['usuarios','perfiles']) ? $_GET['tab'] : 'usuarios';

/* ── POST: guardar usuario ─────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act      = $_POST['action']   ?? '';
    $uid      = (int)($_POST['uid'] ?? 0);

    if ($act === 'save_user') {
        $nombre   = trim($_POST['nombre']    ?? '');
        $email    = trim($_POST['email']     ?? '') ?: null;
        $username = trim($_POST['username']  ?? '');
        $pass     = $_POST['pass']     ?? '';
        $perfil_id= (int)($_POST['perfil_id'] ?? 1);
        $activo   = isset($_POST['activo']) ? 1 : 0;

        if (!$nombre || !$username) $errors[] = 'Nombre y usuario son obligatorios.';
        if (strlen($username) < 3)  $errors[] = 'El usuario debe tener al menos 3 caracteres.';
        if (!$uid && strlen($pass) < 8) $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        if ($pass && strlen($pass) < 8) $errors[] = 'La nueva contraseña debe tener al menos 8 caracteres.';

        if (empty($errors)) {
            $n = $db->real_escape_string($nombre);
            $e = $email ? $db->real_escape_string($email) : null;
            $u = $db->real_escape_string($username);
            $e_val = $e ? "'$e'" : 'NULL';

            if ($uid > 0) {
                // Editar
                $hashSql = '';
                if ($pass) {
                    $h_val = $db->real_escape_string(password_hash($pass, PASSWORD_DEFAULT));
                    $hashSql = ", password_hash='$h_val'";
                }
                $ok = $db->query(
                    "UPDATE admin_usuarios SET nombre='$n', email=$e_val, username='$u',
                     perfil_id=$perfil_id, activo=$activo $hashSql WHERE id=$uid"
                );
            } else {
                // Nuevo
                $h_val = $db->real_escape_string(password_hash($pass, PASSWORD_DEFAULT));
                $ok = $db->query(
                    "INSERT INTO admin_usuarios (nombre, email, username, password_hash, perfil_id, activo)
                     VALUES ('$n', $e_val, '$u', '$h_val', $perfil_id, $activo)"
                );
            }
            if ($ok && !$db->error) {
                $saved = true;
                header('Location: usuarios.php?tab=usuarios&saved=1');
                exit;
            } else {
                $errors[] = str_contains($db->error ?? '', 'Duplicate') ? 'Ese nombre de usuario ya existe.' : ($db->error ?: 'Error al guardar.');
            }
        }
    }

    if ($act === 'toggle_activo' && $uid > 0 && $uid !== $me['id']) {
        $db->query("UPDATE admin_usuarios SET activo = NOT activo WHERE id=$uid");
        header('Location: usuarios.php?tab=usuarios'); exit;
    }

    if ($act === 'delete_user' && $uid > 0 && $uid !== $me['id']) {
        $db->query("DELETE FROM admin_usuarios WHERE id=$uid");
        header('Location: usuarios.php?tab=usuarios'); exit;
    }
}

/* ── Datos ─────────────────────────────────────────────────── */
$savedMsg  = isset($_GET['saved']);
$editUid   = (int)($_GET['edit'] ?? 0);
$editRow   = null;
$showForm  = isset($_GET['new']) || $editUid > 0;

if ($editUid > 0) {
    $editRow = $db->query("SELECT * FROM admin_usuarios WHERE id=$editUid LIMIT 1")->fetch_assoc();
}

$usuarios = fetchAll($db->query(
    "SELECT u.id, u.nombre, u.username, u.email, u.activo, u.ultimo_login, p.nombre AS perfil, p.slug AS perfil_slug
     FROM admin_usuarios u
     JOIN admin_perfiles p ON p.id = u.perfil_id
     ORDER BY u.id"
));

$perfiles = fetchAll($db->query("SELECT * FROM admin_perfiles ORDER BY id"));

$PERMISOS_LABELS = [
    '*'             => '🌟 Todo',
    'panel'         => 'Panel',
    'empleados'     => '👥 Empleados',
    'asistencia'    => '📅 Asistencia',
    'nomina'        => '📋 Nómina',
    'vacaciones'    => '🏖 Vacaciones',
    'finiquitos'    => '💼 Finiquitos',
    'reportes'      => '📊 Reportes',
    'configuracion' => '⚙️ Config',
    'usuarios'      => '👤 Usuarios',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Usuarios – Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#f8fafc;color:#1e293b;font-size:14px}
a{color:inherit;text-decoration:none}
.admin-header{background:#0284C7;color:#fff;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}.admin-brand{display:flex;align-items:center;gap:10px}.admin-brand img{height:36px}
.admin-header h1{font-size:18px;font-weight:800}
.nav-links{display:flex;gap:4px;align-items:center}
.nav-links a{padding:6px 12px;border-radius:6px;font-size:13px;color:rgba(255,255,255,.85);transition:background .15s}
.nav-links a:hover,.nav-links a.active{background:rgba(255,255,255,.2);color:#fff}
.body{padding:24px;max-width:1100px;margin:0 auto}
/* Tabs */
.tabs{display:flex;gap:2px;border-bottom:2px solid #e2e8f0;margin-bottom:24px}
.tab-btn{padding:10px 20px;border:none;background:none;cursor:pointer;font-size:14px;color:#64748b;border-bottom:2px solid transparent;margin-bottom:-2px;font-family:inherit}
.tab-btn.active{color:#0284C7;border-bottom-color:#0284C7;font-weight:600}
.tab-btn:hover:not(.active){color:#1e293b;background:#f1f5f9;border-radius:6px 6px 0 0}
/* Cards */
.card{background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.06);overflow:hidden;margin-bottom:20px}
.card-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #f1f5f9}
.card-head h3{font-size:15px;font-weight:700}
/* Table */
table{width:100%;border-collapse:collapse}
th{padding:10px 14px;text-align:left;font-size:12px;font-weight:600;color:#64748b;background:#f8fafc;border-bottom:1px solid #e2e8f0;white-space:nowrap}
td{padding:10px 14px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#f8fafc}
/* Form */
.form-wrap{padding:24px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.field{margin-bottom:0}
.field label{display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em}
.field input,.field select{width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:7px;font-size:14px;font-family:inherit;background:#fff;transition:border-color .15s}
.field input:focus,.field select:focus{outline:none;border-color:#0284C7;box-shadow:0 0 0 3px rgba(2,132,199,.1)}
.field-hint{font-size:11px;color:#94a3b8;margin-top:4px}
.check-row{display:flex;align-items:center;gap:8px;margin-top:8px}
.check-row input{width:auto}
/* Buttons */
.btn{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:7px;border:none;cursor:pointer;font-size:13px;font-family:inherit;font-weight:500;transition:background .15s}
.btn-primary{background:#0284C7;color:#fff}.btn-primary:hover{background:#0369a1}
.btn-outline{background:#fff;color:#475569;border:1px solid #cbd5e1}.btn-outline:hover{background:#f1f5f9}
.btn-danger{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}.btn-danger:hover{background:#fecaca}
.btn-sm{padding:4px 10px;font-size:12px}
.btn-grp{display:flex;gap:6px;flex-wrap:wrap}
/* Badges */
.badge{display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600}
.badge-green{background:#dcfce7;color:#15803d}
.badge-gray{background:#f1f5f9;color:#475569}
.badge-blue{background:#dbeafe;color:#1d4ed8}
/* Permisos tags */
.perm-tag{display:inline-block;background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe;padding:2px 7px;border-radius:5px;font-size:11px;margin:2px}
/* Alerts */
.alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:13px}
.alert-success{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0}
.alert-error{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}
.no-data{padding:48px;text-align:center;color:#94a3b8;font-size:13px}
.me-badge{font-size:10px;background:#fef9c3;color:#854d0e;padding:1px 5px;border-radius:4px;margin-left:4px}
</style>
</head>
<body>
<?php
$navTitle    = '👤 Usuarios';
$activeModule = 'usuarios';
require __DIR__ . '/_nav.php';
?>
<div class="body">

  <?php if ($savedMsg): ?>
    <div class="alert alert-success">✓ Usuario guardado correctamente.</div>
  <?php endif ?>
  <?php foreach ($errors as $err): ?>
    <div class="alert alert-error">✗ <?= h($err) ?></div>
  <?php endforeach ?>

  <div class="tabs">
    <button class="tab-btn <?= $tab==='usuarios'?'active':'' ?>" onclick="location.href='?tab=usuarios'">👥 Usuarios</button>
    <button class="tab-btn <?= $tab==='perfiles'?'active':'' ?>" onclick="location.href='?tab=perfiles'">🏷 Perfiles</button>
  </div>

  <?php if ($tab === 'usuarios'): ?>

  <!-- ═══ LISTA + FORMULARIO ════════════════════════════════════ -->
  <?php if ($showForm): ?>
  <div class="card" style="margin-bottom:20px">
    <div class="card-head">
      <h3><?= $editUid ? 'Editar usuario' : 'Nuevo usuario' ?></h3>
      <a href="?tab=usuarios" class="btn btn-outline btn-sm">✕ Cancelar</a>
    </div>
    <form method="post" class="form-wrap">
      <input type="hidden" name="action" value="save_user">
      <input type="hidden" name="uid" value="<?= $editUid ?>">
      <div class="form-grid" style="margin-bottom:16px">
        <div class="field">
          <label>Nombre completo</label>
          <input type="text" name="nombre" required value="<?= h($editRow['nombre'] ?? $_POST['nombre'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" value="<?= h($editRow['email'] ?? $_POST['email'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Nombre de usuario</label>
          <input type="text" name="username" required autocomplete="off"
                 value="<?= h($editRow['username'] ?? $_POST['username'] ?? '') ?>"
                 <?= ($editUid && $editUid === $me['id']) ? 'readonly' : '' ?>>
        </div>
        <div class="field">
          <label>Perfil</label>
          <select name="perfil_id">
            <?php foreach ($perfiles as $p):
              $sel = ((int)($editRow['perfil_id'] ?? $_POST['perfil_id'] ?? 1)) === (int)$p['id'] ? 'selected' : '';
              // Evitar que alguien se asigne super_admin si no es super_admin
              if ($p['slug'] === 'super_admin' && $me['slug'] !== 'super_admin') continue;
            ?>
            <option value="<?= $p['id'] ?>" <?= $sel ?>><?= h($p['nombre']) ?></option>
            <?php endforeach ?>
          </select>
        </div>
        <div class="field">
          <label><?= $editUid ? 'Nueva contraseña' : 'Contraseña' ?></label>
          <input type="password" name="pass" autocomplete="new-password"
                 <?= $editUid ? '' : 'required' ?> placeholder="<?= $editUid ? 'Dejar en blanco para no cambiar' : 'Mínimo 8 caracteres' ?>">
          <?php if ($editUid): ?><span class="field-hint">Dejar en blanco para mantener la actual.</span><?php endif ?>
        </div>
        <div class="field" style="display:flex;align-items:flex-end">
          <div class="check-row">
            <input type="checkbox" name="activo" id="activo" value="1"
                   <?= ($editRow ? (int)$editRow['activo'] : 1) ? 'checked' : '' ?>
                   <?= ($editUid && $editUid === $me['id']) ? 'disabled' : '' ?>>
            <label for="activo" style="text-transform:none;letter-spacing:0;font-size:14px">Usuario activo</label>
          </div>
        </div>
      </div>
      <button type="submit" class="btn btn-primary">Guardar usuario</button>
    </form>
  </div>
  <?php endif ?>

  <div class="card">
    <div class="card-head">
      <h3>Usuarios del sistema</h3>
      <?php if (!$showForm): ?>
      <a href="?tab=usuarios&new=1" class="btn btn-primary btn-sm">+ Nuevo usuario</a>
      <?php endif ?>
    </div>
    <?php if (empty($usuarios)): ?>
      <div class="no-data">No hay usuarios registrados.</div>
    <?php else: ?>
    <table>
      <thead><tr>
        <th>#</th><th>Nombre</th><th>Usuario</th><th>Email</th>
        <th>Perfil</th><th>Último acceso</th><th>Estado</th><th>Acciones</th>
      </tr></thead>
      <tbody>
      <?php foreach ($usuarios as $u): $isMe = (int)$u['id'] === $me['id']; ?>
        <tr>
          <td><?= $u['id'] ?></td>
          <td>
            <?= h($u['nombre']) ?>
            <?php if ($isMe): ?><span class="me-badge">Tú</span><?php endif ?>
          </td>
          <td style="font-family:monospace;font-size:13px"><?= h($u['username']) ?></td>
          <td><?= $u['email'] ? h($u['email']) : '<span style="color:#94a3b8">—</span>' ?></td>
          <td>
            <span class="badge <?= $u['perfil_slug']==='super_admin'?'badge-blue':'badge-gray' ?>">
              <?= h($u['perfil']) ?>
            </span>
          </td>
          <td style="font-size:12px;color:#64748b">
            <?= $u['ultimo_login'] ? date('d/m/Y H:i', strtotime($u['ultimo_login'])) : '—' ?>
          </td>
          <td>
            <span class="badge <?= $u['activo']?'badge-green':'badge-gray' ?>">
              <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
            </span>
          </td>
          <td>
            <div class="btn-grp">
              <a href="?tab=usuarios&edit=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Editar</a>
              <?php if (!$isMe): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('<?= $u['activo'] ? '¿Desactivar este usuario?' : '¿Activar este usuario?' ?>')">
                <input type="hidden" name="action" value="toggle_activo">
                <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm">
                  <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                </button>
              </form>
              <form method="post" style="display:inline" onsubmit="return confirm('¿Eliminar definitivamente este usuario?')">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
              </form>
              <?php endif ?>
            </div>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>

  <?php elseif ($tab === 'perfiles'): ?>

  <!-- ═══ PERFILES ══════════════════════════════════════════════ -->
  <div class="card">
    <div class="card-head">
      <h3>Perfiles del sistema</h3>
      <span style="font-size:12px;color:#94a3b8">Los perfiles predeterminados no se pueden editar</span>
    </div>
    <table>
      <thead><tr>
        <th>Perfil</th><th>Descripción</th><th>Módulos permitidos</th><th>Usuarios</th>
      </tr></thead>
      <tbody>
      <?php foreach ($perfiles as $p):
        $perms = json_decode($p['permisos'], true) ?? [];
        $cnt   = (int)$db->query("SELECT COUNT(*) FROM admin_usuarios WHERE perfil_id={$p['id']}")->fetch_row()[0];
      ?>
        <tr>
          <td>
            <strong><?= h($p['nombre']) ?></strong><br>
            <span style="font-size:11px;color:#94a3b8;font-family:monospace"><?= h($p['slug']) ?></span>
          </td>
          <td style="font-size:13px;color:#64748b"><?= h($p['descripcion'] ?? '') ?></td>
          <td>
            <?php foreach ($perms as $perm): ?>
            <span class="perm-tag"><?= h($PERMISOS_LABELS[$perm] ?? $perm) ?></span>
            <?php endforeach ?>
          </td>
          <td style="font-weight:700"><?= $cnt ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
  </div>

  <?php endif ?>
</div>
</body>
</html>
