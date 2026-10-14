<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requirePermiso('asistencia');
require_once __DIR__ . '/../db/csrf.php';
require_once __DIR__ . '/../db/asistencia_calc.php';

/* ── Helpers ──────────────────────────────────────────────── */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fd(string $d): string { return $d ? date('d/m/Y', strtotime($d)) : '—'; }

function tbadge(string $tipo): string {
    $m = [
        'normal'              => ['#dcfce7','#166534', 'Normal'],
        'falta_justificada'   => ['#ffedd5','#9a3412', 'Falta Just.'],
        'falta_injustificada' => ['#fee2e2','#991b1b', 'Falta Injust.'],
        'vacaciones'          => ['#dbeafe','#1d4ed8', 'Vacaciones'],
        'incapacidad'         => ['#f3e8ff','#7e22ce', 'Incapacidad'],
        'permiso_goce'        => ['#ccfbf1','#0f766e', 'Permiso c/Goce'],
        'permiso_sin_goce'    => ['#fef9c3','#854d0e', 'Permiso s/Goce'],
        'descanso'            => ['#f1f5f9','#64748b', 'Descanso'],
        'festivo'             => ['#f1f5f9','#94a3b8', 'Festivo'],
    ];
    [$bg, $color, $label] = $m[$tipo] ?? ['#f1f5f9','#64748b', $tipo];
    return "<span style='display:inline-block;padding:2px 7px;border-radius:4px;font-size:11px;font-weight:700;background:$bg;color:$color;'>$label</span>";
}
function ibadge(string $estado): string {
    $m = ['pendiente'=>['#fef9c3','#854d0e','Pendiente'],'aprobada'=>['#dcfce7','#166534','Aprobada'],'rechazada'=>['#fee2e2','#991b1b','Rechazada']];
    [$bg,$c,$l] = $m[$estado] ?? ['#f1f5f9','#64748b',$estado];
    return "<span style='display:inline-block;padding:2px 7px;border-radius:4px;font-size:11px;font-weight:700;background:$bg;color:$c;'>$l</span>";
}
function fetchAll($result): array {
    $rows = [];
    if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}

$TIPOS_ASIST = ['normal'=>'Normal','falta_justificada'=>'Falta justificada','falta_injustificada'=>'Falta injustificada',
                'vacaciones'=>'Vacaciones','incapacidad'=>'Incapacidad','permiso_goce'=>'Permiso c/goce',
                'permiso_sin_goce'=>'Permiso s/goce','descanso'=>'Descanso','festivo'=>'Festivo'];
$TIPOS_INCID = ['falta_justificada'=>'Falta justificada','incapacidad_imss'=>'Incapacidad IMSS',
                'incapacidad_empresa'=>'Incapacidad empresa','vacaciones'=>'Vacaciones',
                'permiso_goce'=>'Permiso c/goce','permiso_sin_goce'=>'Permiso s/goce','suspension'=>'Suspensión','otro'=>'Otro'];

