<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
$db = getAdminDB();

function fetchAll($result): array {
    $rows = [];
    if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}

// ── Filtros ───────────────────────────────────────────────────────────────
$TABS   = ['headcount','asistencia','nomina','incidencias'];
$tab    = in_array($_GET['tab'] ?? '', $TABS) ? $_GET['tab'] : 'headcount';
$fi     = $_GET['fi']     ?? date('Y-m-01');
$ff     = $_GET['ff']     ?? date('Y-m-t');
$fi_s   = $db->real_escape_string($fi);
$ff_s   = $db->real_escape_string($ff);
$est_f  = $_GET['estatus'] ?? '';
$tipo_f = $_GET['tipo']    ?? '';
$edo_f  = $_GET['estado']  ?? '';

// ── Exportar CSV ──────────────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $exp = $_GET['export'];

    if ($exp === 'headcount') {
        $rows = fetchAll($db->query(
            "SELECT COALESCE(d.nombre,'Sin departamento') AS departamento,
                    e.numero_empleado,
                    CONCAT(e.nombre,' ',e.apellido_paterno,' ',COALESCE(e.apellido_materno,'')) AS empleado,
                    COALESCE(p.nombre,'') AS puesto, e.tipo_contrato,
                    e.fecha_ingreso, e.salario_base, e.estatus
             FROM empleados e
             LEFT JOIN departamentos d ON d.id = e.departamento_id
             LEFT JOIN puestos p ON p.id = e.puesto_id
             ORDER BY d.nombre, e.apellido_paterno"
        ));
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="headcount_'.date('Ymd').'.csv"');
        echo "\xEF\xBB\xBF";
        echo "Departamento,No. Empleado,Empleado,Puesto,Contrato,Fecha Ingreso,Salario Base,Estatus\n";
        foreach ($rows as $r) {
            $cols = [$r['departamento'],$r['numero_empleado'],$r['empleado'],$r['puesto'],
                     $r['tipo_contrato'],$r['fecha_ingreso'],$r['salario_base'],$r['estatus']];
            echo implode(',', array_map(fn($v) => '"'.str_replace('"','""',(string)$v).'"', $cols))."\n";
        }
        exit;
    }

    if ($exp === 'asistencia') {
        $rows = fetchAll($db->query(
            "SELECT e.numero_empleado,
                    CONCAT(e.nombre,' ',e.apellido_paterno,' ',COALESCE(e.apellido_materno,'')) AS empleado,
                    COALESCE(d.nombre,'') AS departamento,
                    COUNT(CASE WHEN a.tipo='normal' THEN 1 END) AS presentes,
                    COUNT(CASE WHEN a.tipo LIKE 'falta%' THEN 1 END) AS faltas,
                    COUNT(CASE WHEN a.tipo='vacaciones' THEN 1 END) AS vacaciones,
                    COUNT(CASE WHEN a.tipo='incapacidad' THEN 1 END) AS incapacidades,
                    COUNT(CASE WHEN a.retardo=1 THEN 1 END) AS retardos,
                    COALESCE(SUM(a.horas_extra),0) AS horas_extra
             FROM empleados e
             LEFT JOIN departamentos d ON d.id = e.departamento_id
             LEFT JOIN asistencia a ON a.empleado_id=e.id AND a.fecha BETWEEN '$fi_s' AND '$ff_s'
             WHERE e.estatus != 'baja_definitiva'
             GROUP BY e.id, e.numero_empleado, e.nombre, e.apellido_paterno, e.apellido_materno, d.nombre
             ORDER BY d.nombre, e.apellido_paterno"
        ));
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="asistencia_'.date('Ymd').'.csv"');
        echo "\xEF\xBB\xBF";
        echo "No. Empleado,Empleado,Departamento,Presentes,Faltas,Vacaciones,Incapacidades,Retardos,Horas Extra\n";
        foreach ($rows as $r) {
            $cols = [$r['numero_empleado'],$r['empleado'],$r['departamento'],
                     $r['presentes'],$r['faltas'],$r['vacaciones'],
                     $r['incapacidades'],$r['retardos'],$r['horas_extra']];
            echo implode(',', array_map(fn($v) => '"'.str_replace('"','""',(string)$v).'"', $cols))."\n";
        }
        exit;
    }

    if ($exp === 'nomina') {
        $rows = fetchAll($db->query(
            "SELECT tipo, fecha_inicio, fecha_fin, COALESCE(fecha_pago,'') AS fecha_pago,
                    estado, empleados_count, total_percepciones, total_deducciones, total_neto
             FROM nomina_periodos
             WHERE fecha_inicio BETWEEN '$fi_s' AND '$ff_s'
             ORDER BY fecha_inicio DESC"
        ));
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="nomina_'.date('Ymd').'.csv"');
        echo "\xEF\xBB\xBF";
        echo "Tipo,Inicio,Fin,Fecha Pago,Estado,Empleados,Total Percepciones,Total Deducciones,Neto Total\n";
        foreach ($rows as $r) {
            $cols = [$r['tipo'],$r['fecha_inicio'],$r['fecha_fin'],$r['fecha_pago'],
                     $r['estado'],$r['empleados_count'],
                     $r['total_percepciones'],$r['total_deducciones'],$r['total_neto']];
            echo implode(',', array_map(fn($v) => '"'.str_replace('"','""',(string)$v).'"', $cols))."\n";
        }
        exit;
    }

    if ($exp === 'incidencias') {
        $wh = "i.fecha_inicio BETWEEN '$fi_s' AND '$ff_s'";
        if ($tipo_f) $wh .= " AND i.tipo='".$db->real_escape_string($tipo_f)."'";
        if ($edo_f)  $wh .= " AND i.estado='".$db->real_escape_string($edo_f)."'";
        $rows = fetchAll($db->query(
            "SELECT e.numero_empleado,
                    CONCAT(e.nombre,' ',e.apellido_paterno) AS empleado,
                    COALESCE(d.nombre,'') AS departamento,
                    i.tipo, i.fecha_inicio, i.fecha_fin, i.dias,
                    i.estado, COALESCE(i.descripcion,'') AS descripcion,
                    COALESCE(i.aprobado_por,'') AS aprobado_por
             FROM incidencias i
             JOIN empleados e ON e.id = i.empleado_id
             LEFT JOIN departamentos d ON d.id = e.departamento_id
             WHERE $wh
             ORDER BY i.fecha_inicio DESC"
        ));
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="incidencias_'.date('Ymd').'.csv"');
        echo "\xEF\xBB\xBF";
        echo "No. Empleado,Empleado,Departamento,Tipo,Fecha Inicio,Fecha Fin,Días,Estado,Descripción,Aprobado Por\n";
        foreach ($rows as $r) {
            $cols = [$r['numero_empleado'],$r['empleado'],$r['departamento'],
                     $r['tipo'],$r['fecha_inicio'],$r['fecha_fin'],$r['dias'],
                     $r['estado'],$r['descripcion'],$r['aprobado_por']];
            echo implode(',', array_map(fn($v) => '"'.str_replace('"','""',(string)$v).'"', $cols))."\n";
        }
        exit;
    }
}

