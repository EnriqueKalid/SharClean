<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
requirePermiso('configuracion');
require_once __DIR__ . '/../db/csrf.php';
require_once __DIR__ . '/../db/cp_import.php';
require_once __DIR__ . '/../db/foto_upload.php';
$db = getAdminDB();

function fetchAll($result): array {
    $rows = [];
    if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}

// ── Guardar sección ───────────────────────────────────────────────────────
$saved  = false;
$errors = [];
$csrf   = generateCsrfToken();

// Importar catálogo de códigos postales (SEPOMEX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'importar_cp') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Token inválido.'); }
    $f = $_FILES['cp_file'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        $errors[] = ($f && in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE]))
            ? 'El archivo excede el tamaño máximo de subida del servidor. Suba el .zip en lugar del .txt.'
            : 'Seleccione el archivo descargado de SEPOMEX.';
    } else {
        $r = importarCatalogoCP($db, $f['tmp_name']);
        if ($r['ok']) {
            setAppConfig('cp_catalogo_importado', date('Y-m-d H:i'));
            header('Location: configuracion.php?tab=cp&cp_ok=' . $r['filas']);
            exit;
        }
        $errors[] = $r['error'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors) && !isset($_POST['accion'])) {
    $grupo = $db->real_escape_string($_POST['grupo'] ?? '');

    // Subida de logo: es público (se ve en el login), por eso va en images/brand/ y no en uploads/
    $logoGuardado = '';
    if (!empty($_FILES['logo_file']['name'])) {
        $r = procesarLogo($_FILES['logo_file'], __DIR__ . '/../images/brand');
        if ($r['ok']) {
            $logoGuardado = 'images/brand/' . $r['filename'];
            $anterior = appConfig('sistema_logo');
            $anteriorPath = __DIR__ . '/../images/brand/' . basename($anterior);
            if (str_starts_with($anterior, 'images/brand/') && is_file($anteriorPath)) unlink($anteriorPath);
        } else {
            $errors[] = $r['error'];
        }
    }

    if (empty($errors)) {
        // Cargar todos los campos del grupo
        $campos = fetchAll($db->query("SELECT clave, tipo FROM config WHERE grupo='$grupo'"));
        foreach ($campos as $c) {
            $clave = $c['clave'];
            if ($clave === 'sistema_logo' && $logoGuardado !== '') {
                $val = $logoGuardado;
            } elseif ($c['tipo'] === 'password' && trim($_POST[$clave] ?? '') === '') {
                continue;   // en blanco = conservar la contraseña actual
            } elseif ($clave === 'sistema_color' && !preg_match('/^#[0-9a-fA-F]{6}$/', trim($_POST[$clave] ?? ''))) {
                continue;   // solo colores hex válidos (se imprimen dentro de <style>)
            } elseif (isset($_POST[$clave])) {
                $val = trim($_POST[$clave]);
            } else {
                continue;
            }
            $k = $db->real_escape_string($clave);
            $v = $db->real_escape_string($val);
            $db->query("UPDATE config SET valor='$v' WHERE clave='$k'");
        }
        // Invalidar cache
        $saved = true;
        header('Location: configuracion.php?tab='.$grupo.'&saved=1');
        exit;
    }
}

// ── Cargar config ─────────────────────────────────────────────────────────
$tab = in_array($_GET['tab'] ?? '', ['empresa','correo','sistema','cp']) ? $_GET['tab'] : 'empresa';
if (isset($_POST['accion']) && $_POST['accion'] === 'importar_cp') $tab = 'cp';
$savedMsg = isset($_GET['saved']);

if ($tab === 'cp') {
    $res = $db->query('SELECT COUNT(*) AS n, COUNT(DISTINCT cp) AS cps FROM codigos_postales');
    $cpStats = $res ? $res->fetch_assoc() : null;   // null = tabla aún no migrada
}

