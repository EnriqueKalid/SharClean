<?php
declare(strict_types=1);

require_once __DIR__ . '/db/config.php';
require_once __DIR__ . '/db/csrf.php';
require_once __DIR__ . '/db/ratelimit.php';

/* ─── Días de vacaciones según LFT (reforma 2022) ─────────── */
function diasVacLFT(int $anos): int {
    if ($anos < 1) return 0;
    if ($anos <= 4) return 10 + ($anos * 2);          // 12,14,16,18
    return 20 + 2 * (int)(($anos - 5) / 5);           // 20,22,24,26...
}

/* ─── Días disponibles este año de aniversario ─────────────── */
function diasDisponibles(array $emp, mysqli $db): array {
    $ingreso = strtotime($emp['fecha_ingreso']);
    $hoy     = time();
    $anos    = (int)(($hoy - $ingreso) / (365.25 * 86400));
    $corresp = diasVacLFT($anos);

    // Año de aniversario actual
    $ult_aniv = mktime(0,0,0, (int)date('m',$ingreso), (int)date('d',$ingreso), (int)date('Y'));
    if ($ult_aniv > $hoy) $ult_aniv = mktime(0,0,0, (int)date('m',$ingreso), (int)date('d',$ingreso), (int)date('Y')-1);
    $prox_aniv = mktime(0,0,0, (int)date('m',$ingreso), (int)date('d',$ingreso), (int)date('Y', $ult_aniv)+1);

    $ult_s  = date('Y-m-d', $ult_aniv);
    $prox_s = date('Y-m-d', $prox_aniv);
    $eid    = (int)$emp['id'];

    $usados = (int)$db->query(
        "SELECT COALESCE(SUM(dias),0) FROM incidencias
         WHERE empleado_id=$eid AND tipo='vacaciones'
           AND estado='aprobada'
           AND fecha_inicio >= '$ult_s' AND fecha_inicio < '$prox_s'"
    )->fetch_row()[0];

    return [
        'anos'      => $anos,
        'corresp'   => $corresp,
        'usados'    => $usados,
        'disponibles' => max(0, $corresp - $usados),
    ];
}

$TIPOS = [
    'vacaciones'          => 'Vacaciones',
    'permiso_goce'        => 'Permiso con goce de sueldo',
    'permiso_sin_goce'    => 'Permiso sin goce de sueldo',
    'incapacidad_imss'    => 'Incapacidad IMSS',
    'incapacidad_empresa' => 'Incapacidad por empresa',
];

$error   = '';
$success = false;
$emp     = null;
$balance = null;
$step    = 1; // 1=buscar empleado, 2=formulario, 3=éxito

/* ─── POST ─────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkRateLimit('solicitar', 30);

    $action = $_POST['action'] ?? '';

    /* ── Paso 1: buscar empleado ─────────────────────────── */
    if ($action === 'buscar') {
        $num = strtoupper(trim($_POST['numero_empleado'] ?? ''));
        if (!$num) { $error = 'Ingresa tu número de empleado.'; }
        else {
            $db   = getDB();
            $num_s = $db->real_escape_string($num);
            $emp  = $db->query(
                "SELECT id, numero_empleado, nombre, apellido_paterno, apellido_materno,
                        fecha_ingreso, departamento_id, puesto_nombre, estatus
                 FROM empleados
                 WHERE numero_empleado='$num_s' AND estatus NOT IN('baja_definitiva') LIMIT 1"
            )->fetch_assoc();
            $db->close();

            if (!$emp) { $error = 'Número de empleado no encontrado o empleado dado de baja.'; }
            else        { $step  = 2; }
        }
    }

    /* ── Paso 2: enviar solicitud ────────────────────────── */
    if ($action === 'solicitar') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            $error = 'Token de seguridad inválido. Recarga la página.';
        } else {
            $eid  = (int)($_POST['empleado_id'] ?? 0);
            $tipo = $_POST['tipo'] ?? '';
            $fi   = trim($_POST['fecha_inicio'] ?? '');
            $ff   = trim($_POST['fecha_fin']    ?? '');
            $desc = trim($_POST['descripcion']  ?? '');

            if (!$eid || !array_key_exists($tipo, $TIPOS) || !$fi || !$ff) {
                $error = 'Completa todos los campos requeridos.';
            } elseif ($ff < $fi) {
                $error = 'La fecha de fin debe ser igual o posterior a la de inicio.';
            } else {
                $db  = getDB();
                $emp = $db->query(
                    "SELECT id, numero_empleado, nombre, apellido_paterno,
                            fecha_ingreso, estatus
                     FROM empleados WHERE id=$eid LIMIT 1"
                )->fetch_assoc();

                if (!$emp) {
                    $error = 'Empleado no encontrado.';
                } else {
                    $balance = diasDisponibles($emp, $db);
                    $dias = (int)(((strtotime($ff) - strtotime($fi)) / 86400) + 1);

                    // Validar días disponibles solo para vacaciones
                    if ($tipo === 'vacaciones' && $dias > $balance['disponibles']) {
                        $error = "Solo tienes {$balance['disponibles']} día(s) de vacaciones disponibles y solicitaste $dias.";
                        $step  = 2;
                    } else {
                        // Verificar que no haya otra incidencia pendiente/aprobada en esas fechas
                        $fi_s   = $db->real_escape_string($fi);
                        $ff_s   = $db->real_escape_string($ff);
                        $tipo_s = $db->real_escape_string($tipo);
                        $desc_s = $desc ? "'".$db->real_escape_string($desc)."'" : 'NULL';

                        $conflicto = $db->query(
                            "SELECT id FROM incidencias
                             WHERE empleado_id=$eid
                               AND estado IN('pendiente','aprobada')
                               AND fecha_inicio <= '$ff_s' AND fecha_fin >= '$fi_s'
                             LIMIT 1"
                        )->fetch_assoc();

                        if ($conflicto) {
                            $error = 'Ya tienes una solicitud pendiente o aprobada que se traslapa con esas fechas.';
                            $step  = 2;
                        } else {
                            $db->query(
                                "INSERT INTO incidencias
                                    (empleado_id, tipo, fecha_inicio, fecha_fin, dias, descripcion, estado)
                                 VALUES ($eid, '$tipo_s', '$fi_s', '$ff_s', $dias, $desc_s, 'pendiente')"
                            );
                            $success = true;
                            $step    = 3;
                        }
                    }
                }
                $db->close();
            }
        }
        if ($error && !$step) $step = 2;
    }
}

