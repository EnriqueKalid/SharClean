<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requirePermiso('panel');
require_once __DIR__ . '/../db/csrf.php';

/* ── Helpers ──────────────────────────────────────────────── */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fdate(string $d): string { return $d ? date('d/m/Y H:i', strtotime($d)) : '—'; }

function cbadge(int $leido): string {
    return $leido ? '<span class="badge b-read">Leído</span>' : '<span class="badge b-new">Nuevo</span>';
}
function qbadge(string $s): string {
    $m = ['pending'=>['b-pending','Pendiente'],'contacted'=>['b-contacted','Contactado'],
          'quoted'=>['b-quoted','Cotizado'],'closed'=>['b-closed','Cerrado']];
    [$c,$l] = $m[$s] ?? ['b-read',$s];
    return "<span class='badge $c'>$l</span>";
}
function abadge(string $s): string {
    $m = ['recibida'=>['b-new','Recibida'],'revisando'=>['b-contacted','Revisando'],
          'entrevista'=>['b-quoted','Entrevista'],'contratado'=>['b-ok','Contratado'],'descartado'=>['b-del','Descartado']];
    [$c,$l] = $m[$s] ?? ['b-read',$s];
    return "<span class='badge $c'>$l</span>";
}
function vbadge(int $activa): string {
    return $activa
        ? '<span class="badge b-ok">Activa</span>'
        : '<span class="badge b-read">Pausada</span>';
}
function canal_badge(string $s): string {
    $m = ['whatsapp'=>['b-ok','💬 WhatsApp'],'web'=>['b-new','🌐 Web'],
          'referido'=>['b-quoted','👥 Referido'],'otro'=>['b-read','Otro']];
    [$c,$l] = $m[$s] ?? ['b-read', $s ?: 'Web'];
    return "<span class='badge $c'>$l</span>";
}
function fetchAll($result): array {
    $rows = [];
    if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}
function publicSiteUrl(string $path): string {
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'sharclean.com.mx';
    $adminDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'));
    $siteRoot = rtrim(preg_replace('#/admin$#', '', $adminDir), '/');
    return $scheme . '://' . $host . $siteRoot . '/' . ltrim($path, '/');
}

/* ── Handle POST actions ──────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Token inválido.'); }
    $db  = getAdminDB();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    $tab = in_array($_POST['tab'] ?? '', ['contactos','cotizaciones','aplicaciones','vacantes'])
           ? $_POST['tab'] : 'contactos';

    if ($act === 'add_apply_manual') {
        $nombre     = trim($_POST['nombre']         ?? '');
        $telefono   = trim($_POST['telefono']       ?? '');
        $email      = trim($_POST['email']          ?? '') ?: null;
        $edad       = (int)($_POST['edad']          ?? 0) ?: null;
        $puesto     = trim($_POST['puesto_interes'] ?? '');
        $vacante_id = (int)($_POST['vacante_id']    ?? 0) ?: null;
        $exp        = trim($_POST['experiencia']    ?? '');
        $canal      = in_array($_POST['canal'] ?? '', ['whatsapp','web','referido','otro'])
                      ? $_POST['canal'] : 'whatsapp';
        $estado     = in_array($_POST['estado'] ?? '', ['recibida','revisando','entrevista','contratado','descartado'])
                      ? $_POST['estado'] : 'recibida';
        if ($nombre !== '' && $telefono !== '' && $puesto !== '') {
            $s = $db->prepare(
                'INSERT INTO aplicaciones_empleo (nombre,edad,telefono,email,puesto_interes,vacante_id,experiencia,canal,estado)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $s->bind_param('sisssisss', $nombre,$edad,$telefono,$email,$puesto,$vacante_id,$exp,$canal,$estado);
            $s->execute();
        }
    } elseif ($act === 'save_vacante') {
        $titulo    = trim($_POST['titulo']    ?? '');
        $area      = trim($_POST['area']      ?? 'Limpieza');
        $tipo      = implode(',', array_filter(array_map('trim', (array)($_POST['tipo'] ?? []))));
        if ($tipo === '') $tipo = 'Matutino';
        $desc      = trim($_POST['descripcion'] ?? '');
        $req       = trim($_POST['requisitos'] ?? '');
        $ubicacion = trim($_POST['ubicacion'] ?? 'CDMX / EDOMEX');
        $maps_url  = trim($_POST['maps_url']  ?? '');
        $form_url  = trim($_POST['form_url']  ?? '');
        $sal_min   = trim($_POST['rango_salarial_min'] ?? '');
        $sal_max   = trim($_POST['rango_salarial_max'] ?? '');
        $sal_min   = ($sal_min !== '' && ctype_digit($sal_min)) ? (int) $sal_min : null;
        $sal_max   = ($sal_max !== '' && ctype_digit($sal_max)) ? (int) $sal_max : null;
        if ($sal_min !== null && $sal_max !== null) {
            $rango_sal = '$' . number_format($sal_min) . ' - $' . number_format($sal_max);
        } elseif ($sal_min !== null) {
            $rango_sal = 'Desde $' . number_format($sal_min);
        } elseif ($sal_max !== null) {
            $rango_sal = 'Hasta $' . number_format($sal_max);
        } else {
            $rango_sal = '';
        }
        $beneficios = trim($_POST['beneficios'] ?? '');
        $activa    = isset($_POST['activa']) ? 1 : 0;
        if ($titulo !== '' && $form_url !== '') {
            if ($id > 0) {
                $s = $db->prepare('UPDATE vacantes SET titulo=?,area=?,tipo=?,descripcion=?,requisitos=?,ubicacion=?,maps_url=?,form_url=?,rango_salarial=?,beneficios=?,activa=? WHERE id=?');
                $s->bind_param('ssssssssssii', $titulo,$area,$tipo,$desc,$req,$ubicacion,$maps_url,$form_url,$rango_sal,$beneficios,$activa,$id);
            } else {
                $s = $db->prepare('INSERT INTO vacantes (titulo,area,tipo,descripcion,requisitos,ubicacion,maps_url,form_url,rango_salarial,beneficios,activa) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                $s->bind_param('ssssssssssi', $titulo,$area,$tipo,$desc,$req,$ubicacion,$maps_url,$form_url,$rango_sal,$beneficios,$activa);
            }
            $s->execute();
        }
    } elseif ($id > 0) {
        if ($act === 'mark_read') {
            $s = $db->prepare('UPDATE contactos SET leido=1 WHERE id=?'); $s->bind_param('i',$id); $s->execute();
        } elseif ($act === 'mark_unread') {
            $s = $db->prepare('UPDATE contactos SET leido=0 WHERE id=?'); $s->bind_param('i',$id); $s->execute();
        } elseif ($act === 'del_contact') {
            $s = $db->prepare('DELETE FROM contactos WHERE id=?'); $s->bind_param('i',$id); $s->execute();
        } elseif ($act === 'upd_quote') {
            $st = $_POST['status'] ?? '';
            if (in_array($st, ['pending','contacted','quoted','closed'])) {
                $s = $db->prepare('UPDATE cotizaciones SET estado=? WHERE id=?'); $s->bind_param('si',$st,$id); $s->execute();
            }
        } elseif ($act === 'upd_apply') {
            $st = $_POST['status'] ?? '';
            if (in_array($st, ['recibida','revisando','entrevista','contratado','descartado'])) {
                $s = $db->prepare('UPDATE aplicaciones_empleo SET estado=? WHERE id=?'); $s->bind_param('si',$st,$id); $s->execute();
            }
        } elseif ($act === 'toggle_vacante') {
            $s = $db->prepare('UPDATE vacantes SET activa = NOT activa WHERE id=?'); $s->bind_param('i',$id); $s->execute();
        } elseif ($act === 'del_vacante') {
            $s = $db->prepare('DELETE FROM vacantes WHERE id=?'); $s->bind_param('i',$id); $s->execute();
        }
    }
    $db->close();
    header("Location: index.php?tab=$tab"); exit;
}

/* ── Fetch data ───────────────────────────────────────────── */
$tab  = in_array($_GET['tab'] ?? '', ['contactos','cotizaciones','aplicaciones','vacantes'])
        ? $_GET['tab'] : 'contactos';