$rows_empresa = fetchAll($db->query("SELECT * FROM config WHERE grupo='empresa' ORDER BY orden"));
$rows_correo  = fetchAll($db->query("SELECT * FROM config WHERE grupo='correo'  ORDER BY orden"));
$rows_sistema = fetchAll($db->query("SELECT * FROM config WHERE grupo='sistema' ORDER BY orden"));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Configuración — Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#f8fafc;color:#1e293b;font-size:14px}
a{color:inherit;text-decoration:none}
.body{padding:24px;max-width:860px;margin:0 auto}
/* Tabs */
.tabs{display:flex;gap:2px;border-bottom:2px solid #e2e8f0;margin-bottom:28px}
.tab-btn{padding:10px 20px;border:none;background:none;cursor:pointer;font-size:14px;color:#64748b;border-bottom:2px solid transparent;margin-bottom:-2px;font-family:inherit}
.tab-btn.active{color:#0284C7;border-bottom-color:#0284C7;font-weight:600}
.tab-btn:hover:not(.active){color:#1e293b;background:#f1f5f9;border-radius:6px 6px 0 0}
/* Formulario */
.card{background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.07);padding:28px 32px;margin-bottom:20px}
.card-title{font-size:16px;font-weight:700;margin-bottom:20px;color:#1e293b;padding-bottom:12px;border-bottom:1px solid #f1f5f9}
.field{margin-bottom:18px}
.field label{display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em}
.field input,.field textarea,.field select{width:100%;padding:9px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;font-family:inherit;background:#fff;transition:border-color .15s}
.field input:focus,.field textarea:focus,.field select:focus{outline:none;border-color:#0284C7;box-shadow:0 0 0 3px rgba(2,132,199,.1)}
.field textarea{resize:vertical;min-height:80px}
.field input[type=color]{height:42px;padding:4px 8px;cursor:pointer}
.field-hint{font-size:11px;color:#94a3b8;margin-top:4px}
.field-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;border:none;cursor:pointer;font-size:14px;font-family:inherit;font-weight:600}
.btn-primary{background:#0284C7;color:#fff}.btn-primary:hover{background:#0369a1}
/* Logo preview */
.logo-preview{display:flex;align-items:center;gap:16px;margin-bottom:16px}
.logo-preview img{max-height:56px;max-width:200px;border:1px solid #e2e8f0;border-radius:8px;padding:6px;background:#f8fafc}
.logo-preview span{font-size:12px;color:#64748b}
/* Alert */
.alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:13px}
.alert-success{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0}
.alert-error{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}
/* Password toggle */
.pass-wrap{position:relative}
.pass-wrap input{padding-right:44px}
.pass-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;font-size:18px;line-height:1}
</style>
<link rel="stylesheet" href="assets/admin.css?v=3">
</head>
<body>

<?php $navTitle = '⚙️ Configuración'; $activeModule = 'configuracion'; require __DIR__ . '/_nav.php'; ?>

<div class="body">

  <?php if ($savedMsg): ?>
    <div class="alert alert-success">✓ Configuración guardada correctamente.</div>
  <?php endif ?>
  <?php foreach ($errors as $e): ?>
    <div class="alert alert-error">✗ <?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endforeach ?>

  <div class="tabs">
    <button class="tab-btn <?= $tab==='empresa'?'active':'' ?>" onclick="location.href='?tab=empresa'">🏢 Empresa</button>
    <button class="tab-btn <?= $tab==='correo'?'active':'' ?>"  onclick="location.href='?tab=correo'">📧 Correo</button>
    <button class="tab-btn <?= $tab==='sistema'?'active':'' ?>" onclick="location.href='?tab=sistema'">🎨 Sistema</button>
    <button class="tab-btn <?= $tab==='cp'?'active':'' ?>"      onclick="location.href='?tab=cp'">📮 Códigos postales</button>
  </div>

  <?php
  function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

  function renderField(array $row): void {
    $clave = $row['clave'];
    $val   = (string)($row['valor'] ?? '');
    $lbl   = $row['etiqueta'];
    $tipo  = $row['tipo'];

    echo '<div class="field">';
    echo '<label for="'.h($clave).'">'.h($lbl).'</label>';

    if ($tipo === 'password') {
      echo '<div class="pass-wrap">';
      // Nunca se imprime la contraseña guardada en el HTML; en blanco = conservarla
      echo '<input type="password" id="'.h($clave).'" name="'.h($clave).'" value="" placeholder="'.($val !== '' ? '•••••••• (guardada)' : '').'" autocomplete="new-password">';
      echo '<button type="button" class="pass-toggle" onclick="togglePass(\''.h($clave).'\')">👁</button>';
      echo '</div>';
      echo '<span class="field-hint">Déjelo en blanco para no modificar la contraseña.</span>';
    } elseif ($tipo === 'textarea') {
      echo '<textarea id="'.h($clave).'" name="'.h($clave).'" rows="3">'.h($val).'</textarea>';
    } elseif ($tipo === 'color') {
      echo '<input type="color" id="'.h($clave).'" name="'.h($clave).'" value="'.h($val).'">';
    } else {
      echo '<input type="'.h($tipo).'" id="'.h($clave).'" name="'.h($clave).'" value="'.h($val).'">';
    }
    echo '</div>';
  }
  ?>

  <!-- ═══ EMPRESA ══════════════════════════════════════════════════════════ -->
  <?php if ($tab === 'empresa'): ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="grupo" value="empresa">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <div class="card">
      <div class="card-title">Identidad de la empresa</div>
      <div class="field-grid">
        <?php foreach ($rows_empresa as $r): if ($r['clave'] === 'empresa_direccion') continue; ?>
          <?php renderField($r) ?>
        <?php endforeach ?>
      </div>
      <?php foreach ($rows_empresa as $r): if ($r['clave'] !== 'empresa_direccion') continue; ?>
        <?php renderField($r) ?>
      <?php endforeach ?>
    </div>
    <div style="display:flex;justify-content:flex-end">
      <button type="submit" class="btn btn-primary">Guardar empresa</button>
    </div>
  </form>

  <!-- ═══ CORREO ═══════════════════════════════════════════════════════════ -->
  <?php elseif ($tab === 'correo'): ?>
  <form method="post">
    <input type="hidden" name="grupo" value="correo">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <div class="card">
      <div class="card-title">Correo electrónico</div>
      <p style="font-size:13px;color:#64748b;margin-bottom:20px;line-height:1.6">
        Estos valores controlan el remitente (<strong>From</strong>) de todos los correos automáticos
        que envía el sistema (notificaciones de contacto, cotizaciones, solicitudes de empleo).
      </p>
      <?php foreach ($rows_correo as $r): renderField($r); endforeach ?>
    </div>
    <div style="display:flex;justify-content:flex-end">
      <button type="submit" class="btn btn-primary">Guardar correo</button>
    </div>
  </form>

  <!-- ═══ SISTEMA ══════════════════════════════════════════════════════════ -->
  <?php elseif ($tab === 'sistema'): ?>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="grupo" value="sistema">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <div class="card">
      <div class="card-title">Apariencia y nombre del sistema</div>

      <?php
      $logoActual = appConfig('sistema_logo', 'images/logo.png');
      ?>
      <div class="field">
        <label>Logo actual</label>
        <div class="logo-preview">
          <img src="../<?= h($logoActual) ?>?t=<?= time() ?>" alt="Logo actual" id="logo-preview-img">
          <span><?= h($logoActual) ?></span>
        </div>
      </div>
      <div class="field">
        <label for="logo_file">Subir nuevo logo</label>
        <input type="file" id="logo_file" name="logo_file" accept=".png,.jpg,.jpeg,.webp"
               onchange="previewLogo(this)">
        <span class="field-hint">PNG, JPG o WEBP · Máx. 2 MB · Recomendado: fondo transparente</span>
      </div>

      <?php foreach ($rows_sistema as $r): if ($r['clave'] === 'sistema_logo') continue; renderField($r); endforeach ?>
    </div>
    <div style="display:flex;justify-content:flex-end">
      <button type="submit" class="btn btn-primary">Guardar sistema</button>
    </div>
  </form>

  <!-- ═══ CÓDIGOS POSTALES ═════════════════════════════════════════════════ -->
  <?php elseif ($tab === 'cp'): ?>
  <?php if (isset($_GET['cp_ok'])): ?>
    <div class="alert alert-success">✓ Catálogo importado: <?= number_format((int)$_GET['cp_ok']) ?> registros.</div>
  <?php endif ?>
  <div class="card">
    <div class="card-title">Catálogo Nacional de Códigos Postales</div>
    <?php if ($cpStats === null): ?>
      <div class="alert alert-error">La tabla <code>codigos_postales</code> no existe. Ejecute primero <code>db/migrate_codigos_postales_v1.sql</code>.</div>
    <?php else: ?>
      <p style="margin-bottom:6px">
        <strong><?= number_format((int)$cpStats['n']) ?></strong> asentamientos en
        <strong><?= number_format((int)$cpStats['cps']) ?></strong> códigos postales.
      </p>
      <p class="field-hint" style="margin-bottom:20px">
        Última importación: <?= appConfig('cp_catalogo_importado') !== '' ? h(appConfig('cp_catalogo_importado')) : 'nunca' ?>.
        Se usa para autollenar Colonia, Ciudad y Estado al capturar el C.P. de un empleado.
      </p>
    <?php endif ?>

    <form method="post" enctype="multipart/form-data"
          onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Importando… (puede tardar un minuto)'">
      <input type="hidden" name="accion" value="importar_cp">
      <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
      <div class="field">
        <label for="cp_file">Archivo de SEPOMEX</label>
        <input type="file" id="cp_file" name="cp_file" accept=".zip,.txt" required>
        <span class="field-hint">
          Descárguelo en <a href="https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/CodigoPostal_Exportar.aspx"
          target="_blank" rel="noopener" style="color:#0284C7;text-decoration:underline">Correos de México</a>
          eligiendo <em>Todos</em> los estados y formato <em>TXT</em>. Suba el .zip tal cual o el CPdescarga.txt.
          La importación reemplaza el catálogo completo.
        </span>
      </div>
      <div style="display:flex;justify-content:flex-end">
        <button type="submit" class="btn btn-primary" <?= $cpStats === null ? 'disabled' : '' ?>>Importar catálogo</button>
      </div>
    </form>
  </div>
  <?php endif ?>

</div>

<script>
function togglePass(id) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
}
function previewLogo(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = e => document.getElementById('logo-preview-img').src = e.target.result;
    reader.readAsDataURL(input.files[0]);
  }
}
</script>
</body>
</html>