/* ── POST handler ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Token inválido.'); }
    $db  = getAdminDB();
    $act = $_POST['action'] ?? '';

    if ($act === 'save_asistencia') {
        $empleado_id = (int)($_POST['empleado_id'] ?? 0);
        $fecha       = trim($_POST['fecha']         ?? date('Y-m-d'));
        $tipo        = $_POST['tipo'] ?? 'normal';
        $global_tipos= ['normal','falta_justificada','falta_injustificada','vacaciones','incapacidad','permiso_goce','permiso_sin_goce','descanso','festivo'];
        if (!in_array($tipo, $global_tipos)) $tipo = 'normal';
        $hora_entrada  = trim($_POST['hora_entrada'] ?? '') ?: null;
        $hora_salida   = trim($_POST['hora_salida']  ?? '') ?: null;
        $observaciones = trim($_POST['observaciones']?? '') ?: null;

        if ($empleado_id > 0) {
            $emp = $db->query("SELECT jornada_horas, hora_entrada_esperada, tolerancia_min FROM empleados WHERE id=$empleado_id LIMIT 1")->fetch_assoc();
            $jornada = (int)($emp['jornada_horas'] ?? 8);
            $horas_trabajadas = null;
            $horas_extra      = 0.0;
            $retardo          = false;
            $minutos_retardo  = 0;

            if (in_array($tipo, ['normal','permiso_goce'])) {
                $horas_trabajadas = calcHorasTrabajadas($hora_entrada, $hora_salida);
                $horas_extra      = calcHorasExtra($horas_trabajadas, $jornada);
                [$retardo, $minutos_retardo] = calcRetardo($hora_entrada, $emp['hora_entrada_esperada'] ?? '08:00:00', (int)($emp['tolerancia_min'] ?? 10));
            }

            $ret_i = $retardo ? 1 : 0;
            $s = $db->prepare(
                'INSERT INTO asistencia
                   (empleado_id,fecha,tipo,hora_entrada,hora_salida,horas_trabajadas,horas_extra,retardo,minutos_retardo,observaciones)
                 VALUES (?,?,?,?,?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                   tipo=VALUES(tipo),hora_entrada=VALUES(hora_entrada),hora_salida=VALUES(hora_salida),
                   horas_trabajadas=VALUES(horas_trabajadas),horas_extra=VALUES(horas_extra),
                   retardo=VALUES(retardo),minutos_retardo=VALUES(minutos_retardo),observaciones=VALUES(observaciones)'
            );
            $s->bind_param('issssddiis', $empleado_id,$fecha,$tipo,$hora_entrada,$hora_salida,$horas_trabajadas,$horas_extra,$ret_i,$minutos_retardo,$observaciones);
            $s->execute();
        }
    } elseif ($act === 'bulk_present') {
        $fecha = trim($_POST['fecha'] ?? date('Y-m-d'));
        $emps  = fetchAll($db->query(
            "SELECT e.id, e.hora_entrada_esperada FROM empleados e
             LEFT JOIN asistencia a ON a.empleado_id = e.id AND a.fecha = '$fecha'
             WHERE e.estatus = 'activo' AND a.id IS NULL"
        ));
        $s = $db->prepare("INSERT IGNORE INTO asistencia (empleado_id,fecha,tipo,hora_entrada,capturado_por) VALUES (?,'$fecha','normal',?,'Bulk-Admin')");
        foreach ($emps as $emp) {
            $hora = $emp['hora_entrada_esperada'] ?? '08:00:00';
            $s->bind_param('is', $emp['id'], $hora);
            $s->execute();
        }
    } elseif ($act === 'del_asistencia') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) { $s = $db->prepare('DELETE FROM asistencia WHERE id=?'); $s->bind_param('i',$id); $s->execute(); }
    } elseif ($act === 'save_incidencia') {
        $emp_id  = (int)($_POST['inc_empleado_id'] ?? 0);
        $tipo_i  = $_POST['inc_tipo'] ?? '';
        $fi      = trim($_POST['inc_fecha_inicio'] ?? '');
        $ff      = trim($_POST['inc_fecha_fin']    ?? '');
        $desc    = trim($_POST['inc_descripcion']  ?? '') ?: null;
        $tipos_v = ['falta_justificada','incapacidad_imss','incapacidad_empresa','vacaciones','permiso_goce','permiso_sin_goce','suspension','otro'];
        if ($emp_id > 0 && in_array($tipo_i, $tipos_v) && $fi && $ff) {
            $dias = (int)((strtotime($ff) - strtotime($fi)) / 86400) + 1;
            $s = $db->prepare('INSERT INTO incidencias (empleado_id,tipo,fecha_inicio,fecha_fin,dias,descripcion) VALUES (?,?,?,?,?,?)');
            $s->bind_param('isssss', $emp_id,$tipo_i,$fi,$ff,$dias,$desc);
            $s->execute();
        }
    } elseif ($act === 'upd_incidencia') {
        $id     = (int)($_POST['id']     ?? 0);
        $estado = $_POST['estado'] ?? '';
        if ($id > 0 && in_array($estado, ['aprobada','rechazada'])) {
            $s = $db->prepare("UPDATE incidencias SET estado=?,aprobado_por='Admin' WHERE id=?");
            $s->bind_param('si', $estado, $id);
            $s->execute();
        }
    } elseif ($act === 'del_incidencia') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) { $s = $db->prepare('DELETE FROM incidencias WHERE id=?'); $s->bind_param('i',$id); $s->execute(); }
    }

    $db->close();
    $back_fecha  = $_POST['fecha'] ?? date('Y-m-d');
    $back_view   = $_POST['back_view'] ?? 'dia';
    header("Location: asistencia.php?view=$back_view&fecha=$back_fecha"); exit;
}

/* ── Fetch data ───────────────────────────────────────────── */
$db   = getAdminDB();
$csrf = generateCsrfToken();

$view   = in_array($_GET['view'] ?? '', ['dia','resumen','incidencias']) ? $_GET['view'] : 'dia';
$fecha  = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $fecha = date('Y-m-d');
$mes    = substr($fecha, 0, 7);  // YYYY-MM
if (isset($_GET['mes']) && preg_match('/^\d{4}-\d{2}$/', $_GET['mes'])) $mes = $_GET['mes'];
$f_dept = (int)($_GET['f_dept'] ?? 0);

