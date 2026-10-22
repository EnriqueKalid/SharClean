<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requirePermiso('empleados');
require_once __DIR__ . '/../db/csrf.php';

/* ── Helpers ──────────────────────────────────────────────── */
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fd(string $d): string { return $d ? date('d/m/Y', strtotime($d)) : '—'; }
function fdt(string $d): string { return $d ? date('d/m/Y H:i', strtotime($d)) : '—'; }
function money(mixed $v): string { return $v !== null ? '$' . number_format((float)$v, 2) : '—'; }

function ebadge(string $s): string {
    $m = [
        'activo'          => ['b-ok',        'Activo'],
        'vacaciones'      => ['b-quoted',     'Vacaciones'],
        'incapacidad'     => ['b-pending',    'Incapacidad'],
        'baja_temporal'   => ['b-contacted',  'Baja Temp.'],
        'baja_definitiva' => ['b-del',        'Baja'],
    ];
    [$c, $l] = $m[$s] ?? ['b-read', $s];
    return "<span class='badge $c'>$l</span>";
}
function ctbadge(string $s): string {
    $m = [
        'indeterminado' => ['b-ok',      'Indeterminado'],
        'determinado'   => ['b-new',     'Determinado'],
        'por_obra'      => ['b-quoted',  'Por obra'],
        'confianza'     => ['b-pending', 'Confianza'],
        'honorarios'    => ['b-read',    'Honorarios'],
    ];
    [$c, $l] = $m[$s] ?? ['b-read', $s];
    return "<span class='badge $c'>$l</span>";
}
function fetchAll($result): array {
    $rows = [];
    if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}
function sel(string $name, array $opts, mixed $current, string $placeholder = ''): string {
    $out = "<select name='$name' class='vf-inp'>";
    if ($placeholder !== '') $out .= "<option value=''>" . h($placeholder) . "</option>";
    foreach ($opts as $v => $l) {
        $sel = ((string)$current === (string)$v) ? " selected" : '';
        $out .= "<option value='" . h((string)$v) . "'$sel>" . h($l) . "</option>";
    }
    return $out . '</select>';
}