// ── Headcount ─────────────────────────────────────────────────────────────
$hc_depts = fetchAll($db->query(
    "SELECT COALESCE(d.nombre,'Sin departamento') AS departamento,
            COUNT(CASE WHEN e.estatus='activo' THEN 1 END) AS activos,
            COUNT(CASE WHEN e.estatus IN('vacaciones','incapacidad','baja_temporal') THEN 1 END) AS con_incidencia,
            COUNT(CASE WHEN e.estatus='baja_definitiva' THEN 1 END) AS bajas,
            COUNT(*) AS total
     FROM empleados e
     LEFT JOIN departamentos d ON d.id = e.departamento_id
     GROUP BY d.id, d.nombre
     ORDER BY d.nombre"
));

$est_where = $est_f ? " AND e.estatus='".$db->real_escape_string($est_f)."'" : '';
$hc_lista = fetchAll($db->query(
    "SELECT e.numero_empleado,
            CONCAT(e.nombre,' ',e.apellido_paterno,' ',COALESCE(e.apellido_materno,'')) AS empleado,
            COALESCE(d.nombre,'—') AS departamento,
            COALESCE(p.nombre,'—') AS puesto,
            e.tipo_contrato, e.fecha_ingreso, e.salario_base, e.estatus
     FROM empleados e
     LEFT JOIN departamentos d ON d.id = e.departamento_id
     LEFT JOIN puestos p ON p.id = e.puesto_id
     WHERE 1=1 $est_where
     ORDER BY d.nombre, e.apellido_paterno"
));
$hc_activos  = array_sum(array_column($hc_depts, 'activos'));
$hc_incid    = array_sum(array_column($hc_depts, 'con_incidencia'));
$hc_bajas    = array_sum(array_column($hc_depts, 'bajas'));
$hc_total    = array_sum(array_column($hc_depts, 'total'));