/* Stats del día */
$today         = date('Y-m-d');
$cPresentes    = (int)$db->query("SELECT COUNT(*) FROM asistencia WHERE fecha='$today' AND tipo='normal'")->fetch_row()[0];
$cFaltas       = (int)$db->query("SELECT COUNT(*) FROM asistencia WHERE fecha='$today' AND tipo LIKE 'falta%'")->fetch_row()[0];
$cRetardos     = (int)$db->query("SELECT COUNT(*) FROM asistencia WHERE fecha='$today' AND retardo=1")->fetch_row()[0];
$cActivos      = (int)$db->query("SELECT COUNT(*) FROM empleados WHERE estatus='activo'")->fetch_row()[0];
$mes_ini       = $mes . '-01';
$mes_fin       = date('Y-m-t', strtotime($mes_ini));
$horasExtraMes = (float)($db->query("SELECT COALESCE(SUM(horas_extra),0) FROM asistencia WHERE fecha BETWEEN '$mes_ini' AND '$mes_fin'")->fetch_row()[0] ?? 0);

/* Catálogos */
$departamentos = fetchAll($db->query('SELECT id,nombre FROM departamentos WHERE activo=1 ORDER BY nombre'));
$empleadosAll  = fetchAll($db->query("SELECT id, CONCAT(nombre,' ',apellido_paterno) AS nombre FROM empleados WHERE estatus='activo' ORDER BY apellido_paterno,nombre"));

/* ── Vista: Día ── */
$roster = [];
if ($view === 'dia') {
    $deptWhere = $f_dept ? "AND e.departamento_id=$f_dept" : '';
    $roster = fetchAll($db->query(
        "SELECT e.id, e.numero_empleado,
                CONCAT(e.nombre,' ',e.apellido_paterno) AS nombre_completo,
                e.jornada_horas, e.hora_entrada_esperada, e.tolerancia_min,
                d.nombre AS dept_nombre,
                a.id AS asist_id, a.tipo, a.hora_entrada, a.hora_salida,
                a.horas_trabajadas, a.horas_extra, a.retardo, a.minutos_retardo, a.observaciones,
                a.capturado_por, a.checkin_lat, a.checkin_lng, a.checkout_lat, a.checkout_lng,
                a.checkin_foto, a.checkout_foto, a.checkin_face_score, a.checkout_face_score,
                a.revision_manual
         FROM empleados e
         LEFT JOIN departamentos d ON d.id = e.departamento_id
         LEFT JOIN asistencia a ON a.empleado_id = e.id AND a.fecha = '$fecha'
         WHERE e.estatus = 'activo' $deptWhere
         ORDER BY d.nombre, e.apellido_paterno, e.nombre"
    ));
}

/* ── Vista: Resumen mensual ── */
$resumenEmps = [];
$pivotData   = [];
$diasEnMes   = 0;
if ($view === 'resumen') {
    $deptWhere = $f_dept ? "AND e.departamento_id=$f_dept" : '';
    $resumenEmps = fetchAll($db->query(
        "SELECT e.id, e.numero_empleado,
                CONCAT(e.nombre,' ',e.apellido_paterno) AS nombre_completo,
                d.nombre AS dept_nombre
         FROM empleados e
         LEFT JOIN departamentos d ON d.id = e.departamento_id
         WHERE e.estatus='activo' $deptWhere
         ORDER BY d.nombre, e.apellido_paterno"
    ));

    $asistMes = fetchAll($db->query(
        "SELECT empleado_id, fecha, tipo, horas_trabajadas, horas_extra, retardo, minutos_retardo
         FROM asistencia
         WHERE fecha BETWEEN '$mes_ini' AND '$mes_fin'"
    ));

    $diasEnMes = (int)date('t', strtotime($mes_ini));
    foreach ($resumenEmps as $e) $pivotData[$e['id']] = array_fill(1, $diasEnMes, null);
    foreach ($asistMes as $row) {
        $day = (int)date('j', strtotime($row['fecha']));
        $pivotData[$row['empleado_id']][$day] = $row;
    }
}

/* ── Vista: Incidencias ── */
$incidencias = [];
if ($view === 'incidencias') {
    $incidencias = fetchAll($db->query(
        "SELECT i.*, CONCAT(e.nombre,' ',e.apellido_paterno) AS emp_nombre, e.numero_empleado
         FROM incidencias i
         JOIN empleados e ON e.id = i.empleado_id
         ORDER BY i.estado ASC, i.fecha_inicio DESC
         LIMIT 200"
    ));
}

$db->close();

