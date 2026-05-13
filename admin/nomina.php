<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requirePermiso('nomina');
require_once __DIR__ . '/../db/csrf.php';

/* ─── Helpers ──────────────────────────────────────────────── */
function h(string $s): string   { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fd(?string $d): string { return ($d && $d !== '0000-00-00') ? date('d/m/Y', strtotime($d)) : '—'; }
function money(mixed $v): string { return '$' . number_format((float)($v ?? 0), 2); }
function fetchAll($r): array    { $a = []; if ($r) while ($row = $r->fetch_assoc()) $a[] = $row; return $a; }

function nbadge(string $s): string {
    $m = [
        'borrador' => ['#fef9c3','#854d0e','Borrador'],
        'cerrado'  => ['#dbeafe','#1d4ed8','Cerrado'],
        'pagado'   => ['#dcfce7','#166534','Pagado'],
    ];
    [$bg, $c, $l] = $m[$s] ?? ['#f1f5f9','#64748b', $s];
    return "<span style='display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:$bg;color:$c;'>$l</span>";
}
function lbadge(string $s): string {
    $m = [
        'calculado' => ['#f1f5f9','#64748b','Calculado'],
        'revisado'  => ['#fef9c3','#854d0e','Revisado'],
        'pagado'    => ['#dcfce7','#166534','Pagado'],
    ];
    [$bg, $c, $l] = $m[$s] ?? ['#f1f5f9','#64748b', $s];
    return "<span style='display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;background:$bg;color:$c;'>$l</span>";
}

/* ─── Constantes fiscales 2025 ─────────────────────────────── */
define('UMA_DIARIA', 113.14);   // UMA diaria vigente 2025

/**
 * IMSS cuota obrera sobre el SBC diario.
 * Tasas: EM-dinero 0.25% + Invalidez y Vida 0.625% + CEAV 1.125% = 2.00%
 * Más: excedente EM-especie 0.40% sobre (SBC - 3×UMA) si SBC > 3 UMA
 */
function calcIMSS(float $sbc, int $dias): float {
    $fijo   = $sbc * $dias * 0.02;
    $exc    = max(0.0, $sbc - 3.0 * UMA_DIARIA);
    $em_exc = $exc * $dias * 0.004;
    return round($fijo + $em_exc, 2);
}

/**
 * ISR período (Art. 96 LISR) por método de proyección anual.
 * Retorna ['isr' => float, 'subsidio' => float] ya prorrateados al período.
 * Tabla mensual 2025 = tabla anual 2025 / 12.
 */
function calcISRPeriodo(float $percepciones, string $tipo): array {
    $factor = ($tipo === 'quincenal') ? 2.0 : 4.345; // proyección a mensual
    $men    = $percepciones * $factor;

    /* Tabla Art. 96 LISR mensual 2025 */
    static $tisr = [
        [0.01,       746.04,       0.00, 0.0192],
        [746.05,    6332.05,      14.32, 0.0640],
        [6332.06,  11128.01,     371.83, 0.1088],
        [11128.02, 12935.82,     893.63, 0.1600],
        [12935.83, 15487.71,    1182.88, 0.1792],
        [15487.72, 31236.49,    1640.18, 0.2136],
        [31236.50, 49233.00,    5004.12, 0.2352],
        [49233.01, 93993.90,    9236.89, 0.3000],
        [93993.91,125325.20,   22665.17, 0.3200],
        [125325.21,375975.61,  32691.18, 0.3400],
        [375975.62, 1e12,     117912.32, 0.3500],
    ];
    $isr_m = 0.0;
    foreach ($tisr as [$li, $ls, $cuota, $tasa]) {
        if ($men < $li) break;
        $isr_m = $cuota + (min($men, $ls) - $li) * $tasa;
    }

    /* Subsidio al empleo mensual 2025 */
    static $tsub = [
        [0,       1768.96, 407.02],
        [1768.97, 2653.38, 406.83],
        [2653.39, 3472.84, 406.62],
        [3472.85, 3537.87, 392.77],
        [3537.88, 4446.15, 382.46],
        [4446.16, 4717.18, 354.23],
        [4717.19, 5335.42, 324.87],
        [5335.43, 6224.67, 294.63],
        [6224.68, 7113.90, 253.54],
        [7113.91, 7382.33, 175.49],
        [7382.34, 1e12,      0.00],
    ];
    $sub_m = 0.0;
    foreach ($tsub as [$li, $ls, $s]) {
        if ($men <= $ls) { $sub_m = $s; break; }
    }

    return [
        'isr'      => round(max(0.0, $isr_m - $sub_m) / $factor, 2),
        'subsidio' => round($sub_m / $factor, 2),
    ];
}

/**
 * Calcula todos los valores de una línea desde la tabla asistencia.
 */
function calcularDesdeAsistencia(array $emp, string $fi, string $ff, string $tipo, mysqli $db): array {
    $eid     = (int)$emp['empleado_id'];
    $sd      = round((float)($emp['salario_base'] ?? 0) / 30, 4);
    $jornada = max(1, (int)($emp['jornada_horas'] ?? 8));
    $dias_cal = (int)((strtotime($ff) - strtotime($fi)) / 86400) + 1;

    $fi_s = $db->real_escape_string($fi);
    $ff_s = $db->real_escape_string($ff);
    $rows = fetchAll($db->query(
        "SELECT tipo,
                COALESCE(horas_extra,0)      AS horas_extra,
                COALESCE(minutos_retardo,0)  AS min_ret
         FROM asistencia
         WHERE empleado_id = $eid
           AND fecha BETWEEN '$fi_s' AND '$ff_s'"
    ));

    $dias_trab = 0; $dias_fi = 0; $dias_fj = 0;
    $dias_vac  = 0; $dias_incap = 0;
    $h_extra   = 0.0; $min_ret = 0;

    foreach ($rows as $r) {
        switch ($r['tipo']) {
            case 'normal':              $dias_trab++;  break;
            case 'falta_injustificada': $dias_fi++;    break;
            case 'falta_justificada':   $dias_fj++;    break;
            case 'vacaciones':          $dias_vac++;   break;
            case 'incapacidad':         $dias_incap++; break;
        }
        $h_extra += (float)$r['horas_extra'];
        $min_ret += (int)$r['min_ret'];
    }

    // Días pagados = todos menos falta injustificada e incapacidad (sin goce)
    $dias_pagados = $dias_trab + $dias_fj + $dias_vac;

    $sueldo_base      = round($sd * $dias_pagados, 2);
    $descuento_faltas = round($sd * $dias_fi, 2);
    $valor_hora       = $jornada > 0 ? $sd / $jornada : 0;
    $he_monto         = round($valor_hora * $h_extra * 2.0, 2); // 200%

    $total_perc = $sueldo_base + $he_monto;
    $imss       = calcIMSS($sd, $dias_cal);
    $isr_data   = calcISRPeriodo($total_perc, $tipo);

    $total_ded = round($imss + $isr_data['isr'] + $descuento_faltas, 2);
    $neto      = round($total_perc - $total_ded, 2);

    return [
        'dias_periodo'             => $dias_cal,
        'dias_trabajados'          => (float)$dias_trab,
        'dias_falta_injustificada' => (float)$dias_fi,
        'dias_falta_justificada'   => (float)$dias_fj,
        'dias_vacaciones'          => (float)$dias_vac,
        'dias_incapacidad'         => (float)$dias_incap,
        'horas_extra'              => $h_extra,
        'minutos_retardo'          => $min_ret,
        'salario_diario'           => $sd,
        'dias_pagados'             => (float)$dias_pagados,
        'sueldo_base'              => $sueldo_base,
        'horas_extra_monto'        => $he_monto,
        'prima_dominical'          => 0.0,
        'bono_asistencia'          => 0.0,
        'otras_percepciones'       => 0.0,
        'total_percepciones'       => round($total_perc, 2),
        'imss_obrero'              => $imss,
        'isr_retenido'             => $isr_data['isr'],
        'subsidio_empleo'          => $isr_data['subsidio'],
        'descuento_faltas'         => $descuento_faltas,
        'otras_deducciones'        => 0.0,
        'total_deducciones'        => $total_ded,
        'neto_pagar'               => $neto,
    ];
}

/** Recalcula totales de un período y actualiza nomina_periodos */
function actualizarTotalesPeriodo(int $pid, mysqli $db): void {
    $r = $db->query(
        "SELECT COUNT(*) AS cnt,
                SUM(total_percepciones) AS tp,
                SUM(total_deducciones)  AS td,
                SUM(neto_pagar)         AS tn
         FROM nomina_lineas WHERE periodo_id = $pid"
    )->fetch_assoc();
    $tp  = round((float)($r['tp'] ?? 0), 2);
    $td  = round((float)($r['td'] ?? 0), 2);
    $tn  = round((float)($r['tn'] ?? 0), 2);
    $cnt = (int)($r['cnt'] ?? 0);
    $db->query(
        "UPDATE nomina_periodos
         SET total_percepciones=$tp, total_deducciones=$td,
             total_neto=$tn, empleados_count=$cnt
         WHERE id=$pid"
    );
}

/* ─── POST (AJAX) ──────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => 'Token inválido.']);
        exit;
    }

    $db  = getAdminDB();
    $act = $_POST['action'] ?? '';

    /* ── crear_periodo ──────────────────────────────────────── */
    if ($act === 'crear_periodo') {
        $tipo   = in_array($_POST['tipo'] ?? '', ['semanal','quincenal']) ? $_POST['tipo'] : '';
        $fi     = trim($_POST['fecha_inicio'] ?? '');
        $ff     = trim($_POST['fecha_fin']    ?? '');
        $fp     = trim($_POST['fecha_pago']   ?? '') ?: null;
        $notas  = trim($_POST['notas']        ?? '') ?: null;
        $notas_s = $notas ? "'".$db->real_escape_string($notas)."'" : 'NULL';
        $fp_s    = $fp    ? "'".$db->real_escape_string($fp)."'"    : 'NULL';

        if (!$tipo || !$fi || !$ff) {
            echo json_encode(['ok'=>false,'msg'=>'Datos incompletos.']); exit;
        }
        if (strtotime($ff) < strtotime($fi)) {
            echo json_encode(['ok'=>false,'msg'=>'La fecha fin debe ser posterior al inicio.']); exit;
        }

        $fi_s = $db->real_escape_string($fi);
        $ff_s = $db->real_escape_string($ff);
        $tipo_s = $db->real_escape_string($tipo);

        // Check duplicate
        $dup = $db->query("SELECT id FROM nomina_periodos WHERE tipo='$tipo_s' AND fecha_inicio='$fi_s'")->fetch_assoc();
        if ($dup) {
            echo json_encode(['ok'=>false,'msg'=>'Ya existe un período $tipo con esa fecha de inicio.']); exit;
        }

        $db->query(
            "INSERT INTO nomina_periodos (tipo, fecha_inicio, fecha_fin, fecha_pago, notas, creado_por)
             VALUES ('$tipo_s','$fi_s','$ff_s',$fp_s,$notas_s,'admin')"
        );
        $pid = (int)$db->insert_id;

        // Crear líneas para todos los empleados activos
        $emps = fetchAll($db->query(
            "SELECT id, COALESCE(salario_base, 0) AS salario_base FROM empleados
             WHERE estatus IN ('activo','vacaciones') ORDER BY apellido_paterno"
        ));
        $dias_cal = (int)((strtotime($ff) - strtotime($fi)) / 86400) + 1;
        foreach ($emps as $e) {
            $eid = (int)$e['id'];
            $sd  = round((float)$e['salario_base'] / 30, 4);
            $db->query(
                "INSERT IGNORE INTO nomina_lineas (periodo_id, empleado_id, dias_periodo, salario_diario)
                 VALUES ($pid, $eid, $dias_cal, $sd)"
            );
        }
        actualizarTotalesPeriodo($pid, $db);
        $db->close();
        echo json_encode(['ok'=>true,'msg'=>'Período creado.','redirect'=>"nomina.php?id=$pid"]);
        exit;
    }

    /* ── calcular_periodo ───────────────────────────────────── */
    if ($act === 'calcular_periodo') {
        $pid = (int)($_POST['periodo_id'] ?? 0);
        if (!$pid) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }

        $periodo = $db->query("SELECT * FROM nomina_periodos WHERE id=$pid")->fetch_assoc();
        if (!$periodo || $periodo['estado'] === 'cerrado') {
            echo json_encode(['ok'=>false,'msg'=>'Período no disponible para cálculo.']); exit;
        }

        $fi   = $periodo['fecha_inicio'];
        $ff   = $periodo['fecha_fin'];
        $tipo = $periodo['tipo'];

        $lineas = fetchAll($db->query(
            "SELECT nl.id AS linea_id, nl.empleado_id,
                    COALESCE(e.salario_base, 0) AS salario_base,
                    COALESCE(e.jornada_horas, 8) AS jornada_horas
             FROM nomina_lineas nl
             JOIN empleados e ON e.id = nl.empleado_id
             WHERE nl.periodo_id = $pid"
        ));

        foreach ($lineas as $linea) {
            $c   = calcularDesdeAsistencia($linea, $fi, $ff, $tipo, $db);
            $lid = (int)$linea['linea_id'];
            $db->query(
                "UPDATE nomina_lineas SET
                    dias_periodo={$c['dias_periodo']},
                    dias_trabajados={$c['dias_trabajados']},
                    dias_falta_injustificada={$c['dias_falta_injustificada']},
                    dias_falta_justificada={$c['dias_falta_justificada']},
                    dias_vacaciones={$c['dias_vacaciones']},
                    dias_incapacidad={$c['dias_incapacidad']},
                    horas_extra={$c['horas_extra']},
                    minutos_retardo={$c['minutos_retardo']},
                    salario_diario={$c['salario_diario']},
                    dias_pagados={$c['dias_pagados']},
                    sueldo_base={$c['sueldo_base']},
                    horas_extra_monto={$c['horas_extra_monto']},
                    total_percepciones={$c['total_percepciones']},
                    imss_obrero={$c['imss_obrero']},
                    isr_retenido={$c['isr_retenido']},
                    subsidio_empleo={$c['subsidio_empleo']},
                    descuento_faltas={$c['descuento_faltas']},
                    total_deducciones={$c['total_deducciones']},
                    neto_pagar={$c['neto_pagar']},
                    estado='calculado'
                 WHERE id=$lid"
            );
        }
        actualizarTotalesPeriodo($pid, $db);
        $db->close();
        echo json_encode(['ok'=>true,'msg'=>count($lineas).' empleados calculados.','reload'=>true]);
        exit;
    }

    /* ── guardar_linea ──────────────────────────────────────── */
    if ($act === 'guardar_linea') {
        $lid = (int)($_POST['linea_id'] ?? 0);
        if (!$lid) { echo json_encode(['ok'=>false,'msg'=>'ID inválido.']); exit; }

        $linea = $db->query("SELECT nl.*, np.estado AS p_estado, np.id AS pid
                             FROM nomina_lineas nl
                             JOIN nomina_periodos np ON np.id = nl.periodo_id
                             WHERE nl.id=$lid")->fetch_assoc();
        if (!$linea || $linea['p_estado'] === 'cerrado') {
            echo json_encode(['ok'=>false,'msg'=>'Línea no editable.']); exit;
        }

        $flds = ['sueldo_base','horas_extra_monto','prima_dominical','bono_asistencia',
                 'otras_percepciones','imss_obrero','isr_retenido','otras_deducciones',
                 'descuento_faltas'];
        $vals = [];
        foreach ($flds as $f) $vals[$f] = round((float)($_POST[$f] ?? $linea[$f]), 2);

        $obs  = trim($_POST['observaciones'] ?? '') ?: null;
        $obs_s = $obs ? "'".$db->real_escape_string($obs)."'" : 'NULL';

        $tp = $vals['sueldo_base'] + $vals['horas_extra_monto'] + $vals['prima_dominical']
            + $vals['bono_asistencia'] + $vals['otras_percepciones'];
        $td = $vals['imss_obrero'] + $vals['isr_retenido'] + $vals['descuento_faltas']
            + $vals['otras_deducciones'];
        $neto = round($tp - $td, 2);

        $sets = [];
        foreach ($vals as $f => $v) $sets[] = "$f=$v";
        $sets[] = "total_percepciones=$tp";
        $sets[] = "total_deducciones=$td";
        $sets[] = "neto_pagar=$neto";
        $sets[] = "observaciones=$obs_s";
        $sets[] = "estado='revisado'";

        $db->query("UPDATE nomina_lineas SET " . implode(',', $sets) . " WHERE id=$lid");
        actualizarTotalesPeriodo((int)$linea['pid'], $db);
        $db->close();
        echo json_encode(['ok'=>true,'msg'=>'Guardado.']);
        exit;
    }

    /* ── cerrar_periodo ─────────────────────────────────────── */
    if ($act === 'cerrar_periodo') {
        $pid = (int)($_POST['periodo_id'] ?? 0);
        $db->query("UPDATE nomina_periodos SET estado='cerrado' WHERE id=$pid AND estado='borrador'");
        $db->close();
        echo json_encode(['ok'=>true,'reload'=>true]);
        exit;
    }

    /* ── marcar_pagado ──────────────────────────────────────── */
    if ($act === 'marcar_pagado') {
        $pid = (int)($_POST['periodo_id'] ?? 0);
        $db->query("UPDATE nomina_periodos SET estado='pagado' WHERE id=$pid AND estado='cerrado'");
        $db->close();
        echo json_encode(['ok'=>true,'reload'=>true]);
        exit;
    }

    /* ── eliminar_periodo ───────────────────────────────────── */
    if ($act === 'eliminar_periodo') {
        $pid = (int)($_POST['periodo_id'] ?? 0);
        $db->query("DELETE FROM nomina_lineas  WHERE periodo_id=$pid");
        $db->query("DELETE FROM nomina_periodos WHERE id=$pid AND estado='borrador'");
        $db->close();
        echo json_encode(['ok'=>true,'redirect'=>'nomina.php']);
        exit;
    }

    $db->close();
    echo json_encode(['ok'=>false,'msg'=>'Acción desconocida.']);
    exit;
}

