<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../db/csrf.php';

/* ─── Helpers ──────────────────────────────────────────────── */
function h(string $s): string   { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fd(?string $d): string { return ($d && $d !== '0000-00-00') ? date('d/m/Y', strtotime($d)) : '—'; }
function fetchAll($r): array    { $a=[]; if($r) while($row=$r->fetch_assoc()) $a[]=$row; return $a; }

function diasVacLFT(int $anos): int {
    if ($anos < 1) return 0;
    if ($anos <= 4) return 10 + ($anos * 2);
    return 20 + 2 * (int)(($anos - 5) / 5);
}

function calcBalance(array $emp, mysqli $db): array {
    $ingreso = strtotime($emp['fecha_ingreso'] ?? date('Y-m-d'));
    $hoy     = time();
    $anos    = max(0, (int)(($hoy - $ingreso) / (365.25 * 86400)));
    $corresp = diasVacLFT($anos);

    $ult_aniv = mktime(0,0,0, (int)date('m',$ingreso), (int)date('d',$ingreso), (int)date('Y'));
    if ($ult_aniv > $hoy) $ult_aniv = mktime(0,0,0, (int)date('m',$ingreso), (int)date('d',$ingreso), (int)date('Y')-1);
    $prox_aniv = mktime(0,0,0, (int)date('m',$ingreso), (int)date('d',$ingreso), (int)date('Y', $ult_aniv)+1);
    $ult_s  = date('Y-m-d', $ult_aniv);
    $prox_s = date('Y-m-d', $prox_aniv);
    $eid    = (int)$emp['id'];

    $usados = (int)$db->query(
        "SELECT COALESCE(SUM(dias),0) FROM incidencias
         WHERE empleado_id=$eid AND tipo='vacaciones' AND estado='aprobada'
           AND fecha_inicio>='$ult_s' AND fecha_inicio<'$prox_s'"
    )->fetch_row()[0];

    return ['anos'=>$anos,'corresp'=>$corresp,'usados'=>$usados,'disponibles'=>max(0,$corresp-$usados)];
}

$TIPOS = [
    'vacaciones'          => ['lbl'=>'Vacaciones',                'color'=>'#dbeafe','c'=>'#1d4ed8'],
    'permiso_goce'        => ['lbl'=>'Permiso c/goce',            'color'=>'#dcfce7','c'=>'#166534'],
    'permiso_sin_goce'    => ['lbl'=>'Permiso s/goce',            'color'=>'#fef9c3','c'=>'#854d0e'],
    'incapacidad_imss'    => ['lbl'=>'Incapacidad IMSS',          'color'=>'#f3e8ff','c'=>'#7e22ce'],
    'incapacidad_empresa' => ['lbl'=>'Incapacidad empresa',       'color'=>'#fee2e2','c'=>'#991b1b'],
];

function tbadge(string $tipo, array $TIPOS): string {
    $t = $TIPOS[$tipo] ?? ['lbl'=>$tipo,'color'=>'#f1f5f9','c'=>'#64748b'];
    return "<span style='display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:{$t['color']};color:{$t['c']};'>{$t['lbl']}</span>";
}
function ebadge(string $e): string {
    $m = ['pendiente'=>['#fef9c3','#854d0e','⏳ Pendiente'],'aprobada'=>['#dcfce7','#166534','✅ Aprobada'],'rechazada'=>['#fee2e2','#991b1b','❌ Rechazada']];
    [$bg,$c,$l] = $m[$e] ?? ['#f1f5f9','#64748b',$e];
    return "<span style='display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:$bg;color:$c;'>$l</span>";
}

/* ─── POST (AJAX) ──────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Token inválido.']); exit;
    }

    $db  = getAdminDB();
    $act = $_POST['action'] ?? '';
    $iid = (int)($_POST['incidencia_id'] ?? 0);

    /* ── aprobar ───────────────────────────────────────────── */
    if ($act === 'aprobar' && $iid) {
        $inc = $db->query("SELECT * FROM incidencias WHERE id=$iid AND estado='pendiente'")->fetch_assoc();
        if (!$inc) { echo json_encode(['ok'=>false,'msg'=>'Solicitud no disponible.']); $db->close(); exit; }

        $notas_s = $db->real_escape_string(trim($_POST['notas'] ?? ''));
        $notas_v = $notas_s ? "'$notas_s'" : 'NULL';

        $db->query("UPDATE incidencias SET estado='aprobada', aprobado_por='admin',
                    notas_admin=$notas_v, fecha_respuesta=NOW() WHERE id=$iid");

        // Mapeo tipo incidencia → tipo asistencia
        $mapa = [
            'vacaciones'          => 'vacaciones',
            'permiso_goce'        => 'permiso_goce',
            'permiso_sin_goce'    => 'permiso_sin_goce',
            'incapacidad_imss'    => 'incapacidad',
            'incapacidad_empresa' => 'incapacidad',
        ];
        $atipo = $mapa[$inc['tipo']] ?? 'permiso_goce';
        $atipo_s = $db->real_escape_string($atipo);
        $eid  = (int)$inc['empleado_id'];
        $obs  = "Generado por aprobación de solicitud #$iid";
        $obs_s = $db->real_escape_string($obs);

        // Poblar asistencia día a día (excepto domingos)
        $cur = strtotime($inc['fecha_inicio']);
        $end = strtotime($inc['fecha_fin']);
        $insertados = 0;
        while ($cur <= $end) {
            if (date('N', $cur) < 7) { // lun-sab
                $fecha = date('Y-m-d', $cur);
                $db->query(
                    "INSERT IGNORE INTO asistencia (empleado_id, fecha, tipo, observaciones, capturado_por)
                     VALUES ($eid, '$fecha', '$atipo_s', '$obs_s', 'Sistema')"
                );
                if ($db->affected_rows > 0) $insertados++;
            }
            $cur = strtotime('+1 day', $cur);
        }

        $db->close();
        echo json_encode(['ok'=>true,'msg'=>"Solicitud aprobada. $insertados día(s) registrados en asistencia.",'reload'=>true]);
        exit;
    }

    /* ── rechazar ──────────────────────────────────────────── */
    if ($act === 'rechazar' && $iid) {
        $notas_s = $db->real_escape_string(trim($_POST['notas'] ?? ''));
        $notas_v = $notas_s ? "'$notas_s'" : 'NULL';
        $db->query("UPDATE incidencias SET estado='rechazada', aprobado_por='admin',
                    notas_admin=$notas_v, fecha_respuesta=NOW() WHERE id=$iid AND estado='pendiente'");
        $db->close();
        echo json_encode(['ok'=>true,'reload'=>true]);
        exit;
    }

    /* ── eliminar (solo pendientes) ────────────────────────── */
    if ($act === 'eliminar' && $iid) {
        $db->query("DELETE FROM incidencias WHERE id=$iid AND estado='pendiente'");
        $db->close();
        echo json_encode(['ok'=>true,'reload'=>true]);
        exit;
    }

    $db->close();
    echo json_encode(['ok'=>false,'msg'=>'Acción desconocida.']);
    exit;
}

/* ─── GET: cargar datos ────────────────────────────────────── */
$db    = getAdminDB();
$token = generateCsrfToken();

// Filtros
$f_estado = in_array($_GET['estado'] ?? '', ['pendiente','aprobada','rechazada','']) ? ($_GET['estado'] ?? '') : '';
$f_tipo   = array_key_exists($_GET['tipo'] ?? '', $TIPOS) ? ($_GET['tipo'] ?? '') : '';
$f_mes    = trim($_GET['mes'] ?? ''); // YYYY-MM

$where = '1=1';
if ($f_estado) $where .= " AND i.estado='".$db->real_escape_string($f_estado)."'";
if ($f_tipo)   $where .= " AND i.tipo='".$db->real_escape_string($f_tipo)."'";
if ($f_mes)    $where .= " AND DATE_FORMAT(i.fecha_inicio,'%Y-%m')='".$db->real_escape_string($f_mes)."'";

$incidencias = fetchAll($db->query(
    "SELECT i.*,
            e.nombre, e.apellido_paterno, e.numero_empleado,
            e.fecha_ingreso, e.salario_base,
            d.nombre AS departamento
     FROM incidencias i
     JOIN empleados e ON e.id = i.empleado_id
     LEFT JOIN departamentos d ON d.id = e.departamento_id
     WHERE $where
       AND i.tipo IN ('vacaciones','permiso_goce','permiso_sin_goce','incapacidad_imss','incapacidad_empresa')
     ORDER BY FIELD(i.estado,'pendiente','aprobada','rechazada'), i.fecha_creacion DESC
     LIMIT 300"
));

// Stats
$cPend = (int)$db->query("SELECT COUNT(*) FROM incidencias WHERE estado='pendiente'")->fetch_row()[0];
$cApro = (int)$db->query("SELECT COUNT(*) FROM incidencias WHERE estado='aprobada' AND MONTH(fecha_creacion)=MONTH(NOW()) AND YEAR(fecha_creacion)=YEAR(NOW())")->fetch_row()[0];
$cRech = (int)$db->query("SELECT COUNT(*) FROM incidencias WHERE estado='rechazada' AND MONTH(fecha_creacion)=MONTH(NOW()) AND YEAR(fecha_creacion)=YEAR(NOW())")->fetch_row()[0];

// Pre-calcular balance de vacaciones para cada empleado en la lista
$balances = [];
foreach ($incidencias as $inc) {
    $eid = (int)$inc['empleado_id'];
    if (!isset($balances[$eid])) {
        $balances[$eid] = calcBalance($inc, $db);
    }
}

$db->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Vacaciones y Permisos – Sharclean Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,Arial,sans-serif;font-size:14px;background:#f4f7fb;color:#1e293b}
a{color:inherit;text-decoration:none}
.admin-header{background:#0284C7;color:#fff;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}
.admin-header h1{font-size:18px;font-weight:800}
.nav-links{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.nav-links a{color:rgba(255,255,255,.85);font-size:13px;padding:6px 12px;border:1px solid rgba(255,255,255,.3);border-radius:6px;white-space:nowrap;transition:.15s}
.nav-links a:hover{background:rgba(255,255,255,.15)}
.nav-links a.active{background:rgba(255,255,255,.95);color:#0284C7;font-weight:700;border-color:transparent}
.content{max-width:1400px;margin:0 auto;padding:24px 20px}
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:20px}
.stat-box{background:#fff;border-radius:10px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.07);text-align:center}
.stat-val{font-size:28px;font-weight:800;color:#0284C7;line-height:1.2}
.stat-val.warn{color:#d97706}.stat-val.green{color:#16a34a}.stat-val.red{color:#dc2626}
.stat-lbl{font-size:11px;color:#64748b;margin-top:4px;font-weight:600;text-transform:uppercase;letter-spacing:.04em}
.card{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.08);margin-bottom:20px}
.filter-bar{display:flex;gap:8px;align-items:center;padding:14px 20px;flex-wrap:wrap;border-bottom:1px solid #f1f5f9}
.filter-bar select,.filter-bar input{padding:6px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;background:#fff;font-family:inherit}
.btn{display:inline-flex;align-items:center;gap:5px;padding:6px 14px;border-radius:8px;border:none;cursor:pointer;font-size:13px;font-weight:600;font-family:inherit;transition:.15s}
.btn-success{background:#16a34a;color:#fff}.btn-success:hover{background:#15803d}
.btn-danger{background:#dc2626;color:#fff}.btn-danger:hover{background:#b91c1c}
.btn-outline{background:#fff;color:#374151;border:1.5px solid #d1d5db}.btn-outline:hover{background:#f9fafb}
.btn-link{background:#dbeafe;color:#1d4ed8}.btn-link:hover{background:#bfdbfe}
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:900px}
th{background:#f8fafc;padding:9px 12px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;border-bottom:2px solid #e2e8f0;white-space:nowrap}
td{padding:10px 12px;border-bottom:1px solid #f1f5f9;font-size:13px;vertical-align:middle}
tr:hover td{background:#fafbfc}
.td-c{text-align:center}
.balance-mini{font-size:11px;color:#64748b;margin-top:2px}
.balance-mini strong{color:#0284C7}
/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:100;display:flex;align-items:center;justify-content:center;display:none}
.modal-overlay.open{display:flex}
.modal{background:#fff;border-radius:16px;padding:28px;width:90%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.2)}
.modal h3{font-size:17px;font-weight:800;margin-bottom:6px}
.modal p{font-size:13px;color:#64748b;margin-bottom:16px;line-height:1.6}
.modal label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px}
.modal textarea{width:100%;padding:8px 10px;border:1.5px solid #d1d5db;border-radius:8px;font-size:13px;font-family:inherit;resize:vertical;min-height:80px;margin-bottom:14px}
.modal textarea:focus{outline:none;border-color:#0284C7}
.modal-actions{display:flex;gap:8px;justify-content:flex-end}
#toast{position:fixed;bottom:24px;right:24px;z-index:999;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;display:none;box-shadow:0 4px 20px rgba(0,0,0,.15)}
#toast.ok{background:#dcfce7;color:#166534}
#toast.err{background:#fee2e2;color:#991b1b}
</style>
</head>
<body>

<header class="admin-header">
  <h1>🏖 Vacaciones y Permisos</h1>
  <div class="nav-links">
    <a href="index.php">Panel</a>
    <a href="empleados.php">👥 Empleados</a>
    <a href="asistencia.php">📅 Asistencia</a>
    <a href="nomina.php">📋 Nómina</a>
    <a href="vacaciones.php" class="active">🏖 Vacaciones</a>
    <a href="finiquitos.php">💼 Finiquitos</a>
    <a href="logout.php">Cerrar sesión</a>
  </div>
</header>

<div class="content">

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-box">
      <div class="stat-val warn"><?= $cPend ?></div>
      <div class="stat-lbl">Pendientes de respuesta</div>
    </div>
    <div class="stat-box">
      <div class="stat-val green"><?= $cApro ?></div>
      <div class="stat-lbl">Aprobadas este mes</div>
    </div>
    <div class="stat-box">
      <div class="stat-val red"><?= $cRech ?></div>
      <div class="stat-lbl">Rechazadas este mes</div>
    </div>
    <div class="stat-box">
      <div class="stat-val"><?= count($incidencias) ?></div>
      <div class="stat-lbl">Total en vista</div>
    </div>
  </div>

  <!-- Link solicitud pública -->
  <div class="card" style="padding:14px 20px;margin-bottom:20px;">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
      <span style="font-size:13px;color:#64748b;">🔗 Link para empleados:</span>
      <code style="background:#f1f5f9;padding:4px 10px;border-radius:6px;font-size:13px;">solicitar.php</code>
      <a href="../solicitar.php" target="_blank" class="btn btn-link btn" style="font-size:12px;">Abrir formulario ↗</a>
    </div>
  </div>

  <!-- Tabla de solicitudes -->
  <div class="card">
    <!-- Filtros -->
    <div class="filter-bar">
      <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
        <select name="estado" onchange="this.form.submit()">
          <option value="">Todos los estados</option>
          <option value="pendiente" <?= $f_estado==='pendiente'?'selected':'' ?>>⏳ Pendiente</option>
          <option value="aprobada"  <?= $f_estado==='aprobada' ?'selected':'' ?>>✅ Aprobada</option>
          <option value="rechazada" <?= $f_estado==='rechazada'?'selected':'' ?>>❌ Rechazada</option>
        </select>
        <select name="tipo" onchange="this.form.submit()">
          <option value="">Todos los tipos</option>
          <?php foreach ($TIPOS as $val => $t): ?>
            <option value="<?= $val ?>" <?= $f_tipo===$val?'selected':'' ?>><?= $t['lbl'] ?></option>
          <?php endforeach ?>
        </select>
        <input type="month" name="mes" value="<?= h($f_mes) ?>" onchange="this.form.submit()" style="padding:6px 10px;">
        <?php if ($f_estado || $f_tipo || $f_mes): ?>
          <a href="vacaciones.php" class="btn btn-outline">✕ Limpiar</a>
        <?php endif ?>
      </form>
    </div>

    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Empleado</th>
            <th>Departamento</th>
            <th>Tipo</th>
            <th>Período</th>
            <th class="td-c">Días</th>
            <th>Balance vacaciones</th>
            <th>Motivo</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($incidencias)): ?>
            <tr><td colspan="10" style="text-align:center;padding:40px;color:#94a3b8;">No hay solicitudes con los filtros seleccionados.</td></tr>
          <?php endif ?>
          <?php foreach ($incidencias as $inc): ?>
            <?php
            $bal    = $balances[(int)$inc['empleado_id']] ?? ['anos'=>0,'corresp'=>0,'usados'=>0,'disponibles'=>0];
            $nombre = h($inc['apellido_paterno'].' '.$inc['nombre']);
            ?>
            <tr>
              <td style="color:#94a3b8;font-size:11px;"><?= $inc['id'] ?></td>
              <td>
                <strong><?= $nombre ?></strong><br>
                <span style="font-size:11px;color:#94a3b8;"><?= h($inc['numero_empleado']) ?></span>
              </td>
              <td style="font-size:12px;color:#64748b;"><?= h($inc['departamento'] ?? '—') ?></td>
              <td><?= tbadge($inc['tipo'], $TIPOS) ?></td>
              <td>
                <?= fd($inc['fecha_inicio']) ?><br>
                <span style="color:#94a3b8;font-size:11px;">al <?= fd($inc['fecha_fin']) ?></span>
              </td>
              <td class="td-c" style="font-weight:700;"><?= (int)$inc['dias'] ?></td>
              <td>
                <span style="font-size:12px;">
                  <?= $bal['disponibles'] ?> disp. / <?= $bal['corresp'] ?> totales
                </span>
                <div class="balance-mini"><?= $bal['anos'] ?> año(s) antigüedad</div>
              </td>
              <td style="max-width:200px;font-size:12px;color:#475569;">
                <?= $inc['descripcion'] ? h(mb_substr($inc['descripcion'], 0, 80)) : '<span style="color:#cbd5e1">—</span>' ?>
                <?php if ($inc['notas_admin']): ?>
                  <div style="margin-top:3px;padding:3px 6px;background:#f1f5f9;border-radius:4px;font-size:11px;color:#64748b;">
                    Admin: <?= h(mb_substr($inc['notas_admin'], 0, 60)) ?>
                  </div>
                <?php endif ?>
              </td>
              <td><?= ebadge($inc['estado']) ?></td>
              <td>
                <?php if ($inc['estado'] === 'pendiente'): ?>
                  <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <button class="btn btn-success" onclick="openAprobar(<?= $inc['id'] ?>, '<?= $nombre ?>')">✅ Aprobar</button>
                    <button class="btn btn-danger"  onclick="openRechazar(<?= $inc['id'] ?>, '<?= $nombre ?>')">❌ Rechazar</button>
                  </div>
                <?php else: ?>
                  <span style="font-size:12px;color:#94a3b8;"><?= fd($inc['fecha_respuesta'] ?? null) ?></span>
                <?php endif ?>
              </td>
            </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /content -->

<!-- Modal Aprobar -->
<div class="modal-overlay" id="modalAprobar">
  <div class="modal">
    <h3>✅ Aprobar solicitud</h3>
    <p id="modalAprobarDesc">Se registrarán los días en asistencia automáticamente.</p>
    <label>Notas (opcional)</label>
    <textarea id="notasAprobar" placeholder="Comentario para el empleado..."></textarea>
    <div class="modal-actions">
      <button class="btn btn-outline" onclick="closeModal('modalAprobar')">Cancelar</button>
      <button class="btn btn-success" onclick="confirmarAprobar()">Confirmar aprobación</button>
    </div>
  </div>
</div>

<!-- Modal Rechazar -->
<div class="modal-overlay" id="modalRechazar">
  <div class="modal">
    <h3>❌ Rechazar solicitud</h3>
    <p id="modalRechazarDesc">El empleado podrá volver a solicitar.</p>
    <label>Motivo del rechazo *</label>
    <textarea id="notasRechazar" placeholder="Indica el motivo del rechazo..."></textarea>
    <div class="modal-actions">
      <button class="btn btn-outline" onclick="closeModal('modalRechazar')">Cancelar</button>
      <button class="btn btn-danger"  onclick="confirmarRechazar()">Confirmar rechazo</button>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
const TOKEN = <?= json_encode($token) ?>;
let currentId = null;

function toast(msg, ok = true) {
  const t = document.getElementById('toast');
  t.textContent = msg; t.className = ok ? 'ok' : 'err';
  t.style.display = 'block';
  clearTimeout(t._t);
  t._t = setTimeout(() => t.style.display = 'none', 3500);
}

async function post(data) {
  data.csrf_token = TOKEN;
  const fd = new FormData();
  Object.entries(data).forEach(([k,v]) => fd.append(k, v));
  const r = await fetch('vacaciones.php', { method:'POST', body:fd });
  return r.json();
}

function openAprobar(id, nombre) {
  currentId = id;
  document.getElementById('modalAprobarDesc').textContent =
    `Aprobar solicitud de ${nombre}. Los días se registrarán automáticamente en asistencia.`;
  document.getElementById('notasAprobar').value = '';
  document.getElementById('modalAprobar').classList.add('open');
}
function openRechazar(id, nombre) {
  currentId = id;
  document.getElementById('modalRechazarDesc').textContent =
    `Rechazar solicitud de ${nombre}.`;
  document.getElementById('notasRechazar').value = '';
  document.getElementById('modalRechazar').classList.add('open');
}
function closeModal(id) {
  document.getElementById(id).classList.remove('open');
  currentId = null;
}

async function confirmarAprobar() {
  if (!currentId) return;
  const notas = document.getElementById('notasAprobar').value;
  const btn = event.target;
  btn.disabled = true; btn.textContent = 'Procesando...';
  const res = await post({ action:'aprobar', incidencia_id:currentId, notas });
  btn.disabled = false; btn.textContent = 'Confirmar aprobación';
  if (res.ok) {
    closeModal('modalAprobar');
    toast(res.msg || 'Aprobado correctamente.');
    setTimeout(() => location.reload(), 1000);
  } else { toast(res.msg || 'Error.', false); }
}

async function confirmarRechazar() {
  if (!currentId) return;
  const notas = document.getElementById('notasRechazar').value.trim();
  if (!notas) { toast('Indica el motivo del rechazo.', false); return; }
  const btn = event.target;
  btn.disabled = true; btn.textContent = 'Procesando...';
  const res = await post({ action:'rechazar', incidencia_id:currentId, notas });
  btn.disabled = false; btn.textContent = 'Confirmar rechazo';
  if (res.ok) {
    closeModal('modalRechazar');
    toast('Solicitud rechazada.');
    setTimeout(() => location.reload(), 800);
  } else { toast(res.msg || 'Error.', false); }
}

// Cerrar modal con click en overlay
document.querySelectorAll('.modal-overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) closeModal(o.id); });
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.open').forEach(o => closeModal(o.id));
});
</script>
</body>
</html>