$db   = getAdminDB();
$csrf = generateCsrfToken();

$f_estado = in_array($_GET['f_estado'] ?? '', ['recibida','revisando','entrevista','contratado','descartado'])
            ? $_GET['f_estado'] : '';
$f_canal  = in_array($_GET['f_canal'] ?? '', ['web','whatsapp','referido','otro'])
            ? $_GET['f_canal'] : '';

$cUnread   = (int)$db->query('SELECT COUNT(*) FROM contactos WHERE leido=0')->fetch_row()[0];
$cPending  = (int)$db->query("SELECT COUNT(*) FROM cotizaciones WHERE estado='pending'")->fetch_row()[0];
$cNew      = (int)$db->query("SELECT COUNT(*) FROM aplicaciones_empleo WHERE estado='recibida'")->fetch_row()[0];
$cActivas  = (int)$db->query("SELECT COUNT(*) FROM vacantes WHERE activa=1")->fetch_row()[0];
$cTotal    = [
    'contactos'    => (int)$db->query('SELECT COUNT(*) FROM contactos')->fetch_row()[0],
    'cotizaciones' => (int)$db->query('SELECT COUNT(*) FROM cotizaciones')->fetch_row()[0],
    'aplicaciones' => (int)$db->query('SELECT COUNT(*) FROM aplicaciones_empleo')->fetch_row()[0],
    'vacantes'     => (int)$db->query('SELECT COUNT(*) FROM vacantes')->fetch_row()[0],
];

if ($tab === 'aplicaciones' && ($f_estado || $f_canal)) {
    $whereParts = [];
    if ($f_estado) $whereParts[] = "estado='" . $db->real_escape_string($f_estado) . "'";
    if ($f_canal)  $whereParts[] = "canal='"  . $db->real_escape_string($f_canal)  . "'";
    $sql  = 'SELECT * FROM aplicaciones_empleo WHERE ' . implode(' AND ', $whereParts) . ' ORDER BY fecha_creacion DESC LIMIT 200';
    $rows = fetchAll($db->query($sql));
} else {
    $rows = match($tab) {
        'cotizaciones' => fetchAll($db->query('SELECT * FROM cotizaciones ORDER BY fecha_creacion DESC LIMIT 200')),
        'aplicaciones' => fetchAll($db->query('SELECT * FROM aplicaciones_empleo ORDER BY fecha_creacion DESC LIMIT 200')),
        'vacantes'     => fetchAll($db->query('SELECT * FROM vacantes ORDER BY activa DESC, fecha_creacion DESC LIMIT 200')),
        default        => fetchAll($db->query('SELECT * FROM contactos ORDER BY leido ASC, fecha_creacion DESC LIMIT 200')),
    };
}

