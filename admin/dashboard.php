<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$db = getAdminDB();

function fetchAll($result): array {
    $rows = [];
    if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($v): string { return '$' . number_format((float)$v, 2); }

$hoy = date('Y-m-d');

/* ── KPIs: Empleados (si tiene permiso) ────────────────────── */
$emp_activos = $emp_nuevos = $emp_bajas = $emp_total = 0;
$emp_depts   = [];
if (hasPermiso('empleados')) {
    $emp_activos = (int)$db->query("SELECT COUNT(*) FROM empleados WHERE estatus='activo'")->fetch_row()[0];
    $emp_total   = (int)$db->query("SELECT COUNT(*) FROM empleados WHERE estatus != 'baja_definitiva'")->fetch_row()[0];
    $emp_nuevos  = (int)$db->query("SELECT COUNT(*) FROM empleados WHERE MONTH(fecha_ingreso)=MONTH(NOW()) AND YEAR(fecha_ingreso)=YEAR(NOW())")->fetch_row()[0];
    $emp_bajas = (int)$db->query("SELECT COUNT(*) FROM empleados WHERE estatus='baja_definitiva'")->fetch_row()[0];
    $emp_depts   = fetchAll($db->query(
        "SELECT COALESCE(d.nombre,'Sin dpto') AS nombre, COUNT(*) AS total
         FROM empleados e
         LEFT JOIN departamentos d ON d.id = e.departamento_id
         WHERE e.estatus='activo'
         GROUP BY d.id, d.nombre ORDER BY total DESC LIMIT 6"
    ));
}

/* ── KPIs: Asistencia hoy (si tiene permiso) ───────────────── */
$asis_presentes = $asis_faltas = $asis_retardos = 0;
$asis_he = 0.0;
if (hasPermiso('asistencia')) {
    $asis_hoy = fetchAll($db->query("SELECT tipo, retardo, horas_extra FROM asistencia WHERE fecha='$hoy'"));
    foreach ($asis_hoy as $a) {
        if ($a['tipo'] === 'normal')             $asis_presentes++;
        elseif (str_starts_with($a['tipo'], 'falta')) $asis_faltas++;
        if ((int)$a['retardo'])                  $asis_retardos++;
        $asis_he += (float)$a['horas_extra'];
    }
}

/* ── KPIs: Nómina (si tiene permiso) ───────────────────────── */
$nom_vigente = null;
$nom_ultimo  = null;
if (hasPermiso('nomina')) {
    $nom_vigente = $db->query(
        "SELECT tipo, fecha_inicio, fecha_fin, estado, empleados_count, total_neto
         FROM nomina_periodos WHERE estado IN('abierto','calculado')
         ORDER BY fecha_inicio DESC LIMIT 1"
    )->fetch_assoc();
    $nom_ultimo = $db->query(
        "SELECT tipo, fecha_inicio, fecha_fin, total_neto, empleados_count
         FROM nomina_periodos WHERE estado='cerrado'
         ORDER BY fecha_fin DESC LIMIT 1"
    )->fetch_assoc();
}

/* ── KPIs: Incidencias pendientes (si tiene permiso) ───────── */
$inc_pendientes = 0;
$inc_proximas   = [];
if (hasPermiso('vacaciones')) {
    $inc_pendientes = (int)$db->query("SELECT COUNT(*) FROM incidencias WHERE estado='pendiente'")->fetch_row()[0];
    $inc_proximas   = fetchAll($db->query(
        "SELECT CONCAT(e.nombre,' ',e.apellido_paterno) AS empleado, i.tipo, i.fecha_inicio, i.dias
         FROM incidencias i
         JOIN empleados e ON e.id = i.empleado_id
         WHERE i.estado='pendiente'
         ORDER BY i.fecha_inicio ASC LIMIT 5"
    ));
}

/* ── Alertas ────────────────────────────────────────────────── */
$alertas = [];
if (hasPermiso('nomina')) {
    $nom_abiertas = (int)$db->query("SELECT COUNT(*) FROM nomina_periodos WHERE estado='abierto'")->fetch_row()[0];
    if ($nom_abiertas > 0)
        $alertas[] = ['tipo'=>'warn', 'msg'=>"Hay $nom_abiertas período(s) de nómina abierto(s) sin calcular.", 'link'=>'nomina.php'];
}
if (hasPermiso('finiquitos')) {
    $fin_borradores = (int)$db->query("SELECT COUNT(*) FROM finiquitos WHERE estado='borrador'")->fetch_row()[0];
    if ($fin_borradores > 0)
        $alertas[] = ['tipo'=>'warn', 'msg'=>"$fin_borradores finiquito(s) en borrador pendientes de firma.", 'link'=>'finiquitos.php'];
}
if (hasPermiso('vacaciones') && $inc_pendientes > 0) {
    $alertas[] = ['tipo'=>'info', 'msg'=>"$inc_pendientes solicitud(es) de vacaciones/permiso esperando aprobación.", 'link'=>'vacaciones.php'];
}

/* ── Actividad reciente: aplicaciones (si tiene permiso) ───── */
$ultimas_aplics = [];
if (hasPermiso('panel')) {
    $ultimas_aplics = fetchAll($db->query(
        "SELECT nombre, puesto_interes, estado, fecha_creacion FROM aplicaciones_empleo
         ORDER BY fecha_creacion DESC LIMIT 5"
    ));
    $ultimos_contactos = fetchAll($db->query(
        "SELECT nombre, email, leido, fecha_creacion FROM contactos
         ORDER BY fecha_creacion DESC LIMIT 5"
    ));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard – Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#f1f5f9;color:#1e293b;font-size:14px}
a{color:inherit;text-decoration:none}

/* Header */
.admin-header{background:#0284C7;color:#fff;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}
.admin-brand{display:flex;align-items:center;gap:10px}
.admin-brand img{height:36px}
.admin-header h1{font-size:18px;font-weight:800}
.nav-links{display:flex;gap:4px;align-items:center}
.nav-links a{padding:6px 12px;border-radius:6px;font-size:13px;color:rgba(255,255,255,.85);transition:background .15s}
.nav-links a:hover,.nav-links a.active{background:rgba(255,255,255,.2);color:#fff}

/* Layout */
.body{padding:24px;max-width:1300px;margin:0 auto}
.section-title{font-size:12px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;margin-bottom:12px}

/* KPI Grid */
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:28px}
.kpi-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 1px 3px rgba(0,0,0,.06);position:relative;overflow:hidden}
.kpi-card::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%;background:var(--accent,#0284C7)}
.kpi-val{font-size:32px;font-weight:800;color:var(--accent,#0284C7);line-height:1}
.kpi-lbl{font-size:12px;color:#64748b;margin-top:6px}
.kpi-sub{font-size:11px;color:#94a3b8;margin-top:3px}

/* Alertas */
.alertas{display:flex;flex-direction:column;gap:8px;margin-bottom:28px}
.alerta{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:10px;font-size:13px}
.alerta-warn{background:#fef9c3;border:1px solid #fde68a;color:#854d0e}
.alerta-info{background:#dbeafe;border:1px solid #bfdbfe;color:#1e40af}
.alerta a{font-weight:700;text-decoration:underline;margin-left:8px}

/* Dos columnas */
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:28px}
@media(max-width:700px){.two-col{grid-template-columns:1fr}}

/* Cards tabla */
.dash-card{background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.06);overflow:hidden}
.dash-card-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #f1f5f9}
.dash-card-head h3{font-size:14px;font-weight:700}
.dash-card-head a{font-size:12px;color:#0284C7}

/* Tabla compacta */
.mini-table{width:100%;border-collapse:collapse}
.mini-table td{padding:9px 14px;border-bottom:1px solid #f1f5f9;font-size:13px;vertical-align:middle}
.mini-table tr:last-child td{border-bottom:none}
.mini-table tr:hover td{background:#f8fafc}
.no-data{padding:28px;text-align:center;color:#94a3b8;font-size:13px}

/* Badges */
.badge{display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600}
.bg-green{background:#dcfce7;color:#15803d}
.bg-blue{background:#dbeafe;color:#1d4ed8}
.bg-yellow{background:#fef9c3;color:#854d0e}
.bg-gray{background:#f1f5f9;color:#475569}
.bg-red{background:#fee2e2;color:#b91c1c}

/* Departamentos */
.dept-row{display:flex;align-items:center;justify-content:space-between;padding:8px 14px;border-bottom:1px solid #f1f5f9;font-size:13px}
.dept-row:last-child{border-bottom:none}
.dept-bar-wrap{flex:1;margin:0 12px;height:6px;background:#e2e8f0;border-radius:3px}
.dept-bar{height:6px;background:#0284C7;border-radius:3px}

/* Nómina widget */
.nom-widget{padding:18px}
.nom-widget .nom-estado{font-size:11px;font-weight:700;padding:3px 10px;border-radius:99px;margin-left:8px}
.nom-widget .nom-monto{font-size:28px;font-weight:800;color:#0284C7;margin:10px 0 4px}
.nom-widget .nom-meta{font-size:12px;color:#64748b}

/* Fecha */
.dash-fecha{font-size:13px;color:#94a3b8;margin-bottom:24px}
</style>
</head>
<body>

<?php
$navTitle     = '🏠 Dashboard';
$activeModule = 'dashboard';
require __DIR__ . '/_nav.php';
?>

<div class="body">

  <?php
  $dias   = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
  $meses  = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $ts     = strtotime($hoy);
  $fecha_fmt = $dias[date('w',$ts)].' '.date('d',$ts).' de '.$meses[(int)date('n',$ts)].' de '.date('Y',$ts);
  ?>
  <p class="dash-fecha">
    <?= ucfirst($fecha_fmt) ?> &mdash;
    Hola, <strong><?= h(adminUser()['nombre']) ?></strong>
  </p>

  <!-- ═══ ALERTAS ══════════════════════════════════════════════ -->
  <?php if (!empty($alertas)): ?>
  <div class="alertas">
    <?php foreach ($alertas as $a): ?>
    <div class="alerta alerta-<?= $a['tipo'] ?>">
      <?= $a['tipo'] === 'warn' ? '⚠️' : 'ℹ️' ?>
      <?= h($a['msg']) ?>
      <a href="<?= $a['link'] ?>">Ver →</a>
    </div>
    <?php endforeach ?>
  </div>
  <?php endif ?>

  <!-- ═══ KPI CARDS ══════════════════════════════════════════════ -->
  <p class="section-title">Indicadores clave</p>
  <div class="kpi-grid">

    <?php if (hasPermiso('empleados')): ?>
    <div class="kpi-card" style="--accent:#0284C7">
      <div class="kpi-val"><?= $emp_activos ?></div>
      <div class="kpi-lbl">Empleados activos</div>
      <div class="kpi-sub"><?= $emp_total ?> en plantilla total</div>
    </div>
    <div class="kpi-card" style="--accent:#16a34a">
      <div class="kpi-val"><?= $emp_nuevos ?></div>
      <div class="kpi-lbl">Ingresos este mes</div>
      <div class="kpi-sub"><?= $emp_bajas ?> baja(s) total</div>
    </div>
    <?php endif ?>

    <?php if (hasPermiso('asistencia')): ?>
    <div class="kpi-card" style="--accent:#7c3aed">
      <div class="kpi-val"><?= $asis_presentes ?></div>
      <div class="kpi-lbl">Presentes hoy</div>
      <div class="kpi-sub"><?= $asis_faltas ?> falta(s) · <?= $asis_retardos ?> retardo(s)</div>
    </div>
    <?php endif ?>

    <?php if (hasPermiso('nomina') && $nom_vigente): ?>
    <div class="kpi-card" style="--accent:#0891b2">
      <div class="kpi-val" style="font-size:22px"><?= money($nom_vigente['total_neto']) ?></div>
      <div class="kpi-lbl">Nómina <?= h($nom_vigente['estado']) ?></div>
      <div class="kpi-sub"><?= $nom_vigente['empleados_count'] ?> empleados · <?= ucfirst(h($nom_vigente['tipo'])) ?></div>
    </div>
    <?php endif ?>

    <?php if (hasPermiso('vacaciones')): ?>
    <div class="kpi-card" style="--accent:<?= $inc_pendientes > 0 ? '#d97706' : '#94a3b8' ?>">
      <div class="kpi-val"><?= $inc_pendientes ?></div>
      <div class="kpi-lbl">Solicitudes pendientes</div>
      <div class="kpi-sub">Vacaciones y permisos</div>
    </div>
    <?php endif ?>

  </div>

  <!-- ═══ ASISTENCIA HOY + NÓMINA ════════════════════════════════ -->
  <?php if (hasPermiso('asistencia') || hasPermiso('nomina')): ?>
  <div class="two-col" style="margin-bottom:28px">

    <?php if (hasPermiso('asistencia')): ?>
    <div class="dash-card">
      <div class="dash-card-head">
        <h3>📅 Asistencia de hoy</h3>
        <a href="asistencia.php">Ver detalle →</a>
      </div>
      <?php
      $total_asis = $asis_presentes + $asis_faltas;
      $pct = $total_asis > 0 ? round($asis_presentes * 100 / $total_asis) : 0;
      ?>
      <div style="padding:18px">
        <div style="display:flex;justify-content:space-between;margin-bottom:10px">
          <span style="font-size:13px;color:#64748b">Asistencia</span>
          <span style="font-size:13px;font-weight:700;color:#0284C7"><?= $pct ?>%</span>
        </div>
        <div style="height:8px;background:#e2e8f0;border-radius:4px;margin-bottom:18px">
          <div style="height:8px;background:#0284C7;border-radius:4px;width:<?= $pct ?>%"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
          <div style="text-align:center;padding:12px;background:#f8fafc;border-radius:8px">
            <div style="font-size:22px;font-weight:800;color:#16a34a"><?= $asis_presentes ?></div>
            <div style="font-size:11px;color:#64748b;margin-top:2px">Presentes</div>
          </div>
          <div style="text-align:center;padding:12px;background:#f8fafc;border-radius:8px">
            <div style="font-size:22px;font-weight:800;color:#dc2626"><?= $asis_faltas ?></div>
            <div style="font-size:11px;color:#64748b;margin-top:2px">Faltas</div>
          </div>
          <div style="text-align:center;padding:12px;background:#f8fafc;border-radius:8px">
            <div style="font-size:22px;font-weight:800;color:#d97706"><?= $asis_retardos ?></div>
            <div style="font-size:11px;color:#64748b;margin-top:2px">Retardos</div>
          </div>
          <div style="text-align:center;padding:12px;background:#f8fafc;border-radius:8px">
            <div style="font-size:22px;font-weight:800;color:#7c3aed"><?= number_format($asis_he,1) ?></div>
            <div style="font-size:11px;color:#64748b;margin-top:2px">Hrs Extra</div>
          </div>
        </div>
      </div>
    </div>
    <?php endif ?>

    <?php if (hasPermiso('nomina')): ?>
    <div class="dash-card">
      <div class="dash-card-head">
        <h3>📋 Nómina</h3>
        <a href="nomina.php">Ir a nómina →</a>
      </div>
      <?php if ($nom_vigente): ?>
      <div class="nom-widget">
        <div style="display:flex;align-items:center">
          <span style="font-size:13px;color:#64748b"><?= ucfirst(h($nom_vigente['tipo'])) ?> vigente</span>
          <?php
          $badgeCls = match($nom_vigente['estado']) {
              'calculado' => 'bg-blue', 'cerrado' => 'bg-green', default => 'bg-yellow'
          };
          ?>
          <span class="badge <?= $badgeCls ?> nom-estado"><?= h($nom_vigente['estado']) ?></span>
        </div>
        <div class="nom-monto"><?= money($nom_vigente['total_neto']) ?></div>
        <div class="nom-meta">
          <?= h($nom_vigente['fecha_inicio']) ?> al <?= h($nom_vigente['fecha_fin']) ?> ·
          <?= $nom_vigente['empleados_count'] ?> empleados
        </div>
      </div>
      <?php elseif ($nom_ultimo): ?>
      <div class="nom-widget">
        <div style="font-size:12px;color:#94a3b8;margin-bottom:8px">No hay período abierto. Último cerrado:</div>
        <div class="nom-monto"><?= money($nom_ultimo['total_neto']) ?></div>
        <div class="nom-meta">
          <?= ucfirst(h($nom_ultimo['tipo'])) ?> ·
          <?= h($nom_ultimo['fecha_inicio']) ?> al <?= h($nom_ultimo['fecha_fin']) ?>
        </div>
        <a href="nomina.php" style="display:inline-block;margin-top:14px;font-size:13px;color:#0284C7;font-weight:600">+ Abrir nuevo período</a>
      </div>
      <?php else: ?>
      <div class="no-data">Sin períodos de nómina registrados.</div>
      <?php endif ?>
    </div>
    <?php endif ?>

  </div>
  <?php endif ?>

  <!-- ═══ PLANTILLA POR DEPARTAMENTO + SOLICITUDES ══════════════ -->
  <div class="two-col">

    <?php if (hasPermiso('empleados') && !empty($emp_depts)): ?>
    <div class="dash-card">
      <div class="dash-card-head">
        <h3>👥 Plantilla activa por área</h3>
        <a href="empleados.php">Ver empleados →</a>
      </div>
      <?php $max = max(array_column($emp_depts, 'total')) ?: 1; ?>
      <?php foreach ($emp_depts as $d): ?>
      <div class="dept-row">
        <span style="min-width:120px;font-size:13px"><?= h($d['nombre']) ?></span>
        <div class="dept-bar-wrap">
          <div class="dept-bar" style="width:<?= round($d['total'] * 100 / $max) ?>%"></div>
        </div>
        <span style="font-weight:700;color:#0284C7;min-width:28px;text-align:right"><?= $d['total'] ?></span>
      </div>
      <?php endforeach ?>
    </div>
    <?php endif ?>

    <?php if (hasPermiso('vacaciones')): ?>
    <div class="dash-card">
      <div class="dash-card-head">
        <h3>🏖 Solicitudes pendientes</h3>
        <a href="vacaciones.php">Aprobar →</a>
      </div>
      <?php if (empty($inc_proximas)): ?>
        <div class="no-data">Sin solicitudes pendientes 🎉</div>
      <?php else: ?>
        <table class="mini-table">
          <?php foreach ($inc_proximas as $s):
            $tipo_label = [
              'vacaciones'=>'Vacaciones','permiso_goce'=>'Permiso c/goce',
              'permiso_sin_goce'=>'Permiso s/goce','falta_justificada'=>'Falta just.',
              'incapacidad_imss'=>'Incapacidad IMSS','incapacidad_empresa'=>'Inc. Empresa',
              'suspension'=>'Suspensión','otro'=>'Otro',
            ][$s['tipo']] ?? $s['tipo'];
          ?>
          <tr>
            <td>
              <div style="font-weight:600"><?= h($s['empleado']) ?></div>
              <div style="font-size:11px;color:#94a3b8"><?= h($tipo_label) ?> · <?= $s['dias'] ?> día(s)</div>
            </td>
            <td style="text-align:right;white-space:nowrap;font-size:12px;color:#64748b">
              <?= date('d/m/Y', strtotime($s['fecha_inicio'])) ?>
            </td>
          </tr>
          <?php endforeach ?>
        </table>
      <?php endif ?>
    </div>
    <?php endif ?>

  </div>

  <!-- ═══ ACTIVIDAD RECIENTE ════════════════════════════════════ -->
  <?php if (hasPermiso('panel')): ?>
  <p class="section-title" style="margin-top:28px">Actividad reciente</p>
  <div class="two-col">

    <div class="dash-card">
      <div class="dash-card-head">
        <h3>🧑‍💼 Últimas aplicaciones</h3>
        <a href="index.php?tab=aplicaciones">Ver todas →</a>
      </div>
      <?php if (empty($ultimas_aplics)): ?>
        <div class="no-data">Sin aplicaciones recientes.</div>
      <?php else: ?>
      <table class="mini-table">
        <?php
        $amap = ['recibida'=>'bg-blue','revisando'=>'bg-yellow','entrevista'=>'bg-yellow',
                 'contratado'=>'bg-green','descartado'=>'bg-red'];
        foreach ($ultimas_aplics as $a): ?>
        <tr>
          <td>
            <div style="font-weight:600"><?= h($a['nombre']) ?></div>
            <div style="font-size:11px;color:#94a3b8"><?= h($a['puesto_interes']) ?></div>
          </td>
          <td style="text-align:right">
            <span class="badge <?= $amap[$a['estado']] ?? 'bg-gray' ?>"><?= h($a['estado']) ?></span>
          </td>
        </tr>
        <?php endforeach ?>
      </table>
      <?php endif ?>
    </div>

    <div class="dash-card">
      <div class="dash-card-head">
        <h3>✉️ Últimos contactos</h3>
        <a href="index.php?tab=contactos">Ver todos →</a>
      </div>
      <?php if (empty($ultimos_contactos)): ?>
        <div class="no-data">Sin mensajes recientes.</div>
      <?php else: ?>
      <table class="mini-table">
        <?php foreach ($ultimos_contactos as $c): ?>
        <tr>
          <td>
            <div style="font-weight:600"><?= h($c['nombre']) ?></div>
            <div style="font-size:11px;color:#94a3b8"><?= h($c['email']) ?></div>
          </td>
          <td style="text-align:right">
            <span class="badge <?= $c['leido'] ? 'bg-gray' : 'bg-blue' ?>">
              <?= $c['leido'] ? 'Leído' : 'Nuevo' ?>
            </span>
          </td>
        </tr>
        <?php endforeach ?>
      </table>
      <?php endif ?>
    </div>

  </div>
  <?php endif ?>

</div><!-- /body -->
</body>
</html>
