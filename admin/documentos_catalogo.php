<?php
/**
 * Sharclean Admin – Catálogo de documentos del expediente del empleado
 * Define qué documentos se piden, en qué categoría y cuáles son obligatorios.
 */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
requirePermiso('empleados');
require_once __DIR__ . '/../db/expediente.php';

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$db   = getAdminDB();
$csrf = generateCsrfToken();

if (!expedienteDisponible($db)) {
    $sinMigrar = true;
} else {
    $sinMigrar = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $act = $_POST['action'] ?? '';
        $id  = (int)($_POST['id'] ?? 0);
        $msg = '';

        if ($act === 'save') {
            $nombre = trim($_POST['nombre'] ?? '');
            $cat    = trim($_POST['categoria'] ?? '') ?: 'Otros';
            $desc   = trim($_POST['descripcion'] ?? '');
            $obl    = isset($_POST['obligatorio']) ? 1 : 0;
            $orden  = (int)($_POST['orden'] ?? 0);
            if ($nombre === '' || strlen($nombre) > 120 || strlen($cat) > 60 || strlen($desc) > 255) {
                $msg = 'err:Revise los datos: el nombre es obligatorio (máx. 120 caracteres).';
            } else {
                $n = $db->real_escape_string($nombre);
                $c = $db->real_escape_string($cat);
                $d = $desc !== '' ? "'" . $db->real_escape_string($desc) . "'" : 'NULL';
                $db->query($id > 0
                    ? "UPDATE documento_tipos SET nombre='$n', categoria='$c', descripcion=$d, obligatorio=$obl, orden=$orden WHERE id=$id"
                    : "INSERT INTO documento_tipos (nombre, categoria, descripcion, obligatorio, orden) VALUES ('$n','$c',$d,$obl,$orden)");
                $msg = 'ok:Documento guardado.';
            }
        } elseif ($act === 'toggle' && $id > 0) {
            $db->query("UPDATE documento_tipos SET activo = NOT activo WHERE id=$id");
            $msg = 'ok:Estado actualizado.';
        } elseif ($act === 'delete' && $id > 0) {
            $enUso = (int)$db->query("SELECT COUNT(*) FROM empleado_documentos WHERE tipo_id=$id")->fetch_row()[0];
            if ($enUso > 0) {
                $msg = "err:No se puede eliminar: hay $enUso archivo(s) cargados con este tipo. Desactívelo en su lugar.";
            } else {
                $db->query("DELETE FROM documento_tipos WHERE id=$id");
                $msg = 'ok:Documento eliminado del catálogo.';
            }
        }
        $_SESSION['cat_flash'] = $msg;
        header('Location: documentos_catalogo.php');
        exit;
    }

    $tipos = expedienteTipos($db, false);
    $usos  = [];
    $res   = $db->query('SELECT tipo_id, COUNT(*) AS n FROM empleado_documentos WHERE tipo_id IS NOT NULL GROUP BY tipo_id');
    if ($res) while ($r = $res->fetch_assoc()) $usos[(int)$r['tipo_id']] = (int)$r['n'];

    $porCat = [];
    foreach ($tipos as $t) $porCat[$t['categoria']][] = $t;
    $categorias = array_values(array_unique(array_merge(EXPEDIENTE_CATEGORIAS, array_keys($porCat))));
}