/* ─── GET: cargar datos ────────────────────────────────────── */
$db    = getAdminDB();
$token = generateCsrfToken();
$pid   = (int)($_GET['id'] ?? 0);

$periodo = null;
$lineas  = [];
$periodos = [];

if ($pid) {
    $periodo = $db->query("SELECT * FROM nomina_periodos WHERE id=$pid")->fetch_assoc();
    if ($periodo) {
        $lineas = fetchAll($db->query(
            "SELECT nl.*,
                    e.numero_empleado, e.nombre, e.apellido_paterno, e.apellido_materno,
                    e.salario_base,
                    d.nombre AS departamento
             FROM nomina_lineas nl
             JOIN empleados e ON e.id = nl.empleado_id
             LEFT JOIN departamentos d ON d.id = e.departamento_id
             WHERE nl.periodo_id = $pid
             ORDER BY e.apellido_paterno, e.nombre"
        ));
    }
} else {
    $periodos = fetchAll($db->query(
        "SELECT * FROM nomina_periodos ORDER BY fecha_inicio DESC LIMIT 100"
    ));
}

/* ── CSV export (antes de cualquier HTML) ───────────────────── */
if (isset($_GET['export']) && $_GET['export'] === 'csv' && $pid && $periodo) {
    $rows = fetchAll($db->query(
        "SELECT e.numero_empleado, e.nombre, e.apellido_paterno, e.apellido_materno,
                e.rfc, e.nss, e.clabe_bancaria, e.banco,
                nl.salario_diario, nl.dias_pagados,
                nl.sueldo_base, nl.horas_extra_monto, nl.prima_dominical,
                nl.bono_asistencia, nl.otras_percepciones, nl.total_percepciones,
                nl.imss_obrero, nl.isr_retenido, nl.descuento_faltas,
                nl.otras_deducciones, nl.total_deducciones, nl.neto_pagar,
                nl.observaciones
         FROM nomina_lineas nl
         JOIN empleados e ON e.id = nl.empleado_id
         WHERE nl.periodo_id = $pid
         ORDER BY e.apellido_paterno, e.nombre"
    ));
    $db->close();

    header('Content-Type: text/csv; charset=UTF-8');
    $fn = 'nomina_' . $periodo['tipo'] . '_' . $periodo['fecha_inicio'] . '.csv';
    header('Content-Disposition: attachment; filename="' . $fn . '"');

    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel
    fputcsv($out, [
        'No.Empleado','Nombre','Ap. Paterno','Ap. Materno','RFC','NSS',
        'CLABE','Banco','Sal/Día','Días pag.',
        'Sueldo base','H.Extra $','Prima Dom.','Bono Asist.','Otras perc.',
        'TOTAL PERCEPCIONES',
        'IMSS obrero','ISR','Desc.faltas','Otras ded.',
        'TOTAL DEDUCCIONES','NETO A PAGAR','Observaciones'
    ]);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['numero_empleado'],
            $r['nombre'], $r['apellido_paterno'], $r['apellido_materno'] ?? '',
            $r['rfc'] ?? '', $r['nss'] ?? '',
            $r['clabe_bancaria'] ?? '', $r['banco'] ?? '',
            $r['salario_diario'], $r['dias_pagados'],
            $r['sueldo_base'], $r['horas_extra_monto'],
            $r['prima_dominical'], $r['bono_asistencia'], $r['otras_percepciones'],
            $r['total_percepciones'],
            $r['imss_obrero'], $r['isr_retenido'], $r['descuento_faltas'],
            $r['otras_deducciones'], $r['total_deducciones'],
            $r['neto_pagar'], $r['observaciones'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}
$db->close();

$editable = ($periodo && $periodo['estado'] === 'borrador');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nómina – Sharclean Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,Arial,sans-serif;font-size:14px;background:#f4f7fb;color:#1e293b}
a{color:inherit;text-decoration:none}

/* Header */
.admin-header{background:#0284C7;color:#fff;padding:0 24px;height:56px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:10}.admin-brand{display:flex;align-items:center;gap:10px}.admin-brand img{height:36px}
.admin-header h1{font-size:18px;font-weight:800}
.nav-links{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.nav-links a{color:rgba(255,255,255,.85);font-size:13px;padding:6px 12px;border:1px solid rgba(255,255,255,.3);border-radius:6px;white-space:nowrap;transition:.15s}
.nav-links a:hover{background:rgba(255,255,255,.15)}
.nav-links a.active{background:rgba(255,255,255,.95);color:#0284C7;font-weight:700;border-color:transparent}

/* Layout */
.content{max-width:1400px;margin:0 auto;padding:24px 20px}

/* Buttons */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;border:none;cursor:pointer;font-size:13px;font-weight:600;transition:.15s}
.btn-primary{background:#0284C7;color:#fff}.btn-primary:hover{background:#0369a1}
.btn-success{background:#16a34a;color:#fff}.btn-success:hover{background:#15803d}
.btn-warn{background:#d97706;color:#fff}.btn-warn:hover{background:#b45309}
.btn-danger{background:#dc2626;color:#fff}.btn-danger:hover{background:#b91c1c}
.btn-outline{background:#fff;color:#374151;border:1px solid #d1d5db}.btn-outline:hover{background:#f9fafb}
.btn-sm{padding:5px 10px;font-size:12px;border-radius:6px}

/* Cards */
.card{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.08);padding:20px;margin-bottom:20px}
.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:12px;flex-wrap:wrap}
.card-title{font-size:16px;font-weight:700}

/* Stats */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:20px}
.stat-box{background:#fff;border-radius:10px;padding:16px;box-shadow:0 1px 4px rgba(0,0,0,.07);text-align:center}
.stat-val{font-size:22px;font-weight:800;color:#0284C7;line-height:1.2}
.stat-val.green{color:#16a34a}.stat-val.red{color:#dc2626}
.stat-lbl{font-size:11px;color:#64748b;margin-top:4px;font-weight:600;text-transform:uppercase;letter-spacing:.04em}

/* Table */
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:900px}
th{background:#f8fafc;padding:9px 10px;text-align:left;font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.04em;border-bottom:2px solid #e2e8f0;white-space:nowrap}
td{padding:8px 10px;border-bottom:1px solid #f1f5f9;font-size:13px;vertical-align:middle}
tr:hover td{background:#f8fafc}
.td-r{text-align:right}
.td-c{text-align:center}
.td-money{text-align:right;font-variant-numeric:tabular-nums;font-weight:600}
.td-neto{text-align:right;font-weight:800;color:#0284C7;font-size:14px}
.tfoot-row td{background:#f0f9ff;font-weight:700;border-top:2px solid #bae6fd;font-size:13px}

/* Form */
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px}
label{display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:5px}
.inp{width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;font-size:13px;font-family:inherit}
.inp:focus{outline:none;border-color:#0284C7;box-shadow:0 0 0 3px rgba(2,132,199,.12)}
select.inp{cursor:pointer;background:#fff}

/* Edit panel */
.edit-panel{background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:16px;margin-top:4px;display:none}
.edit-panel.open{display:block}
.edit-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-bottom:12px}
.edit-grid label{font-size:11px;color:#374151;font-weight:600;display:block;margin-bottom:3px}
.edit-grid .inp{padding:6px 8px;font-size:12px}
.edit-sep{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin:10px 0 6px;padding-bottom:4px;border-bottom:1px solid #e2e8f0}
.edit-actions{display:flex;gap:8px;margin-top:10px}

/* Toast */
#toast{position:fixed;bottom:24px;right:24px;z-index:9999;padding:12px 20px;border-radius:10px;font-size:13px;font-weight:600;display:none;max-width:320px;box-shadow:0 4px 20px rgba(0,0,0,.15)}
#toast.ok{background:#dcfce7;color:#166534}
#toast.err{background:#fee2e2;color:#991b1b}

/* List view period cards */
.periodo-row{display:grid;grid-template-columns:120px 180px 1fr 1fr 1fr 140px 130px;gap:12px;align-items:center;padding:12px 16px;border-bottom:1px solid #f1f5f9}
.periodo-row:hover{background:#f8fafc}
</style>
</head>
<body>

<?php $navTitle = '📋 Nómina'; $activeModule = 'nomina'; require __DIR__ . '/_nav.php'; ?>

<div class="content">

<?php if ($pid && $periodo): /* ══════ DETALLE DE PERÍODO ═══════ */ ?>

  <?php
  $tp_sum = array_sum(array_column($lineas, 'total_percepciones'));
  $td_sum = array_sum(array_column($lineas, 'total_deducciones'));
  $tn_sum = array_sum(array_column($lineas, 'neto_pagar'));
  $tipo_lbl = $periodo['tipo'] === 'quincenal' ? 'Quincenal' : 'Semanal';
  ?>

  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;flex-wrap:wrap;">
    <a href="nomina.php" class="btn btn-outline btn-sm">← Todos los períodos</a>
    <h2 style="font-size:18px;font-weight:800;">
      Nómina <?= h($tipo_lbl) ?>
      &nbsp;<?= h(fd($periodo['fecha_inicio'])) ?> – <?= h(fd($periodo['fecha_fin'])) ?>
    </h2>
    <?= nbadge($periodo['estado']) ?>
    <?php if ($periodo['fecha_pago']): ?>
      <span style="font-size:12px;color:#64748b;">Pago: <?= fd($periodo['fecha_pago']) ?></span>
    <?php endif ?>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-box">
      <div class="stat-val"><?= count($lineas) ?></div>
      <div class="stat-lbl">Empleados</div>
    </div>
    <div class="stat-box">
      <div class="stat-val"><?= money($tp_sum) ?></div>
      <div class="stat-lbl">Total percepciones</div>
    </div>
    <div class="stat-box">
      <div class="stat-val red"><?= money($td_sum) ?></div>
      <div class="stat-lbl">Total deducciones</div>
    </div>
    <div class="stat-box">
      <div class="stat-val green"><?= money($tn_sum) ?></div>
      <div class="stat-lbl">Total neto a pagar</div>
    </div>
  </div>

  <!-- Acciones -->
  <div class="card" style="padding:14px 20px;">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <?php if ($editable): ?>
        <button class="btn btn-primary" onclick="calcular()">
          ⚙️ Calcular desde asistencia
        </button>
        <button class="btn btn-warn" onclick="cerrar()">
          🔒 Cerrar período
        </button>
        <button class="btn btn-danger btn-sm" onclick="eliminar()">
          🗑 Eliminar
        </button>
      <?php elseif ($periodo['estado'] === 'cerrado'): ?>
        <button class="btn btn-success" onclick="marcarPagado()">
          ✅ Marcar como pagado
        </button>
      <?php endif ?>
      <a href="nomina.php?id=<?= $pid ?>&export=csv" class="btn btn-outline">
        📥 Exportar CSV
      </a>
      <button class="btn btn-outline" onclick="window.print()">🖨 Imprimir</button>
      <?php if ($periodo['notas']): ?>
        <span style="font-size:12px;color:#64748b;margin-left:4px;">📝 <?= h($periodo['notas']) ?></span>
      <?php endif ?>
    </div>
  </div>

  <!-- Tabla de líneas -->
  <div class="card" style="padding:0;">
    <div class="tbl-wrap">
      <table id="lineasTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Empleado</th>
            <th>Depto.</th>
            <th class="td-r">Sal/día</th>
            <th class="td-c">Días pag.</th>
            <th class="td-c">H.Extra</th>
            <th class="td-r">Sueldo base</th>
            <th class="td-r">H.E. monto</th>
            <th class="td-r">Otras perc.</th>
            <th class="td-r" style="background:#e0f2fe;">Total perc.</th>
            <th class="td-r">IMSS</th>
            <th class="td-r">ISR</th>
            <th class="td-r">Desc.faltas</th>
            <th class="td-r">Otras ded.</th>
            <th class="td-r" style="background:#fee2e2;">Total ded.</th>
            <th class="td-r" style="background:#dcfce7;">NETO</th>
            <th class="td-c">Estado</th>
            <?php if ($editable): ?><th></th><?php endif ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lineas as $i => $l): ?>
            <?php $name = h($l['apellido_paterno'].' '.$l['nombre']) ?>
            <tr id="row-<?= $l['id'] ?>">
              <td style="color:#94a3b8;font-size:11px;"><?= h($l['numero_empleado'] ?? '') ?></td>
              <td><strong><?= $name ?></strong></td>
              <td style="font-size:12px;color:#64748b;"><?= h($l['departamento'] ?? '—') ?></td>
              <td class="td-money"><?= money($l['salario_diario']) ?></td>
              <td class="td-c"><?= number_format((float)$l['dias_pagados'],1) ?></td>
              <td class="td-c"><?= number_format((float)$l['horas_extra'],2) ?></td>
              <td class="td-money"><?= money($l['sueldo_base']) ?></td>
              <td class="td-money"><?= money($l['horas_extra_monto']) ?></td>
              <td class="td-money"><?= money((float)$l['prima_dominical']+(float)$l['bono_asistencia']+(float)$l['otras_percepciones']) ?></td>
              <td class="td-money" style="background:#f0f9ff;"><?= money($l['total_percepciones']) ?></td>
              <td class="td-money"><?= money($l['imss_obrero']) ?></td>
              <td class="td-money"><?= money($l['isr_retenido']) ?></td>
              <td class="td-money"><?= money($l['descuento_faltas']) ?></td>
              <td class="td-money"><?= money($l['otras_deducciones']) ?></td>
              <td class="td-money" style="background:#fff5f5;"><?= money($l['total_deducciones']) ?></td>
              <td class="td-neto" style="background:#f0fdf4;"><?= money($l['neto_pagar']) ?></td>
              <td class="td-c"><?= lbadge($l['estado']) ?></td>
              <?php if ($editable): ?>
              <td>
                <button class="btn btn-outline btn-sm" onclick="toggleEdit(<?= $l['id'] ?>)">✏️</button>
              </td>
              <?php endif ?>
            </tr>
            <?php if ($editable): ?>
            <tr id="edit-<?= $l['id'] ?>">
              <td colspan="<?= $editable ? 18 : 17 ?>" style="padding:0 10px 10px;">
                <div class="edit-panel" id="panel-<?= $l['id'] ?>">
                  <div class="edit-sep">Percepciones</div>
                  <div class="edit-grid">
                    <div><label>Sueldo base</label><input class="inp" type="number" step="0.01" name="sueldo_base" value="<?= (float)$l['sueldo_base'] ?>"></div>
                    <div><label>H. Extra monto</label><input class="inp" type="number" step="0.01" name="horas_extra_monto" value="<?= (float)$l['horas_extra_monto'] ?>"></div>
                    <div><label>Prima dominical</label><input class="inp" type="number" step="0.01" name="prima_dominical" value="<?= (float)$l['prima_dominical'] ?>"></div>
                    <div><label>Bono asistencia</label><input class="inp" type="number" step="0.01" name="bono_asistencia" value="<?= (float)$l['bono_asistencia'] ?>"></div>
                    <div><label>Otras percepciones</label><input class="inp" type="number" step="0.01" name="otras_percepciones" value="<?= (float)$l['otras_percepciones'] ?>"></div>
                  </div>
                  <div class="edit-sep">Deducciones</div>
                  <div class="edit-grid">
                    <div><label>IMSS obrero</label><input class="inp" type="number" step="0.01" name="imss_obrero" value="<?= (float)$l['imss_obrero'] ?>"></div>
                    <div><label>ISR retenido</label><input class="inp" type="number" step="0.01" name="isr_retenido" value="<?= (float)$l['isr_retenido'] ?>"></div>
                    <div><label>Desc. faltas</label><input class="inp" type="number" step="0.01" name="descuento_faltas" value="<?= (float)$l['descuento_faltas'] ?>"></div>
                    <div><label>Otras deducciones</label><input class="inp" type="number" step="0.01" name="otras_deducciones" value="<?= (float)$l['otras_deducciones'] ?>"></div>
                  </div>
                  <div style="margin-top:8px;">
                    <label>Observaciones</label>
                    <input class="inp" type="text" name="observaciones" value="<?= h($l['observaciones'] ?? '') ?>" placeholder="Notas opcionales" style="max-width:400px;">
                  </div>
                  <div class="edit-actions">
                    <button class="btn btn-primary btn-sm" onclick="guardarLinea(<?= $l['id'] ?>, this)">💾 Guardar</button>
                    <button class="btn btn-outline btn-sm" onclick="toggleEdit(<?= $l['id'] ?>)">✕ Cancelar</button>
                  </div>
                </div>
              </td>
            </tr>
            <?php endif ?>
          <?php endforeach ?>
        </tbody>
        <tfoot>
          <tr class="tfoot-row">
            <td colspan="9" style="font-weight:700;">Totales (<?= count($lineas) ?> empleados)</td>
            <td class="td-money"><?= money($tp_sum) ?></td>
            <td></td><td></td><td></td><td></td>
            <td class="td-money"><?= money($td_sum) ?></td>
            <td class="td-neto"><?= money($tn_sum) ?></td>
            <td colspan="<?= $editable ? 2 : 1 ?>"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

<?php else: /* ══════ LISTA DE PERÍODOS ═══════ */ ?>

  <!-- Formulario nuevo período -->
  <div class="card" id="formCard">
    <div class="card-header">
      <span class="card-title">📋 Períodos de nómina</span>
      <button class="btn btn-primary btn-sm" onclick="toggleForm()">+ Nuevo período</button>
    </div>

    <div id="formNew" style="display:none;border-top:1px solid #e2e8f0;padding-top:16px;margin-top:4px;">
      <form id="createForm">
        <input type="hidden" name="action" value="crear_periodo">
        <input type="hidden" name="csrf_token" value="<?= h($token) ?>">
        <div class="form-row" style="margin-bottom:14px;">
          <div>
            <label>Tipo de período *</label>
            <select name="tipo" class="inp" required>
              <option value="">— Seleccionar —</option>
              <option value="quincenal">Quincenal</option>
              <option value="semanal">Semanal</option>
            </select>
          </div>
          <div>
            <label>Fecha inicio *</label>
            <input type="date" name="fecha_inicio" class="inp" required>
          </div>
          <div>
            <label>Fecha fin *</label>
            <input type="date" name="fecha_fin" class="inp" required>
          </div>
          <div>
            <label>Fecha de pago</label>
            <input type="date" name="fecha_pago" class="inp">
          </div>
          <div>
            <label>Notas</label>
            <input type="text" name="notas" class="inp" placeholder="Ej. Quincena 1 Mayo 2025">
          </div>
        </div>
        <div style="display:flex;gap:10px;">
          <button type="submit" class="btn btn-primary btn-sm">✓ Crear período</button>
          <button type="button" class="btn btn-outline btn-sm" onclick="toggleForm()">Cancelar</button>
        </div>
        <p id="createMsg" style="margin-top:10px;font-size:13px;display:none;"></p>
      </form>
    </div>
  </div>

  <!-- Tabla de períodos -->
  <div class="card" style="padding:0;">
    <?php if (empty($periodos)): ?>
      <p style="padding:40px;text-align:center;color:#94a3b8;">No hay períodos registrados. Cree el primero con el botón de arriba.</p>
    <?php else: ?>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>Tipo</th>
            <th>Período</th>
            <th>Fecha pago</th>
            <th class="td-c">Empleados</th>
            <th class="td-r">Total percepciones</th>
            <th class="td-r">Total neto</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($periodos as $p): ?>
          <tr>
            <td><?= $p['tipo'] === 'quincenal' ? 'Quincenal' : 'Semanal' ?></td>
            <td>
              <strong><?= fd($p['fecha_inicio']) ?></strong>
              <span style="color:#94a3b8;"> – </span>
              <strong><?= fd($p['fecha_fin']) ?></strong>
            </td>
            <td><?= fd($p['fecha_pago']) ?></td>
            <td class="td-c"><?= (int)$p['empleados_count'] ?></td>
            <td class="td-money"><?= money($p['total_percepciones']) ?></td>
            <td class="td-neto"><?= money($p['total_neto']) ?></td>
            <td><?= nbadge($p['estado']) ?></td>
            <td>
              <a href="nomina.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm">Ver detalle</a>
            </td>
          </tr>
          <?php endforeach ?>
        </tbody>
      </table>
    </div>
    <?php endif ?>
  </div>

<?php endif ?>
</div><!-- /content -->

<div id="toast"></div>

<script>
const TOKEN = <?= json_encode($token) ?>;
const PID   = <?= $pid ?: 'null' ?>;

function toast(msg, ok = true) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className   = ok ? 'ok' : 'err';
  t.style.display = 'block';
  clearTimeout(t._t);
  t._t = setTimeout(() => t.style.display = 'none', 3500);
}

async function post(data) {
  data.csrf_token = TOKEN;
  const fd = new FormData();
  Object.entries(data).forEach(([k,v]) => fd.append(k, v));
  const r = await fetch('nomina.php', { method: 'POST', body: fd });
  return r.json();
}

/* ── Lista: toggle form ──────────────────────────────────── */
function toggleForm() {
  const f = document.getElementById('formNew');
  f.style.display = f.style.display === 'none' ? 'block' : 'none';
}

const createForm = document.getElementById('createForm');
if (createForm) {
  createForm.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = createForm.querySelector('[type=submit]');
    btn.disabled = true; btn.textContent = 'Creando...';
    const fd = new FormData(createForm);
    const data = Object.fromEntries(fd.entries());
    const res = await post(data);
    btn.disabled = false; btn.textContent = '✓ Crear período';
    if (res.ok) {
      if (res.redirect) window.location.href = res.redirect;
    } else {
      const m = document.getElementById('createMsg');
      m.style.display = 'block';
      m.style.color = '#dc2626';
      m.textContent = res.msg;
    }
  });
}

/* ── Detalle: acciones ───────────────────────────────────── */
async function calcular() {
  if (!confirm('¿Calcular nómina desde asistencia? Se sobreescribirán los valores actuales.')) return;
  const btn = event.target;
  btn.disabled = true; btn.textContent = '⏳ Calculando...';
  const res = await post({ action: 'calcular_periodo', periodo_id: PID });
  if (res.ok) { toast(res.msg); setTimeout(() => location.reload(), 800); }
  else { toast(res.msg, false); btn.disabled = false; btn.textContent = '⚙️ Calcular desde asistencia'; }
}

async function cerrar() {
  if (!confirm('¿Cerrar el período? Ya no se podrá editar.')) return;
  const res = await post({ action: 'cerrar_periodo', periodo_id: PID });
  if (res.ok) location.reload();
  else toast(res.msg, false);
}

async function marcarPagado() {
  if (!confirm('¿Marcar período como pagado?')) return;
  const res = await post({ action: 'marcar_pagado', periodo_id: PID });
  if (res.ok) location.reload();
  else toast(res.msg, false);
}

async function eliminar() {
  if (!confirm('¿Eliminar este período y todas sus líneas? Esta acción no se puede deshacer.')) return;
  const res = await post({ action: 'eliminar_periodo', periodo_id: PID });
  if (res.ok) { if (res.redirect) window.location.href = res.redirect; }
  else toast(res.msg, false);
}

/* ── Edición inline ─────────────────────────────────────── */
function toggleEdit(lid) {
  const panel = document.getElementById('panel-' + lid);
  panel.classList.toggle('open');
}

async function guardarLinea(lid, btn) {
  const panel = document.getElementById('panel-' + lid);
  const inputs = panel.querySelectorAll('input');
  const data = { action: 'guardar_linea', linea_id: lid };
  inputs.forEach(i => { if (i.name) data[i.name] = i.value; });

  btn.disabled = true; btn.textContent = '⏳ Guardando...';
  const res = await post(data);
  btn.disabled = false; btn.textContent = '💾 Guardar';

  if (res.ok) {
    toast('Guardado correctamente.');
    setTimeout(() => location.reload(), 600);
  } else {
    toast(res.msg || 'Error al guardar.', false);
  }
}
</script>
</body>
</html>