// ── Asistencia ────────────────────────────────────────────────────────────
$asis_rows = fetchAll($db->query(
    "SELECT e.numero_empleado,
            CONCAT(e.nombre,' ',e.apellido_paterno,' ',COALESCE(e.apellido_materno,'')) AS empleado,
            COALESCE(d.nombre,'—') AS departamento,
            COUNT(CASE WHEN a.tipo='normal' THEN 1 END) AS presentes,
            COUNT(CASE WHEN a.tipo LIKE 'falta%' THEN 1 END) AS faltas,
            COUNT(CASE WHEN a.tipo='vacaciones' THEN 1 END) AS vacaciones,
            COUNT(CASE WHEN a.tipo='incapacidad' THEN 1 END) AS incapacidades,
            COUNT(CASE WHEN a.retardo=1 THEN 1 END) AS retardos,
            COALESCE(SUM(a.horas_extra),0) AS horas_extra
     FROM empleados e
     LEFT JOIN departamentos d ON d.id = e.departamento_id
     LEFT JOIN asistencia a ON a.empleado_id=e.id AND a.fecha BETWEEN '$fi_s' AND '$ff_s'
     WHERE e.estatus != 'baja_definitiva'
     GROUP BY e.id, e.numero_empleado, e.nombre, e.apellido_paterno, e.apellido_materno, d.nombre
     ORDER BY d.nombre, e.apellido_paterno"
));

// ── Nómina ────────────────────────────────────────────────────────────────
$nom_rows = fetchAll($db->query(
    "SELECT id, tipo, fecha_inicio, fecha_fin, fecha_pago,
            estado, empleados_count,
            total_percepciones, total_deducciones, total_neto
     FROM nomina_periodos
     WHERE fecha_inicio BETWEEN '$fi_s' AND '$ff_s'
     ORDER BY fecha_inicio DESC"
));
$nom_percepciones = array_sum(array_column($nom_rows, 'total_percepciones'));
$nom_deducciones  = array_sum(array_column($nom_rows, 'total_deducciones'));
$nom_neto         = array_sum(array_column($nom_rows, 'total_neto'));

// ── Incidencias ───────────────────────────────────────────────────────────
$inc_where = "i.fecha_inicio BETWEEN '$fi_s' AND '$ff_s'";
if ($tipo_f) $inc_where .= " AND i.tipo='".$db->real_escape_string($tipo_f)."'";
if ($edo_f)  $inc_where .= " AND i.estado='".$db->real_escape_string($edo_f)."'";
$inc_rows = fetchAll($db->query(
    "SELECT i.id,
            CONCAT(e.nombre,' ',e.apellido_paterno) AS empleado,
            e.numero_empleado,
            COALESCE(d.nombre,'—') AS departamento,
            i.tipo, i.fecha_inicio, i.fecha_fin, i.dias,
            i.estado, COALESCE(i.descripcion,'') AS descripcion,
            COALESCE(i.aprobado_por,'') AS aprobado_por
     FROM incidencias i
     JOIN empleados e ON e.id = i.empleado_id
     LEFT JOIN departamentos d ON d.id = e.departamento_id
     WHERE $inc_where
     ORDER BY i.fecha_inicio DESC"
));
$inc_pend = count(array_filter($inc_rows, fn($r) => $r['estado'] === 'pendiente'));
$inc_apro = count(array_filter($inc_rows, fn($r) => $r['estado'] === 'aprobada'));
$inc_rech = count(array_filter($inc_rows, fn($r) => $r['estado'] === 'rechazada'));