/* Helpers de vista */
$fechaLabel = date('l d \d\e F \d\e Y', strtotime($fecha));
$mesLabel   = date('F Y', strtotime($mes_ini));
$prevFecha  = date('Y-m-d', strtotime($fecha . ' -1 day'));
$nextFecha  = date('Y-m-d', strtotime($fecha . ' +1 day'));
$prevMes    = date('Y-m', strtotime($mes_ini . ' -1 month'));
$nextMes    = date('Y-m', strtotime($mes_ini . ' +1 month'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Asistencia – Sharclean Admin</title>
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
.body{max-width:1500px;margin:0 auto;padding:20px 16px;}
/* Stats */
.stats{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:20px;}
.stat{background:#fff;border-radius:10px;padding:16px 20px;box-shadow:0 1px 3px rgba(0,0,0,.07);}
.stat-val{font-size:28px;font-weight:800;color:#0284C7;}
.stat-lbl{font-size:12px;color:#64748b;margin-top:3px;}
/* Tabs */
.tabs{display:flex;gap:6px;margin-bottom:16px;}
.tab-link{padding:8px 18px;border-radius:8px;color:#64748b;background:#fff;border:1px solid #e2e8f0;font-weight:500;font-size:13px;}
.tab-link:hover{border-color:#0284C7;color:#0284C7;}
.tab-link.active{background:#0284C7;color:#fff;border-color:#0284C7;}
/* Cards */
.card{background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.07);overflow:hidden;margin-bottom:16px;}
.card-header{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-bottom:1px solid #f1f5f9;flex-wrap:wrap;gap:8px;}
.card-header h2{font-size:14px;font-weight:700;}
/* Nav fecha */
.date-nav{display:flex;align-items:center;gap:8px;}
.date-nav a,.date-nav input{padding:5px 10px;border-radius:6px;border:1.5px solid #e2e8f0;font-size:13px;background:#fff;cursor:pointer;}
.date-nav a{color:#475569;}
.date-nav a:hover{border-color:#0284C7;color:#0284C7;}
/* Filtros */
.filter-bar{display:flex;gap:8px;align-items:center;padding:10px 18px;background:#f8fafc;border-bottom:1px solid #e2e8f0;flex-wrap:wrap;}
.filter-bar select{padding:5px 8px;border-radius:5px;border:1.5px solid #e2e8f0;font-size:12px;background:#fff;}
/* Table */
.tbl-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th{background:#f8fafc;padding:9px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap;}
td{padding:8px 12px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#f8fafc;}
/* Form elements */
.inp-sm{padding:5px 7px;border:1.5px solid #e2e8f0;border-radius:5px;font-size:12px;font-family:inherit;outline:none;background:#fff;}
.inp-sm:focus{border-color:#0284C7;}
select.inp-sm{min-width:130px;}
input[type=time].inp-sm{width:100px;}
input[type=text].inp-sm{width:180px;}
/* Buttons */
.btn{padding:5px 11px;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;border:none;white-space:nowrap;}
.btn-primary{background:#0284C7;color:#fff;}
.btn-primary:hover{background:#0369a1;}
.btn-muted{background:#e2e8f0;color:#475569;}
.btn-muted:hover{background:#cbd5e1;}
.btn-danger{background:#fee2e2;color:#dc2626;}
.btn-success{background:#dcfce7;color:#166534;}
.btn-warning{background:#fef9c3;color:#854d0e;}
/* Roster */
.emp-name{font-weight:600;font-size:13px;}
.emp-sub{font-size:11px;color:#94a3b8;margin-top:1px;}
.num-emp{font-size:11px;font-weight:700;color:#7c3aed;background:#ede9fe;padding:1px 6px;border-radius:3px;}
.retardo-badge{display:inline-block;padding:1px 6px;border-radius:4px;font-size:10px;font-weight:700;background:#fef3c7;color:#92400e;}
.chk-audit{display:inline-flex;gap:6px;align-items:center;font-size:11px;color:#0369a1;background:#f0f9ff;padding:2px 8px;border-radius:5px;border:1px solid #bae6fd;}
.chk-audit a{color:#0284C7;text-decoration:underline;}
/* Resumen grid */
.res-cell{display:block;text-align:center;font-size:10px;font-weight:700;padding:3px 2px;border-radius:3px;min-width:22px;}
.res-empty{background:#f8fafc;color:#cbd5e1;}
/* Incidencias form */
.inc-form{padding:16px 18px;background:#f8fafc;border-bottom:1px solid #e2e8f0;}
.inc-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;align-items:end;}
.inc-field label{display:block;font-size:11px;font-weight:600;color:#475569;margin-bottom:4px;}
.vf-inp{width:100%;padding:6px 8px;border:1.5px solid #e2e8f0;border-radius:5px;font-size:13px;font-family:inherit;outline:none;background:#fff;}
.vf-inp:focus{border-color:#0284C7;}
.empty{padding:48px;text-align:center;color:#94a3b8;font-size:14px;}
@media(max-width:900px){.stats{grid-template-columns:repeat(3,1fr);}.inc-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:580px){.stats{grid-template-columns:repeat(2,1fr);}}
</style>
</head>
<body>

<?php $navTitle = '📅 Asistencia'; $activeModule = 'asistencia'; require __DIR__ . '/_nav.php'; ?>

<div class="body">

  <!-- Stats -->
  <div class="stats">
    <div class="stat">
      <div class="stat-val"><?= $cPresentes ?></div>
      <div class="stat-lbl">Presentes hoy</div>
    </div>
    <div class="stat">
      <div class="stat-val" style="color:#dc2626;"><?= $cFaltas ?></div>
      <div class="stat-lbl">Faltas hoy</div>
    </div>
    <div class="stat">
      <div class="stat-val" style="color:#d97706;"><?= $cRetardos ?></div>
      <div class="stat-lbl">Retardos hoy</div>
    </div>
    <div class="stat">
      <div class="stat-val" style="color:#6d28d9;"><?= number_format($horasExtraMes, 1) ?></div>
      <div class="stat-lbl">Hrs extra <?= date('M', strtotime($mes_ini)) ?></div>
    </div>
    <div class="stat">
      <div class="stat-val" style="color:#0f766e;"><?= $cActivos ?></div>
      <div class="stat-lbl">Empleados activos</div>
    </div>
  </div>

  <!-- Tabs de vista -->
  <div class="tabs">
    <a href="?view=dia&fecha=<?= $fecha ?>"          class="tab-link <?= $view==='dia'         ? 'active' : '' ?>">📋 Vista diaria</a>
    <a href="?view=resumen&mes=<?= $mes ?>"           class="tab-link <?= $view==='resumen'      ? 'active' : '' ?>">📆 Resumen mensual</a>
    <a href="?view=incidencias&fecha=<?= $fecha ?>"  class="tab-link <?= $view==='incidencias'  ? 'active' : '' ?>">📌 Incidencias</a>
  </div>

  <?php /* ══════════════ VISTA DÍA ══════════════ */ if ($view === 'dia'): ?>

  <div class="card">
    <div class="card-header">
      <h2>Asistencia — <?= h(ucfirst($fechaLabel)) ?></h2>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
        <!-- Navegar fecha -->
        <div class="date-nav">
          <a href="?view=dia&fecha=<?= $prevFecha ?>&f_dept=<?= $f_dept ?>">‹ Anterior</a>
          <form method="get" style="display:contents;">
            <input type="hidden" name="view"   value="dia">
            <input type="hidden" name="f_dept" value="<?= $f_dept ?>">
            <input type="date" name="fecha" class="inp-sm" value="<?= h($fecha) ?>" onchange="this.form.submit()">
          </form>
          <a href="?view=dia&fecha=<?= $nextFecha ?>&f_dept=<?= $f_dept ?>">Siguiente ›</a>
          <a href="?view=dia&fecha=<?= date('Y-m-d') ?>" class="btn btn-primary">Hoy</a>
        </div>
        <!-- Bulk present -->
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action"     value="bulk_present">
          <input type="hidden" name="fecha"      value="<?= h($fecha) ?>">
          <input type="hidden" name="back_view"  value="dia">
          <button type="submit" class="btn btn-success"
                  onclick="return confirm('¿Marcar como presentes a todos los empleados sin registro en esta fecha?')">
            ✅ Marcar todos presentes
          </button>
        </form>
      </div>
    </div>

    <!-- Filtro departamento -->
    <div class="filter-bar">
      <form method="get" style="display:contents;">
        <input type="hidden" name="view"  value="dia">
        <input type="hidden" name="fecha" value="<?= h($fecha) ?>">
        <label style="font-size:12px;font-weight:600;color:#64748b;">Departamento:</label>
        <select name="f_dept" onchange="this.form.submit()">
          <option value="">Todos</option>
          <?php foreach ($departamentos as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $f_dept===(int)$d['id'] ? 'selected' : '' ?>><?= h($d['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <span style="margin-left:auto;font-size:12px;color:#94a3b8;"><?= count($roster) ?> empleado(s)</span>
    </div>

    <?php if (empty($roster)): ?>
      <div class="empty">No hay empleados activos.</div>
    <?php else: ?>
    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>Empleado</th><th>Departamento</th>
          <th>Tipo</th><th>Entrada</th><th>Salida</th>
          <th>Hrs trabajadas</th><th>Hrs extra</th><th>Retardo</th>
          <th>Observaciones</th><th>Guardar</th>
        </tr></thead>
        <tbody>
        <?php foreach ($roster as $r):
          $ya_tiene = (bool)$r['asist_id'];
        ?>
          <tr style="<?= !$ya_tiene ? 'background:#fffbeb;' : '' ?>">
            <td>
              <div class="emp-name"><?= h($r['nombre_completo']) ?></div>
              <span class="num-emp"><?= h($r['numero_empleado']) ?></span>
            </td>
            <td><span style="font-size:12px;color:#64748b;"><?= h($r['dept_nombre'] ?? '—') ?></span></td>
            <td colspan="8">
              <form method="post" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="csrf_token"   value="<?= h($csrf) ?>">
                <input type="hidden" name="action"       value="save_asistencia">
                <input type="hidden" name="empleado_id"  value="<?= (int)$r['id'] ?>">
                <input type="hidden" name="fecha"        value="<?= h($fecha) ?>">
                <input type="hidden" name="back_view"    value="dia">

                <select name="tipo" class="inp-sm tipo-sel" onchange="toggleHoras(this)">
                  <?php foreach ($TIPOS_ASIST as $tv => $tl): ?>
                    <option value="<?= $tv ?>" <?= ($r['tipo'] ?? 'normal') === $tv ? 'selected' : '' ?>><?= $tl ?></option>
                  <?php endforeach; ?>
                </select>

                <span class="hora-fields">
                  <input type="time" name="hora_entrada" class="inp-sm"
                         value="<?= h(substr($r['hora_entrada'] ?? $r['hora_entrada_esperada'] ?? '08:00:00', 0, 5)) ?>">
                  <span style="color:#94a3b8;">→</span>
                  <input type="time" name="hora_salida"  class="inp-sm"
                         value="<?= h(substr($r['hora_salida'] ?? '', 0, 5)) ?>">
                </span>

                <?php if ($ya_tiene): ?>
                  <span style="font-size:12px;color:#475569;">
                    <?= $r['horas_trabajadas'] ? number_format((float)$r['horas_trabajadas'],1).'h' : '' ?>
                    <?php if ((float)($r['horas_extra'] ?? 0) > 0): ?>
                      <span style="color:#7c3aed;font-weight:700;">+<?= number_format((float)$r['horas_extra'],1) ?>h extra</span>
                    <?php endif; ?>
                    <?php if ($r['retardo']): ?>
                      <span class="retardo-badge">⏰ <?= $r['minutos_retardo'] ?>min retardo</span>
                    <?php endif; ?>
                  </span>
                <?php endif; ?>

                <?php if (($r['capturado_por'] ?? '') === 'Empleado'): ?>
                  <span class="chk-audit" title="Marcado por el propio empleado desde el portal de check-in">
                    📱 Autoservicio
                    <?php if ($r['checkin_foto']): ?>
                      <a href="asistencia_foto.php?a=<?= (int)$r['asist_id'] ?>&tipo=checkin" target="_blank" rel="noopener">📷 entrada</a>
                    <?php endif; ?>
                    <?php if ($r['checkout_foto']): ?>
                      <a href="asistencia_foto.php?a=<?= (int)$r['asist_id'] ?>&tipo=checkout" target="_blank" rel="noopener">📷 salida</a>
                    <?php endif; ?>
                    <?php if ($r['checkin_lat'] !== null && $r['checkin_lng'] !== null): ?>
                      <a href="https://www.google.com/maps?q=<?= h((string)$r['checkin_lat']) ?>,<?= h((string)$r['checkin_lng']) ?>" target="_blank" rel="noopener">📍 mapa</a>
                    <?php endif; ?>
                    <?php if ($r['checkin_face_score'] !== null): ?>
                      <span>· rostro <?= number_format((float)$r['checkin_face_score'], 0) ?>%</span>
                    <?php endif; ?>
                    <?php if ($r['revision_manual']): ?>
                      <span class="retardo-badge" style="background:#fee2e2;color:#991b1b;">⚠ revisar</span>
                    <?php endif; ?>
                  </span>
                <?php endif; ?>

                <input type="text" name="observaciones" class="inp-sm"
                       value="<?= h($r['observaciones'] ?? '') ?>"
                       placeholder="Observaciones…" style="width:150px;">

                <button type="submit" class="btn btn-primary">💾</button>

                <?php if ($ya_tiene): ?>
                  <?php /* Botón eliminar registro */ ?>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action"     value="del_asistencia">
                    <input type="hidden" name="id"         value="<?= (int)$r['asist_id'] ?>">
                    <input type="hidden" name="fecha"      value="<?= h($fecha) ?>">
                    <input type="hidden" name="back_view"  value="dia">
                    <button type="submit" class="btn btn-danger"
                            onclick="return confirm('¿Eliminar este registro de asistencia?')">🗑</button>
                  </form>
                <?php endif; ?>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <?php /* ══════════════ VISTA RESUMEN ══════════════ */ elseif ($view === 'resumen'): ?>

  <div class="card">
    <div class="card-header">
      <h2>Resumen mensual — <?= h(ucfirst($mesLabel)) ?></h2>
      <div class="date-nav">
        <a href="?view=resumen&mes=<?= $prevMes ?>&f_dept=<?= $f_dept ?>">‹ Mes anterior</a>
        <form method="get" style="display:contents;">
          <input type="hidden" name="view"   value="resumen">
          <input type="hidden" name="f_dept" value="<?= $f_dept ?>">
          <input type="month" name="mes" class="inp-sm" value="<?= h($mes) ?>" onchange="this.form.submit()">
        </form>
        <a href="?view=resumen&mes=<?= $nextMes ?>&f_dept=<?= $f_dept ?>">Mes siguiente ›</a>
      </div>
    </div>

    <!-- Filtro departamento -->
    <div class="filter-bar">
      <form method="get" style="display:contents;">
        <input type="hidden" name="view" value="resumen">
        <input type="hidden" name="mes"  value="<?= h($mes) ?>">
        <label style="font-size:12px;font-weight:600;color:#64748b;">Departamento:</label>
        <select name="f_dept" onchange="this.form.submit()">
          <option value="">Todos</option>
          <?php foreach ($departamentos as $d): ?>
            <option value="<?= $d['id'] ?>" <?= $f_dept===(int)$d['id'] ? 'selected' : '' ?>><?= h($d['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
      <!-- Leyenda -->
      <div style="display:flex;gap:6px;align-items:center;margin-left:auto;flex-wrap:wrap;">
        <span style="font-size:11px;color:#64748b;">Leyenda:</span>
        <?php foreach ([
          ['#dcfce7','#166534','N'],['#ffedd5','#9a3412','FJ'],['#fee2e2','#991b1b','FI'],
          ['#dbeafe','#1d4ed8','V'],['#f3e8ff','#7e22ce','Inc'],['#f1f5f9','#64748b','D']
        ] as [$bg,$c,$lbl]): ?>
          <span style="display:inline-block;padding:1px 6px;border-radius:3px;font-size:10px;font-weight:700;background:<?= $bg ?>;color:<?= $c ?>;"><?= $lbl ?></span>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if (empty($resumenEmps)): ?>
      <div class="empty">No hay empleados activos.</div>
    <?php else: ?>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th style="position:sticky;left:0;z-index:2;background:#f8fafc;">Empleado</th>
            <th>Dept</th>
            <?php for ($d = 1; $d <= $diasEnMes; $d++): ?>
              <?php $dow = (int)date('N', strtotime("$mes-" . str_pad($d,2,'0',STR_PAD_LEFT))); ?>
              <th style="<?= in_array($dow,[6,7]) ? 'color:#94a3b8;' : '' ?>;min-width:28px;"><?= $d ?></th>
            <?php endfor; ?>
            <th>Días</th><th>Faltas</th><th>H.Extra</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($resumenEmps as $emp):
          $total_dias  = 0; $total_faltas = 0; $total_extra = 0.0;
          $colorMap = [
            'normal'=>['#dcfce7','#166534','N'],'falta_justificada'=>['#ffedd5','#9a3412','FJ'],
            'falta_injustificada'=>['#fee2e2','#991b1b','FI'],'vacaciones'=>['#dbeafe','#1d4ed8','V'],
            'incapacidad'=>['#f3e8ff','#7e22ce','I'],'permiso_goce'=>['#ccfbf1','#0f766e','PG'],
            'permiso_sin_goce'=>['#fef9c3','#854d0e','PS'],'descanso'=>['#f1f5f9','#64748b','D'],'festivo'=>['#f1f5f9','#94a3b8','Fe'],
          ];
        ?>
          <tr>
            <td style="position:sticky;left:0;z-index:1;background:#fff;">
              <div style="font-size:12px;font-weight:600;"><?= h($emp['nombre_completo']) ?></div>
              <span class="num-emp"><?= h($emp['numero_empleado']) ?></span>
            </td>
            <td style="font-size:11px;color:#94a3b8;white-space:nowrap;"><?= h($emp['dept_nombre'] ?? '—') ?></td>
            <?php for ($d = 1; $d <= $diasEnMes; $d++):
              $celda = $pivotData[$emp['id']][$d] ?? null;
              $dow   = (int)date('N', strtotime("$mes-" . str_pad($d,2,'0',STR_PAD_LEFT)));
              $isWeekend = in_array($dow, [6,7]);
              if ($celda) {
                [$bg,$clr,$lbl] = $colorMap[$celda['tipo']] ?? ['#f1f5f9','#64748b','?'];
                if ($celda['tipo'] === 'normal') $total_dias++;
                elseif (str_contains($celda['tipo'],'falta')) $total_faltas++;
                $total_extra += (float)($celda['horas_extra'] ?? 0);
                echo "<td><span class='res-cell' style='background:$bg;color:$clr;" . ($celda['retardo'] ? 'text-decoration:underline dotted;' : '') . "'>$lbl</span></td>";
              } else {
                echo "<td><span class='res-cell res-empty'>" . ($isWeekend ? '—' : '') . "</span></td>";
              }
            endfor; ?>
            <td style="text-align:center;font-weight:700;font-size:13px;"><?= $total_dias ?></td>
            <td style="text-align:center;color:#dc2626;font-weight:700;"><?= $total_faltas ?: '—' ?></td>
            <td style="text-align:center;color:#7c3aed;font-weight:700;"><?= $total_extra > 0 ? number_format($total_extra,1) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <?php /* ══════════════ VISTA INCIDENCIAS ══════════════ */ elseif ($view === 'incidencias'): ?>

  <!-- Formulario nueva incidencia -->
  <div class="card">
    <div class="card-header"><h2>➕ Registrar incidencia</h2></div>
    <div class="inc-form">
      <form method="post">
        <input type="hidden" name="csrf_token"  value="<?= h($csrf) ?>">
        <input type="hidden" name="action"      value="save_incidencia">
        <input type="hidden" name="back_view"   value="incidencias">
        <div class="inc-grid">
          <div class="inc-field">
            <label>Empleado *</label>
            <select name="inc_empleado_id" class="vf-inp" required>
              <option value="">— Seleccionar —</option>
              <?php foreach ($empleadosAll as $e): ?>
                <option value="<?= $e['id'] ?>"><?= h($e['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="inc-field">
            <label>Tipo *</label>
            <select name="inc_tipo" class="vf-inp" required>
              <option value="">— Seleccionar —</option>
              <?php foreach ($TIPOS_INCID as $tv => $tl): ?>
                <option value="<?= $tv ?>"><?= $tl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="inc-field">
            <label>Fecha inicio *</label>
            <input type="date" name="inc_fecha_inicio" class="vf-inp" required value="<?= date('Y-m-d') ?>">
          </div>
          <div class="inc-field">
            <label>Fecha fin *</label>
            <input type="date" name="inc_fecha_fin" class="vf-inp" required value="<?= date('Y-m-d') ?>">
          </div>
          <div class="inc-field" style="grid-column:span 3;">
            <label>Descripción / Motivo</label>
            <input type="text" name="inc_descripcion" class="vf-inp" placeholder="Motivo o descripción de la incidencia…">
          </div>
          <div class="inc-field" style="display:flex;align-items:flex-end;">
            <button type="submit" class="btn btn-primary" style="width:100%;">💾 Registrar</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Lista de incidencias -->
  <div class="card">
    <div class="card-header"><h2>Historial de incidencias</h2></div>
    <?php if (empty($incidencias)): ?>
      <div class="empty">No hay incidencias registradas.</div>
    <?php else: ?>
    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>#</th><th>Empleado</th><th>Tipo</th>
          <th>Inicio</th><th>Fin</th><th>Días</th>
          <th>Descripción</th><th>Estado</th><th>Acciones</th>
        </tr></thead>
        <tbody>
        <?php foreach ($incidencias as $i): ?>
          <tr>
            <td style="color:#94a3b8;font-size:12px;"><?= $i['id'] ?></td>
            <td>
              <strong style="font-size:13px;"><?= h($i['emp_nombre']) ?></strong>
              <div style="font-size:11px;color:#94a3b8;"><?= h($i['numero_empleado']) ?></div>
            </td>
            <td><?= tbadge($i['tipo']) ?></td>
            <td style="font-size:12px;white-space:nowrap;"><?= fd($i['fecha_inicio']) ?></td>
            <td style="font-size:12px;white-space:nowrap;"><?= fd($i['fecha_fin']) ?></td>
            <td style="text-align:center;font-weight:700;"><?= $i['dias'] ?? '—' ?></td>
            <td style="font-size:12px;color:#475569;max-width:200px;"><?= h($i['descripcion'] ?? '—') ?></td>
            <td><?= ibadge($i['estado']) ?></td>
            <td style="white-space:nowrap;">
              <?php if ($i['estado'] === 'pendiente'): ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action"     value="upd_incidencia">
                  <input type="hidden" name="id"         value="<?= (int)$i['id'] ?>">
                  <input type="hidden" name="estado"     value="aprobada">
                  <input type="hidden" name="back_view"  value="incidencias">
                  <button type="submit" class="btn btn-success">✓ Aprobar</button>
                </form>
                <form method="post" style="display:inline;margin-left:3px;">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action"     value="upd_incidencia">
                  <input type="hidden" name="id"         value="<?= (int)$i['id'] ?>">
                  <input type="hidden" name="estado"     value="rechazada">
                  <input type="hidden" name="back_view"  value="incidencias">
                  <button type="submit" class="btn btn-warning">✗ Rechazar</button>
                </form>
              <?php endif; ?>
              <form method="post" style="display:inline;margin-left:3px;">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action"     value="del_incidencia">
                <input type="hidden" name="id"         value="<?= (int)$i['id'] ?>">
                <input type="hidden" name="back_view"  value="incidencias">
                <button type="submit" class="btn btn-danger"
                        onclick="return confirm('¿Eliminar esta incidencia?')">🗑</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <?php endif; ?>

</div><!-- /body -->

<script>
/* Ocultar campos de hora si el tipo no los necesita */
const tiposConHora = ['normal','permiso_goce','permiso_sin_goce'];
function toggleHoras(sel) {
  const row     = sel.closest('form');
  const campos  = row.querySelector('.hora-fields');
  if (!campos) return;
  campos.style.visibility = tiposConHora.includes(sel.value) ? 'visible' : 'hidden';
}
document.querySelectorAll('.tipo-sel').forEach(function(sel) {
  toggleHoras(sel);
  sel.addEventListener('change', function() { toggleHoras(this); });
});
</script>
</body>
</html>