/* ── POST handler ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Token inválido.'); }
    $db  = getAdminDB();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);

    if ($act === 'save_empleado') {
        /* ── Sanitizar campos ── */
        $nombre        = trim($_POST['nombre']            ?? '');
        $ap_pat        = trim($_POST['apellido_paterno']  ?? '');
        $ap_mat        = trim($_POST['apellido_materno']  ?? '') ?: null;
        $fn            = trim($_POST['fecha_nacimiento']  ?? '') ?: null;
        $sexo          = in_array($_POST['sexo'] ?? '', ['M','F','NB']) ? $_POST['sexo'] : null;
        $edo_civil     = in_array($_POST['estado_civil'] ?? '', ['soltero','casado','union_libre','divorciado','viudo'])
                         ? $_POST['estado_civil'] : null;
        $curp          = strtoupper(trim($_POST['curp']              ?? '')) ?: null;
        $rfc           = strtoupper(trim($_POST['rfc']               ?? '')) ?: null;
        $nss           = trim($_POST['nss']               ?? '') ?: null;
        $telefono      = trim($_POST['telefono']          ?? '') ?: null;
        $email         = trim($_POST['email']             ?? '') ?: null;
        $email_corp    = trim($_POST['email_corporativo'] ?? '') ?: null;
        $direccion     = trim($_POST['direccion']         ?? '') ?: null;
        $colonia       = trim($_POST['colonia']           ?? '') ?: null;
        $ciudad        = trim($_POST['ciudad']            ?? '') ?: 'CDMX';
        $edo_res       = trim($_POST['estado_residencia'] ?? '') ?: null;
        $cp            = trim($_POST['cp']                ?? '') ?: null;
        $puesto_id     = (int)($_POST['puesto_id']        ?? 0) ?: null;
        $dept_id       = (int)($_POST['departamento_id']  ?? 0) ?: null;
        /* Si eligió puesto pero no departamento, resolverlo desde el catálogo */
        if ($puesto_id && !$dept_id) {
            $rp = $db->prepare('SELECT departamento_id FROM puestos WHERE id=? LIMIT 1');
            $rp->bind_param('i', $puesto_id);
            $rp->execute();
            $rp->bind_result($dept_id);
            $rp->fetch();
            $rp->close();
        }
        $puesto_nom    = trim($_POST['puesto_nombre']     ?? '') ?: null;
        $tipo_contrato = in_array($_POST['tipo_contrato'] ?? '', ['indeterminado','determinado','por_obra','confianza','honorarios'])
                         ? $_POST['tipo_contrato'] : 'indeterminado';
        $fecha_ingreso = trim($_POST['fecha_ingreso']     ?? '');
        $fecha_fin     = trim($_POST['fecha_fin_contrato']?? '') ?: null;
        $raw_sal       = str_replace([',', ' '], '', $_POST['salario_base'] ?? '');
        $salario       = is_numeric($raw_sal) ? (float)$raw_sal : null;
        $periodo       = in_array($_POST['periodo_pago'] ?? '', ['semanal','quincenal','mensual'])
                         ? $_POST['periodo_pago'] : 'quincenal';
        $banco         = trim($_POST['banco']             ?? '') ?: null;
        $clabe         = trim($_POST['clabe_bancaria']    ?? '') ?: null;
        $estatus       = in_array($_POST['estatus'] ?? '', ['activo','vacaciones','incapacidad','baja_temporal','baja_definitiva'])
                         ? $_POST['estatus'] : 'activo';
        $fecha_baja    = trim($_POST['fecha_baja']        ?? '') ?: null;
        $motivo_baja   = trim($_POST['motivo_baja']       ?? '') ?: null;
        $aplicacion_id = (int)($_POST['aplicacion_id']   ?? 0) ?: null;
        $notas         = trim($_POST['notas']             ?? '') ?: null;

        /* ── Portal del empleado ── */
        $portal_username = trim($_POST['portal_username'] ?? '') ?: null;
        $portal_password = $_POST['portal_password'] ?? '';
        $portal_pin      = trim($_POST['portal_pin'] ?? '');
        $portal_activo   = ($_POST['portal_activo'] ?? '1') === '0' ? 0 : 1;

        /* ── Foto de referencia (si se subió una nueva) ── */
        $fotoReferenciaNueva = null;
        if (!empty($_FILES['foto_referencia']['name']) && $_FILES['foto_referencia']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png'];
            $maxSize      = 3 * 1024 * 1024; // 3 MB
            if (in_array($_FILES['foto_referencia']['type'], $allowedTypes, true)
                && $_FILES['foto_referencia']['size'] <= $maxSize) {
                $uploadDir = __DIR__ . '/../uploads/empleados/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0750, true);
                $ext  = pathinfo($_FILES['foto_referencia']['name'], PATHINFO_EXTENSION);
                $name = uniqid('ref_', true) . '.' . strtolower($ext);
                if (move_uploaded_file($_FILES['foto_referencia']['tmp_name'], $uploadDir . $name)) {
                    $fotoReferenciaNueva = $name;
                }
            }
        }

        if ($nombre !== '' && $ap_pat !== '' && $fecha_ingreso !== '') {
            if ($id > 0) {
                /* ── UPDATE ── */
                $numero = trim($_POST['numero_empleado'] ?? '');
                $s = $db->prepare(
                    'UPDATE empleados SET
                       numero_empleado=?,nombre=?,apellido_paterno=?,apellido_materno=?,
                       fecha_nacimiento=?,sexo=?,estado_civil=?,curp=?,rfc=?,nss=?,
                       telefono=?,email=?,email_corporativo=?,
                       direccion=?,colonia=?,ciudad=?,estado_residencia=?,cp=?,
                       departamento_id=?,puesto_id=?,puesto_nombre=?,
                       tipo_contrato=?,fecha_ingreso=?,fecha_fin_contrato=?,
                       salario_base=?,periodo_pago=?,banco=?,clabe_bancaria=?,
                       estatus=?,fecha_baja=?,motivo_baja=?,aplicacion_id=?,notas=?
                     WHERE id=?'
                );
                /* 33 data params + 1 id = 34 total
                   s×18, i×2, s×4, d, s×6, i, s, i(id) */
                $s->bind_param(
                    'ssssssssssssssssssiissssdsssssisi',
                    $numero,$nombre,$ap_pat,$ap_mat,
                    $fn,$sexo,$edo_civil,$curp,$rfc,$nss,
                    $telefono,$email,$email_corp,
                    $direccion,$colonia,$ciudad,$edo_res,$cp,
                    $dept_id,$puesto_id,$puesto_nom,
                    $tipo_contrato,$fecha_ingreso,$fecha_fin,
                    $salario,$periodo,$banco,$clabe,
                    $estatus,$fecha_baja,$motivo_baja,$aplicacion_id,$notas,
                    $id
                );
                $s->execute();
            } else {
                /* ── INSERT – número auto ── */
                $max    = (int)$db->query('SELECT COALESCE(MAX(id),0) FROM empleados')->fetch_row()[0];
                $numero = 'EMP-' . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);

                $s = $db->prepare(
                    'INSERT INTO empleados
                       (numero_empleado,nombre,apellido_paterno,apellido_materno,
                        fecha_nacimiento,sexo,estado_civil,curp,rfc,nss,
                        telefono,email,email_corporativo,
                        direccion,colonia,ciudad,estado_residencia,cp,
                        departamento_id,puesto_id,puesto_nombre,
                        tipo_contrato,fecha_ingreso,fecha_fin_contrato,
                        salario_base,periodo_pago,banco,clabe_bancaria,
                        estatus,fecha_baja,motivo_baja,aplicacion_id,notas)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                );
                /* 33 params: s×18, i×2, s×4, d, s×6, i, s */
                $s->bind_param(
                    'ssssssssssssssssssiissssdsssssisi',
                    $numero,$nombre,$ap_pat,$ap_mat,
                    $fn,$sexo,$edo_civil,$curp,$rfc,$nss,
                    $telefono,$email,$email_corp,
                    $direccion,$colonia,$ciudad,$edo_res,$cp,
                    $dept_id,$puesto_id,$puesto_nom,
                    $tipo_contrato,$fecha_ingreso,$fecha_fin,
                    $salario,$periodo,$banco,$clabe,
                    $estatus,$fecha_baja,$motivo_baja,$aplicacion_id,$notas
                );
                $s->execute();
                $new_id = $db->insert_id;
                /* Corregir número con ID real */
                $final_num = 'EMP-' . str_pad((string)$new_id, 4, '0', STR_PAD_LEFT);
                $db->query("UPDATE empleados SET numero_empleado='$final_num' WHERE id=$new_id");

                /* Marcar candidato como contratado */
                if ($aplicacion_id) {
                    $su = $db->prepare('UPDATE aplicaciones_empleo SET estado=? WHERE id=?');
                    $est = 'contratado';
                    $su->bind_param('si', $est, $aplicacion_id);
                    $su->execute();
                }
            }

            /* ── Guardar foto de referencia / credenciales de portal ── */
            $empIdFinal = $id > 0 ? $id : $new_id;
            $cols   = ['portal_username=?', 'portal_activo=?'];
            $types  = 'si';
            $params = [$portal_username, $portal_activo];

            if ($fotoReferenciaNueva !== null) {
                $cols[]   = 'foto_referencia=?';
                $types   .= 's';
                $params[] = $fotoReferenciaNueva;
            }
            if ($portal_password !== '') {
                $cols[]   = 'portal_password_hash=?';
                $types   .= 's';
                $params[] = password_hash($portal_password, PASSWORD_DEFAULT);
            }
            if ($portal_pin !== '' && preg_match('/^\d{4,6}$/', $portal_pin)) {
                $cols[]   = 'portal_pin_hash=?';
                $types   .= 's';
                $params[] = password_hash($portal_pin, PASSWORD_DEFAULT);
            }

            $types   .= 'i';
            $params[] = $empIdFinal;
            $sp = $db->prepare('UPDATE empleados SET ' . implode(',', $cols) . ' WHERE id=?');
            $sp->bind_param($types, ...$params);
            $sp->execute();
        }
    } elseif ($act === 'toggle_baja' && $id > 0) {
        $row = $db->query("SELECT estatus FROM empleados WHERE id=$id LIMIT 1")->fetch_assoc();
        if ($row) {
            if ($row['estatus'] === 'baja_definitiva') {
                $su = $db->prepare('UPDATE empleados SET estatus=?,fecha_baja=NULL,motivo_baja=NULL WHERE id=?');
                $est = 'activo'; $su->bind_param('si', $est, $id);
            } else {
                $su = $db->prepare('UPDATE empleados SET estatus=?,fecha_baja=CURDATE() WHERE id=?');
                $est = 'baja_definitiva'; $su->bind_param('si', $est, $id);
            }
            $su->execute();
        }
    } elseif ($act === 'del_empleado' && $id > 0) {
        $chk = $db->prepare('SELECT estatus FROM empleados WHERE id=?');
        $chk->bind_param('i', $id);
        $chk->execute();
        $chk->bind_result($estatus_actual);
        $chk->fetch();
        $chk->close();
        if ($estatus_actual === 'baja_definitiva') {
            $su = $db->prepare('DELETE FROM empleados WHERE id=?');
            $su->bind_param('i', $id);
            $su->execute();
        }
    }

    $db->close();
    header('Location: empleados.php'); exit;
}