$flash = $_SESSION['cat_flash'] ?? '';
unset($_SESSION['cat_flash']);
[$flashTipo, $flashMsg] = $flash !== '' ? explode(':', $flash, 2) : ['', ''];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Catálogo de documentos — Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-size:14px}
a{color:inherit;text-decoration:none}
.body{padding:28px;max-width:1000px;margin:0 auto}
.head-row{display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap}
.head-row p{color:#64748b;font-size:13px;flex:1;min-width:240px}
.card{background:#fff;border:1px solid #e6e9f0;border-radius:14px;margin-bottom:18px;overflow:hidden}
.card-head{padding:14px 18px;border-bottom:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between}
.card-head h3{font-size:14px;font-weight:700}
.card-head span{font-size:12px;color:#94a3b8}
.row{display:flex;align-items:center;gap:12px;padding:12px 18px;border-bottom:1px solid #f1f5f9}
.row:last-child{border-bottom:none}
.row.off{opacity:.5}
.row-main{flex:1;min-width:0}
.row-main strong{font-weight:600}
.row-main small{display:block;color:#94a3b8;font-size:12px;margin-top:2px}
.badge{display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600;margin-left:6px;vertical-align:1px}
.b-req{background:#fee2e2;color:#b91c1c}
.b-opt{background:#f1f5f9;color:#64748b}
.b-off{background:#f1f5f9;color:#94a3b8}
.uses{font-size:12px;color:#64748b;white-space:nowrap}
.actions{display:flex;gap:6px;align-items:center}
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:9px;border:1px solid transparent;cursor:pointer;font:inherit;font-size:13px;font-weight:600;background:none;white-space:nowrap}
.btn-primary{background:var(--brand);color:#fff}.btn-primary:hover{filter:brightness(.92)}
.btn-ghost{border-color:#e2e8f0;color:#475569;background:#fff}.btn-ghost:hover{background:#f8fafc}
.btn-danger{color:#b91c1c;border-color:#fecaca;background:#fff}.btn-danger:hover{background:#fef2f2}
.btn-sm{padding:5px 10px;font-size:12px}
details.edit summary{list-style:none;cursor:pointer}
details.edit summary::-webkit-details-marker{display:none}
.edit-form{display:grid;grid-template-columns:2fr 1.4fr 80px;gap:10px;padding:14px 18px;background:#f8fafc;border-bottom:1px solid #f1f5f9}
.edit-form .full{grid-column:1/-1}
.edit-form label{display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:4px;text-transform:uppercase;letter-spacing:.04em}
.edit-form input[type=text],.edit-form input[type=number]{width:100%;padding:8px 10px;border:1px solid #cbd5e1;border-radius:8px;font:inherit;font-size:13px;background:#fff}
.edit-form input:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)}
.edit-form .chk{display:flex;align-items:center;gap:8px;font-size:13px}
.edit-form .foot{grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px}
.alert{padding:12px 16px;border-radius:10px;margin-bottom:18px;font-size:13px}
.alert-ok{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0}
.alert-err{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}
@media (max-width:640px){.edit-form{grid-template-columns:1fr}.row{flex-wrap:wrap}.actions{width:100%;justify-content:flex-end}}
</style>
<link rel="stylesheet" href="assets/admin.css?v=3">
</head>
<body>
<?php $navTitle = 'Catálogo de documentos'; $activeModule = 'empleados'; require __DIR__ . '/_nav.php'; ?>

<div class="body">
  <?php if ($sinMigrar): ?>
    <div class="alert alert-err">Ejecute primero <code>db/migrate_expediente_v1.sql</code> para habilitar el expediente digital.</div>
  <?php else: ?>

  <?php if ($flashMsg !== ''): ?>
    <div class="alert <?= $flashTipo === 'ok' ? 'alert-ok' : 'alert-err' ?>"><?= h($flashMsg) ?></div>
  <?php endif ?>

  <div class="head-row">
    <a href="empleados.php" class="btn btn-ghost">← Empleados</a>
    <p>Los documentos <strong>obligatorios</strong> cuentan para el avance del expediente de cada empleado.
       Desactivar un tipo lo oculta de los expedientes sin borrar los archivos ya cargados.</p>
  </div>

  <datalist id="cats"><?php foreach ($categorias as $c): ?><option value="<?= h($c) ?>"><?php endforeach ?></datalist>

  <!-- Nuevo tipo -->
  <div class="card">
    <details class="edit">
      <summary class="card-head"><h3>+ Agregar documento al catálogo</h3><span>clic para abrir</span></summary>
      <?php $t = ['id' => 0, 'nombre' => '', 'categoria' => 'Otros', 'descripcion' => '', 'obligatorio' => 0, 'orden' => 0]; include __DIR__ . '/_documento_tipo_form.php'; ?>
    </details>
  </div>

  <?php foreach ($categorias as $cat): if (empty($porCat[$cat])) continue; ?>
  <div class="card">
    <div class="card-head">
      <h3><?= h($cat) ?></h3>
      <span><?= count(array_filter($porCat[$cat], fn($x) => $x['obligatorio'] && $x['activo'])) ?> obligatorio(s)</span>
    </div>
    <?php foreach ($porCat[$cat] as $t): $n = $usos[(int)$t['id']] ?? 0; ?>
    <details class="edit">
      <summary class="row<?= $t['activo'] ? '' : ' off' ?>">
        <div class="row-main">
          <strong><?= h($t['nombre']) ?></strong>
          <?php if (!$t['activo']): ?><span class="badge b-off">Inactivo</span>
          <?php elseif ($t['obligatorio']): ?><span class="badge b-req">Obligatorio</span>
          <?php else: ?><span class="badge b-opt">Opcional</span><?php endif ?>
          <?php if ($t['descripcion']): ?><small><?= h($t['descripcion']) ?></small><?php endif ?>
        </div>
        <span class="uses"><?= $n ?> archivo(s)</span>
        <span class="btn btn-ghost btn-sm">Editar</span>
      </summary>
      <?php include __DIR__ . '/_documento_tipo_form.php'; ?>
      <div class="row" style="justify-content:flex-end;background:#f8fafc">
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="toggle">
          <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
          <button type="submit" class="btn btn-ghost btn-sm"><?= $t['activo'] ? 'Desactivar' : 'Activar' ?></button>
        </form>
        <?php if ($n === 0): ?>
        <form method="post" onsubmit="return confirm('¿Eliminar este documento del catálogo?')">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
          <button type="submit" class="btn btn-danger btn-sm">Eliminar</button>
        </form>
        <?php endif ?>
      </div>
    </details>
    <?php endforeach ?>
  </div>
  <?php endforeach ?>

  <?php endif ?>
</div>
</body>
</html>