// ── Helpers vista ─────────────────────────────────────────────────────────
$TIPOS_INC = [
    'falta_justificada'  => 'Falta Justificada',
    'incapacidad_imss'   => 'Incapacidad IMSS',
    'incapacidad_empresa'=> 'Incapacidad Empresa',
    'vacaciones'         => 'Vacaciones',
    'permiso_goce'       => 'Permiso con Goce',
    'permiso_sin_goce'   => 'Permiso sin Goce',
    'suspension'         => 'Suspensión',
    'otro'               => 'Otro',
];
$ESTATUS_BADGE = [
    'activo'          => 'badge-green',
    'baja_definitiva' => 'badge-red',
    'vacaciones'      => 'badge-blue',
    'incapacidad'     => 'badge-yellow',
    'baja_temporal'   => 'badge-gray',
];
$ESTADO_BADGE = [
    'pendiente'  => 'badge-yellow',
    'aprobada'   => 'badge-green',
    'rechazada'  => 'badge-red',
    'calculado'  => 'badge-blue',
    'cerrado'    => 'badge-gray',
    'pagado'     => 'badge-green',
    'borrador'   => 'badge-yellow',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reportes — Sharclean Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,-apple-system,sans-serif;background:#f8fafc;color:#1e293b;font-size:14px}
a{color:inherit;text-decoration:none}
.admin-header{background:#0284C7;color:#fff;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}.admin-brand{display:flex;align-items:center;gap:10px}.admin-brand img{height:36px}
.admin-header h1{font-size:18px;font-weight:800}
.nav-links{display:flex;gap:4px;align-items:center}
.nav-links a{padding:6px 12px;border-radius:6px;font-size:13px;color:rgba(255,255,255,.85);transition:background .15s}
.nav-links a:hover,.nav-links a.active{background:rgba(255,255,255,.2);color:#fff}
.body{padding:24px;max-width:1400px;margin:0 auto}
/* Tabs */
.tabs{display:flex;gap:2px;border-bottom:2px solid #e2e8f0;margin-bottom:24px}
.tab-btn{padding:10px 20px;border:none;background:none;cursor:pointer;font-size:14px;color:#64748b;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .15s;font-family:inherit}
.tab-btn.active{color:#0284C7;border-bottom-color:#0284C7;font-weight:600}
.tab-btn:hover:not(.active){color:#1e293b;background:#f1f5f9;border-radius:6px 6px 0 0}
/* Filtros */
.filter-bar{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:20px}
.filter-bar label{font-size:12px;color:#64748b;display:flex;flex-direction:column;gap:4px}
.filter-bar input,.filter-bar select{padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;background:#fff;font-family:inherit}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:6px;border:none;cursor:pointer;font-size:13px;font-family:inherit;font-weight:500;transition:background .15s}
.btn-primary{background:#0284C7;color:#fff}.btn-primary:hover{background:#0369a1}
.btn-outline{background:#fff;color:#475569;border:1px solid #cbd5e1}.btn-outline:hover{background:#f1f5f9}
.btn-sm{padding:5px 10px;font-size:12px}
/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px}
.stat-card{background:#fff;border-radius:10px;padding:16px 20px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.stat-card .val{font-size:28px;font-weight:800;color:#0284C7}
.stat-card .lbl{font-size:12px;color:#64748b;margin-top:2px}
/* Tabla */
.tbl-wrap{background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.06);overflow:hidden}
.tbl-head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #f1f5f9}
.tbl-head h3{font-size:15px;font-weight:700}
table{width:100%;border-collapse:collapse}
th{padding:10px 14px;text-align:left;font-size:12px;font-weight:600;color:#64748b;background:#f8fafc;border-bottom:1px solid #e2e8f0;white-space:nowrap}
td{padding:10px 14px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#f8fafc}
.text-right{text-align:right}
.text-num{font-variant-numeric:tabular-nums}
/* Badges */
.badge{display:inline-block;padding:2px 8px;border-radius:99px;font-size:11px;font-weight:600}
.badge-green{background:#dcfce7;color:#15803d}
.badge-red{background:#fee2e2;color:#b91c1c}
.badge-yellow{background:#fef9c3;color:#854d0e}
.badge-blue{background:#dbeafe;color:#1d4ed8}
.badge-gray{background:#f1f5f9;color:#475569}
/* Resumen por departamento */
.dept-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-bottom:20px}
.dept-card{background:#fff;border-radius:10px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.06)}
.dept-card h4{font-size:13px;font-weight:700;margin-bottom:10px;color:#1e293b}
.dept-card .row-stat{display:flex;justify-content:space-between;font-size:12px;padding:3px 0;border-bottom:1px solid #f1f5f9}
.dept-card .row-stat:last-child{border:none;font-weight:700}
.no-data{padding:48px;text-align:center;color:#94a3b8;font-size:13px}
</style>
</head>
<body>

<header class="admin-header">
  <div class="admin-brand">
    <img src="../images/logo.png" alt="Sharclean">
    <h1>📊 Reportes</h1>
  </div>
  <div class="nav-links">
    <a href="index.php">Panel</a>
    <a href="empleados.php">👥 Empleados</a>
    <a href="asistencia.php">📅 Asistencia</a>
    <a href="nomina.php">📋 Nómina</a>
    <a href="vacaciones.php">🏖 Vacaciones</a>
    <a href="finiquitos.php">💼 Finiquitos</a>
    <a href="reportes.php" class="active">📊 Reportes</a>
    <a href="logout.php">Cerrar sesión</a>
  </div>
</header>

<div class="body">

  <!-- Tabs -->
  <div class="tabs">
    <?php foreach (['headcount'=>'👥 Headcount','asistencia'=>'📅 Asistencia','nomina'=>'📋 Nómina','incidencias'=>'📋 Incidencias'] as $t=>$lbl): ?>
      <button class="tab-btn <?= $tab===$t?'active':'' ?>" onclick="location.href='?tab=<?= $t ?>&fi=<?= h($fi) ?>&ff=<?= h($ff) ?>'">
        <?= $lbl ?>
      </button>
    <?php endforeach ?>
  </div>

  <?php
  function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
  function money($v): string { return '$'.number_format((float)$v, 2); }
  function badge(string $v, array $map): string {
    $cls = $map[$v] ?? 'badge-gray';
    return '<span class="badge '.$cls.'">'.h($v).'</span>';
  }
  ?>

  <!-- ═══════════════════════════════════ HEADCOUNT ═══════════════════════ -->
  <?php if ($tab === 'headcount'): ?>

  <div class="stats-row">
    <div class="stat-card"><div class="val"><?= $hc_total ?></div><div class="lbl">Total empleados</div></div>
    <div class="stat-card"><div class="val" style="color:#16a34a"><?= $hc_activos ?></div><div class="lbl">Activos</div></div>
    <div class="stat-card"><div class="val" style="color:#d97706"><?= $hc_incid ?></div><div class="lbl">Con incidencia</div></div>
    <div class="stat-card"><div class="val" style="color:#dc2626"><?= $hc_bajas ?></div><div class="lbl">Bajas definitivas</div></div>
  </div>

  <div class="dept-grid">
    <?php foreach ($hc_depts as $d): ?>
    <div class="dept-card">
      <h4><?= h($d['departamento']) ?></h4>
      <div class="row-stat"><span>Activos</span><span style="color:#16a34a;font-weight:700"><?= $d['activos'] ?></span></div>
      <div class="row-stat"><span>Con incidencia</span><span style="color:#d97706"><?= $d['con_incidencia'] ?></span></div>
      <div class="row-stat"><span>Bajas</span><span style="color:#dc2626"><?= $d['bajas'] ?></span></div>
      <div class="row-stat"><span>Total</span><span><?= $d['total'] ?></span></div>
    </div>
    <?php endforeach ?>
  </div>

  <div class="tbl-wrap">
    <div class="tbl-head">
      <h3>Directorio de empleados</h3>
      <div style="display:flex;gap:8px;align-items:center">
        <form method="get" style="display:flex;gap:8px;align-items:center">
          <input type="hidden" name="tab" value="headcount">
          <select name="estatus" onchange="this.form.submit()" style="padding:6px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px">
            <option value="">Todos los estatus</option>
            <option value="activo"          <?= $est_f==='activo'?'selected':'' ?>>Activo</option>
            <option value="baja_definitiva" <?= $est_f==='baja_definitiva'?'selected':'' ?>>Baja definitiva</option>
            <option value="vacaciones"      <?= $est_f==='vacaciones'?'selected':'' ?>>Vacaciones</option>
            <option value="incapacidad"     <?= $est_f==='incapacidad'?'selected':'' ?>>Incapacidad</option>
            <option value="baja_temporal"   <?= $est_f==='baja_temporal'?'selected':'' ?>>Baja temporal</option>
          </select>
        </form>
        <a href="?tab=headcount&export=headcount&fi=<?= h($fi) ?>&ff=<?= h($ff) ?>" class="btn btn-outline btn-sm">⬇ CSV</a>
      </div>
    </div>
    <?php if (empty($hc_lista)): ?>
      <div class="no-data">Sin resultados</div>
    <?php else: ?>
    <table>
      <thead><tr>
        <th>No.</th><th>Empleado</th><th>Departamento</th><th>Puesto</th>
        <th>Contrato</th><th>Ingreso</th><th class="text-right">Salario Base</th><th>Estatus</th>
      </tr></thead>
      <tbody>
      <?php foreach ($hc_lista as $r): ?>
        <tr>
          <td class="text-num"><?= h($r['numero_empleado']) ?></td>
          <td><?= h($r['empleado']) ?></td>
          <td><?= h($r['departamento']) ?></td>
          <td><?= h($r['puesto']) ?></td>
          <td><?= h($r['tipo_contrato']) ?></td>
          <td class="text-num"><?= h($r['fecha_ingreso']) ?></td>
          <td class="text-right text-num"><?= money($r['salario_base']) ?></td>
          <td><?= badge($r['estatus'], $ESTATUS_BADGE) ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>

  <!-- ═══════════════════════════════════ ASISTENCIA ═══════════════════════ -->
  <?php elseif ($tab === 'asistencia'): ?>

  <form method="get" class="filter-bar">
    <input type="hidden" name="tab" value="asistencia">
    <label>Desde<input type="date" name="fi" value="<?= h($fi) ?>"></label>
    <label>Hasta<input type="date" name="ff" value="<?= h($ff) ?>"></label>
    <button type="submit" class="btn btn-primary">Filtrar</button>
    <a href="?tab=asistencia&export=asistencia&fi=<?= h($fi) ?>&ff=<?= h($ff) ?>" class="btn btn-outline">⬇ CSV</a>
  </form>

  <?php
  $tot_pres  = array_sum(array_column($asis_rows, 'presentes'));
  $tot_falt  = array_sum(array_column($asis_rows, 'faltas'));
  $tot_ret   = array_sum(array_column($asis_rows, 'retardos'));
  $tot_he    = array_sum(array_column($asis_rows, 'horas_extra'));
  ?>
  <div class="stats-row">
    <div class="stat-card"><div class="val"><?= $tot_pres ?></div><div class="lbl">Registros presentes</div></div>
    <div class="stat-card"><div class="val" style="color:#dc2626"><?= $tot_falt ?></div><div class="lbl">Faltas</div></div>
    <div class="stat-card"><div class="val" style="color:#d97706"><?= $tot_ret ?></div><div class="lbl">Retardos</div></div>
    <div class="stat-card"><div class="val" style="color:#7c3aed"><?= number_format((float)$tot_he,1) ?></div><div class="lbl">Horas extra</div></div>
  </div>

  <div class="tbl-wrap">
    <div class="tbl-head">
      <h3>Resumen por empleado — <?= h($fi) ?> a <?= h($ff) ?></h3>
    </div>
    <?php if (empty($asis_rows)): ?>
      <div class="no-data">Sin registros en el período</div>
    <?php else: ?>
    <table>
      <thead><tr>
        <th>No.</th><th>Empleado</th><th>Departamento</th>
        <th class="text-right">Presentes</th><th class="text-right">Faltas</th>
        <th class="text-right">Vacaciones</th><th class="text-right">Incapacidades</th>
        <th class="text-right">Retardos</th><th class="text-right">Horas Extra</th>
      </tr></thead>
      <tbody>
      <?php foreach ($asis_rows as $r): ?>
        <tr>
          <td class="text-num"><?= h($r['numero_empleado']) ?></td>
          <td><?= h($r['empleado']) ?></td>
          <td><?= h($r['departamento']) ?></td>
          <td class="text-right text-num"><?= $r['presentes'] ?></td>
          <td class="text-right text-num <?= $r['faltas']>0?'style="color:#dc2626;font-weight:700"':'' ?>"><?= $r['faltas'] ?></td>
          <td class="text-right text-num"><?= $r['vacaciones'] ?></td>
          <td class="text-right text-num"><?= $r['incapacidades'] ?></td>
          <td class="text-right text-num <?= $r['retardos']>0?'style="color:#d97706"':'' ?>"><?= $r['retardos'] ?></td>
          <td class="text-right text-num"><?= number_format((float)$r['horas_extra'],1) ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>

  <!-- ═══════════════════════════════════ NÓMINA ═══════════════════════════ -->
  <?php elseif ($tab === 'nomina'): ?>

  <form method="get" class="filter-bar">
    <input type="hidden" name="tab" value="nomina">
    <label>Desde<input type="date" name="fi" value="<?= h($fi) ?>"></label>
    <label>Hasta<input type="date" name="ff" value="<?= h($ff) ?>"></label>
    <button type="submit" class="btn btn-primary">Filtrar</button>
    <a href="?tab=nomina&export=nomina&fi=<?= h($fi) ?>&ff=<?= h($ff) ?>" class="btn btn-outline">⬇ CSV</a>
  </form>

  <div class="stats-row">
    <div class="stat-card"><div class="val"><?= count($nom_rows) ?></div><div class="lbl">Períodos en rango</div></div>
    <div class="stat-card"><div class="val"><?= money($nom_percepciones) ?></div><div class="lbl">Total percepciones</div></div>
    <div class="stat-card"><div class="val" style="color:#dc2626"><?= money($nom_deducciones) ?></div><div class="lbl">Total deducciones</div></div>
    <div class="stat-card"><div class="val" style="color:#16a34a"><?= money($nom_neto) ?></div><div class="lbl">Total neto pagado</div></div>
  </div>

  <div class="tbl-wrap">
    <div class="tbl-head">
      <h3>Períodos de nómina — <?= h($fi) ?> a <?= h($ff) ?></h3>
    </div>
    <?php if (empty($nom_rows)): ?>
      <div class="no-data">Sin períodos en el rango seleccionado</div>
    <?php else: ?>
    <table>
      <thead><tr>
        <th>Tipo</th><th>Inicio</th><th>Fin</th><th>Fecha Pago</th>
        <th>Estado</th><th class="text-right">Empleados</th>
        <th class="text-right">Percepciones</th><th class="text-right">Deducciones</th>
        <th class="text-right">Neto</th>
      </tr></thead>
      <tbody>
      <?php foreach ($nom_rows as $r): ?>
        <tr>
          <td><?= ucfirst(h($r['tipo'])) ?></td>
          <td class="text-num"><?= h($r['fecha_inicio']) ?></td>
          <td class="text-num"><?= h($r['fecha_fin']) ?></td>
          <td class="text-num"><?= $r['fecha_pago'] ? h($r['fecha_pago']) : '—' ?></td>
          <td><?= badge($r['estado'], $ESTADO_BADGE) ?></td>
          <td class="text-right text-num"><?= $r['empleados_count'] ?></td>
          <td class="text-right text-num"><?= money($r['total_percepciones']) ?></td>
          <td class="text-right text-num"><?= money($r['total_deducciones']) ?></td>
          <td class="text-right text-num" style="font-weight:700"><?= money($r['total_neto']) ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
      <tfoot>
        <tr style="background:#f8fafc;font-weight:700">
          <td colspan="6" style="padding:10px 14px;color:#64748b;font-size:12px">TOTALES</td>
          <td class="text-right text-num" style="padding:10px 14px"><?= money($nom_percepciones) ?></td>
          <td class="text-right text-num" style="padding:10px 14px"><?= money($nom_deducciones) ?></td>
          <td class="text-right text-num" style="padding:10px 14px;color:#16a34a"><?= money($nom_neto) ?></td>
        </tr>
      </tfoot>
    </table>
    <?php endif ?>
  </div>

  <!-- ═══════════════════════════════════ INCIDENCIAS ══════════════════════ -->
  <?php elseif ($tab === 'incidencias'): ?>

  <form method="get" class="filter-bar">
    <input type="hidden" name="tab" value="incidencias">
    <label>Desde<input type="date" name="fi" value="<?= h($fi) ?>"></label>
    <label>Hasta<input type="date" name="ff" value="<?= h($ff) ?>"></label>
    <label>Tipo
      <select name="tipo">
        <option value="">Todos</option>
        <?php foreach ($TIPOS_INC as $k=>$v): ?>
          <option value="<?= h($k) ?>" <?= $tipo_f===$k?'selected':'' ?>><?= h($v) ?></option>
        <?php endforeach ?>
      </select>
    </label>
    <label>Estado
      <select name="estado">
        <option value="">Todos</option>
        <option value="pendiente"  <?= $edo_f==='pendiente'?'selected':'' ?>>Pendiente</option>
        <option value="aprobada"   <?= $edo_f==='aprobada'?'selected':'' ?>>Aprobada</option>
        <option value="rechazada"  <?= $edo_f==='rechazada'?'selected':'' ?>>Rechazada</option>
      </select>
    </label>
    <button type="submit" class="btn btn-primary">Filtrar</button>
    <a href="?tab=incidencias&export=incidencias&fi=<?= h($fi) ?>&ff=<?= h($ff) ?>&tipo=<?= h($tipo_f) ?>&estado=<?= h($edo_f) ?>" class="btn btn-outline">⬇ CSV</a>
  </form>

  <div class="stats-row">
    <div class="stat-card"><div class="val"><?= count($inc_rows) ?></div><div class="lbl">Total incidencias</div></div>
    <div class="stat-card"><div class="val" style="color:#d97706"><?= $inc_pend ?></div><div class="lbl">Pendientes</div></div>
    <div class="stat-card"><div class="val" style="color:#16a34a"><?= $inc_apro ?></div><div class="lbl">Aprobadas</div></div>
    <div class="stat-card"><div class="val" style="color:#dc2626"><?= $inc_rech ?></div><div class="lbl">Rechazadas</div></div>
  </div>

  <div class="tbl-wrap">
    <div class="tbl-head">
      <h3>Incidencias — <?= h($fi) ?> a <?= h($ff) ?></h3>
    </div>
    <?php if (empty($inc_rows)): ?>
      <div class="no-data">Sin incidencias en el rango seleccionado</div>
    <?php else: ?>
    <table>
      <thead><tr>
        <th>No.</th><th>Empleado</th><th>Departamento</th><th>Tipo</th>
        <th>Inicio</th><th>Fin</th><th class="text-right">Días</th>
        <th>Estado</th><th>Aprobado Por</th>
      </tr></thead>
      <tbody>
      <?php foreach ($inc_rows as $r): ?>
        <tr>
          <td class="text-num"><?= h($r['numero_empleado']) ?></td>
          <td><?= h($r['empleado']) ?></td>
          <td><?= h($r['departamento']) ?></td>
          <td><?= h($TIPOS_INC[$r['tipo']] ?? $r['tipo']) ?></td>
          <td class="text-num"><?= h($r['fecha_inicio']) ?></td>
          <td class="text-num"><?= h($r['fecha_fin']) ?></td>
          <td class="text-right text-num"><?= $r['dias'] ?></td>
          <td><?= badge($r['estado'], $ESTADO_BADGE) ?></td>
          <td><?= $r['aprobado_por'] ? h($r['aprobado_por']) : '—' ?></td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php endif ?>
  </div>

  <?php endif ?>

</div><!-- /body -->
</body>
</html>