/* ── Fetch data ───────────────────────────────────────────── */
$db   = getAdminDB();
$csrf = generateCsrfToken();

$f_dept    = (int)($_GET['f_dept']    ?? 0);
$f_estatus = in_array($_GET['f_estatus'] ?? '', ['activo','vacaciones','incapacidad','baja_temporal','baja_definitiva'])
             ? $_GET['f_estatus'] : '';

/* Stats */
$cActivos = (int)$db->query("SELECT COUNT(*) FROM empleados WHERE estatus='activo'")->fetch_row()[0];
$cIncid   = (int)$db->query("SELECT COUNT(*) FROM empleados WHERE estatus IN('vacaciones','incapacidad','baja_temporal')")->fetch_row()[0];
$cBajas   = (int)$db->query("SELECT COUNT(*) FROM empleados WHERE estatus='baja_definitiva'")->fetch_row()[0];
$cTotal   = (int)$db->query('SELECT COUNT(*) FROM empleados')->fetch_row()[0];

/* Catálogos */
$departamentos = fetchAll($db->query('SELECT id, nombre FROM departamentos WHERE activo=1 ORDER BY nombre'));
$puestos       = fetchAll($db->query('SELECT id, nombre, departamento_id FROM puestos WHERE activo=1 ORDER BY nombre'));

/* Lista de empleados con filtros */
// Valores validados contra allowlists arriba, seguros para query directa
$where = '1=1';
if ($f_dept)    $where .= ' AND e.departamento_id=' . (int)$f_dept;
if ($f_estatus) $where .= " AND e.estatus='" . $db->real_escape_string($f_estatus) . "'";

$sqlBase = "SELECT e.*, d.nombre AS dept_nombre
            FROM empleados e
            LEFT JOIN departamentos d ON d.id = e.departamento_id
            WHERE $where
            ORDER BY FIELD(e.estatus,'activo','vacaciones','incapacidad','baja_temporal','baja_definitiva'), e.nombre ASC
            LIMIT 500";
$empleados = fetchAll($db->query($sqlBase));

/* Empleado a editar */
$editEmp = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $editEmp = $db->query("SELECT * FROM empleados WHERE id=$eid LIMIT 1")->fetch_assoc();
}

/* Pre-llenado desde candidato */
$fromAplicacion = null;
if (isset($_GET['from_aplicacion']) && !$editEmp) {
    $aid = (int)$_GET['from_aplicacion'];
    $fromAplicacion = $db->query("SELECT * FROM aplicaciones_empleo WHERE id=$aid LIMIT 1")->fetch_assoc();
}

$db->close();

$showForm    = isset($_GET['form']) || $editEmp !== null || $fromAplicacion !== null;
$fromApId    = $fromAplicacion['id'] ?? ($editEmp['aplicacion_id'] ?? 0);
$pre         = $editEmp ?? [];          /* datos para el formulario */