/* Vacante a editar (si aplica) */
$editVacante = null;
$editSalMin = '';
$editSalMax = '';
if ($tab === 'vacantes' && isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $res = $db->query("SELECT * FROM vacantes WHERE id=$eid LIMIT 1");
    if ($res) $editVacante = $res->fetch_assoc();
    if ($editVacante) {
        preg_match_all('/[\d,]+/', $editVacante['rango_salarial'] ?? '', $salNums);
        $salNums   = array_map(fn($n) => (int) str_replace(',', '', $n), $salNums[0] ?? []);
        $editSalMin = $salNums[0] ?? '';
        $editSalMax = $salNums[1] ?? '';
    }
}

/* Vacantes activas para el selector del formulario de candidatos */
$vacantesOpts = fetchAll($db->query('SELECT id, titulo FROM vacantes WHERE activa=1 ORDER BY titulo'));

$db->close();

/* ── Mini-form helper ─────────────────────────────────────── */
function actionForm(string $action, int $id, string $tab, string $csrf, string $btnClass, string $btnLabel, string $extra = ''): string {
    return "<form method='post' style='display:inline;'>
      <input type='hidden' name='csrf_token' value='" . h($csrf) . "'>
      <input type='hidden' name='action' value='$action'>
      <input type='hidden' name='id' value='$id'>
      <input type='hidden' name='tab' value='$tab'>
      $extra
      <button type='submit' class='btn-sm $btnClass'>$btnLabel</button>
    </form>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SISIAdmin – Sharclean</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f1f5f9;color:#1e293b;font-size:14px;}
a{color:inherit;text-decoration:none;}
.admin-header{background:#0284C7;color:#fff;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10;}.admin-brand{display:flex;align-items:center;gap:10px}.admin-brand img{height:36px}
.admin-header h1{font-size:18px;font-weight:800;}
.nav-links{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.nav-links a{color:rgba(255,255,255,.85);font-size:13px;padding:6px 12px;border:1px solid rgba(255,255,255,.3);border-radius:6px;white-space:nowrap;transition:.15s}
.nav-links a:hover{background:rgba(255,255,255,.15)}
.nav-links a.active{background:rgba(255,255,255,.95);color:#0284C7;font-weight:700;border-color:transparent}
.body{max-width:1280px;margin:0 auto;padding:24px 16px;}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.stat{background:#fff;border-radius:10px;padding:20px 24px;box-shadow:0 1px 3px rgba(0,0,0,.07);}
.stat-val{font-size:34px;font-weight:800;color:#0284C7;}
.stat-lbl{font-size:13px;color:#64748b;margin-top:4px;}
.stat-sub{font-size:12px;color:#94a3b8;margin-top:2px;}
.tabs{display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap;}
.tab-link{padding:8px 18px;border-radius:8px;color:#64748b;background:#fff;border:1px solid #e2e8f0;font-weight:500;font-size:13px;transition:all .15s;}
.tab-link:hover{border-color:#0284C7;color:#0284C7;}
.tab-link.active{background:#0284C7;color:#fff;border-color:#0284C7;}
.cnt{background:#ef4444;color:#fff;border-radius:9999px;padding:1px 7px;font-size:11px;font-weight:700;margin-left:6px;}
.card{background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.07);overflow:hidden;}
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th{background:#f8fafc;padding:10px 14px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap;}
td{padding:10px 14px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#f8fafc;}
.badge{display:inline-block;padding:3px 9px;border-radius:5px;font-size:11px;font-weight:700;}
.b-new{background:#dbeafe;color:#1d4ed8;}
.b-read{background:#f1f5f9;color:#94a3b8;}
.b-pending{background:#fef9c3;color:#854d0e;}
.b-contacted{background:#ffedd5;color:#9a3412;}
.b-quoted{background:#f3e8ff;color:#7e22ce;}
.b-closed{background:#f1f5f9;color:#94a3b8;}
.b-ok{background:#dcfce7;color:#166534;}
.b-del{background:#fee2e2;color:#991b1b;}
.btn-sm{padding:5px 10px;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;border:none;white-space:nowrap;}
.btn-primary{background:#0284C7;color:#fff;}
.btn-muted{background:#e2e8f0;color:#475569;}
.btn-danger{background:#fee2e2;color:#dc2626;}
.btn-danger:hover{background:#fecaca;}
select.sel-sm{padding:5px 8px;border-radius:5px;border:1px solid #e2e8f0;font-size:12px;background:#fff;margin-right:4px;}
.empty{padding:56px;text-align:center;color:#94a3b8;font-size:15px;}
.msg-preview{max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#475569;}
/* ── Vacantes form ──────────────────────────────────────── */
.vac-header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #f1f5f9;}
.vac-form-wrap{padding:20px 18px;border-bottom:2px solid #e2e8f0;background:#f8fafc;}
.vac-form .vf-title{font-size:15px;font-weight:700;color:#1e293b;margin-bottom:16px;}
.vf-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.vf-field label{display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:5px;}
.vf-field input[type=text],.vf-field select,.vf-field textarea{width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px;font-family:inherit;outline:none;transition:border-color .15s;background:#fff;}
.vf-field input[type=text]:focus,.vf-field select:focus,.vf-field textarea:focus{border-color:#0284C7;}
.vf-field textarea{resize:vertical;}
.vf-span2{grid-column:span 2;}
.vf-check{grid-column:span 2;display:flex;align-items:center;}
.vf-check label{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:400;color:#374151;cursor:pointer;margin:0;}
.vf-actions{display:flex;gap:10px;margin-top:16px;align-items:center;}
.vf-actions .btn-sm{text-decoration:none;display:inline-block;}
.msel{position:relative;}
.msel-control{display:flex;align-items:center;justify-content:space-between;gap:6px;width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px;background:#fff;cursor:pointer;user-select:none;}
.msel-control:focus{outline:none;border-color:#0284C7;}
.msel.open .msel-control{border-color:#0284C7;}
.msel-text{color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.msel-text.placeholder{color:#94a3b8;}
.msel-arrow{color:#94a3b8;font-size:11px;transition:transform .15s;flex-shrink:0;}
.msel.open .msel-arrow{transform:rotate(180deg);}
.msel-menu{display:none;position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1.5px solid #e2e8f0;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.1);padding:6px;z-index:20;}
.msel.open .msel-menu{display:block;}
.msel-opt{display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:5px;font-size:13px;font-weight:400;color:#374151;cursor:pointer;}
.msel-opt:hover{background:#f1f5f9;}
.link-menu{display:none;position:absolute;top:calc(100% + 4px);right:0;background:#fff;border:1.5px solid #e2e8f0;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.12);padding:10px;z-index:30;width:320px;}
.link-menu-wrap.open .link-menu{display:block;}
.link-menu__item{margin-bottom:10px;}
.link-menu__item:last-child{margin-bottom:0;}
.link-menu__item label{display:block;font-size:11px;font-weight:600;color:#64748b;margin-bottom:4px;}
.link-menu__row{display:flex;gap:6px;}
.link-menu__row input{flex:1;min-width:0;padding:6px 8px;border:1.5px solid #e2e8f0;border-radius:5px;font-size:11px;color:#334155;background:#f8fafc;}
.link-menu__row button{flex-shrink:0;padding:6px 10px;border:1.5px solid #0284C7;border-radius:5px;font-size:11px;font-weight:600;color:#0284C7;background:#fff;cursor:pointer;}
.link-menu__row button:hover{background:#0284C7;color:#fff;}
.acciones-row{display:flex;flex-wrap:wrap;gap:4px;align-items:center;}
.acciones-row .btn-sm{padding:4px 7px;font-size:11px;white-space:nowrap;}
@media(max-width:640px){.stats{grid-template-columns:repeat(2,1fr);}.vf-grid{grid-template-columns:1fr;}.vf-span2,.vf-check{grid-column:1;}}
.btn-success{background:#dcfce7;color:#166534;}
.btn-success:hover{background:#bbf7d0;}
.filter-bar{display:flex;gap:8px;align-items:center;padding:10px 18px;background:#f8fafc;border-bottom:1px solid #e2e8f0;flex-wrap:wrap;}
.filter-bar label{font-size:12px;font-weight:600;color:#64748b;white-space:nowrap;}
.filter-bar select{padding:5px 8px;border-radius:5px;border:1px solid #e2e8f0;font-size:12px;background:#fff;}
</style>
</head>
<body>

<?php $navTitle = 'Panel de Control'; $activeModule = 'panel'; require __DIR__ . '/_nav.php'; ?>

<div class="body">

  <!-- Stats -->
  <div class="stats">
    <div class="stat">
      <div class="stat-val"><?= $cUnread ?></div>
      <div class="stat-lbl">Mensajes sin leer</div>
      <div class="stat-sub"><?= $cTotal['contactos'] ?> total</div>
    </div>
    <div class="stat">
      <div class="stat-val"><?= $cPending ?></div>
      <div class="stat-lbl">Cotizaciones pendientes</div>
      <div class="stat-sub"><?= $cTotal['cotizaciones'] ?> total</div>
    </div>
    <div class="stat">
      <div class="stat-val"><?= $cNew ?></div>
      <div class="stat-lbl">Aplicaciones nuevas</div>
      <div class="stat-sub"><?= $cTotal['aplicaciones'] ?> total</div>
    </div>
    <div class="stat">
      <div class="stat-val"><?= $cActivas ?></div>
      <div class="stat-lbl">Vacantes activas</div>
      <div class="stat-sub"><?= $cTotal['vacantes'] ?> total</div>
    </div>
  </div>

  <!-- Tabs -->
  <div class="tabs">
    <a href="?tab=contactos"    class="tab-link <?= $tab==='contactos'    ? 'active' : '' ?>">
      Contactos <?= $cUnread  ? "<span class='cnt'>$cUnread</span>"  : '' ?>
    </a>
    <a href="?tab=cotizaciones" class="tab-link <?= $tab==='cotizaciones' ? 'active' : '' ?>">
      Cotizaciones <?= $cPending ? "<span class='cnt'>$cPending</span>" : '' ?>
    </a>
    <a href="?tab=aplicaciones" class="tab-link <?= $tab==='aplicaciones' ? 'active' : '' ?>">
      Aplicaciones <?= $cNew    ? "<span class='cnt'>$cNew</span>"    : '' ?>
    </a>
    <a href="?tab=vacantes" class="tab-link <?= $tab==='vacantes' ? 'active' : '' ?>">
      Vacantes
    </a>
    <a href="empleados.php" class="tab-link" style="border-color:#7c3aed;color:#7c3aed;">
      👥 Empleados
    </a>
  </div>

  <!-- Table card -->
  <div class="card">
    <?php if (empty($rows)): ?>
      <div class="empty">No hay registros todavía.</div>

    <?php elseif ($tab === 'contactos'): ?>
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>#</th><th>Nombre</th><th>Email</th><th>IP</th>
            <th>Mensaje</th><th>Fecha</th><th>Estado</th><th>Acciones</th>
          </tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= $r['id'] ?></td>
              <td><strong><?= h($r['nombre']) ?></strong></td>
              <td><a href="mailto:<?= h($r['email']) ?>" style="color:#0284C7;"><?= h($r['email']) ?></a></td>
              <td style="color:#94a3b8;font-size:12px;"><?= h($r['ip_address'] ?? '—') ?></td>
              <td><div class="msg-preview" title="<?= h($r['mensaje']) ?>"><?= h(mb_substr($r['mensaje'], 0, 90)) ?>…</div></td>
              <td style="white-space:nowrap;color:#64748b;font-size:12px;"><?= fdate($r['fecha_creacion']) ?></td>
              <td><?= cbadge((int)$r['leido']) ?></td>
              <td style="white-space:nowrap;">
                <?php if (!(int)$r['leido']): ?>
                  <?= actionForm('mark_read', (int)$r['id'], 'contactos', $csrf, 'btn-sm btn-primary', '✓ Leído') ?>
                <?php else: ?>
                  <?= actionForm('mark_unread', (int)$r['id'], 'contactos', $csrf, 'btn-sm btn-muted', 'Sin leer') ?>
                <?php endif; ?>
                <?= actionForm('del_contact', (int)$r['id'], 'contactos', $csrf, 'btn-sm btn-danger', '🗑', '') ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php elseif ($tab === 'cotizaciones'): ?>
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>#</th><th>Nombre</th><th>Empresa</th><th>Teléfono</th>
            <th>Servicio</th><th>Área</th><th>Estado</th><th>Fecha</th><th>Actualizar</th>
          </tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= $r['id'] ?></td>
              <td><strong><?= h($r['nombre']) ?></strong><br><a href="mailto:<?= h($r['email']) ?>" style="color:#0284C7;font-size:12px;"><?= h($r['email']) ?></a></td>
              <td><?= h($r['empresa'] ?: '—') ?></td>
              <td><?= h($r['telefono']) ?></td>
              <td><?= h($r['servicio']) ?></td>
              <td><?= $r['area_m2'] ? number_format((float)$r['area_m2'], 0) . ' m²' : '—' ?></td>
              <td><?= qbadge($r['estado']) ?></td>
              <td style="white-space:nowrap;color:#64748b;font-size:12px;"><?= fdate($r['fecha_creacion']) ?></td>
              <td>
                <?php
                $opts = ['pending'=>'Pendiente','contacted'=>'Contactado','quoted'=>'Cotizado','closed'=>'Cerrado'];
                $sel  = "<select name='status' class='sel-sm'>";
                foreach ($opts as $v => $l) $sel .= "<option value='$v'" . ($r['estado']===$v?' selected':'') . ">$l</option>";
                $sel .= '</select>';
                echo actionForm('upd_quote', (int)$r['id'], 'cotizaciones', $csrf, 'btn-sm btn-primary', '✓', $sel);
                ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

    <?php elseif ($tab === 'aplicaciones'): ?>

      <!-- Formulario registro manual de candidato -->
      <?php if (isset($_GET['form'])): ?>
      <div class="vac-form-wrap">
        <form method="post" class="vac-form">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action"     value="add_apply_manual">
          <input type="hidden" name="tab"        value="aplicaciones">

          <div class="vf-title">📋 Registrar candidato manualmente</div>

          <div class="vf-grid">
            <div class="vf-field">
              <label>Nombre completo *</label>
              <input type="text" name="nombre" required placeholder="Nombre y apellidos">
            </div>
            <div class="vf-field">
              <label>Teléfono *</label>
              <input type="text" name="telefono" required placeholder="55 1234 5678">
            </div>
            <div class="vf-field">
              <label>Edad</label>
              <input type="number" name="edad" min="15" max="80" placeholder="Ej. 28">
            </div>
            <div class="vf-field">
              <label>Correo electrónico</label>
              <input type="email" name="email" placeholder="(opcional)">
            </div>
            <div class="vf-field">
              <label>Puesto de interés *</label>
              <input type="text" name="puesto_interes" required placeholder="Ej. Afanador">
            </div>
            <div class="vf-field">
              <label>Vacante relacionada</label>
              <select name="vacante_id">
                <option value="">— Sin asignar —</option>
                <?php foreach ($vacantesOpts as $vo): ?>
                  <option value="<?= $vo['id'] ?>"><?= h($vo['titulo']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="vf-field">
              <label>Canal de origen</label>
              <select name="canal">
                <option value="whatsapp">💬 WhatsApp</option>
                <option value="web">🌐 Web</option>
                <option value="referido">👥 Referido</option>
                <option value="otro">Otro</option>
              </select>
            </div>
            <div class="vf-field">
              <label>Estado inicial</label>
              <select name="estado">
                <option value="recibida">Recibida</option>
                <option value="revisando">Revisando</option>
                <option value="entrevista">Entrevista</option>
              </select>
            </div>
            <div class="vf-field vf-span2">
              <label>Experiencia / Notas</label>
              <textarea name="experiencia" rows="3"
                        placeholder="Experiencia previa, observaciones del reclutador…"></textarea>
            </div>
          </div>

          <div class="vf-actions">
            <button type="submit" class="btn-sm btn-primary">💾 Registrar candidato</button>
            <a href="?tab=aplicaciones" class="btn-sm btn-muted">Cancelar</a>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <!-- Filtros de aplicaciones -->
      <div class="filter-bar">
        <form method="get" style="display:contents;">
          <input type="hidden" name="tab" value="aplicaciones">
          <label>Estado:</label>
          <select name="f_estado" onchange="this.form.submit()">
            <option value="" <?= !$f_estado ? 'selected' : '' ?>>Todos</option>
            <?php foreach (['recibida'=>'Recibida','revisando'=>'Revisando','entrevista'=>'Entrevista','contratado'=>'Contratado','descartado'=>'Descartado'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $f_estado===$v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
          <label>Canal:</label>
          <select name="f_canal" onchange="this.form.submit()">
            <option value="" <?= !$f_canal ? 'selected' : '' ?>>Todos</option>
            <?php foreach (['web'=>'🌐 Web','whatsapp'=>'💬 WhatsApp','referido'=>'👥 Referido','otro'=>'Otro'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= $f_canal===$v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($f_estado || $f_canal): ?>
            <a href="?tab=aplicaciones" class="btn-sm btn-muted" style="text-decoration:none;">✕ Limpiar</a>
          <?php endif; ?>
        </form>
        <span style="font-size:12px;color:#94a3b8;margin-left:auto;"><?= count($rows) ?> resultado(s)</span>
      </div>

      <!-- Header de tabla + botón registrar -->
      <div class="vac-header">
        <span style="font-size:13px;color:#64748b;">
          <?= $cNew ?> nueva(s) · <?= $cTotal['aplicaciones'] ?> total
        </span>
        <?php if (!isset($_GET['form'])): ?>
          <a href="?tab=aplicaciones&form=1" class="btn-sm btn-primary">+ Registrar candidato</a>
        <?php endif; ?>
      </div>

      <!-- Tabla de aplicaciones -->
      <?php if (empty($rows)): ?>
        <div class="empty">No hay aplicaciones todavía.</div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>#</th><th>Nombre</th><th>Edad</th><th>Teléfono</th>
            <th>Puesto</th><th>Canal</th><th>Estado</th><th>CV</th><th>Fecha</th><th>Actualizar</th>
          </tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= $r['id'] ?></td>
              <td>
                <strong><?= h($r['nombre']) ?></strong>
                <?php if ($r['email']): ?>
                  <br><a href="mailto:<?= h($r['email']) ?>" style="color:#0284C7;font-size:12px;"><?= h($r['email']) ?></a>
                <?php endif; ?>
              </td>
              <td><?= $r['edad'] ?: '—' ?></td>
              <td><?= h($r['telefono']) ?></td>
              <td><?= h($r['puesto_interes']) ?></td>
              <td><?= canal_badge($r['canal'] ?? 'web') ?></td>
              <td><?= abadge($r['estado']) ?></td>
              <td>
                <?php if ($r['cv_filename']): ?>
                  <a href="cv.php?f=<?= urlencode($r['cv_filename']) ?>" target="_blank" style="color:#0284C7;font-size:12px;">Ver CV</a>
                <?php else: ?>
                  <span style="color:#94a3b8;font-size:12px;">—</span>
                <?php endif; ?>
              </td>
              <td style="white-space:nowrap;color:#64748b;font-size:12px;"><?= fdate($r['fecha_creacion']) ?></td>
              <td style="white-space:nowrap;">
                <?php
                $opts = ['recibida'=>'Recibida','revisando'=>'Revisando','entrevista'=>'Entrevista','contratado'=>'Contratado','descartado'=>'Descartado'];
                $sel  = "<select name='status' class='sel-sm'>";
                foreach ($opts as $v => $l) $sel .= "<option value='$v'" . ($r['estado']===$v?' selected':'') . ">$l</option>";
                $sel .= '</select>';
                echo actionForm('upd_apply', (int)$r['id'], 'aplicaciones', $csrf, 'btn-sm btn-primary', '✓', $sel);
                ?>
                <?php if ($r['estado'] === 'contratado'): ?>
                  <a href="empleados.php?from_aplicacion=<?= (int)$r['id'] ?>"
                     class="btn-sm btn-success"
                     style="text-decoration:none;display:inline-block;margin-left:4px;">
                    👥 Contratar
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

    <?php elseif ($tab === 'vacantes'): ?>

      <!-- Formulario nueva / editar vacante -->
      <?php if (isset($_GET['form']) || $editVacante): ?>
      <div class="vac-form-wrap">
        <form method="post" class="vac-form">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action"     value="save_vacante">
          <input type="hidden" name="tab"        value="vacantes">
          <?php if ($editVacante): ?>
            <input type="hidden" name="id" value="<?= (int)$editVacante['id'] ?>">
          <?php endif; ?>

          <div class="vf-title"><?= $editVacante ? '✏️ Editar vacante' : '➕ Nueva vacante' ?></div>

          <div class="vf-grid">
            <div class="vf-field vf-span2">
              <label>Título del puesto *</label>
              <input type="text" name="titulo" required
                     value="<?= h($editVacante['titulo'] ?? '') ?>"
                     placeholder="Ej. Afanador / Auxiliar de Limpieza">
            </div>
            <div class="vf-field">
              <label>Servicio</label>
              <input type="text" name="area"
                     value="<?= h($editVacante['area'] ?? 'Limpieza') ?>"
                     placeholder="Ej. Limpieza, Mantenimiento…">
            </div>
            <!-- área como select oculto — se habilitará desde configuración en futuras versiones -->
            <div class="vf-field" style="display:none;">
              <label>Área</label>
              <select name="_area_legacy" disabled>
                <?php foreach (['Operaciones','Limpieza','Mantenimiento','Administración'] as $opt): ?>
                  <option value="<?= $opt ?>"><?= $opt ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="vf-field">
              <label>Horario</label>
              <?php
                $tiposOpts = ['Matutino','Vespertino','Nocturno','Mixto'];
                $tiposSel  = array_filter(explode(',', $editVacante['tipo'] ?? 'Matutino'));
              ?>
              <div class="msel" id="msel-tipo">
                <div class="msel-control" tabindex="0" onclick="mselToggle(this.parentElement)">
                  <span class="msel-text<?= $tiposSel ? '' : ' placeholder' ?>"><?= h($tiposSel ? implode(', ', $tiposSel) : 'Selecciona…') ?></span>
                  <span class="msel-arrow">▾</span>
                </div>
                <div class="msel-menu">
                  <?php foreach ($tiposOpts as $opt): ?>
                    <label class="msel-opt">
                      <input type="checkbox" name="tipo[]" value="<?= $opt ?>"
                             <?= in_array($opt, $tiposSel) ? 'checked' : '' ?>
                             onchange="mselUpdate(this)">
                      <?= $opt ?>
                    </label>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
            <div class="vf-field">
              <label>Área de Trabajo</label>
              <input type="text" name="ubicacion"
                     value="<?= h($editVacante['ubicacion'] ?? 'CDMX / EDOMEX') ?>"
                     placeholder="CDMX / EDOMEX">
            </div>
            <div class="vf-field">
              <label>Link Google Maps <span style="font-weight:400;color:#94a3b8;">(opcional)</span></label>
              <input type="url" name="maps_url"
                     value="<?= h($editVacante['maps_url'] ?? '') ?>"
                     placeholder="https://maps.app.goo.gl/...">
            </div>
            <div class="vf-field vf-span2">
              <label>Formulario de postulación (Google Forms) *</label>
              <input type="url" name="form_url" required
                     value="<?= h($editVacante['form_url'] ?? '') ?>"
                     placeholder="https://forms.gle/...">
            </div>
            <div class="vf-field">
              <label>Salario mínimo <span style="font-weight:400;color:#94a3b8;">(opcional)</span></label>
              <input type="number" name="rango_salarial_min" min="0" step="1" inputmode="numeric"
                     value="<?= h((string)$editSalMin) ?>"
                     placeholder="Ej. 7000">
            </div>
            <div class="vf-field">
              <label>Salario máximo <span style="font-weight:400;color:#94a3b8;">(opcional)</span></label>
              <input type="number" name="rango_salarial_max" min="0" step="1" inputmode="numeric"
                     value="<?= h((string)$editSalMax) ?>"
                     placeholder="Ej. 9000">
            </div>
            <div class="vf-field vf-span2">
              <label>Beneficios <span style="font-weight:400;color:#94a3b8;">(opcional)</span></label>
              <textarea name="beneficios" rows="3"
                        placeholder="Ej. Prestaciones de ley, vales de despensa, uniformes, capacitación…"><?= h($editVacante['beneficios'] ?? '') ?></textarea>
            </div>
            <div class="vf-field vf-span2">
              <label>Descripción del puesto</label>
              <textarea name="descripcion" rows="3"
                        placeholder="¿Qué actividades realizará el candidato?"><?= h($editVacante['descripcion'] ?? '') ?></textarea>
            </div>
            <div class="vf-field vf-span2">
              <label>Requisitos</label>
              <textarea name="requisitos" rows="3"
                        placeholder="Experiencia, estudios, disponibilidad, otros…"><?= h($editVacante['requisitos'] ?? '') ?></textarea>
            </div>
            <div class="vf-field vf-check">
              <label>
                <input type="checkbox" name="activa" value="1"
                       <?= ($editVacante['activa'] ?? 1) ? 'checked' : '' ?>>
                Vacante activa (visible en el sitio y recibe postulaciones)
              </label>
            </div>
          </div>

          <div class="vf-actions">
            <button type="submit" class="btn-sm btn-primary">
              💾 <?= $editVacante ? 'Guardar cambios' : 'Crear vacante' ?>
            </button>
            <a href="?tab=vacantes" class="btn-sm btn-muted">Cancelar</a>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <!-- Header de tabla + botón nueva -->
      <div class="vac-header">
        <span style="font-size:13px;color:#64748b;">
          <?= $cActivas ?> activa(s) · <?= $cTotal['vacantes'] ?> total
        </span>
        <?php if (!isset($_GET['form']) && !$editVacante): ?>
          <a href="?tab=vacantes&form=1" class="btn-sm btn-primary">+ Nueva vacante</a>
        <?php endif; ?>
      </div>

      <!-- Tabla de vacantes -->
      <?php if (empty($rows)): ?>
        <div class="empty">
          Sin vacantes registradas.
          <a href="?tab=vacantes&form=1" style="color:#0284C7;">Crear la primera</a>
        </div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>#</th>
            <th>Título</th>
            <th>Servicio</th>
            <th>Horario</th>
            <th>Área de Trabajo</th>
            <th>Rango salarial</th>
            <th>Formulario</th>
            <th>Estado</th>
            <th>Creada</th>
            <th>Acciones</th>
          </tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= $r['id'] ?></td>
              <td>
                <strong><?= h($r['titulo']) ?></strong>
                <?php if ($r['descripcion']): ?>
                  <div class="msg-preview" style="font-size:12px;margin-top:2px;"
                       title="<?= h($r['descripcion']) ?>">
                    <?= h(mb_substr($r['descripcion'], 0, 60)) ?>…
                  </div>
                <?php endif; ?>
              </td>
              <td><?= h($r['area']) ?></td>
              <td style="color:#64748b;font-size:12px;"><?= h(str_replace(',', ', ', $r['tipo'])) ?></td>
              <td style="color:#64748b;font-size:12px;">
                <?= h($r['ubicacion'] ?: '—') ?>
                <?php if (!empty($r['maps_url'])): ?>
                  <a href="<?= h($r['maps_url']) ?>" target="_blank" rel="noopener"
                     title="Ver en Google Maps" style="margin-left:4px;text-decoration:none;">📍</a>
                <?php endif; ?>
              </td>
              <td style="color:#64748b;font-size:12px;"><?= h($r['rango_salarial'] ?: '—') ?></td>
              <td style="font-size:12px;">
                <?php if (!empty($r['form_url'])): ?>
                  <a href="<?= h($r['form_url']) ?>" target="_blank" rel="noopener"
                     style="color:#0284C7;text-decoration:none;" title="Abrir formulario">📋 Ver</a>
                <?php else: ?>
                  <span style="color:#ef4444;">Sin URL</span>
                <?php endif; ?>
              </td>
              <td><?= vbadge((int)$r['activa']) ?></td>
              <td style="white-space:nowrap;color:#64748b;font-size:12px;"><?= fdate($r['fecha_creacion']) ?></td>
              <td style="position:relative;min-width:150px;">
                <div class="acciones-row">
                <a href="?tab=vacantes&edit=<?= $r['id'] ?>" class="btn-sm btn-muted" style="text-decoration:none;display:inline-block;">✏️ Editar</a>
                <?= actionForm('toggle_vacante', (int)$r['id'], 'vacantes', $csrf, 'btn-sm btn-muted',
                    $r['activa'] ? '⏸ Pausar' : '▶ Activar') ?>
                <?= actionForm('del_vacante', (int)$r['id'], 'vacantes', $csrf, 'btn-sm btn-danger', '🗑') ?>
                <div class="link-menu-wrap" style="display:inline-block;position:relative;">
                  <button type="button" class="btn-sm btn-muted" onclick="linkMenuToggle(this)">🔗 Liga</button>
                  <div class="link-menu">
                    <div class="link-menu__item">
                      <label>Esta vacante</label>
                      <div class="link-menu__row">
                        <input type="text" readonly value="<?= h(publicSiteUrl('vacantes.php?id=' . $r['id'])) ?>">
                        <button type="button" onclick="copyLinkBtn(this)">Copiar</button>
                      </div>
                    </div>
                    <?php if ($r['area']): ?>
                    <div class="link-menu__item">
                      <label>Todas de "<?= h($r['area']) ?>"</label>
                      <div class="link-menu__row">
                        <input type="text" readonly value="<?= h(publicSiteUrl('vacantes.php?area=' . rawurlencode($r['area']))) ?>">
                        <button type="button" onclick="copyLinkBtn(this)">Copiar</button>
                      </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($r['ubicacion']): ?>
                    <div class="link-menu__item">
                      <label>Todas en "<?= h($r['ubicacion']) ?>"</label>
                      <div class="link-menu__row">
                        <input type="text" readonly value="<?= h(publicSiteUrl('vacantes.php?ubicacion=' . rawurlencode($r['ubicacion']))) ?>">
                        <button type="button" onclick="copyLinkBtn(this)">Copiar</button>
                      </div>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

    <?php endif; ?>
  </div><!-- /card -->

</div><!-- /body -->
<script>
function mselToggle(msel){
  document.querySelectorAll('.msel.open').forEach(function(o){ if (o !== msel) o.classList.remove('open'); });
  msel.classList.toggle('open');
}
function mselUpdate(checkbox){
  var msel = checkbox.closest('.msel');
  var checked = Array.prototype.map.call(msel.querySelectorAll('input[type=checkbox]:checked'), function(c){ return c.value; });
  var text = msel.querySelector('.msel-text');
  if (checked.length) {
    text.textContent = checked.join(', ');
    text.classList.remove('placeholder');
  } else {
    text.textContent = 'Selecciona…';
    text.classList.add('placeholder');
  }
}
document.addEventListener('click', function(e){
  document.querySelectorAll('.msel.open').forEach(function(msel){
    if (!msel.contains(e.target)) msel.classList.remove('open');
  });
  document.querySelectorAll('.link-menu-wrap.open').forEach(function(wrap){
    if (!wrap.contains(e.target)) wrap.classList.remove('open');
  });
});
function linkMenuToggle(btn){
  var wrap = btn.closest('.link-menu-wrap');
  document.querySelectorAll('.link-menu-wrap.open').forEach(function(o){ if (o !== wrap) o.classList.remove('open'); });
  wrap.classList.toggle('open');
}
function copyLinkBtn(btn){
  var input = btn.previousElementSibling;
  input.select();
  input.setSelectionRange(0, 99999);
  var done = function(){
    var old = btn.textContent;
    btn.textContent = '✓ Copiado';
    setTimeout(function(){ btn.textContent = old; }, 1500);
  };
  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(input.value).then(done).catch(function(){ document.execCommand('copy'); done(); });
  } else {
    document.execCommand('copy');
    done();
  }
}
</script>
</body>
</html>
