<?php
/**
 * admin/finiquitos.php — Finiquitos y Liquidaciones LFT
 * Sharclean RRHH
 */
require_once __DIR__ . '/auth.php';

function fetchAll($result): array {
    $rows = [];
    if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}

/* ── Constantes LFT 2025 ───────────────────────────────────── */
define('SM_DIARIO_2025', 278.80); // Salario mínimo general 2025

/* ── Días de vacaciones LFT (reforma 2022) ─────────────────── */
function diasVacLFT(int $anos): int {
    if ($anos <= 0) return 12;
    $tabla = [1 => 12, 2 => 14, 3 => 16, 4 => 18];
    if (isset($tabla[$anos])) return $tabla[$anos];
    return 20 + 2 * (int)(($anos - 5) / 5);
}

/* ── Factor de integración (Art. 84 + 87 + 80 LFT) ────────── */
function factorIntegracion(int $anosCompletos): float {
    $dv = diasVacLFT(max(1, $anosCompletos));
    return round(1 + 15 / 365 + 0.25 * $dv / 365, 6);
}

/* ── Fecha en español ──────────────────────────────────────── */
function fechaEsp(string $date): string {
    $meses = ['enero','febrero','marzo','abril','mayo','junio',
              'julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $dt = new DateTime($date);
    return $dt->format('j') . ' de ' . $meses[(int)$dt->format('n') - 1] . ' de ' . $dt->format('Y');
}

/* ── Cálculo principal ─────────────────────────────────────── */
function calcFiniquito(array $emp, string $fechaTermino, int $diasPendientes, string $motivo): array {
    if (empty($emp['salario_base']) || (float)$emp['salario_base'] <= 0) {
        throw new RuntimeException('Este empleado no tiene salario registrado. Actualice el expediente antes de continuar.');
    }
    $fi = new DateTime($emp['fecha_ingreso']);
    $ft = new DateTime($fechaTermino);
    if ($ft < $fi) {
        throw new RuntimeException('La fecha de término no puede ser anterior a la fecha de ingreso.');
    }

    $sd = (float)$emp['salario_base'] / 30;

    $diff          = $fi->diff($ft);
    $anosCompletos = $diff->y;
    $diasTotales   = (int)$diff->days;
    $anosFloat     = $diasTotales / 365.0;

    $factor = factorIntegracion($anosCompletos);
    $sdi    = round($sd * $factor, 2);

    // ── Aguinaldo proporcional (días en año calendario) ──────
    $inicioAnio   = new DateTime($ft->format('Y') . '-01-01');
    $inicioAg     = ($fi > $inicioAnio) ? clone $fi : clone $inicioAnio;
    $diasAnio     = $inicioAg->diff($ft)->days + 1;
    $diasAgProp   = round($diasAnio * 15 / 365, 4);
    $montoAg      = round($diasAgProp * $sd, 2);

    // ── Vacaciones proporcionales (año aniversario actual) ───
    $ultAniv = clone $fi;
    $ultAniv->modify("+{$anosCompletos} years");
    if ($ultAniv > $ft) {
        $ultAniv->modify('-1 year');
        $anosParaVac = max(1, $anosCompletos);
    } else {
        $anosParaVac = $anosCompletos + 1;
    }
    $diasEnAniv  = $ultAniv->diff($ft)->days;
    $diasVacAnio = diasVacLFT($anosParaVac);
    $diasVacProp = round($diasEnAniv * $diasVacAnio / 365, 4);
    $montoVac    = round($diasVacProp * $sd, 2);
    $montoPV     = round($montoVac * 0.25, 2);

    // ── Días pendientes de pago ──────────────────────────────
    $montoPend = round($diasPendientes * $sd, 2);

    // ── Prima de antigüedad Art. 162 LFT ────────────────────
    $capSD         = min($sd, 2 * SM_DIARIO_2025);
    $primaAnt      = 0;
    $primaAntAplica = in_array($motivo, ['despido_injustificado', 'despido_justificado', 'defuncion', 'mutuo_acuerdo'])
                      || ($motivo === 'renuncia' && $anosCompletos >= 15);
    if ($primaAntAplica) {
        $primaAnt = round(12 * $anosFloat * $capSD, 2);
    }

    // ── Liquidación (despido injustificado) ─────────────────
    $tipo           = ($motivo === 'despido_injustificado') ? 'liquidacion' : 'finiquito';
    $montoTresMeses = 0;
    $montoVeinteDias = 0;
    if ($tipo === 'liquidacion') {
        $montoTresMeses  = round(90 * $sdi, 2);
        $montoVeinteDias = round(20 * $anosFloat * $sdi, 2);
    }

    $totalPerc = $montoPend + $montoAg + $montoVac + $montoPV + $primaAnt + $montoTresMeses + $montoVeinteDias;

    return [
        'tipo'                        => $tipo,
        'salario_diario'              => round($sd, 2),
        'salario_diario_integrado'    => $sdi,
        'factor_integracion'          => $factor,
        'anos_completos'              => $anosCompletos,
        'anos_servicio'               => round($anosFloat, 4),
        'dias_servicio_total'         => $diasTotales,
        'dias_anio_aguinaldo'         => $diasAnio,
        'dias_aniversario_vac'        => $diasEnAniv,
        'dias_vac_anio'               => $diasVacAnio,
        'dias_pendientes'             => $diasPendientes,
        'monto_dias_pendientes'       => $montoPend,
        'dias_aguinaldo_prop'         => $diasAgProp,
        'monto_aguinaldo_prop'        => $montoAg,
        'dias_vacaciones_prop'        => $diasVacProp,
        'monto_vacaciones_prop'       => $montoVac,
        'monto_prima_vacacional_prop' => $montoPV,
        'monto_tres_meses'            => $montoTresMeses,
        'monto_veinte_dias'           => $montoVeinteDias,
        'monto_prima_antiguedad'      => $primaAnt,
        'total_percepciones'          => $totalPerc,
        'total_deducciones'           => 0.0,
        'total_neto'                  => $totalPerc,
    ];
}

$db = getAdminDB();

/* ══════════════════════════════════════════════════════════
   POST handlers
══════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    /* ── calcular ──────────────────────────────────────────── */
    if ($action === 'calcular') {
        $eid = (int)($_POST['empleado_id'] ?? 0);
        $ft  = trim($_POST['fecha_termino'] ?? '');
        $dp  = max(0, (int)($_POST['dias_pendientes'] ?? 0));
        $mot = trim($_POST['motivo'] ?? '');
        if (!$eid || !$ft || !$mot) { echo json_encode(['ok' => false, 'msg' => 'Faltan datos.']); exit; }
        $res = $db->query("SELECT nombre,apellido_paterno,apellido_materno,fecha_ingreso,salario_base FROM empleados WHERE id=$eid");
        $emp = $res ? $res->fetch_assoc() : null;
        if (!$emp) { echo json_encode(['ok' => false, 'msg' => 'Empleado no encontrado.']); exit; }
        try {
            $calc = calcFiniquito($emp, $ft, $dp, $mot);
            echo json_encode(['ok' => true, 'calc' => $calc, 'emp' => [
                'nombre'        => trim($emp['nombre'] . ' ' . $emp['apellido_paterno'] . ' ' . $emp['apellido_materno']),
                'fecha_ingreso' => $emp['fecha_ingreso'],
                'salario_base'  => $emp['salario_base'],
            ]]);
        } catch (RuntimeException $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
        }
        exit;
    }

    /* ── guardar ───────────────────────────────────────────── */
    if ($action === 'guardar') {
        $motivosValidos = ['renuncia','despido_injustificado','despido_justificado','fin_contrato','mutuo_acuerdo','defuncion'];
        $eid    = (int)($_POST['empleado_id'] ?? 0);
        $ft     = trim($_POST['fecha_termino'] ?? '');
        $dp     = max(0, (int)($_POST['dias_pendientes'] ?? 0));
        $mot    = trim($_POST['motivo'] ?? '');
        $otrasP = max(0, (float)($_POST['otras_percepciones'] ?? 0));
        $otrasD = max(0, (float)($_POST['otras_deducciones'] ?? 0));
        $notas  = $db->real_escape_string(trim($_POST['notas'] ?? ''));
        $darBaja = !empty($_POST['dar_baja']);

        if (!$eid || !$ft || !in_array($mot, $motivosValidos)) {
            echo json_encode(['ok' => false, 'msg' => 'Datos incompletos o inválidos.']);
            exit;
        }
        $res = $db->query("SELECT nombre,apellido_paterno,apellido_materno,fecha_ingreso,salario_base FROM empleados WHERE id=$eid");
        $emp = $res ? $res->fetch_assoc() : null;
        if (!$emp) { echo json_encode(['ok' => false, 'msg' => 'Empleado no encontrado.']); exit; }
        try {
            $c = calcFiniquito($emp, $ft, $dp, $mot);
        } catch (RuntimeException $e) {
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
            exit;
        }

        $totalPerc = round($c['total_percepciones'] + $otrasP, 2);
        $totalDed  = round($c['total_deducciones']  + $otrasD, 2);
        $totalNeto = round($totalPerc - $totalDed, 2);
        $tipo      = $db->real_escape_string($c['tipo']);
        $motE      = $db->real_escape_string($mot);
        $ftE       = $db->real_escape_string($ft);

        $db->query("INSERT INTO finiquitos
            (empleado_id, tipo, motivo, fecha_termino,
             salario_diario, salario_diario_integrado, factor_integracion,
             anos_servicio, dias_servicio_total,
             dias_pendientes, monto_dias_pendientes,
             dias_aguinaldo_prop, monto_aguinaldo_prop,
             dias_vacaciones_prop, monto_vacaciones_prop, monto_prima_vacacional_prop,
             monto_prima_antiguedad, monto_tres_meses, monto_veinte_dias,
             otras_percepciones, otras_deducciones, notas,
             total_percepciones, total_deducciones, total_neto)
            VALUES
            ($eid, '$tipo', '$motE', '$ftE',
             {$c['salario_diario']}, {$c['salario_diario_integrado']}, {$c['factor_integracion']},
             {$c['anos_servicio']}, {$c['dias_servicio_total']},
             $dp, {$c['monto_dias_pendientes']},
             {$c['dias_aguinaldo_prop']}, {$c['monto_aguinaldo_prop']},
             {$c['dias_vacaciones_prop']}, {$c['monto_vacaciones_prop']}, {$c['monto_prima_vacacional_prop']},
             {$c['monto_prima_antiguedad']}, {$c['monto_tres_meses']}, {$c['monto_veinte_dias']},
             $otrasP, $otrasD, '$notas',
             $totalPerc, $totalDed, $totalNeto)");

        if ($db->error) { echo json_encode(['ok' => false, 'msg' => 'Error DB: ' . $db->error]); exit; }
        $fid = $db->insert_id;

        if ($darBaja) {
            $ftEsc  = $db->real_escape_string($ft);
            $motBaja = $db->real_escape_string($mot);
            $db->query("UPDATE empleados SET estatus='baja_definitiva', fecha_baja='$ftEsc', motivo_baja='$motBaja' WHERE id=$eid");
        }
        echo json_encode(['ok' => true, 'id' => $fid]);
        exit;
    }

    /* ── marcar_firmado ────────────────────────────────────── */
    if ($action === 'marcar_firmado') {
        $id = (int)($_POST['id'] ?? 0);
        $db->query("UPDATE finiquitos SET estado='firmado' WHERE id=$id AND estado='borrador'");
        echo json_encode(['ok' => $db->affected_rows > 0]);
        exit;
    }

    /* ── marcar_pagado ─────────────────────────────────────── */
    if ($action === 'marcar_pagado') {
        $id = (int)($_POST['id'] ?? 0);
        $fp = $db->real_escape_string(trim($_POST['fecha_pago'] ?? date('Y-m-d')));
        $db->query("UPDATE finiquitos SET estado='pagado', fecha_pago='$fp' WHERE id=$id AND estado IN ('borrador','firmado')");
        echo json_encode(['ok' => $db->affected_rows > 0]);
        exit;
    }

    /* ── eliminar ──────────────────────────────────────────── */
    if ($action === 'eliminar') {
        $id = (int)($_POST['id'] ?? 0);
        $db->query("DELETE FROM finiquitos WHERE id=$id AND estado='borrador'");
        echo json_encode(['ok' => $db->affected_rows > 0]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Acción no reconocida.']);
    exit;
}

/* ══════════════════════════════════════════════════════════
   GET views
══════════════════════════════════════════════════════════ */
$view = $_GET['view'] ?? 'list';
$motivoLabels = [
    'renuncia'              => 'Renuncia voluntaria',
    'despido_injustificado' => 'Despido injustificado',
    'despido_justificado'   => 'Despido justificado',
    'fin_contrato'          => 'Fin de contrato',
    'mutuo_acuerdo'         => 'Acuerdo mutuo',
    'defuncion'             => 'Defunción',
];

if ($view === 'list') {
    $finiquitos = fetchAll($db->query("
        SELECT f.id, f.tipo, f.motivo, f.fecha_termino, f.total_neto, f.estado, f.created_at,
               CONCAT(e.nombre,' ',e.apellido_paterno) AS emp_nombre,
               e.numero_empleado
        FROM finiquitos f
        JOIN empleados e ON e.id = f.empleado_id
        ORDER BY f.id DESC
        LIMIT 300
    "));
    $db->close();

} elseif ($view === 'crear') {
    $empleados = fetchAll($db->query("
        SELECT id, numero_empleado, nombre, apellido_paterno, apellido_materno, salario_base, fecha_ingreso
        FROM empleados
        WHERE estatus NOT IN ('baja_definitiva')
        ORDER BY apellido_paterno, nombre
    "));
    $db->close();

} elseif ($view === 'detalle') {
    $fid = (int)($_GET['id'] ?? 0);
    $fin = null;
    if ($fid) {
        $res = $db->query("
            SELECT f.*,
                   CONCAT(e.nombre,' ',e.apellido_paterno,' ',IFNULL(e.apellido_materno,'')) AS emp_nombre,
                   e.numero_empleado, e.puesto_nombre, e.rfc, e.nss, e.curp,
                   d.nombre AS dept_nombre
            FROM finiquitos f
            JOIN empleados e ON e.id = f.empleado_id
            LEFT JOIN departamentos d ON d.id = e.departamento_id
            WHERE f.id = $fid
        ");
        $fin = $res ? $res->fetch_assoc() : null;
    }
    $db->close();
    if (!$fin) { header('Location: finiquitos.php'); exit; }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Finiquitos – Sharclean Admin</title>
<style>
:root{--primary:#0284C7;--primary-dark:#0369A1;--danger:#DC2626;--success:#16A34A;--warn:#D97706;--gray:#64748B;--light:#F8FAFC;--border:#E2E8F0;--radius:8px;--shadow:0 1px 3px rgba(0,0,0,.1)}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,sans-serif;background:#f1f5f9;color:#1e293b;font-size:14px}
.admin-header{background:#0284C7;color:#fff;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}.admin-brand{display:flex;align-items:center;gap:10px}.admin-brand img{height:36px}
.admin-header h1{font-size:18px;font-weight:800}
.nav-links{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.nav-links a{color:rgba(255,255,255,.85);font-size:13px;padding:6px 12px;border:1px solid rgba(255,255,255,.3);border-radius:6px;white-space:nowrap;transition:.15s}
.nav-links a:hover{background:rgba(255,255,255,.15)}
.nav-links a.active{background:rgba(255,255,255,.95);color:#0284C7;font-weight:700;border-color:transparent}
.container{max-width:1200px;margin:24px auto;padding:0 16px}
.card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:20px;box-shadow:var(--shadow)}
.card-title{font-size:16px;font-weight:600;margin-bottom:16px;color:#0f172a}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border:none;border-radius:var(--radius);cursor:pointer;font-size:13px;font-weight:500;text-decoration:none;transition:.2s}
.btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:var(--primary-dark)}
.btn-success{background:var(--success);color:#fff}.btn-success:hover{background:#15803d}
.btn-danger{background:var(--danger);color:#fff}.btn-danger:hover{background:#b91c1c}
.btn-outline{background:#fff;border:1px solid var(--border);color:#475569}.btn-outline:hover{border-color:#94a3b8;background:#f8fafc}
.btn-sm{padding:5px 10px;font-size:12px}
.btn:disabled{opacity:.5;cursor:not-allowed}
table{width:100%;border-collapse:collapse}
th{background:#f8fafc;padding:10px 12px;text-align:left;font-size:12px;color:var(--gray);text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border)}
td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#fafbfc}
.badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600}
.badge-gray{background:#f1f5f9;color:var(--gray)}.badge-blue{background:#eff6ff;color:#1d4ed8}.badge-green{background:#f0fdf4;color:#15803d}
.badge-fin{background:#eff6ff;color:#1d4ed8}.badge-liq{background:#fef3c7;color:#92400e}
.form-row{display:grid;gap:14px;margin-bottom:14px}
.form-row.cols-2{grid-template-columns:1fr 1fr}
.form-row.cols-3{grid-template-columns:1fr 1fr 1fr}
.form-row.cols-4{grid-template-columns:1fr 1fr 1fr 1fr}
label{display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:4px}
input,select,textarea{width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;font-size:14px;background:#fff;color:#1e293b}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 2px rgba(2,132,199,.15)}
textarea{resize:vertical;min-height:80px}
.hint{font-size:11px;color:var(--gray);margin-top:3px}
.alert{padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;font-size:13px}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626}
.preview-box{background:#f8fafc;border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:16px}
.salary-info{display:flex;gap:20px;flex-wrap:wrap;margin-bottom:16px}
.salary-stat{text-align:center;background:#fff;border:1px solid var(--border);border-radius:6px;padding:10px 16px;min-width:100px}
.salary-stat .val{font-size:18px;font-weight:700;color:var(--primary);display:block}
.salary-stat .lbl{font-size:10px;color:var(--gray);text-transform:uppercase}
.calc-table td{padding:6px 10px}
.calc-table .subtotal td{border-top:2px solid var(--border);font-weight:600}
.calc-table .total-row td{background:#eff6ff;font-size:16px;font-weight:700;color:var(--primary)}
.total-display{font-size:24px;font-weight:700;color:var(--success);text-align:right;margin-top:12px}
.section-sep{border:none;border-top:2px solid var(--border);margin:16px 0}
.tipo-fin{color:#1d4ed8;font-weight:600}.tipo-liq{color:#92400e;font-weight:600}
/* ── Print styles ─────────────────────────────────────────── */
@media print{
  .admin-header,.nav-links,.no-print,.btn{display:none!important}
  body{background:#fff;color:#000;font-size:11pt}
  .container{max-width:none;margin:0;padding:0}
  .card{border:none;box-shadow:none;padding:0;margin-bottom:0}
  .doc-header{text-align:center;margin-bottom:20px;border-bottom:2px solid #000;padding-bottom:12px}
  .doc-company{font-size:16pt;font-weight:bold}
  .doc-title{font-size:14pt;margin:6px 0}
  table{font-size:10pt}
  th,td{padding:5px 8px}
  .print-total{font-size:14pt;font-weight:bold;text-align:right;border-top:2px solid #000;padding-top:8px;margin-top:8px}
  .firmas{margin-top:60px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:40px;page-break-inside:avoid}
  .firma-box{text-align:center;border-top:1px solid #000;padding-top:8px;font-size:9pt}
}
</style>
</head>
<body>

<header class="admin-header">
  <div class="admin-brand">
    <img src="../images/logo.png" alt="Sharclean">
    <h1>💼 Finiquitos y Liquidaciones</h1>
  </div>
  <div class="nav-links">
    <a href="index.php">Panel</a>
    <a href="empleados.php">👥 Empleados</a>
    <a href="asistencia.php">📅 Asistencia</a>
    <a href="nomina.php">📋 Nómina</a>
    <a href="vacaciones.php">🏖 Vacaciones</a>
    <a href="finiquitos.php" class="active">💼 Finiquitos</a>
    <a href="reportes.php">📊 Reportes</a>
    <a href="configuracion.php">⚙️ Config</a>
    <a href="logout.php">Cerrar sesión</a>
  </div>
</header>

<div class="container">

<?php /* ═══════════════════════════ LIST VIEW ═══════════════════════════ */ ?>
<?php if ($view === 'list'): ?>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
  <h2 style="font-size:18px;color:#0f172a">Finiquitos y Liquidaciones</h2>
  <a href="finiquitos.php?view=crear" class="btn btn-primary">+ Nuevo cálculo</a>
</div>

<div class="card">
<?php if (empty($finiquitos)): ?>
  <p style="text-align:center;color:var(--gray);padding:40px 0">No hay finiquitos registrados. <a href="finiquitos.php?view=crear">Crear el primero</a>.</p>
<?php else: ?>
<table>
  <thead>
    <tr>
      <th>#</th>
      <th>Empleado</th>
      <th>Tipo</th>
      <th>Motivo</th>
      <th>Fecha término</th>
      <th>Total neto</th>
      <th>Estado</th>
      <th>Acciones</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($finiquitos as $f): ?>
    <tr>
      <td style="color:var(--gray);font-size:12px"><?= $f['id'] ?></td>
      <td>
        <strong><?= htmlspecialchars($f['emp_nombre'], ENT_QUOTES) ?></strong><br>
        <span style="font-size:11px;color:var(--gray)"><?= htmlspecialchars($f['numero_empleado']) ?></span>
      </td>
      <td>
        <?php if ($f['tipo'] === 'liquidacion'): ?>
          <span class="badge badge-liq">Liquidación</span>
        <?php else: ?>
          <span class="badge badge-fin">Finiquito</span>
        <?php endif ?>
      </td>
      <td><?= htmlspecialchars($motivoLabels[$f['motivo']] ?? $f['motivo']) ?></td>
      <td><?= (new DateTime($f['fecha_termino']))->format('d/m/Y') ?></td>
      <td style="font-weight:600;color:var(--success)">$<?= number_format($f['total_neto'], 2) ?></td>
      <td>
        <?php $est = $f['estado'];
          $badgeClass = $est === 'pagado' ? 'badge-green' : ($est === 'firmado' ? 'badge-blue' : 'badge-gray');
          $estLabel   = $est === 'pagado' ? 'Pagado' : ($est === 'firmado' ? 'Firmado' : 'Borrador');
        ?>
        <span class="badge <?= $badgeClass ?>"><?= $estLabel ?></span>
      </td>
      <td><a href="finiquitos.php?view=detalle&id=<?= $f['id'] ?>" class="btn btn-outline btn-sm">Ver / Imprimir</a></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
<?php endif ?>
</div>

<?php /* ═══════════════════════════ CREAR VIEW ══════════════════════════ */ ?>
<?php elseif ($view === 'crear'): ?>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
  <a href="finiquitos.php" class="btn btn-outline btn-sm">← Regresar</a>
  <h2 style="font-size:18px;color:#0f172a">Nuevo Finiquito / Liquidación</h2>
</div>

<div class="card">
  <p class="card-title">Datos del finiquito</p>

  <div class="form-row cols-2">
    <div>
      <label for="empleado_id">Empleado *</label>
      <select id="empleado_id">
        <option value="">— Selecciona un empleado —</option>
        <?php foreach ($empleados as $e): ?>
          <option value="<?= $e['id'] ?>"
                  data-salario="<?= $e['salario_base'] ?>"
                  data-ingreso="<?= $e['fecha_ingreso'] ?>">
            <?= htmlspecialchars($e['apellido_paterno'] . ' ' . $e['nombre'] . ' — ' . $e['numero_empleado'], ENT_QUOTES) ?>
          </option>
        <?php endforeach ?>
      </select>
    </div>
    <div>
      <label for="fecha_termino">Fecha de término (último día laborado) *</label>
      <input type="date" id="fecha_termino" value="<?= date('Y-m-d') ?>">
    </div>
  </div>

  <div class="form-row cols-2">
    <div>
      <label for="motivo">Motivo de baja *</label>
      <select id="motivo">
        <option value="">— Selecciona motivo —</option>
        <option value="renuncia">Renuncia voluntaria → Finiquito</option>
        <option value="despido_injustificado">Despido injustificado → Liquidación</option>
        <option value="despido_justificado">Despido justificado (causa) → Finiquito + Prima ant.</option>
        <option value="fin_contrato">Fin de contrato → Finiquito</option>
        <option value="mutuo_acuerdo">Acuerdo mutuo → Finiquito + Prima ant.</option>
        <option value="defuncion">Defunción → Finiquito + Prima ant.</option>
      </select>
    </div>
    <div>
      <label for="dias_pendientes">Días trabajados pendientes de pago</label>
      <input type="number" id="dias_pendientes" value="0" min="0" max="31">
      <p class="hint">Días del período en curso que aún no se han pagado (0 si el último pago ya cubre hasta la fecha de término).</p>
    </div>
  </div>

  <div style="text-align:center;margin:16px 0">
    <button class="btn btn-primary" id="btnCalcular" onclick="calcularFiniquito()">
      🔢 Calcular finiquito
    </button>
  </div>
  <div id="errCalc" class="alert alert-error" style="display:none"></div>
</div>

<!-- Preview section -->
<div id="previewSection" style="display:none">
  <div class="card">
    <p class="card-title" id="previewTitulo">Cálculo</p>
    <div class="salary-info" id="salaryInfo"></div>
    <table class="calc-table" style="margin-bottom:12px" id="calcTable"></table>

    <hr class="section-sep">
    <div class="form-row cols-2">
      <div>
        <label for="otras_percepciones">Otras percepciones adicionales ($)</label>
        <input type="number" id="otras_percepciones" value="0" min="0" step="0.01" oninput="actualizarTotal()">
        <p class="hint">Bonos, partes proporcionales adicionales, etc.</p>
      </div>
      <div>
        <label for="otras_deducciones">Otras deducciones ($)</label>
        <input type="number" id="otras_deducciones" value="0" min="0" step="0.01" oninput="actualizarTotal()">
        <p class="hint">Préstamos, uniformes, anticipos, etc.</p>
      </div>
    </div>
    <div class="total-display" id="totalDisplay"></div>

    <hr class="section-sep">
    <div class="form-row">
      <div>
        <label for="notas">Notas internas</label>
        <textarea id="notas" placeholder="Observaciones, acuerdos adicionales..."></textarea>
      </div>
    </div>
    <div style="margin-bottom:14px">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
        <input type="checkbox" id="dar_baja" style="width:auto">
        Marcar al empleado como <strong>Baja definitiva</strong> en el sistema al guardar
      </label>
    </div>
    <div id="errGuardar" class="alert alert-error" style="display:none"></div>
    <button class="btn btn-success" id="btnGuardar" onclick="guardarFiniquito()" style="width:100%;justify-content:center;font-size:15px;padding:12px">
      💾 Guardar finiquito
    </button>
  </div>
</div>

<?php /* ═══════════════════════════ DETALLE VIEW ═════════════════════════ */ ?>
<?php elseif ($view === 'detalle'): ?>

<?php
$est       = $fin['estado'];
$estLabel  = $est === 'pagado' ? 'Pagado' : ($est === 'firmado' ? 'Firmado' : 'Borrador');
$estBadge  = $est === 'pagado' ? 'badge-green' : ($est === 'firmado' ? 'badge-blue' : 'badge-gray');
$tipoLabel = $fin['tipo'] === 'liquidacion' ? 'LIQUIDACIÓN' : 'FINIQUITO';
$tipoClass = $fin['tipo'] === 'liquidacion' ? 'tipo-liq' : 'tipo-fin';
?>

<!-- Acciones (ocultas en impresión) -->
<div class="no-print" style="display:flex;align-items:center;gap:10px;margin-bottom:16px;flex-wrap:wrap">
  <a href="finiquitos.php" class="btn btn-outline btn-sm">← Todos los finiquitos</a>
  <span class="badge <?= $estBadge ?>"><?= $estLabel ?></span>
  <div style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap">
    <?php if ($est === 'borrador'): ?>
      <button class="btn btn-outline btn-sm" onclick="accion('marcar_firmado')">✅ Marcar como firmado</button>
    <?php endif ?>
    <?php if (in_array($est, ['borrador','firmado'])): ?>
      <button class="btn btn-success btn-sm" onclick="showPagarModal()">💳 Registrar pago</button>
    <?php endif ?>
    <button class="btn btn-primary btn-sm" onclick="window.print()">🖨 Imprimir</button>
    <?php if ($est === 'borrador'): ?>
      <button class="btn btn-danger btn-sm" onclick="eliminar()">🗑 Eliminar</button>
    <?php endif ?>
  </div>
</div>

<!-- Modal pagar -->
<div id="modalPagar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;display:none;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:8px;padding:24px;width:340px;box-shadow:0 10px 40px rgba(0,0,0,.2)">
    <h3 style="margin-bottom:12px">Registrar fecha de pago</h3>
    <label>Fecha de pago</label>
    <input type="date" id="fechaPagoInput" value="<?= date('Y-m-d') ?>" style="margin-bottom:16px">
    <div style="display:flex;gap:8px;justify-content:flex-end">
      <button class="btn btn-outline" onclick="document.getElementById('modalPagar').style.display='none'">Cancelar</button>
      <button class="btn btn-success" onclick="accionPagar()">Confirmar</button>
    </div>
  </div>
</div>

<!-- Documento -->
<div class="card" id="docFiniquito">
  <div class="doc-header" style="text-align:center;margin-bottom:20px;border-bottom:2px solid #0f172a;padding-bottom:12px">
    <div class="doc-company" style="font-size:20px;font-weight:700;color:#0f172a">SHARCLEAN SERVICIOS DE LIMPIEZA S.A. DE C.V.</div>
    <div class="doc-title" style="font-size:16px;font-weight:600;margin:6px 0;color:var(--primary)"><?= $tipoLabel ?> DE RELACIÓN LABORAL</div>
    <div style="font-size:12px;color:var(--gray)">Folio: FIN-<?= str_pad($fin['id'], 5, '0', STR_PAD_LEFT) ?> &nbsp;|&nbsp; <?= $estLabel ?></div>
  </div>

  <!-- Datos del trabajador -->
  <table style="width:100%;border:1px solid var(--border);border-radius:6px;overflow:hidden;margin-bottom:16px">
    <tr>
      <td style="padding:7px 12px;font-weight:600;color:#475569;background:#f8fafc;width:160px;border-bottom:1px solid var(--border)">Trabajador</td>
      <td style="padding:7px 12px;border-bottom:1px solid var(--border)"><strong><?= htmlspecialchars(trim($fin['emp_nombre']), ENT_QUOTES) ?></strong></td>
      <td style="padding:7px 12px;font-weight:600;color:#475569;background:#f8fafc;width:160px;border-bottom:1px solid var(--border)">Núm. Empleado</td>
      <td style="padding:7px 12px;border-bottom:1px solid var(--border)"><?= htmlspecialchars($fin['numero_empleado']) ?></td>
    </tr>
    <tr>
      <td style="padding:7px 12px;font-weight:600;color:#475569;background:#f8fafc;border-bottom:1px solid var(--border)">Puesto</td>
      <td style="padding:7px 12px;border-bottom:1px solid var(--border)"><?= htmlspecialchars($fin['puesto_nombre'] ?? '—') ?></td>
      <td style="padding:7px 12px;font-weight:600;color:#475569;background:#f8fafc;border-bottom:1px solid var(--border)">Departamento</td>
      <td style="padding:7px 12px;border-bottom:1px solid var(--border)"><?= htmlspecialchars($fin['dept_nombre'] ?? '—') ?></td>
    </tr>
    <tr>
      <td style="padding:7px 12px;font-weight:600;color:#475569;background:#f8fafc;border-bottom:1px solid var(--border)">RFC</td>
      <td style="padding:7px 12px;border-bottom:1px solid var(--border)"><?= htmlspecialchars($fin['rfc'] ?? '—') ?></td>
      <td style="padding:7px 12px;font-weight:600;color:#475569;background:#f8fafc;border-bottom:1px solid var(--border)">NSS (IMSS)</td>
      <td style="padding:7px 12px;border-bottom:1px solid var(--border)"><?= htmlspecialchars($fin['nss'] ?? '—') ?></td>
    </tr>
    <tr>
      <td style="padding:7px 12px;font-weight:600;color:#475569;background:#f8fafc;border-bottom:1px solid var(--border)">Fecha de ingreso</td>
      <td style="padding:7px 12px;border-bottom:1px solid var(--border)"><?= fechaEsp($fin['fecha_termino'] ? $fin['fecha_termino'] : '—') ?></td>
      <td style="padding:7px 12px;font-weight:600;color:#475569;background:#f8fafc;border-bottom:1px solid var(--border)">Fecha de término</td>
      <td style="padding:7px 12px;border-bottom:1px solid var(--border)"><?= fechaEsp($fin['fecha_termino']) ?></td>
    </tr>
    <tr>
      <td style="padding:7px 12px;font-weight:600;color:#475569;background:#f8fafc">Motivo</td>
      <td style="padding:7px 12px" colspan="3"><strong><?= htmlspecialchars($motivoLabels[$fin['motivo']] ?? $fin['motivo']) ?></strong></td>
    </tr>
  </table>

  <!-- Datos salariales -->
  <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:16px">
    <?php
    $stats = [
        'Salario diario'    => '$' . number_format($fin['salario_diario'], 2),
        'SDI'               => '$' . number_format($fin['salario_diario_integrado'], 2),
        'Factor integr.'    => $fin['factor_integracion'],
        'Años de servicio'  => number_format($fin['anos_servicio'], 2),
        'Días trabajados'   => $fin['dias_servicio_total'],
    ];
    foreach ($stats as $lbl => $val): ?>
    <div class="salary-stat">
      <span class="val"><?= $val ?></span>
      <span class="lbl"><?= $lbl ?></span>
    </div>
    <?php endforeach ?>
  </div>

  <!-- Tabla de cálculo -->
  <table class="calc-table" style="width:100%;border:1px solid var(--border);border-radius:6px;overflow:hidden;margin-bottom:16px">
    <thead>
      <tr>
        <th style="width:45%">Concepto</th>
        <th style="width:30%">Referencia</th>
        <th style="width:25%;text-align:right">Monto</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $rows = [];
      if ($fin['dias_pendientes'] > 0)
          $rows[] = ['Días pendientes de pago', "{$fin['dias_pendientes']} días × \${$fin['salario_diario']}/día", $fin['monto_dias_pendientes']];
      $rows[] = ['Aguinaldo proporcional', number_format($fin['dias_aguinaldo_prop'],2).' días (15 días/año)', $fin['monto_aguinaldo_prop']];
      $rows[] = ['Vacaciones proporcionales', number_format($fin['dias_vacaciones_prop'],2).' días', $fin['monto_vacaciones_prop']];
      $rows[] = ['Prima vacacional (25%)', 'Art. 80 LFT', $fin['monto_prima_vacacional_prop']];
      if ($fin['monto_prima_antiguedad'] > 0)
          $rows[] = ['Prima de antigüedad', '12 días × '.number_format($fin['anos_servicio'],2).' años (Art. 162)', $fin['monto_prima_antiguedad']];
      if ($fin['tipo'] === 'liquidacion') {
          $rows[] = ['3 meses de salario integrado', '90 días × $'.number_format($fin['salario_diario_integrado'],2).'/día', $fin['monto_tres_meses']];
          $rows[] = ['20 días por año de servicio', '20 × '.number_format($fin['anos_servicio'],2).' años × SDI', $fin['monto_veinte_dias']];
      }
      if ($fin['otras_percepciones'] > 0)
          $rows[] = ['Otras percepciones', '—', $fin['otras_percepciones']];
      foreach ($rows as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r[0]) ?></td>
        <td style="color:var(--gray);font-size:12px"><?= htmlspecialchars($r[1]) ?></td>
        <td style="text-align:right">$<?= number_format($r[2], 2) ?></td>
      </tr>
      <?php endforeach ?>
      <tr class="subtotal">
        <td colspan="2" style="font-weight:600">Total percepciones</td>
        <td style="text-align:right;font-weight:600">$<?= number_format($fin['total_percepciones'], 2) ?></td>
      </tr>
      <?php if ($fin['otras_deducciones'] > 0): ?>
      <tr>
        <td>Otras deducciones</td>
        <td style="color:var(--gray);font-size:12px">—</td>
        <td style="text-align:right;color:var(--danger)">−$<?= number_format($fin['otras_deducciones'], 2) ?></td>
      </tr>
      <?php endif ?>
      <tr class="total-row">
        <td colspan="2" style="font-size:15px">TOTAL NETO A RECIBIR</td>
        <td style="text-align:right;font-size:15px">$<?= number_format($fin['total_neto'], 2) ?></td>
      </tr>
    </tbody>
  </table>

  <?php if (!empty($fin['notas'])): ?>
  <div style="background:#f8fafc;border-left:4px solid var(--primary);padding:10px 14px;border-radius:0 6px 6px 0;margin-bottom:16px;font-size:13px">
    <strong>Notas:</strong> <?= nl2br(htmlspecialchars($fin['notas'], ENT_QUOTES)) ?>
  </div>
  <?php endif ?>

  <?php if ($fin['estado'] === 'pagado' && $fin['fecha_pago']): ?>
  <p style="font-size:12px;color:var(--gray);margin-bottom:16px">Pagado el: <?= fechaEsp($fin['fecha_pago']) ?></p>
  <?php endif ?>

  <!-- Firmas -->
  <div class="firmas" style="margin-top:60px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:40px;text-align:center">
    <?php
    $ciudad = 'Ciudad de México';
    $hoy    = fechaEsp(date('Y-m-d'));
    $firmas = [
        ['Trabajador', htmlspecialchars(trim($fin['emp_nombre']), ENT_QUOTES), "Firma y acuse de recibo"],
        ['Recursos Humanos', 'RRHH – Sharclean', 'Nombre y firma'],
        ['Representante Legal', 'Sharclean S.A. de C.V.', 'Nombre y firma'],
    ];
    foreach ($firmas as $firma): ?>
    <div style="border-top:1px solid #0f172a;padding-top:8px;font-size:11px">
      <strong><?= $firma[0] ?></strong><br>
      <?= $firma[1] ?><br>
      <span style="color:var(--gray)"><?= $firma[2] ?></span>
    </div>
    <?php endforeach ?>
  </div>
  <p style="text-align:center;font-size:11px;color:var(--gray);margin-top:20px">
    En <?= $ciudad ?>, a <?= $hoy ?>.
  </p>
</div>

<?php endif ?>
</div><!-- /container -->

<script>
<?php if ($view === 'crear'): ?>
/* ── CREAR view JS ──────────────────────────────────────────── */
let calcData = null;

async function calcularFiniquito() {
  const eid  = document.getElementById('empleado_id').value;
  const ft   = document.getElementById('fecha_termino').value;
  const mot  = document.getElementById('motivo').value;
  const dp   = document.getElementById('dias_pendientes').value;
  const errEl = document.getElementById('errCalc');
  errEl.style.display = 'none';

  if (!eid || !ft || !mot) { errEl.textContent = 'Selecciona empleado, fecha y motivo.'; errEl.style.display = 'block'; return; }

  const btn = document.getElementById('btnCalcular');
  btn.disabled = true; btn.textContent = 'Calculando...';

  const fd = new FormData();
  fd.append('action', 'calcular');
  fd.append('empleado_id', eid);
  fd.append('fecha_termino', ft);
  fd.append('motivo', mot);
  fd.append('dias_pendientes', dp);

  try {
    const res  = await fetch('finiquitos.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) { errEl.textContent = data.msg; errEl.style.display = 'block'; return; }
    calcData = data.calc;
    renderPreview(data.calc, data.emp);
    document.getElementById('previewSection').style.display = 'block';
    document.getElementById('previewSection').scrollIntoView({ behavior: 'smooth' });
  } catch { errEl.textContent = 'Error de conexión.'; errEl.style.display = 'block'; }
  finally { btn.disabled = false; btn.textContent = '🔢 Calcular finiquito'; }
}

function fmt(n) { return new Intl.NumberFormat('es-MX', { style:'currency', currency:'MXN' }).format(n || 0); }

function renderPreview(c, emp) {
  const tipoLabel = c.tipo === 'liquidacion' ? '⚖️ LIQUIDACIÓN' : '📄 FINIQUITO';
  document.getElementById('previewTitulo').textContent = tipoLabel + ' — ' + emp.nombre;

  document.getElementById('salaryInfo').innerHTML = `
    <div class="salary-stat"><span class="val">${fmt(c.salario_diario)}</span><span class="lbl">Salario diario</span></div>
    <div class="salary-stat"><span class="val">${fmt(c.salario_diario_integrado)}</span><span class="lbl">SDI</span></div>
    <div class="salary-stat"><span class="val">${c.factor_integracion}</span><span class="lbl">Factor integr.</span></div>
    <div class="salary-stat"><span class="val">${Number(c.anos_servicio).toFixed(2)}</span><span class="lbl">Años servicio</span></div>
    <div class="salary-stat"><span class="val">${c.dias_servicio_total}</span><span class="lbl">Días totales</span></div>
  `;

  const rows = [];
  if (c.dias_pendientes > 0)
    rows.push([`Días pendientes de pago`, `${c.dias_pendientes} días × ${fmt(c.salario_diario)}/día`, c.monto_dias_pendientes]);
  rows.push([`Aguinaldo proporcional`, `${Number(c.dias_aguinaldo_prop).toFixed(2)} días de ${c.dias_anio_aguinaldo} en año`, c.monto_aguinaldo_prop]);
  rows.push([`Vacaciones proporcionales`, `${Number(c.dias_vacaciones_prop).toFixed(2)} días (${c.dias_vac_anio} días/año)`, c.monto_vacaciones_prop]);
  rows.push([`Prima vacacional (25%)`, `Art. 80 LFT`, c.monto_prima_vacacional_prop]);
  if (c.monto_prima_antiguedad > 0)
    rows.push([`Prima de antigüedad`, `12 días × ${Number(c.anos_servicio).toFixed(2)} años (Art. 162)`, c.monto_prima_antiguedad]);
  if (c.tipo === 'liquidacion') {
    rows.push([`3 meses de salario integrado`, `90 días × ${fmt(c.salario_diario_integrado)}/día`, c.monto_tres_meses]);
    rows.push([`20 días por año de servicio`, `20 × ${Number(c.anos_servicio).toFixed(2)} años × SDI`, c.monto_veinte_dias]);
  }

  const tbody = rows.map(r =>
    `<tr><td>${r[0]}</td><td style="color:#64748b;font-size:12px">${r[1]}</td><td style="text-align:right;font-weight:600">${fmt(r[2])}</td></tr>`
  ).join('');

  document.getElementById('calcTable').innerHTML = `
    <thead><tr><th>Concepto</th><th>Referencia</th><th style="text-align:right">Monto</th></tr></thead>
    <tbody>${tbody}</tbody>
  `;

  actualizarTotal();
}

function actualizarTotal() {
  if (!calcData) return;
  const otrasP = parseFloat(document.getElementById('otras_percepciones').value) || 0;
  const otrasD = parseFloat(document.getElementById('otras_deducciones').value) || 0;
  const neto   = calcData.total_percepciones + otrasP - otrasD;
  document.getElementById('totalDisplay').textContent = 'Total neto: ' + fmt(neto);
}

async function guardarFiniquito() {
  if (!calcData) return;
  const errEl = document.getElementById('errGuardar');
  errEl.style.display = 'none';
  const btn = document.getElementById('btnGuardar');
  btn.disabled = true; btn.textContent = 'Guardando...';

  const fd = new FormData();
  fd.append('action', 'guardar');
  fd.append('empleado_id', document.getElementById('empleado_id').value);
  fd.append('fecha_termino', document.getElementById('fecha_termino').value);
  fd.append('motivo', document.getElementById('motivo').value);
  fd.append('dias_pendientes', document.getElementById('dias_pendientes').value);
  fd.append('otras_percepciones', document.getElementById('otras_percepciones').value);
  fd.append('otras_deducciones', document.getElementById('otras_deducciones').value);
  fd.append('notas', document.getElementById('notas').value);
  if (document.getElementById('dar_baja').checked) fd.append('dar_baja', '1');

  try {
    const res  = await fetch('finiquitos.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (!data.ok) { errEl.textContent = data.msg || 'Error al guardar.'; errEl.style.display = 'block'; return; }
    window.location = 'finiquitos.php?view=detalle&id=' + data.id;
  } catch { errEl.textContent = 'Error de conexión.'; errEl.style.display = 'block'; }
  finally { btn.disabled = false; btn.textContent = '💾 Guardar finiquito'; }
}
<?php endif ?>

<?php if ($view === 'detalle'): ?>
/* ── DETALLE view JS ────────────────────────────────────────── */
const finId = <?= (int)$fin['id'] ?>;

async function accion(actionName) {
  if (!confirm('¿Confirmas esta acción?')) return;
  const fd = new FormData();
  fd.append('action', actionName);
  fd.append('id', finId);
  const res  = await fetch('finiquitos.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) location.reload(); else alert(data.msg || 'Error.');
}

function showPagarModal() {
  document.getElementById('modalPagar').style.display = 'flex';
}

async function accionPagar() {
  const fp = document.getElementById('fechaPagoInput').value;
  if (!fp) return;
  const fd = new FormData();
  fd.append('action', 'marcar_pagado');
  fd.append('id', finId);
  fd.append('fecha_pago', fp);
  const res  = await fetch('finiquitos.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) location.reload(); else alert('Error al registrar pago.');
}

async function eliminar() {
  if (!confirm('¿Eliminar este borrador? Esta acción no se puede deshacer.')) return;
  const fd = new FormData();
  fd.append('action', 'eliminar');
  fd.append('id', finId);
  const res  = await fetch('finiquitos.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.ok) window.location = 'finiquitos.php'; else alert('No se pudo eliminar (solo se eliminan borradores).');
}
<?php endif ?>
</script>

</body>
</html>