$token = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Solicitud de Vacaciones y Permisos – Sharclean</title>
<link rel="icon" href="images/favicon.ico" type="image/x-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',system-ui,sans-serif;background:#f0f9ff;min-height:100vh;color:#1e293b}
.header{background:#0284C7;color:#fff;padding:16px 24px;display:flex;align-items:center;gap:16px}
.header img{height:36px}
.header h1{font-size:18px;font-weight:800}
.header p{font-size:13px;opacity:.8}
.container{max-width:600px;margin:40px auto;padding:0 16px 60px}
.card{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.08);padding:32px}
.card-title{font-size:20px;font-weight:800;margin-bottom:6px;color:#0284C7}
.card-sub{font-size:14px;color:#64748b;margin-bottom:28px}
label{display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px}
.inp{width:100%;padding:10px 14px;border:1.5px solid #d1d5db;border-radius:10px;font-size:14px;font-family:inherit;transition:.15s}
.inp:focus{outline:none;border-color:#0284C7;box-shadow:0 0 0 3px rgba(2,132,199,.12)}
select.inp{cursor:pointer;background:#fff}
.form-group{margin-bottom:20px}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:12px 28px;border-radius:10px;border:none;cursor:pointer;font-size:15px;font-weight:700;font-family:inherit;transition:.15s;width:100%}
.btn-primary{background:#0284C7;color:#fff}.btn-primary:hover{background:#0369a1}
.btn-outline{background:#fff;color:#374151;border:1.5px solid #d1d5db}.btn-outline:hover{background:#f9fafb}
.error{background:#fee2e2;color:#991b1b;border-radius:8px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:600}
.info-box{background:#f0f9ff;border:1.5px solid #bae6fd;border-radius:10px;padding:16px;margin-bottom:24px}
.info-box p{font-size:13px;color:#0369a1;line-height:1.6}
.info-box strong{color:#0284C7}
.balance-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:12px}
.b-stat{text-align:center;background:#fff;border-radius:8px;padding:10px 6px;border:1px solid #bae6fd}
.b-val{font-size:22px;font-weight:800;color:#0284C7}
.b-lbl{font-size:10px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.04em;margin-top:2px}
.success-icon{font-size:64px;text-align:center;margin-bottom:16px}
.success-title{font-size:22px;font-weight:800;text-align:center;color:#16a34a;margin-bottom:8px}
.success-sub{font-size:14px;color:#64748b;text-align:center;line-height:1.7;margin-bottom:24px}
.divider{height:1px;background:#f1f5f9;margin:24px 0}
.tip{font-size:12px;color:#94a3b8;margin-top:8px}
</style>
</head>
<body>

<div class="header">
  <img src="images/logo.png" alt="Sharclean">
  <div>
    <h1>Solicitud de Vacaciones y Permisos</h1>
    <p>Sharclean – Portal del Colaborador</p>
  </div>
</div>

<div class="container">

<?php if ($step === 3): /* ══ ÉXITO ══ */ ?>

  <div class="card">
    <div class="success-icon">✅</div>
    <div class="success-title">¡Solicitud enviada!</div>
    <div class="success-sub">
      Tu solicitud fue recibida y está en revisión.<br>
      Un administrador la atenderá a la brevedad.<br>
      Recibirás respuesta a través de tu supervisor.
    </div>
    <a href="solicitar.php" class="btn btn-outline">Hacer otra solicitud</a>
  </div>

<?php elseif ($step === 2 && $emp): /* ══ FORMULARIO ══ */ ?>

  <?php
  if (!$balance) {
      $db2 = getDB();
      $balance = diasDisponibles($emp, $db2);
      $db2->close();
  }
  $nombre_completo = trim($emp['nombre'].' '.$emp['apellido_paterno']);
  ?>

  <div class="card">
    <div class="card-title">Nueva solicitud</div>
    <div class="card-sub">Completar el formulario para registrar tu solicitud</div>

    <div class="info-box">
      <p>
        <strong><?= htmlspecialchars($nombre_completo, ENT_QUOTES, 'UTF-8') ?></strong>
        &nbsp;·&nbsp; <?= htmlspecialchars($emp['numero_empleado'], ENT_QUOTES, 'UTF-8') ?>
        &nbsp;·&nbsp; <?= (int)(((time() - strtotime($emp['fecha_ingreso'])) / (365.25*86400))) ?> año(s) de antigüedad
      </p>
      <div class="balance-grid">
        <div class="b-stat">
          <div class="b-val"><?= $balance['corresp'] ?></div>
          <div class="b-lbl">Días LFT</div>
        </div>
        <div class="b-stat">
          <div class="b-val"><?= $balance['usados'] ?></div>
          <div class="b-lbl">Usados</div>
        </div>
        <div class="b-stat">
          <div class="b-val" style="color:<?= $balance['disponibles'] > 0 ? '#16a34a' : '#dc2626' ?>"><?= $balance['disponibles'] ?></div>
          <div class="b-lbl">Disponibles</div>
        </div>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="error">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif ?>

    <form method="post" action="solicitar.php">
      <input type="hidden" name="action"      value="solicitar">
      <input type="hidden" name="csrf_token"  value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="empleado_id" value="<?= (int)$emp['id'] ?>">

      <div class="form-group">
        <label>Tipo de solicitud *</label>
        <select name="tipo" class="inp" required>
          <option value="">— Seleccionar —</option>
          <?php foreach ($TIPOS as $val => $lbl): ?>
            <option value="<?= $val ?>" <?= ($_POST['tipo'] ?? '') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach ?>
        </select>
      </div>

      <div class="form-row form-group">
        <div>
          <label>Fecha de inicio *</label>
          <input type="date" name="fecha_inicio" class="inp" required value="<?= htmlspecialchars($_POST['fecha_inicio'] ?? '', ENT_QUOTES, 'UTF-8') ?>" min="<?= date('Y-m-d') ?>">
        </div>
        <div>
          <label>Fecha de fin *</label>
          <input type="date" name="fecha_fin" class="inp" required value="<?= htmlspecialchars($_POST['fecha_fin'] ?? '', ENT_QUOTES, 'UTF-8') ?>" min="<?= date('Y-m-d') ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Motivo o descripción</label>
        <textarea name="descripcion" class="inp" rows="3" placeholder="Describe brevemente el motivo de tu solicitud..."><?= htmlspecialchars($_POST['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>

      <button type="submit" class="btn btn-primary" style="margin-bottom:12px">Enviar solicitud</button>
      <a href="solicitar.php" class="btn btn-outline">← Cambiar empleado</a>
    </form>
  </div>

<?php else: /* ══ PASO 1: BUSCAR EMPLEADO ══ */ ?>

  <div class="card">
    <div class="card-title">Solicitar vacaciones o permiso</div>
    <div class="card-sub">Ingresa tu número de empleado para continuar</div>

    <?php if ($error): ?>
      <div class="error">⚠️ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif ?>

    <form method="post" action="solicitar.php">
      <input type="hidden" name="action" value="buscar">
      <div class="form-group">
        <label>Número de empleado *</label>
        <input type="text" name="numero_empleado" class="inp" placeholder="Ej. EMP-0001"
               value="<?= htmlspecialchars($_POST['numero_empleado'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
               style="text-transform:uppercase" required autofocus>
        <p class="tip">Tu número de empleado aparece en tu recibo de nómina o contrato.</p>
      </div>
      <button type="submit" class="btn btn-primary">Continuar →</button>
    </form>

    <div class="divider"></div>
    <p style="font-size:12px;color:#94a3b8;text-align:center;">
      ¿No conoces tu número de empleado? Comunícate con tu supervisor o con administración.
    </p>
  </div>

<?php endif ?>
</div>
</body>
</html>