/* Si viene de candidato y no hay datos de edición, pre-llenar nombre/teléfono */
if ($fromAplicacion && !$editEmp) {
    $partes = explode(' ', trim($fromAplicacion['nombre']), 2);
    $pre['nombre']           = $partes[0] ?? '';
    $pre['apellido_paterno'] = $partes[1] ?? '';
    $pre['telefono']         = $fromAplicacion['telefono'] ?? '';
    $pre['email']            = $fromAplicacion['email'] ?? '';
    $pre['puesto_nombre']    = $fromAplicacion['puesto_interes'] ?? '';
    $pre['fecha_ingreso']    = date('Y-m-d');
    $pre['aplicacion_id']    = $fromAplicacion['id'];
}

/* Helpers de valor */
$v = fn(string $k, string $def = '') => h((string)($pre[$k] ?? $def));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Empleados – Sharclean Admin</title>
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
.body{max-width:1400px;margin:0 auto;padding:24px 16px;}
/* Stats */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
.stat{background:#fff;border-radius:10px;padding:20px 24px;box-shadow:0 1px 3px rgba(0,0,0,.07);}
.stat-val{font-size:34px;font-weight:800;color:#0284C7;}
.stat-lbl{font-size:13px;color:#64748b;margin-top:4px;}
.stat-sub{font-size:12px;color:#94a3b8;margin-top:2px;}
/* Filter bar */
.filter-bar{display:flex;gap:8px;align-items:center;padding:12px 18px;background:#fff;border-radius:10px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.07);flex-wrap:wrap;}
.filter-bar label{font-size:12px;font-weight:600;color:#64748b;white-space:nowrap;}
.filter-bar select{padding:6px 9px;border-radius:6px;border:1.5px solid #e2e8f0;font-size:13px;background:#fff;outline:none;}
.filter-bar select:focus{border-color:#0284C7;}
/* Cards */
.card{background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.07);overflow:hidden;margin-bottom:20px;}
.card-header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #f1f5f9;}
.card-header h2{font-size:15px;font-weight:700;color:#1e293b;}
/* Table */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;}
th{background:#f8fafc;padding:10px 14px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap;}
td{padding:10px 14px;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
tr:last-child td{border-bottom:none;}
tr:hover td{background:#f8fafc;}
/* Badges */
.badge{display:inline-block;padding:3px 9px;border-radius:5px;font-size:11px;font-weight:700;}
.b-new{background:#dbeafe;color:#1d4ed8;}
.b-read{background:#f1f5f9;color:#94a3b8;}
.b-pending{background:#fef9c3;color:#854d0e;}
.b-contacted{background:#ffedd5;color:#9a3412;}
.b-quoted{background:#f3e8ff;color:#7e22ce;}
.b-ok{background:#dcfce7;color:#166534;}
.b-del{background:#fee2e2;color:#991b1b;}
/* Buttons */
.btn-sm{padding:5px 10px;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;border:none;white-space:nowrap;display:inline-block;}
.btn-primary{background:#0284C7;color:#fff;}
.btn-primary:hover{background:#0369a1;}
.btn-muted{background:#e2e8f0;color:#475569;}
.btn-muted:hover{background:#cbd5e1;}
.btn-danger{background:#fee2e2;color:#dc2626;}
.btn-danger:hover{background:#fecaca;}
.btn-success{background:#dcfce7;color:#166534;}
.btn-success:hover{background:#bbf7d0;}
.btn-violet{background:#ede9fe;color:#7c3aed;}
.btn-violet:hover{background:#ddd6fe;}
/* Form */
.form-wrap{padding:24px 20px;}
.form-section{margin-bottom:28px;}
.form-section-title{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:#0284C7;margin-bottom:14px;padding-bottom:6px;border-bottom:2px solid #e0f2fe;}
.vf-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;}
.vf-grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;}
.vf-field label{display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:5px;}
.vf-inp{width:100%;padding:8px 10px;border:1.5px solid #e2e8f0;border-radius:6px;font-size:13px;font-family:inherit;outline:none;transition:border-color .15s;background:#fff;}
.vf-inp:focus{border-color:#0284C7;}
textarea.vf-inp{resize:vertical;}
.vf-span2{grid-column:span 2;}
.vf-span3{grid-column:span 3;}
.form-actions{display:flex;gap:10px;margin-top:20px;padding-top:16px;border-top:1px solid #e2e8f0;align-items:center;}
.from-candidate-banner{background:#ede9fe;border:1px solid #c4b5fd;border-radius:8px;padding:10px 14px;margin-bottom:20px;font-size:13px;color:#6d28d9;}
.empty{padding:56px;text-align:center;color:#94a3b8;font-size:15px;}
.num-emp{font-size:12px;font-weight:700;color:#7c3aed;background:#ede9fe;padding:2px 8px;border-radius:4px;}
@media(max-width:900px){.stats{grid-template-columns:repeat(2,1fr);}.vf-grid{grid-template-columns:repeat(2,1fr);}.vf-span3{grid-column:span 2;}}
@media(max-width:580px){.stats{grid-template-columns:1fr 1fr;}.vf-grid,.vf-grid-2{grid-template-columns:1fr;}.vf-span2,.vf-span3{grid-column:1;}}
</style>
</head>
<body>

<?php $navTitle = '👥 Empleados'; $activeModule = 'empleados'; require __DIR__ . '/_nav.php'; ?>

<div class="body">

  <!-- Stats -->
  <div class="stats">
    <div class="stat">
      <div class="stat-val"><?= $cActivos ?></div>
      <div class="stat-lbl">Empleados activos</div>
      <div class="stat-sub"><?= $cTotal ?> total registrados</div>
    </div>
    <div class="stat">
      <div class="stat-val"><?= $cIncid ?></div>
      <div class="stat-lbl">Con incidencia</div>
      <div class="stat-sub">Vacaciones / Incapacidad / Baja temp.</div>
    </div>
    <div class="stat">
      <div class="stat-val"><?= $cBajas ?></div>
      <div class="stat-lbl">Bajas definitivas</div>
      <div class="stat-sub">&nbsp;</div>
    </div>
    <div class="stat">
      <div class="stat-val"><?= count($empleados) ?></div>
      <div class="stat-lbl">Resultado del filtro</div>
      <div class="stat-sub">&nbsp;</div>
    </div>
  </div>

  <!-- Filter bar -->
  <div class="filter-bar">
    <form method="get" style="display:contents;">
      <label>Departamento:</label>
      <select name="f_dept" onchange="this.form.submit()">
        <option value="">Todos los departamentos</option>
        <?php foreach ($departamentos as $d): ?>
          <option value="<?= $d['id'] ?>" <?= $f_dept === (int)$d['id'] ? 'selected' : '' ?>><?= h($d['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Estatus:</label>
      <select name="f_estatus" onchange="this.form.submit()">
        <option value="" <?= !$f_estatus ? 'selected' : '' ?>>Todos los estatus</option>
        <?php foreach (['activo'=>'Activo','vacaciones'=>'Vacaciones','incapacidad'=>'Incapacidad','baja_temporal'=>'Baja Temporal','baja_definitiva'=>'Baja Definitiva'] as $vv=>$ll): ?>
          <option value="<?= $vv ?>" <?= $f_estatus===$vv ? 'selected' : '' ?>><?= $ll ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($f_dept || $f_estatus): ?>
        <a href="empleados.php" class="btn-sm btn-muted">✕ Limpiar</a>
      <?php endif; ?>
    </form>
    <div style="margin-left:auto;display:flex;gap:8px;">
      <?php if (!$showForm): ?>
        <a href="empleados_importar.php" class="btn-sm btn-muted">📤 Importar CSV</a>
        <a href="empleados.php?form=1" class="btn-sm btn-primary">+ Nuevo empleado</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Formulario (crear / editar) ──────────────────────── -->
  <?php if ($showForm): ?>
  <div class="card">
    <div class="card-header">
      <h2><?= $editEmp ? '✏️ Editar empleado' : '➕ ' . ($fromAplicacion ? 'Contratar candidato' : 'Nuevo empleado') ?></h2>
      <a href="empleados.php" class="btn-sm btn-muted">← Cancelar</a>
    </div>

    <?php if ($fromAplicacion && !$editEmp): ?>
    <div style="padding:0 20px;padding-top:16px;">
      <div class="from-candidate-banner">
        Contratando desde candidato: <strong><?= h($fromAplicacion['nombre']) ?></strong>
        — <?= h($fromAplicacion['puesto_interes']) ?>.
        Revisa y completa los datos antes de guardar.
      </div>
    </div>
    <?php endif; ?>

    <div class="form-wrap">
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token"    value="<?= h($csrf) ?>">
        <input type="hidden" name="action"        value="save_empleado">
        <input type="hidden" name="id"            value="<?= (int)($editEmp['id'] ?? 0) ?>">
        <input type="hidden" name="aplicacion_id" value="<?= (int)$fromApId ?>">

        <!-- ① Datos personales -->
        <div class="form-section">
          <div class="form-section-title">① Datos personales</div>
          <div class="vf-grid">
            <div class="vf-field">
              <label>Nombre(s) *</label>
              <input type="text" name="nombre" class="vf-inp" required value="<?= $v('nombre') ?>" placeholder="Nombre(s)">
            </div>
            <div class="vf-field">
              <label>Apellido paterno *</label>
              <input type="text" name="apellido_paterno" class="vf-inp" required value="<?= $v('apellido_paterno') ?>" placeholder="Apellido paterno">
            </div>
            <div class="vf-field">
              <label>Apellido materno</label>
              <input type="text" name="apellido_materno" class="vf-inp" value="<?= $v('apellido_materno') ?>" placeholder="Apellido materno">
            </div>
            <div class="vf-field">
              <label>Fecha de nacimiento</label>
              <input type="date" name="fecha_nacimiento" class="vf-inp" value="<?= $v('fecha_nacimiento') ?>">
            </div>
            <div class="vf-field">
              <label>Sexo</label>
              <?= sel('sexo', ['M'=>'Masculino','F'=>'Femenino','NB'=>'No binario'], $pre['sexo'] ?? '', '— Seleccionar —') ?>
            </div>
            <div class="vf-field">
              <label>Estado civil</label>
              <?= sel('estado_civil', ['soltero'=>'Soltero/a','casado'=>'Casado/a','union_libre'=>'Unión libre','divorciado'=>'Divorciado/a','viudo'=>'Viudo/a'], $pre['estado_civil'] ?? '', '— Seleccionar —') ?>
            </div>
          </div>
        </div>

        <!-- ② Documentos oficiales -->
        <div class="form-section">
          <div class="form-section-title">② Documentos oficiales</div>
          <div class="vf-grid">
            <div class="vf-field">
              <label>CURP <span style="font-weight:400;color:#94a3b8;">(18 caracteres)</span></label>
              <input type="text" name="curp" class="vf-inp" maxlength="18"
                     value="<?= $v('curp') ?>" placeholder="XXXX000000XXXXXX00"
                     style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">
            </div>
            <div class="vf-field">
              <label>RFC <span style="font-weight:400;color:#94a3b8;">(12-13 caracteres)</span></label>
              <input type="text" name="rfc" class="vf-inp" maxlength="13"
                     value="<?= $v('rfc') ?>" placeholder="XXXX000000XXX"
                     style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">
            </div>
            <div class="vf-field">
              <label>NSS — IMSS <span style="font-weight:400;color:#94a3b8;">(11 dígitos)</span></label>
              <input type="text" name="nss" class="vf-inp" maxlength="11"
                     value="<?= $v('nss') ?>" placeholder="00000000000">
            </div>
          </div>
        </div>

        <!-- ③ Contacto y domicilio -->
        <div class="form-section">
          <div class="form-section-title">③ Contacto y domicilio</div>
          <div class="vf-grid">
            <div class="vf-field">
              <label>Teléfono</label>
              <input type="text" name="telefono" class="vf-inp" value="<?= $v('telefono') ?>" placeholder="55 1234 5678">
            </div>
            <div class="vf-field">
              <label>Correo personal</label>
              <input type="email" name="email" class="vf-inp" value="<?= $v('email') ?>" placeholder="correo@ejemplo.com">
            </div>
            <div class="vf-field">
              <label>Correo corporativo</label>
              <input type="email" name="email_corporativo" class="vf-inp" value="<?= $v('email_corporativo') ?>" placeholder="nombre@sharclean.com">
            </div>
            <div class="vf-field vf-span3">
              <label>Calle y número</label>
              <input type="text" name="direccion" class="vf-inp" value="<?= $v('direccion') ?>" placeholder="Calle, número exterior e interior">
            </div>
            <div class="vf-field" id="colonia-wrap">
              <label>Colonia</label>
              <input type="text" id="colonia-input" name="colonia" class="vf-inp" value="<?= $v('colonia') ?>">
            </div>
            <div class="vf-field">
              <label>Ciudad</label>
              <input type="text" id="ciudad-input" name="ciudad" class="vf-inp" value="<?= $v('ciudad', 'CDMX') ?>">
            </div>
            <div class="vf-field">
              <label>Estado</label>
              <input type="text" id="estado-input" name="estado_residencia" class="vf-inp" value="<?= $v('estado_residencia') ?>">
            </div>
            <div class="vf-field">
              <label>C.P.</label>
              <input type="text" id="cp-input" name="cp" class="vf-inp" maxlength="5" value="<?= $v('cp') ?>" placeholder="06600">
              <span id="cp-status" style="font-size:11px;color:#94a3b8;"></span>
            </div>
          </div>
        </div>

        <!-- ④ Datos laborales -->
        <div class="form-section">
          <div class="form-section-title">④ Datos laborales</div>
          <div class="vf-grid">
            <div class="vf-field">
              <label>Departamento</label>
              <select name="departamento_id" class="vf-inp" id="sel-dept">
                <option value="">— Sin asignar —</option>
                <?php foreach ($departamentos as $d): ?>
                  <option value="<?= $d['id'] ?>" <?= ((string)($pre['departamento_id'] ?? '')) === (string)$d['id'] ? 'selected' : '' ?>><?= h($d['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="vf-field">
              <label>Puesto (catálogo)</label>
              <select name="puesto_id" class="vf-inp" id="sel-puesto">
                <option value="">— Sin asignar —</option>
                <?php foreach ($puestos as $p): ?>
                  <option value="<?= $p['id'] ?>" data-dept="<?= $p['departamento_id'] ?>"
                    <?= ((string)($pre['puesto_id'] ?? '')) === (string)$p['id'] ? 'selected' : '' ?>>
                    <?= h($p['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="vf-field">
              <label>Puesto libre <span style="font-weight:400;color:#94a3b8;">(si no está en catálogo)</span></label>
              <input type="text" name="puesto_nombre" class="vf-inp" value="<?= $v('puesto_nombre') ?>" placeholder="Ej. Auxiliar de limpieza">
            </div>
            <div class="vf-field">
              <label>Tipo de contrato</label>
              <?= sel('tipo_contrato',
                  ['indeterminado'=>'Tiempo indeterminado','determinado'=>'Tiempo determinado',
                   'por_obra'=>'Por obra o proyecto','confianza'=>'Confianza','honorarios'=>'Honorarios'],
                  $pre['tipo_contrato'] ?? 'indeterminado') ?>
            </div>
            <div class="vf-field">
              <label>Fecha de ingreso *</label>
              <input type="date" name="fecha_ingreso" class="vf-inp" required value="<?= $v('fecha_ingreso', date('Y-m-d')) ?>">
            </div>
            <div class="vf-field">
              <label>Fin de contrato <span style="font-weight:400;color:#94a3b8;">(si aplica)</span></label>
              <input type="date" name="fecha_fin_contrato" class="vf-inp" value="<?= $v('fecha_fin_contrato') ?>">
            </div>
            <div class="vf-field">
              <label>Salario base mensual <span style="font-weight:400;color:#94a3b8;">(MXN bruto)</span></label>
              <input type="number" name="salario_base" class="vf-inp" step="0.01" min="0"
                     value="<?= h((string)($pre['salario_base'] ?? '')) ?>" placeholder="0.00">
            </div>
            <div class="vf-field">
              <label>Período de pago</label>
              <?= sel('periodo_pago', ['semanal'=>'Semanal','quincenal'=>'Quincenal','mensual'=>'Mensual'], $pre['periodo_pago'] ?? 'quincenal') ?>
            </div>
            <div class="vf-field">
              <label>Estatus laboral</label>
              <?= sel('estatus', ['activo'=>'Activo','vacaciones'=>'Vacaciones','incapacidad'=>'Incapacidad','baja_temporal'=>'Baja temporal','baja_definitiva'=>'Baja definitiva'], $pre['estatus'] ?? 'activo') ?>
            </div>
            <div class="vf-field">
              <label>Número de empleado <span style="font-weight:400;color:#94a3b8;">(auto si vacío)</span></label>
              <input type="text" name="numero_empleado" class="vf-inp"
                     value="<?= $v('numero_empleado') ?>" placeholder="EMP-0001">
            </div>
            <div class="vf-field">
              <label>Fecha de baja</label>
              <input type="date" name="fecha_baja" class="vf-inp" value="<?= $v('fecha_baja') ?>">
            </div>
            <div class="vf-field">
              <label>Motivo de baja</label>
              <input type="text" name="motivo_baja" class="vf-inp" value="<?= $v('motivo_baja') ?>" placeholder="Renuncia voluntaria, término de contrato…">
            </div>
          </div>
        </div>

        <!-- ⑤ Datos bancarios -->
        <div class="form-section">
          <div class="form-section-title">⑤ Datos bancarios</div>
          <div class="vf-grid-2">
            <div class="vf-field">
              <label>Banco</label>
              <input type="text" name="banco" class="vf-inp" value="<?= $v('banco') ?>" placeholder="BBVA, Santander, HSBC…">
            </div>
            <div class="vf-field">
              <label>CLABE interbancaria <span style="font-weight:400;color:#94a3b8;">(18 dígitos)</span></label>
              <input type="text" name="clabe_bancaria" class="vf-inp" maxlength="18"
                     value="<?= $v('clabe_bancaria') ?>" placeholder="000000000000000000">
            </div>
          </div>
        </div>

        <!-- ⑥ Portal del empleado (check-in) -->
        <div class="form-section">
          <div class="form-section-title">⑥ Portal del empleado — check-in</div>
          <div class="vf-grid">
            <div class="vf-field">
              <label>Foto de referencia <span style="font-weight:400;color:#94a3b8;">(para verificar la selfie del check-in)</span></label>
              <?php if (!empty($pre['foto_referencia'])): ?>
                <div style="margin-bottom:6px;">
                  <img src="empleado_foto.php?emp=<?= (int)($editEmp['id'] ?? 0) ?>" alt="Foto actual"
                       style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1.5px solid #e2e8f0;">
                </div>
              <?php endif; ?>
              <input type="file" name="foto_referencia" class="vf-inp" accept="image/jpeg,image/png">
            </div>
            <div class="vf-field">
              <label>Usuario de portal</label>
              <input type="text" name="portal_username" class="vf-inp" maxlength="50"
                     value="<?= $v('portal_username') ?>" placeholder="nombre.apellido" autocomplete="off">
            </div>
            <div class="vf-field">
              <label>Nueva contraseña <span style="font-weight:400;color:#94a3b8;">(dejar vacío para no cambiar)</span></label>
              <input type="password" name="portal_password" class="vf-inp" autocomplete="new-password" placeholder="••••••••">
            </div>
            <div class="vf-field">
              <label>PIN alterno <span style="font-weight:400;color:#94a3b8;">(4-6 dígitos, para empleados con impedimento)</span></label>
              <input type="text" name="portal_pin" class="vf-inp" maxlength="6" inputmode="numeric"
                     autocomplete="off" placeholder="Dejar vacío para no cambiar">
            </div>
            <div class="vf-field">
              <label>Acceso al portal</label>
              <?= sel('portal_activo', ['1' => 'Habilitado', '0' => 'Deshabilitado'], (string)($pre['portal_activo'] ?? '1')) ?>
            </div>
          </div>
        </div>

        <!-- ⑦ Notas internas -->
        <div class="form-section">
          <div class="form-section-title">⑦ Notas internas</div>
          <div class="vf-field">
            <label>Notas del expediente</label>
            <textarea name="notas" class="vf-inp" rows="3"
              placeholder="Observaciones del reclutador, referencias, información adicional…"><?= $v('notas') ?></textarea>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn-sm btn-primary">
            💾 <?= $editEmp ? 'Guardar cambios' : 'Crear empleado' ?>
          </button>
          <a href="empleados.php" class="btn-sm btn-muted">Cancelar</a>
          <?php if ($editEmp): ?>
            <span style="font-size:12px;color:#94a3b8;margin-left:auto;">
              Creado <?= fdt($editEmp['fecha_creacion']) ?>
              <?= $editEmp['fecha_actualiza'] ? '· Actualizado ' . fdt($editEmp['fecha_actualiza']) : '' ?>
            </span>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Tabla de empleados ───────────────────────────────── -->
  <div class="card">
    <div class="card-header">
      <h2>Directorio de empleados</h2>
      <?php if (!$showForm): ?>
        <a href="empleados.php?form=1" class="btn-sm btn-primary">+ Nuevo empleado</a>
      <?php endif; ?>
    </div>

    <?php if (empty($empleados)): ?>
      <div class="empty">
        No hay empleados registrados todavía.
        <br><a href="empleados.php?form=1" style="color:#0284C7;display:inline-block;margin-top:8px;">Registrar el primero</a>
      </div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>N° Emp</th>
            <th>Nombre completo</th>
            <th>Puesto / Área</th>
            <th>Contrato</th>
            <th>Salario base</th>
            <th>Ingreso</th>
            <th>Estatus</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($empleados as $e): ?>
          <tr>
            <td style="color:#94a3b8;font-size:12px;"><?= $e['id'] ?></td>
            <td><span class="num-emp"><?= h($e['numero_empleado']) ?></span></td>
            <td>
              <strong><?= h($e['nombre'] . ' ' . $e['apellido_paterno'] . ($e['apellido_materno'] ? ' ' . $e['apellido_materno'] : '')) ?></strong>
              <?php if ($e['email'] || $e['telefono']): ?>
                <div style="font-size:11px;color:#94a3b8;margin-top:2px;">
                  <?= $e['telefono'] ? h($e['telefono']) : '' ?>
                  <?= ($e['telefono'] && $e['email']) ? ' · ' : '' ?>
                  <?= $e['email'] ? '<a href="mailto:' . h($e['email']) . '" style="color:#0284C7;">' . h($e['email']) . '</a>' : '' ?>
                </div>
              <?php endif; ?>
            </td>
            <td>
              <?= h($e['puesto_nombre'] ?: '—') ?>
              <?php if ($e['dept_nombre']): ?>
                <div style="font-size:11px;color:#94a3b8;"><?= h($e['dept_nombre']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= ctbadge($e['tipo_contrato']) ?></td>
            <td style="font-size:13px;font-weight:600;color:#0f172a;"><?= money($e['salario_base']) ?></td>
            <td style="white-space:nowrap;font-size:12px;color:#64748b;"><?= fd($e['fecha_ingreso']) ?></td>
            <td><?= ebadge($e['estatus']) ?></td>
            <td style="white-space:nowrap;">
              <a href="empleados.php?edit=<?= $e['id'] ?>" class="btn-sm btn-muted" style="margin-right:3px;">✏️ Editar</a>
              <!-- Toggle baja -->
              <form method="post" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="toggle_baja">
                <input type="hidden" name="id"     value="<?= (int)$e['id'] ?>">
                <button type="submit" class="btn-sm <?= $e['estatus']==='baja_definitiva' ? 'btn-success' : 'btn-muted' ?>"
                        onclick="return confirm('<?= $e['estatus']==='baja_definitiva' ? '¿Reactivar empleado?' : '¿Dar de baja a este empleado?' ?>')">
                  <?= $e['estatus']==='baja_definitiva' ? '▶ Reactivar' : '⏹ Dar baja' ?>
                </button>
              </form>
              <!-- Eliminar (solo si ya está en baja definitiva) -->
              <?php if ($e['estatus'] === 'baja_definitiva'): ?>
              <form method="post" style="display:inline;margin-left:3px;">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="del_empleado">
                <input type="hidden" name="id"     value="<?= (int)$e['id'] ?>">
                <button type="submit" class="btn-sm btn-danger"
                        onclick="return confirm('¿Eliminar permanentemente a <?= h(addslashes($e['nombre'])) ?>? Esta acción no se puede deshacer.')">
                  🗑
                </button>
              </form>
              <?php else: ?>
              <span title="Dar de baja al empleado antes de eliminar" style="display:inline-block;margin-left:3px;opacity:.35;cursor:not-allowed;font-size:1rem;">🗑</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /body -->

<script>
(function() {
  const deptSel   = document.getElementById('sel-dept');
  const puestoSel = document.getElementById('sel-puesto');
  if (!deptSel || !puestoSel) return;

  function filterPuestos() {
    const deptId = deptSel.value;
    Array.from(puestoSel.options).forEach(function(opt) {
      if (!opt.value) return;
      opt.hidden = deptId && opt.dataset.dept !== deptId;
    });
  }

  /* Al elegir puesto → auto-asignar su departamento */
  puestoSel.addEventListener('change', function() {
    const selected = puestoSel.options[puestoSel.selectedIndex];
    if (selected && selected.dataset.dept) {
      deptSel.value = selected.dataset.dept;
      filterPuestos();
    }
  });

  deptSel.addEventListener('change', filterPuestos);
  filterPuestos();
})();

/* ── Autocompletar Colonia/Ciudad/Estado a partir del C.P. ── */
(function() {
  const cpInput     = document.getElementById('cp-input');
  const cpStatus    = document.getElementById('cp-status');
  const coloniaWrap = document.getElementById('colonia-wrap');
  const ciudadInput = document.getElementById('ciudad-input');
  const estadoInput = document.getElementById('estado-input');
  if (!cpInput) return;

  function setColoniaOptions(colonias, valorActual) {
    const prevInput = document.getElementById('colonia-input');
    const select = document.createElement('select');
    select.id   = 'colonia-input';
    select.name = 'colonia';
    select.className = 'vf-inp';
    colonias.forEach(function(nombre) {
      const opt = document.createElement('option');
      opt.value = nombre;
      opt.textContent = nombre;
      if (nombre === valorActual) opt.selected = true;
      select.appendChild(opt);
    });
    if (valorActual && !colonias.includes(valorActual)) {
      const opt = document.createElement('option');
      opt.value = valorActual;
      opt.textContent = valorActual;
      opt.selected = true;
      select.insertBefore(opt, select.firstChild);
    }
    coloniaWrap.replaceChild(select, prevInput);
  }

  cpInput.addEventListener('blur', function() {
    const cp = cpInput.value.trim();
    if (!/^\d{5}$/.test(cp)) return;

    cpStatus.textContent = 'Buscando…';
    fetch('cp_lookup.php?cp=' + encodeURIComponent(cp))
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (!data.ok) { cpStatus.textContent = ''; return; }
        cpStatus.textContent = '';
        if (data.ciudad || data.municipio) ciudadInput.value = data.ciudad || data.municipio;
        if (data.estado) estadoInput.value = data.estado;
        if (data.colonias && data.colonias.length) {
          const colInput = document.getElementById('colonia-input');
          setColoniaOptions(data.colonias, colInput.value);
        }
      })
      .catch(function() { cpStatus.textContent = ''; });
  });
})();
</script>
</body>
</html>
