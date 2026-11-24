<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requirePermiso('empleados');
require_once __DIR__ . '/../db/csrf.php';
require_once __DIR__ . '/../db/expediente.php';   // incluye db/foto_upload.php
require_once __DIR__ . '/../db/registro.php';
require_once __DIR__ . '/../db/mailer.php';

/* ── Helpers ──────────────────────────────────────────────── */
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fd(?string $d): string { return $d ? date('d/m/Y', strtotime($d)) : '—'; }
function fdt(?string $d): string { return $d ? date('d/m/Y H:i', strtotime($d)) : '—'; }
function money(mixed $v): string { return $v !== null ? '$' . number_format((float)$v, 2) : '—'; }
function kb(int $b): string { return $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' KB'; }

function ebadge(string $s): string {
    $m = [
        'activo'          => ['b-ok',        'Activo'],
        'vacaciones'      => ['b-quoted',     'Vacaciones'],
        'incapacidad'     => ['b-pending',    'Incapacidad'],
        'baja_temporal'   => ['b-contacted',  'Baja Temp.'],
        'baja_definitiva' => ['b-del',        'Baja'],
    ];
    [$c, $l] = $m[$s] ?? ['b-read', $s];
    return "<span class='badge $c'>" . h($l) . "</span>";
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
    return "<span class='badge $c'>" . h($l) . "</span>";
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
function iniciales(array $e): string {
    $s = mb_substr((string)($e['nombre'] ?? ''), 0, 1) . mb_substr((string)($e['apellido_paterno'] ?? ''), 0, 1);
    return mb_strtoupper($s) ?: '?';
}
/** URL de la foto de referencia con versión (cacheable mientras no cambie). */
function fotoUrl(array $e): string {
    return 'empleado_foto.php?emp=' . (int)$e['id'] . '&v=' . substr(md5((string)$e['foto_referencia']), 0, 8);
}

const EMP_TABS = ['personal', 'laboral', 'bancario', 'portal', 'expediente'];

/** Avisos que sobreviven al redirect (POST → GET). */
function empFlash(string $tipo, string $msg): void { $_SESSION['emp_flash'][$tipo][] = $msg; }

/* ── POST handler ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Token inválido.'); }
    $db  = getAdminDB();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    $tab = in_array($_POST['tab'] ?? '', EMP_TABS, true) ? $_POST['tab'] : 'personal';
    $redirect = 'empleados.php';

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

        /* ── Foto de referencia: se valida por contenido real y se re-codifica a ≤800 px ── */
        $fotoReferenciaNueva = null;
        $refDir = __DIR__ . '/../uploads/empleados';
        if (!empty($_FILES['foto_referencia']['name'])) {
            $r = procesarFotoReferencia($_FILES['foto_referencia'], $refDir);
            if ($r['ok']) $fotoReferenciaNueva = $r['filename'];
            else          empFlash('err', 'Foto de referencia: ' . $r['error']);
        }

        if ($nombre !== '' && $ap_pat !== '' && $fecha_ingreso !== '') {
            $new_id = 0;
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
                   s×18, i×2, s×4, d, s×6, i, s, i(id) — cada letra corresponde a su variable en orden */
                $s->bind_param(
                    'ssssssssssssssssssiissssdssssssisi',
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
                $guardado = $s->execute();
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
                    'ssssssssssssssssssiissssdssssssis',
                    $numero,$nombre,$ap_pat,$ap_mat,
                    $fn,$sexo,$edo_civil,$curp,$rfc,$nss,
                    $telefono,$email,$email_corp,
                    $direccion,$colonia,$ciudad,$edo_res,$cp,
                    $dept_id,$puesto_id,$puesto_nom,
                    $tipo_contrato,$fecha_ingreso,$fecha_fin,
                    $salario,$periodo,$banco,$clabe,
                    $estatus,$fecha_baja,$motivo_baja,$aplicacion_id,$notas
                );
                $guardado = $s->execute();
                if ($guardado) {
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
            }

            if (!$guardado) {
                error_log('[Sharclean empleados] save failed: ' . $s->error);
                if ($fotoReferenciaNueva !== null) unlink("$refDir/$fotoReferenciaNueva");
                empFlash('err', str_contains($s->error, 'Duplicate')
                    ? 'No se pudo guardar: el número de empleado ya existe.'
                    : 'No se pudo guardar el empleado. Revisa los datos e intenta de nuevo.');
                $db->close();
                header('Location: ' . ($id > 0 ? "empleados.php?edit=$id&tab=$tab" : 'empleados.php?form=1'));
                exit;
            }

            /* ── Guardar foto de referencia / credenciales de portal ── */
            $empIdFinal = $id > 0 ? $id : $new_id;

            $fotoAnterior = '';
            if ($fotoReferenciaNueva !== null) {
                $fr = $db->query("SELECT foto_referencia FROM empleados WHERE id=$empIdFinal LIMIT 1");
                $fotoAnterior = basename((string)(($fr ? $fr->fetch_assoc() : null)['foto_referencia'] ?? ''));
            }

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
            if ($sp->execute()) {
                if ($fotoAnterior !== '' && $fotoAnterior !== $fotoReferenciaNueva && is_file("$refDir/$fotoAnterior")) {
                    unlink("$refDir/$fotoAnterior");
                }
            } elseif (str_contains($db->error, 'Duplicate')) {
                empFlash('err', 'Ese usuario de portal ya lo tiene otro empleado.');
            }

            empFlash('ok', $id > 0 ? 'Cambios guardados.' : 'Empleado creado. Ya puedes cargar su expediente.');
            $redirect = "empleados.php?edit=$empIdFinal&tab=" . ($id > 0 ? $tab : 'expediente');
        } else {
            if ($fotoReferenciaNueva !== null) unlink("$refDir/$fotoReferenciaNueva");
            empFlash('err', 'Nombre, apellido paterno y fecha de ingreso son obligatorios.');
            $redirect = $id > 0 ? "empleados.php?edit=$id&tab=$tab" : 'empleados.php?form=1';
        }
    } elseif ($act === 'doc_upload' && $id > 0) {
        /* ── Subir uno o varios archivos al expediente ── */
        $existe = $db->query("SELECT 1 FROM empleados WHERE id=$id LIMIT 1");
        $tipoId = (int)($_POST['tipo_id'] ?? 0) ?: null;
        $nombre = trim($_POST['doc_nombre'] ?? '') ?: null;
        if ($tipoId) {
            $tr = $db->query("SELECT 1 FROM documento_tipos WHERE id=$tipoId AND activo=1 LIMIT 1");
            if (!$tr || !$tr->fetch_row()) $tipoId = -1;
        }
        $f = $_FILES['documento'] ?? null;

        if (!$existe || !$existe->fetch_row() || $tipoId === -1) {
            empFlash('err', 'Solicitud no válida.');
        } elseif (!$tipoId && $nombre === null) {
            empFlash('err', 'Escribe el nombre del documento adicional.');
        } elseif (!$f || !is_array($f['name']) || ($f['name'][0] ?? '') === '') {
            empFlash('err', 'Selecciona al menos un archivo.');
        } else {
            $subidos = 0;
            foreach (array_keys($f['name']) as $i) {
                $one = ['name' => $f['name'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
                $r = guardarDocumentoEmpleado($db, $id, $tipoId, $tipoId ? null : $nombre, $one, adminUser()['id']);
                $r['ok'] ? $subidos++ : empFlash('err', $r['error']);
            }
            if ($subidos) empFlash('ok', $subidos === 1 ? 'Documento cargado.' : "$subidos documentos cargados.");
        }
        $redirect = "empleados.php?edit=$id&tab=expediente";
    } elseif ($act === 'doc_delete' && $id > 0) {
        $docId = (int)($_POST['doc_id'] ?? 0);
        eliminarDocumentoEmpleado($db, $docId, $id)
            ? empFlash('ok', 'Documento eliminado.')
            : empFlash('err', 'No se encontró el documento.');
        $redirect = "empleados.php?edit=$id&tab=expediente";
    } elseif ($act === 'reg_aprobar' && $id > 0) {
        /* ── Autorregistro: RRHH confirma que la selfie es de esta persona ── */
        $me = adminUser()['id'] ?: 'NULL';
        $db->query("UPDATE empleados SET registro_estado='aprobado', registro_revisado_por=$me
                    WHERE id=$id AND registro_estado='pendiente'");
        $db->affected_rows === 1
            ? empFlash('ok', 'Registro aprobado: el empleado ya puede entrar al portal y checar.')
            : empFlash('err', 'Este registro ya no estaba pendiente.');
        $redirect = "empleados.php?edit=$id";
    } elseif ($act === 'reg_rechazar' && $id > 0) {
        /* Se quitan selfie y credenciales; el empleado puede volver a registrarse con la liga */
        $me  = adminUser()['id'] ?: 'NULL';
        $fr  = $db->query("SELECT foto_referencia FROM empleados WHERE id=$id AND registro_estado='pendiente' LIMIT 1");
        $row = $fr ? $fr->fetch_assoc() : null;
        if ($row) {
            $db->query("UPDATE empleados SET registro_estado='rechazado', registro_revisado_por=$me,
                          portal_username=NULL, portal_password_hash=NULL, portal_pin_hash=NULL, foto_referencia=NULL
                        WHERE id=$id AND registro_estado='pendiente'");
            $f = __DIR__ . '/../uploads/empleados/' . basename((string)$row['foto_referencia']);
            if ($row['foto_referencia'] && is_file($f)) unlink($f);
            empFlash('ok', 'Registro rechazado. El empleado puede volver a registrarse con la liga.');
        } else {
            empFlash('err', 'Este registro ya no estaba pendiente.');
        }
        $redirect = "empleados.php?edit=$id";
    } elseif ($act === 'reg_liga') {
        /* ── Liga general de registro: activar / desactivar / generar nueva ── */
        $op = $_POST['op'] ?? '';
        if ($op === 'activar') {
            if (strlen(appConfig('registro_clave')) !== 32) registroNuevaClave();
            setAppConfig('registro_activo', '1');
            empFlash('ok', 'Liga de registro activada.');
        } elseif ($op === 'desactivar') {
            setAppConfig('registro_activo', '0');
            empFlash('ok', 'Liga de registro desactivada: ya nadie puede usarla.');
        } elseif ($op === 'rotar') {
            registroNuevaClave();
            empFlash('ok', 'Se generó una liga nueva. La anterior (y las invitaciones ya enviadas) ya no funcionan.');
        }
        $redirect = ($_POST['volver'] ?? '') === 'invitar' ? 'empleados.php?invitar=1' : 'empleados.php?liga=1';
    } elseif ($act === 'inv_correo') {
        /* ── Invitación por correo a los empleados seleccionados (aún sin acceso al portal) ── */
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['ids'] ?? [])))));
        if (!registroActivo()) {
            empFlash('err', 'Activa la liga de registro antes de enviar invitaciones.');
        } elseif (!$ids) {
            empFlash('err', 'Selecciona al menos un empleado.');
        } else {
            @set_time_limit(300);
            $clave   = appConfig('registro_clave');
            $empresa = appConfig('empresa_nombre', 'la empresa');
            $track   = invitacionDisponible($db);
            $lista   = fetchAll($db->query('SELECT e.id, e.nombre, e.numero_empleado, e.email, e.email_corporativo
                                            FROM empleados e WHERE e.id IN (' . implode(',', $ids) . ') AND ' . REGISTRO_ELEGIBLE_SQL . '
                                            LIMIT 300'));
            $ok = $fail = $sinCorreo = 0;
            foreach ($lista as $e) {
                $to = empleadoCorreo($e);
                if ($to === '') { $sinCorreo++; continue; }
                $html = invitacionCorreoHtml($e, registroUrlPersonal($clave, (string)$e['numero_empleado']), $empresa);
                if (sendMail($to, "Activa tu cuenta del portal de empleados – $empresa", $html)) {
                    $ok++;
                    if ($track) invitacionMarcar($db, (int)$e['id'], 'correo');
                } else {
                    $fail++;
                }
            }
            if ($ok)        empFlash('ok',  $ok === 1 ? 'Se envió 1 invitación por correo.' : "Se enviaron $ok invitaciones por correo.");
            if ($fail)      empFlash('err', "No se pudo enviar el correo a $fail empleado(s). Revisa la configuración de correo.");
            if ($sinCorreo) empFlash('err', "$sinCorreo empleado(s) no tienen correo válido: invítalos por WhatsApp.");
            $omitidos = count($ids) - count($lista);
            if ($omitidos > 0) empFlash('err', "$omitidos empleado(s) se omitieron porque ya tienen acceso o un registro en revisión.");
        }
        $redirect = 'empleados.php?invitar=1';
    } elseif ($act === 'inv_wa' && $id > 0) {
        /* ── El admin abrió el WhatsApp de la invitación: solo se registra la fecha (AJAX) ── */
        if (invitacionDisponible($db)) invitacionMarcar($db, $id, 'whatsapp');
        $db->close();
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'fecha' => date('d/m/Y H:i')]);
        exit;
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
        $chk = $db->prepare('SELECT estatus, foto_referencia FROM empleados WHERE id=?');
        $chk->bind_param('i', $id);
        $chk->execute();
        $chk->bind_result($estatus_actual, $foto_actual);
        $chk->fetch();
        $chk->close();
        if ($estatus_actual === 'baja_definitiva') {
            $su = $db->prepare('DELETE FROM empleados WHERE id=?');
            $su->bind_param('i', $id);
            if ($su->execute()) {
                /* Borrar también sus archivos: foto de referencia y expediente */
                $ref = __DIR__ . '/../uploads/empleados/' . basename((string)$foto_actual);
                if ($foto_actual && is_file($ref)) unlink($ref);
                if (expedienteDisponible($db)) eliminarExpedienteCompleto($db, $id);
            }
        }
    }

    $db->close();
    header('Location: ' . $redirect); exit;
}

/* ── Fetch data ───────────────────────────────────────────── */
$db   = getAdminDB();
$csrf = generateCsrfToken();

$flash = $_SESSION['emp_flash'] ?? [];
unset($_SESSION['emp_flash']);

$f_dept    = (int)($_GET['f_dept']    ?? 0);
$f_registro = ($_GET['f_registro'] ?? '') === 'pendiente';
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

/* Expediente digital (si ya se ejecutó migrate_expediente_v1.sql) */
$expOk  = expedienteDisponible($db);

/* Autorregistro (si ya se ejecutó migrate_registro_v1.sql) */
$regOk     = registroDisponible($db);
$regPend   = $regOk ? registroPendientes($db) : 0;
$regActiva = $regOk && registroActivo();
$regUrl    = $regOk && strlen(appConfig('registro_clave')) === 32 ? registroUrl(appConfig('registro_clave')) : '';

/* Invitaciones: empleados que aún no activan su cuenta del portal */
$invTrack = $regOk && invitacionDisponible($db);
$porInvitar = $regOk && !isset($_GET['form']) && !isset($_GET['edit']) && !isset($_GET['from_aplicacion'])
    ? fetchAll($db->query('SELECT e.id, e.nombre, e.apellido_paterno, e.apellido_materno, e.numero_empleado,
                                  e.telefono, e.email, e.email_corporativo, e.registro_estado'
                          . ($invTrack ? ', e.invitacion_fecha, e.invitacion_canal' : '') . '
                           FROM empleados e WHERE ' . REGISTRO_ELEGIBLE_SQL . '
                           ORDER BY ' . ($invTrack ? 'e.invitacion_fecha IS NOT NULL, ' : '') . 'e.nombre, e.apellido_paterno
                           LIMIT 500'))
    : [];
$avance = $expOk ? expedienteAvance($db) : ['total' => 0, 'por_empleado' => []];

/* Empleado a editar */
$editEmp = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $editEmp = $db->query("SELECT e.*, d.nombre AS dept_nombre FROM empleados e
                           LEFT JOIN departamentos d ON d.id = e.departamento_id
                           WHERE e.id=$eid LIMIT 1")->fetch_assoc();
}

/* Pre-llenado desde candidato */
$fromAplicacion = null;
if (isset($_GET['from_aplicacion']) && !$editEmp) {
    $aid = (int)$_GET['from_aplicacion'];
    $fromAplicacion = $db->query("SELECT * FROM aplicaciones_empleo WHERE id=$aid LIMIT 1")->fetch_assoc();
}

$showForm = isset($_GET['form']) || $editEmp !== null || $fromAplicacion !== null;

/* Ficha: expediente del empleado */
$tiposDoc = $docsPorTipo = $docsExtra = [];
if ($editEmp && $expOk) {
    $tiposDoc = expedienteTipos($db);
    foreach (expedienteDocumentos($db, (int)$editEmp['id']) as $d) {
        if ($d['tipo_id'] === null) $docsExtra[] = $d;
        else $docsPorTipo[(int)$d['tipo_id']][] = $d;
    }
}

/* Directorio */
$empleados = [];
if (!$showForm) {
    // Valores validados contra allowlists arriba, seguros para query directa
    $where = '1=1';
    if ($f_dept)    $where .= ' AND e.departamento_id=' . (int)$f_dept;
    if ($f_estatus) $where .= " AND e.estatus='" . $db->real_escape_string($f_estatus) . "'";
    if ($f_registro && $regOk) $where .= " AND e.registro_estado='pendiente'";

    $empleados = fetchAll($db->query("SELECT e.*, d.nombre AS dept_nombre
                FROM empleados e
                LEFT JOIN departamentos d ON d.id = e.departamento_id
                WHERE $where
                ORDER BY FIELD(e.estatus,'activo','vacaciones','incapacidad','baja_temporal','baja_definitiva'), e.nombre ASC
                LIMIT 500"));
}

$db->close();

$fromApId = $fromAplicacion['id'] ?? ($editEmp['aplicacion_id'] ?? 0);
$pre      = $editEmp ?? [];          /* datos para el formulario */

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

$tab = in_array($_GET['tab'] ?? '', EMP_TABS, true) ? $_GET['tab'] : 'personal';
if ($tab === 'expediente' && !($editEmp && $expOk)) $tab = 'personal';

/* Avance del expediente del empleado en edición */
$expCubiertos = $editEmp ? ($avance['por_empleado'][(int)$editEmp['id']] ?? 0) : 0;

/* Helpers de valor */
$v = fn(string $k, string $def = '') => h((string)($pre[$k] ?? $def));

/** Barra de avance del expediente (x de y obligatorios). */
$barra = function (int $hechos, int $total): string {
    if ($total === 0) return '<span class="exp-none">—</span>';
    $pct = (int)round($hechos / $total * 100);
    $cls = $pct >= 100 ? 'full' : ($pct >= 50 ? 'mid' : 'low');
    return "<span class='exp-bar $cls' title='$hechos de $total documentos obligatorios'><i style='width:{$pct}%'></i></span>"
         . "<span class='exp-num'>$hechos/$total</span>";
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Empleados – Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-size:14px}
a{color:inherit;text-decoration:none}
.body{max-width:1280px;margin:0 auto;padding:28px}
.muted{color:#94a3b8}
/* Stats */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px}
.stat{background:#fff;border:1px solid #e6e9f0;border-radius:14px;padding:18px 20px}
.stat-val{font-size:30px;font-weight:750;letter-spacing:-.02em;color:#0f172a}
.stat-lbl{font-size:13px;color:#475569;margin-top:2px;font-weight:600}
.stat-sub{font-size:12px;color:#94a3b8;margin-top:2px}
.stat:first-child .stat-val{color:var(--brand)}
/* Toolbar */
.toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:16px}
.search{position:relative;flex:1;min-width:220px;max-width:360px}
.search svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#94a3b8}
.search input{width:100%;padding:9px 12px 9px 36px;border:1px solid #e2e8f0;border-radius:10px;font:inherit;background:#fff}
.toolbar select{padding:9px 10px;border:1px solid #e2e8f0;border-radius:10px;font:inherit;font-size:13px;background:#fff}
.search input:focus,.toolbar select:focus,.vf-inp:focus{outline:none;border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-soft)}
.toolbar .spacer{flex:1}
/* Cards */
.card{background:#fff;border:1px solid #e6e9f0;border-radius:14px;overflow:hidden;margin-bottom:20px}
.card-header{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 18px;border-bottom:1px solid #f1f5f9}
.card-header h2,.card-header h3{font-size:15px;font-weight:700;color:#0f172a}
/* Table */
table{width:100%;border-collapse:collapse}
th{background:#f8fafc;padding:10px 14px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;border-bottom:1px solid #e2e8f0;white-space:nowrap}
td{padding:10px 14px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
tr:last-child td{border-bottom:none}
tbody tr[data-href]{cursor:pointer;transition:background .12s}
tbody tr[data-href]:hover td{background:#f8fafc}
.who{display:flex;align-items:center;gap:12px;min-width:220px}
.who strong{display:block;font-weight:600;color:#0f172a}
.who small{display:block;font-size:12px;color:#94a3b8;margin-top:1px}
.av{width:38px;height:38px;flex:0 0 38px;border-radius:50%;object-fit:cover;display:grid;place-items:center;background:#e0f2fe;color:#0369a1;font-weight:700;font-size:13px}
.av-lg{width:84px;height:84px;flex-basis:84px;font-size:28px;box-shadow:0 0 0 3px #fff,0 0 0 5px var(--brand-soft)}
.num-emp{font-size:11px;font-weight:700;color:#7c3aed;background:#ede9fe;padding:1px 7px;border-radius:5px;margin-left:6px;vertical-align:1px}
/* Badges */
.badge{display:inline-block;padding:3px 9px;border-radius:99px;font-size:11px;font-weight:700;white-space:nowrap}
.b-new{background:#dbeafe;color:#1d4ed8}.b-read{background:#f1f5f9;color:#64748b}.b-pending{background:#fef9c3;color:#854d0e}
.b-contacted{background:#ffedd5;color:#9a3412}.b-quoted{background:#f3e8ff;color:#7e22ce}.b-ok{background:#dcfce7;color:#166534}.b-del{background:#fee2e2;color:#991b1b}
/* Avance de expediente */
.exp-bar{display:inline-block;width:64px;height:6px;border-radius:99px;background:#eef2f7;overflow:hidden;vertical-align:middle;margin-right:8px}
.exp-bar i{display:block;height:100%;border-radius:99px}
.exp-bar.low i{background:#f97316}.exp-bar.mid i{background:#eab308}.exp-bar.full i{background:#22c55e}
.exp-num{font-size:12px;font-weight:600;color:#475569}
.exp-none{color:#cbd5e1}
/* Buttons */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 14px;border-radius:10px;border:1px solid transparent;cursor:pointer;font:inherit;font-size:13px;font-weight:600;white-space:nowrap;background:none;transition:background .15s,filter .15s}
.btn-primary{background:var(--brand);color:#fff}.btn-primary:hover{filter:brightness(.92)}
.btn-ghost{background:#fff;border-color:#e2e8f0;color:#334155}.btn-ghost:hover{background:#f8fafc}
.btn-danger{background:#fff;border-color:#fecaca;color:#dc2626}.btn-danger:hover{background:#fef2f2}
.btn-success{background:#fff;border-color:#bbf7d0;color:#166534}.btn-success:hover{background:#f0fdf4}
.btn-sm{padding:5px 10px;font-size:12px;border-radius:8px}
.btn[disabled]{opacity:.5;cursor:default}
.row-actions{display:flex;gap:6px;justify-content:flex-end;align-items:center}
.row-actions form{display:inline}
/* Ficha */
.ficha-head{display:flex;align-items:center;gap:20px;padding:22px 24px;flex-wrap:wrap}
.ficha-head h2{font-size:21px;font-weight:750;letter-spacing:-.01em;color:#0f172a}
.ficha-meta{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;margin-top:6px;color:#64748b;font-size:13px}
.ficha-exp{margin-left:auto;text-align:right;min-width:170px}
.ficha-exp small{display:block;font-size:12px;color:#64748b;margin-bottom:6px}
.ficha-exp .exp-bar{width:120px;height:8px}
.tabs{display:flex;gap:4px;padding:0 16px;border-top:1px solid #f1f5f9;overflow-x:auto;scrollbar-width:none}
.tabs::-webkit-scrollbar{display:none}
.tab{padding:13px 14px;border:none;background:none;font:inherit;font-size:14px;font-weight:600;color:#64748b;cursor:pointer;border-bottom:2px solid transparent;white-space:nowrap}
.tab:hover{color:#0f172a}
.tab.active{color:var(--brand);border-bottom-color:var(--brand)}
.tab[disabled]{opacity:.45;cursor:not-allowed}
.panel{display:none}
.panel.active{display:block;animation:page-in .2s ease both}
/* Form */
.form-wrap{padding:24px}
.form-section{margin-bottom:28px}
.form-section:last-child{margin-bottom:0}
.form-section-title{font-size:12px;font-weight:750;text-transform:uppercase;letter-spacing:.07em;color:#64748b;margin-bottom:14px}
.vf-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.vf-grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
.vf-field label{display:block;font-size:12px;font-weight:600;color:#475569;margin-bottom:5px}
.vf-field label span{font-weight:400;color:#94a3b8}
.vf-inp{width:100%;padding:9px 11px;border:1px solid #dbe1ea;border-radius:9px;font:inherit;font-size:14px;background:#fff;transition:border-color .15s}
textarea.vf-inp{resize:vertical}
.vf-span2{grid-column:span 2}.vf-span3{grid-column:span 3}
.save-bar{position:sticky;bottom:0;display:flex;gap:10px;align-items:center;padding:14px 24px;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-top:1px solid #f1f5f9}
.save-bar[hidden]{display:none}
.save-bar .meta{margin-left:auto;font-size:12px;color:#94a3b8}
.from-candidate-banner{background:#ede9fe;border:1px solid #c4b5fd;border-radius:10px;padding:10px 14px;margin:16px 24px 0;font-size:13px;color:#6d28d9}
.alert{padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:13px}
.alert-ok{background:#dcfce7;color:#15803d;border:1px solid #bbf7d0}
.alert-err{background:#fee2e2;color:#b91c1c;border:1px solid #fecaca}
.alert-info{background:#f0f9ff;color:#0369a1;border:1px solid #bae6fd}
.empty{padding:56px;text-align:center;color:#94a3b8;font-size:15px}
/* Autorregistro */
.reg-review{display:flex;gap:20px;align-items:center;flex-wrap:wrap;padding:20px 24px;border:1.5px solid #fde68a;background:#fffbeb;border-radius:14px;margin-bottom:16px}
.reg-review img,.reg-review .ref-img{width:120px;height:120px;border-radius:14px;object-fit:cover;flex:0 0 120px}
.reg-review h3{font-size:16px;font-weight:750;color:#92400e;margin-bottom:4px}
.reg-review p{font-size:13px;color:#78350f;line-height:1.5;max-width:560px}
.reg-review .acts{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap}
.reg-review form{display:inline}
.b-reg{background:#fef3c7;color:#92400e;margin-left:6px}
dialog#liga-dialog{border:none;border-radius:18px;padding:0;width:min(560px,94vw);box-shadow:0 24px 64px rgba(15,23,42,.3);margin:auto}
dialog#liga-dialog::backdrop{background:rgba(15,23,42,.5)}
.liga-head{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid #f1f5f9}
.liga-head h3{font-size:16px;font-weight:750}
.liga-body{padding:20px 22px;display:flex;flex-direction:column;gap:14px;font-size:13px;color:#475569;line-height:1.55}
.liga-url{display:flex;gap:8px}
.liga-url input{flex:1;min-width:0;font-family:ui-monospace,Consolas,monospace;font-size:12px}
.liga-estado{display:inline-flex;align-items:center;gap:6px;font-weight:700}
.liga-estado i{width:8px;height:8px;border-radius:50%;background:#94a3b8}
.liga-estado.on i{background:#22c55e}
.liga-acts{display:flex;gap:8px;flex-wrap:wrap}
.liga-acts form{display:inline}
/* Invitaciones de autorregistro */
.inv-cnt{display:inline-block;min-width:20px;padding:1px 6px;border-radius:99px;background:var(--brand,#0284c7);color:#fff;font-size:11px;font-weight:700;text-align:center}
dialog#inv-dialog{border:none;border-radius:18px;padding:0;width:min(860px,96vw);max-height:90vh;box-shadow:0 24px 64px rgba(15,23,42,.3);margin:auto}
dialog#inv-dialog::backdrop{background:rgba(15,23,42,.5)}
#inv-dialog form.inv-form{display:flex;flex-direction:column;max-height:calc(90vh - 60px)}
.inv-top{padding:16px 22px;display:flex;flex-direction:column;gap:12px;font-size:13px;color:#475569;line-height:1.55;border-bottom:1px solid #f1f5f9}
.inv-tools{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.inv-tools input[type=search]{flex:1;min-width:180px;padding:8px 12px;border:1.5px solid #e2e8f0;border-radius:10px;font:inherit}
.inv-list{overflow:auto;flex:1}
.inv-list table{width:100%}
.inv-list td,.inv-list th{padding:10px 14px;font-size:13px;vertical-align:middle}
.inv-list th{position:sticky;top:0;background:#f8fafc;z-index:1}
.inv-list .muted{color:#94a3b8;font-size:12px}
.inv-wa{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;background:#25d366;color:#fff;font-weight:700;font-size:12px;text-decoration:none;white-space:nowrap}
.inv-wa:hover{filter:brightness(.93)}
.inv-wa.sent{background:#dcfce7;color:#15803d}
.inv-foot{padding:14px 22px;border-top:1px solid #f1f5f9;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.inv-foot .spacer{flex:1}
@media(max-width:640px){.inv-list .col-mail{display:none}}
/* Foto de referencia */
.ref-box{display:flex;gap:20px;align-items:center;flex-wrap:wrap;padding:18px;border:1.5px dashed #cbd5e1;border-radius:14px;background:#f8fafc;margin-bottom:24px}
.ref-img{width:120px;height:120px;border-radius:14px;object-fit:cover;background:#e2e8f0;display:grid;place-items:center;color:#64748b;font-size:34px;font-weight:700;flex:0 0 120px}
.ref-img[hidden]{display:none}
.ref-actions{display:flex;flex-direction:column;gap:8px;flex:1;min-width:220px}
.ref-actions .btns{display:flex;gap:8px;flex-wrap:wrap}
.ref-actions p{font-size:12px;color:#64748b;line-height:1.5}
#ref-status{font-size:13px;font-weight:600}
#ref-status[data-kind=ok]{color:#15803d}#ref-status[data-kind=err]{color:#b91c1c}#ref-status[data-kind=warn]{color:#b45309}
dialog#cam-dialog{border:none;border-radius:18px;padding:0;width:min(560px,94vw);background:#0f172a;color:#fff;box-shadow:0 24px 64px rgba(0,0,0,.35)}
dialog#cam-dialog::backdrop{background:rgba(15,23,42,.6)}
.cam-stage{position:relative;aspect-ratio:3/4;max-height:70vh;background:#000;overflow:hidden}
.cam-stage video{width:100%;height:100%;object-fit:cover}
.cam-stage video.mirror{transform:scaleX(-1)}
.cam-oval{position:absolute;left:50%;top:46%;width:58%;aspect-ratio:3/4;transform:translate(-50%,-50%);border:3px dashed rgba(255,255,255,.75);border-radius:50%;box-shadow:0 0 0 999px rgba(0,0,0,.35);pointer-events:none;transition:border-color .2s}
.cam-oval.ok{border-style:solid;border-color:#4ade80}
.cam-foot{padding:14px 16px;display:flex;flex-direction:column;gap:12px}
#cam-msg{font-size:13px;color:#cbd5e1;min-height:18px;text-align:center}
.cam-btns{display:flex;gap:8px;justify-content:center}
.cam-btns .btn-ghost{background:transparent;color:#e2e8f0;border-color:#334155}
/* Expediente */
.exp-intro{display:flex;align-items:center;gap:12px;flex-wrap:wrap;padding:18px 24px;border-bottom:1px solid #f1f5f9;color:#64748b;font-size:13px}
.exp-intro .spacer{flex:1}
.exp-cat{padding:6px 0}
.exp-cat h4{font-size:12px;font-weight:750;text-transform:uppercase;letter-spacing:.07em;color:#64748b;padding:16px 24px 6px}
.doc{display:grid;grid-template-columns:28px 1fr auto;gap:12px;align-items:start;padding:12px 24px;border-top:1px solid #f6f7f9}
.doc-state{width:24px;height:24px;border-radius:50%;display:grid;place-items:center;font-size:13px;font-weight:700;margin-top:1px}
.doc-state.ok{background:#dcfce7;color:#15803d}.doc-state.req{background:#fee2e2;color:#dc2626}.doc-state.opt{background:#f1f5f9;color:#94a3b8}
.doc-name{font-weight:600;color:#0f172a}
.doc-name .tag{font-size:10px;font-weight:700;color:#b91c1c;background:#fee2e2;padding:1px 6px;border-radius:99px;margin-left:6px;vertical-align:1px}
.doc-desc{font-size:12px;color:#94a3b8;margin-top:2px}
.files{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.file{display:flex;align-items:center;gap:10px;padding:6px 8px 6px 6px;border:1px solid #e6e9f0;border-radius:10px;background:#fff;max-width:100%}
.file-thumb{width:40px;height:40px;border-radius:7px;object-fit:cover;background:#f1f5f9;display:grid;place-items:center;font-size:10px;font-weight:800;color:#dc2626;flex:0 0 40px}
.file-info{min-width:0;font-size:12px;line-height:1.35}
.file-info a{display:block;font-weight:600;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px}
.file-info a:hover{color:var(--brand)}
.file-info span{color:#94a3b8}
.file form{display:inline}
.file .x{border:none;background:none;color:#94a3b8;cursor:pointer;font-size:16px;padding:2px 6px;border-radius:6px}
.file .x:hover{background:#fef2f2;color:#dc2626}
.up-btn input{display:none}
.extra-form{display:flex;gap:8px;flex-wrap:wrap;padding:14px 24px 20px}
.extra-form input[type=text]{flex:1;min-width:200px}
@media(max-width:900px){.stats{grid-template-columns:repeat(2,1fr)}.vf-grid{grid-template-columns:repeat(2,1fr)}.vf-span3{grid-column:span 2}.ficha-exp{margin-left:0;text-align:left}}
@media(max-width:640px){.vf-grid,.vf-grid-2{grid-template-columns:1fr}.vf-span2,.vf-span3{grid-column:1}.doc{grid-template-columns:24px 1fr;padding:12px 16px}.doc .up-btn{grid-column:2}.form-wrap{padding:18px 16px}.save-bar{padding:12px 16px}.search{max-width:none}.toolbar .spacer{display:none}}
</style>
<link rel="stylesheet" href="assets/admin.css?v=3">
</head>
<body>

<?php
$navTitle = $editEmp ? 'Expediente del empleado' : ($showForm ? 'Nuevo empleado' : 'Empleados');
$activeModule = 'empleados';
require __DIR__ . '/_nav.php';
?>

<div class="body">

  <?php foreach ($flash['ok'] ?? [] as $m): ?><div class="alert alert-ok">✓ <?= h($m) ?></div><?php endforeach ?>
  <?php foreach ($flash['err'] ?? [] as $m): ?><div class="alert alert-err">✗ <?= h($m) ?></div><?php endforeach ?>

<?php if ($showForm): /* ═══════════════════ FICHA (crear / editar) ═══════════════════ */ ?>

  <div style="margin-bottom:14px"><a href="empleados.php" class="btn btn-ghost btn-sm">← Directorio</a></div>

  <?php if ($editEmp && ($editEmp['registro_estado'] ?? null) === 'pendiente'): ?>
  <div class="reg-review">
    <?php if (!empty($editEmp['foto_referencia'])): ?>
      <img src="<?= h(fotoUrl($editEmp)) ?>" alt="Selfie del registro">
    <?php else: ?>
      <span class="ref-img av"><?= h(iniciales($editEmp)) ?></span>
    <?php endif ?>
    <div>
      <h3>Registro pendiente de aprobar</h3>
      <p>Se registró desde la liga el <?= fdt($editEmp['registro_fecha']) ?>
         <?php $accesos = array_filter([
             $editEmp['portal_username'] ? 'el usuario <strong>' . h($editEmp['portal_username']) . '</strong>' : '',
             $editEmp['portal_pin_hash'] ? 'un <strong>PIN</strong>' : '',
         ]); ?>
         <?= $accesos ? 'con ' . implode(' y ', $accesos) : '' ?>. Confirma que la selfie es de esta persona:
         con ella se verificará su identidad en cada check-in. Revisa también sus datos en las pestañas.</p>
      <div class="acts">
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="reg_aprobar">
          <input type="hidden" name="id" value="<?= (int)$editEmp['id'] ?>">
          <button type="submit" class="btn btn-primary">✓ Aprobar y activar acceso</button>
        </form>
        <form method="post" onsubmit="return confirm('¿Rechazar el registro? Se borran su selfie, su contraseña y su PIN, y podrá registrarse de nuevo.')">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="reg_rechazar">
          <input type="hidden" name="id" value="<?= (int)$editEmp['id'] ?>">
          <button type="submit" class="btn btn-danger">Rechazar</button>
        </form>
      </div>
    </div>
  </div>
  <?php endif ?>

  <div class="card" style="overflow:visible">
    <?php if ($editEmp): ?>
    <div class="ficha-head">
      <?php if (!empty($editEmp['foto_referencia'])): ?>
        <img class="av av-lg" src="<?= h(fotoUrl($editEmp)) ?>" alt="">
      <?php else: ?>
        <span class="av av-lg"><?= h(iniciales($editEmp)) ?></span>
      <?php endif ?>
      <div>
        <h2><?= h(trim($editEmp['nombre'] . ' ' . $editEmp['apellido_paterno'] . ' ' . ($editEmp['apellido_materno'] ?? ''))) ?></h2>
        <div class="ficha-meta">
          <span class="num-emp" style="margin-left:0"><?= h($editEmp['numero_empleado']) ?></span>
          <span><?= h($editEmp['puesto_nombre'] ?: 'Sin puesto') ?><?= $editEmp['dept_nombre'] ? ' · ' . h($editEmp['dept_nombre']) : '' ?></span>
          <?= ebadge($editEmp['estatus']) ?>
          <span>Ingreso <?= fd($editEmp['fecha_ingreso']) ?></span>
        </div>
      </div>
      <?php if ($expOk): ?>
      <div class="ficha-exp">
        <small>Expediente (obligatorios)</small>
        <?= $barra($expCubiertos, $avance['total']) ?>
      </div>
      <?php endif ?>
    </div>
    <?php else: ?>
    <div class="card-header"><h2><?= $fromAplicacion ? 'Contratar candidato' : 'Nuevo empleado' ?></h2></div>
    <?php endif ?>

    <?php if ($fromAplicacion && !$editEmp): ?>
      <div class="from-candidate-banner">
        Contratando desde candidato: <strong><?= h($fromAplicacion['nombre']) ?></strong>
        — <?= h($fromAplicacion['puesto_interes']) ?>. Revisa y completa los datos antes de guardar.
      </div>
    <?php endif; ?>

    <nav class="tabs" role="tablist">
      <button type="button" class="tab" data-tab="personal">Datos personales</button>
      <button type="button" class="tab" data-tab="laboral">Laboral</button>
      <button type="button" class="tab" data-tab="bancario">Bancario</button>
      <button type="button" class="tab" data-tab="portal">Portal y foto</button>
      <button type="button" class="tab" data-tab="expediente"
        <?= ($editEmp && $expOk) ? '' : 'disabled title="' . ($expOk ? 'Guarda primero al empleado para cargar su expediente' : 'Ejecuta db/migrate_expediente_v1.sql') . '"' ?>>
        Expediente<?= ($editEmp && $expOk && $avance['total']) ? ' · ' . $expCubiertos . '/' . $avance['total'] : '' ?>
      </button>
    </nav>
  </div>

  <div class="card">
    <form method="post" enctype="multipart/form-data" id="emp-form">
      <input type="hidden" name="csrf_token"    value="<?= h($csrf) ?>">
      <input type="hidden" name="action"        value="save_empleado">
      <input type="hidden" name="id"            value="<?= (int)($editEmp['id'] ?? 0) ?>">
      <input type="hidden" name="aplicacion_id" value="<?= (int)$fromApId ?>">
      <input type="hidden" name="tab"           value="<?= h($tab) ?>" id="tab-input">

      <!-- ═══ Datos personales ═══ -->
      <section class="panel form-wrap" data-panel="personal">
        <div class="form-section">
          <div class="form-section-title">Identidad</div>
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

        <div class="form-section">
          <div class="form-section-title">Documentos oficiales</div>
          <div class="vf-grid">
            <div class="vf-field">
              <label>CURP <span>(18 caracteres)</span></label>
              <input type="text" name="curp" class="vf-inp" maxlength="18"
                     value="<?= $v('curp') ?>" placeholder="XXXX000000XXXXXX00"
                     style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">
            </div>
            <div class="vf-field">
              <label>RFC <span>(12-13 caracteres)</span></label>
              <input type="text" name="rfc" class="vf-inp" maxlength="13"
                     value="<?= $v('rfc') ?>" placeholder="XXXX000000XXX"
                     style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()">
            </div>
            <div class="vf-field">
              <label>NSS — IMSS <span>(11 dígitos)</span></label>
              <input type="text" name="nss" class="vf-inp" maxlength="11" inputmode="numeric"
                     value="<?= $v('nss') ?>" placeholder="00000000000">
            </div>
          </div>
        </div>

        <div class="form-section">
          <div class="form-section-title">Contacto y domicilio</div>
          <div class="vf-grid">
            <div class="vf-field">
              <label>Teléfono</label>
              <input type="tel" name="telefono" class="vf-inp" value="<?= $v('telefono') ?>" placeholder="55 1234 5678">
            </div>
            <div class="vf-field">
              <label>Correo personal</label>
              <input type="email" name="email" class="vf-inp" value="<?= $v('email') ?>" placeholder="correo@ejemplo.com">
            </div>
            <div class="vf-field">
              <label>Correo corporativo</label>
              <input type="email" name="email_corporativo" class="vf-inp" value="<?= $v('email_corporativo') ?>" placeholder="nombre@empresa.com">
            </div>
            <div class="vf-field vf-span3">
              <label>Calle y número</label>
              <input type="text" name="direccion" class="vf-inp" value="<?= $v('direccion') ?>" placeholder="Calle, número exterior e interior">
            </div>
            <div class="vf-field">
              <label>C.P.</label>
              <input type="text" id="cp-input" name="cp" class="vf-inp" maxlength="5" inputmode="numeric" value="<?= $v('cp') ?>" placeholder="06600">
              <span id="cp-status" style="font-size:11px;color:#94a3b8;"></span>
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
          </div>
        </div>
      </section>

      <!-- ═══ Laboral ═══ -->
      <section class="panel form-wrap" data-panel="laboral">
        <div class="form-section">
          <div class="form-section-title">Puesto y contrato</div>
          <div class="vf-grid">
            <div class="vf-field">
              <label>Departamento</label>
              <select name="departamento_id" class="vf-inp" id="sel-dept">
                <option value="">— Sin asignar —</option>
                <?php foreach ($departamentos as $d): ?>
                  <option value="<?= (int)$d['id'] ?>" <?= ((string)($pre['departamento_id'] ?? '')) === (string)$d['id'] ? 'selected' : '' ?>><?= h($d['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="vf-field">
              <label>Puesto (catálogo)</label>
              <select name="puesto_id" class="vf-inp" id="sel-puesto">
                <option value="">— Sin asignar —</option>
                <?php foreach ($puestos as $p): ?>
                  <option value="<?= (int)$p['id'] ?>" data-dept="<?= (int)$p['departamento_id'] ?>"
                    <?= ((string)($pre['puesto_id'] ?? '')) === (string)$p['id'] ? 'selected' : '' ?>>
                    <?= h($p['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="vf-field">
              <label>Puesto libre <span>(si no está en catálogo)</span></label>
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
              <label>Fin de contrato <span>(si aplica)</span></label>
              <input type="date" name="fecha_fin_contrato" class="vf-inp" value="<?= $v('fecha_fin_contrato') ?>">
            </div>
            <div class="vf-field">
              <label>Salario base mensual <span>(MXN bruto)</span></label>
              <input type="number" name="salario_base" class="vf-inp" step="0.01" min="0" inputmode="decimal"
                     value="<?= h((string)($pre['salario_base'] ?? '')) ?>" placeholder="0.00">
            </div>
            <div class="vf-field">
              <label>Período de pago</label>
              <?= sel('periodo_pago', ['semanal'=>'Semanal','quincenal'=>'Quincenal','mensual'=>'Mensual'], $pre['periodo_pago'] ?? 'quincenal') ?>
            </div>
            <div class="vf-field">
              <label>Número de empleado <span>(auto si vacío)</span></label>
              <input type="text" name="numero_empleado" class="vf-inp"
                     value="<?= $v('numero_empleado') ?>" placeholder="EMP-0001">
            </div>
          </div>
        </div>

        <div class="form-section">
          <div class="form-section-title">Estatus</div>
          <div class="vf-grid">
            <div class="vf-field">
              <label>Estatus laboral</label>
              <?= sel('estatus', ['activo'=>'Activo','vacaciones'=>'Vacaciones','incapacidad'=>'Incapacidad','baja_temporal'=>'Baja temporal','baja_definitiva'=>'Baja definitiva'], $pre['estatus'] ?? 'activo') ?>
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

        <div class="form-section">
          <div class="form-section-title">Notas internas</div>
          <div class="vf-field">
            <textarea name="notas" class="vf-inp" rows="3"
              placeholder="Observaciones del reclutador, referencias, información adicional…"><?= $v('notas') ?></textarea>
          </div>
        </div>
      </section>

      <!-- ═══ Bancario ═══ -->
      <section class="panel form-wrap" data-panel="bancario">
        <div class="form-section">
          <div class="form-section-title">Datos bancarios para nómina</div>
          <div class="vf-grid-2">
            <div class="vf-field">
              <label>Banco</label>
              <input type="text" name="banco" class="vf-inp" value="<?= $v('banco') ?>" placeholder="BBVA, Santander, HSBC…">
            </div>
            <div class="vf-field">
              <label>CLABE interbancaria <span>(18 dígitos)</span></label>
              <input type="text" name="clabe_bancaria" class="vf-inp" maxlength="18" inputmode="numeric"
                     value="<?= $v('clabe_bancaria') ?>" placeholder="000000000000000000">
            </div>
          </div>
        </div>
      </section>

      <!-- ═══ Portal y foto ═══ -->
      <section class="panel form-wrap" data-panel="portal">
        <div class="form-section">
          <div class="form-section-title">Foto de referencia — verificación facial del check-in</div>
          <div class="ref-box" id="ref-photo"
               data-faceapi="../portal/assets/face-api/face-api.min.js"
               data-weights="../portal/assets/face-api/weights">
            <?php if (!empty($pre['foto_referencia']) && $editEmp): ?>
              <img class="ref-img" id="ref-preview" src="<?= h(fotoUrl($editEmp)) ?>" alt="Foto de referencia">
            <?php else: ?>
              <span class="ref-img" id="ref-initials"><?= h($editEmp ? iniciales($editEmp) : '?') ?></span>
              <img class="ref-img" id="ref-preview" alt="" hidden>
            <?php endif ?>
            <div class="ref-actions">
              <div class="btns">
                <button type="button" class="btn btn-primary" id="ref-cam-btn">📷 Tomar foto con cámara</button>
                <label class="btn btn-ghost">Subir foto<input type="file" id="ref-file" accept="image/*" hidden></label>
              </div>
              <p>De frente, sin lentes oscuros ni gorra, con buena luz y solo el empleado en la foto.
                 Antes de aceptarla se verifica que se detecte un rostro claro.</p>
              <span id="ref-status" aria-live="polite"></span>
              <input type="file" name="foto_referencia" id="foto_referencia" accept="image/jpeg" hidden>
            </div>
          </div>
        </div>

        <div class="form-section">
          <div class="form-section-title">Acceso al portal del empleado</div>
          <div class="vf-grid">
            <div class="vf-field">
              <label>Usuario de portal</label>
              <input type="text" name="portal_username" class="vf-inp" maxlength="50"
                     value="<?= $v('portal_username') ?>" placeholder="nombre.apellido" autocomplete="off">
            </div>
            <div class="vf-field">
              <label>Nueva contraseña <span>(vacío = no cambiar)</span></label>
              <input type="password" name="portal_password" class="vf-inp" autocomplete="new-password" placeholder="••••••••">
            </div>
            <div class="vf-field">
              <label>PIN alterno <span>(4-6 dígitos)</span></label>
              <input type="text" name="portal_pin" class="vf-inp" maxlength="6" inputmode="numeric" pattern="\d{4,6}"
                     autocomplete="off" placeholder="Vacío = no cambiar">
            </div>
            <div class="vf-field">
              <label>Acceso al portal</label>
              <?= sel('portal_activo', ['1' => 'Habilitado', '0' => 'Deshabilitado'], (string)($pre['portal_activo'] ?? '1')) ?>
            </div>
          </div>
        </div>
      </section>

      <div class="save-bar" id="save-bar">
        <button type="submit" class="btn btn-primary"><?= $editEmp ? 'Guardar cambios' : 'Crear empleado' ?></button>
        <a href="empleados.php" class="btn btn-ghost">Cancelar</a>
        <?php if ($editEmp): ?>
          <span class="meta">
            Creado <?= fdt($editEmp['fecha_creacion']) ?>
            <?= $editEmp['fecha_actualiza'] ? '· Actualizado ' . fdt($editEmp['fecha_actualiza']) : '' ?>
          </span>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($editEmp && $expOk): ?>
    <!-- ═══ Expediente (fuera del formulario principal: cada documento tiene su propio formulario) ═══ -->
    <section class="panel" data-panel="expediente">
      <div class="exp-intro">
        <span>PDF o fotos (JPG, PNG). Desde el celular puedes fotografiar el documento directamente; las fotos se comprimen antes de subir.</span>
        <span class="spacer"></span>
        <a href="documentos_catalogo.php" class="btn btn-ghost btn-sm">Configurar catálogo</a>
      </div>

      <?php
      $cat = null;
      foreach ($tiposDoc as $t):
        $docs = $docsPorTipo[(int)$t['id']] ?? [];
        if ($t['categoria'] !== $cat): ?>
          <?php if ($cat !== null): ?></div><?php endif; $cat = $t['categoria']; ?>
          <div class="exp-cat"><h4><?= h($cat) ?></h4>
        <?php endif;
        $estado = $docs ? 'ok' : ($t['obligatorio'] ? 'req' : 'opt'); ?>
        <div class="doc">
          <span class="doc-state <?= $estado ?>"><?= $docs ? '✓' : ($t['obligatorio'] ? '!' : '·') ?></span>
          <div>
            <div class="doc-name"><?= h($t['nombre']) ?><?php if ($t['obligatorio'] && !$docs): ?><span class="tag">Pendiente</span><?php endif ?></div>
            <?php if ($t['descripcion']): ?><div class="doc-desc"><?= h($t['descripcion']) ?></div><?php endif ?>
            <?php if ($docs): ?><div class="files"><?php foreach ($docs as $d) include __DIR__ . '/_documento_archivo.php'; ?></div><?php endif ?>
          </div>
          <form method="post" enctype="multipart/form-data" class="up-btn">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <input type="hidden" name="action" value="doc_upload">
            <input type="hidden" name="id" value="<?= (int)$editEmp['id'] ?>">
            <input type="hidden" name="tipo_id" value="<?= (int)$t['id'] ?>">
            <label class="btn <?= $docs ? 'btn-ghost' : 'btn-primary' ?> btn-sm">
              <?= $docs ? '+ Agregar' : 'Subir' ?>
              <input type="file" name="documento[]" multiple accept="application/pdf,image/*" data-compress="2000" data-autosubmit>
            </label>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if ($cat !== null): ?></div><?php endif ?>

      <div class="exp-cat">
        <h4>Documentos adicionales</h4>
        <?php if ($docsExtra): ?>
          <div class="doc"><span class="doc-state opt">·</span>
            <div class="files" style="margin-top:0"><?php foreach ($docsExtra as $d) include __DIR__ . '/_documento_archivo.php'; ?></div>
          </div>
        <?php endif ?>
        <form method="post" enctype="multipart/form-data" class="extra-form">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="doc_upload">
          <input type="hidden" name="id" value="<?= (int)$editEmp['id'] ?>">
          <input type="text" name="doc_nombre" class="vf-inp" maxlength="150" required placeholder="Nombre del documento (ej. Constancia de curso de seguridad)">
          <label class="btn btn-ghost">Elegir archivo(s)
            <input type="file" name="documento[]" multiple accept="application/pdf,image/*" data-compress="2000" hidden>
          </label>
          <button type="submit" class="btn btn-primary">Subir</button>
        </form>
      </div>
    </section>
    <?php endif ?>
  </div>

  <dialog id="cam-dialog">
    <div class="cam-stage">
      <video id="cam-video" playsinline muted></video>
      <div class="cam-oval"></div>
    </div>
    <div class="cam-foot">
      <div id="cam-msg" aria-live="polite"></div>
      <div class="cam-btns">
        <button type="button" class="btn btn-ghost" id="cam-cancel">Cancelar</button>
        <button type="button" class="btn btn-ghost" id="cam-switch">↺ Cambiar cámara</button>
        <button type="button" class="btn btn-primary" id="cam-capture">Capturar</button>
      </div>
    </div>
  </dialog>

<?php else: /* ═══════════════════ DIRECTORIO ═══════════════════ */ ?>

  <div class="stats">
    <div class="stat">
      <div class="stat-val"><?= $cActivos ?></div>
      <div class="stat-lbl">Empleados activos</div>
      <div class="stat-sub"><?= $cTotal ?> registrados</div>
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
      <?php
        $completos = 0;
        foreach ($empleados as $e) if ($avance['total'] && ($avance['por_empleado'][(int)$e['id']] ?? 0) >= $avance['total']) $completos++;
      ?>
      <div class="stat-val"><?= $expOk ? $completos . '<span class="muted" style="font-size:18px">/' . count($empleados) . '</span>' : '—' ?></div>
      <div class="stat-lbl">Expedientes completos</div>
      <div class="stat-sub"><?= $expOk ? 'Del listado actual' : 'Expediente sin habilitar' ?></div>
    </div>
  </div>

  <?php if ($regPend > 0 && !$f_registro): ?>
    <div class="alert alert-info" style="background:#fffbeb;border-color:#fde68a;color:#92400e">
      <strong><?= $regPend ?></strong> empleado(s) completaron su registro desde la liga y esperan que apruebes su selfie.
      <a href="empleados.php?f_registro=pendiente" style="font-weight:700;text-decoration:underline;margin-left:6px">Revisar</a>
    </div>
  <?php elseif ($f_registro): ?>
    <div class="alert alert-info">Mostrando solo registros pendientes de aprobar. <a href="empleados.php" style="font-weight:700;text-decoration:underline">Ver todos</a></div>
  <?php endif ?>

  <?php if (!$expOk): ?>
    <div class="alert alert-info">Para habilitar el expediente digital ejecuta <code>db/migrate_expediente_v1.sql</code>.</div>
  <?php endif ?>

  <div class="toolbar">
    <label class="search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      <input type="search" id="emp-search" placeholder="Buscar por nombre, número, puesto…" aria-label="Buscar empleado">
    </label>
    <form method="get" style="display:contents">
      <select name="f_dept" onchange="this.form.submit()" aria-label="Departamento">
        <option value="">Todos los departamentos</option>
        <?php foreach ($departamentos as $d): ?>
          <option value="<?= (int)$d['id'] ?>" <?= $f_dept === (int)$d['id'] ? 'selected' : '' ?>><?= h($d['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="f_estatus" onchange="this.form.submit()" aria-label="Estatus">
        <option value="" <?= !$f_estatus ? 'selected' : '' ?>>Todos los estatus</option>
        <?php foreach (['activo'=>'Activo','vacaciones'=>'Vacaciones','incapacidad'=>'Incapacidad','baja_temporal'=>'Baja Temporal','baja_definitiva'=>'Baja Definitiva'] as $vv=>$ll): ?>
          <option value="<?= $vv ?>" <?= $f_estatus===$vv ? 'selected' : '' ?>><?= $ll ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($f_dept || $f_estatus): ?><a href="empleados.php" class="btn btn-ghost btn-sm">✕ Limpiar</a><?php endif; ?>
    </form>
    <span class="spacer"></span>
    <?php if ($expOk): ?><a href="documentos_catalogo.php" class="btn btn-ghost">Catálogo de documentos</a><?php endif ?>
    <?php if ($regOk): ?>
      <button type="button" class="btn btn-ghost" id="inv-btn" title="Enviar por correo o WhatsApp la invitación para que registren su selfie, contraseña y/o PIN">
        📨 Invitar a registrarse<?php if ($porInvitar): ?> <span class="inv-cnt"><?= count($porInvitar) ?></span><?php endif ?>
      </button>
      <button type="button" class="btn btn-ghost" id="liga-btn">🔗 Liga de registro</button>
    <?php endif ?>
    <a href="empleados_importar.php" class="btn btn-ghost">Importar CSV</a>
    <a href="empleados.php?form=1" class="btn btn-primary">+ Nuevo empleado</a>
  </div>

  <div class="card">
    <?php if (empty($empleados)): ?>
      <div class="empty">
        No hay empleados con estos filtros.
        <br><a href="empleados.php?form=1" style="color:var(--brand);display:inline-block;margin-top:8px;">Registrar empleado</a>
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Empleado</th>
            <th>Puesto / Área</th>
            <th>Contrato</th>
            <th>Ingreso</th>
            <?php if ($expOk): ?><th>Expediente</th><?php endif ?>
            <th>Estatus</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($empleados as $e):
          $nomCompleto = trim($e['nombre'] . ' ' . $e['apellido_paterno'] . ' ' . ($e['apellido_materno'] ?? ''));
          $buscar = mb_strtolower($nomCompleto . ' ' . $e['numero_empleado'] . ' ' . $e['puesto_nombre'] . ' ' . $e['dept_nombre'] . ' ' . $e['telefono'] . ' ' . $e['email']); ?>
          <tr data-href="empleados.php?edit=<?= (int)$e['id'] ?>" data-search="<?= h($buscar) ?>">
            <td>
              <div class="who">
                <?php if (!empty($e['foto_referencia'])): ?>
                  <img class="av" src="<?= h(fotoUrl($e)) ?>" alt="" loading="lazy">
                <?php else: ?>
                  <span class="av"><?= h(iniciales($e)) ?></span>
                <?php endif ?>
                <div>
                  <strong><?= h($nomCompleto) ?><span class="num-emp"><?= h($e['numero_empleado']) ?></span></strong>
                  <small><?= h($e['telefono'] ?: ($e['email'] ?: '')) ?></small>
                </div>
              </div>
            </td>
            <td>
              <?= h($e['puesto_nombre'] ?: '—') ?>
              <?php if ($e['dept_nombre']): ?><div style="font-size:12px;color:#94a3b8;"><?= h($e['dept_nombre']) ?></div><?php endif; ?>
            </td>
            <td><?= ctbadge($e['tipo_contrato']) ?></td>
            <td style="white-space:nowrap;font-size:13px;color:#64748b;"><?= fd($e['fecha_ingreso']) ?></td>
            <?php if ($expOk): ?><td style="white-space:nowrap"><?= $barra($avance['por_empleado'][(int)$e['id']] ?? 0, $avance['total']) ?></td><?php endif ?>
            <td><?= ebadge($e['estatus']) ?><?php if (($e['registro_estado'] ?? null) === 'pendiente'): ?><span class="badge b-reg">Por aprobar</span><?php endif ?></td>
            <td>
              <div class="row-actions">
                <a href="empleados.php?edit=<?= (int)$e['id'] ?>" class="btn btn-ghost btn-sm">Abrir</a>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="toggle_baja">
                  <input type="hidden" name="id"     value="<?= (int)$e['id'] ?>">
                  <button type="submit" class="btn btn-sm <?= $e['estatus']==='baja_definitiva' ? 'btn-success' : 'btn-ghost' ?>"
                          onclick="return confirm('<?= $e['estatus']==='baja_definitiva' ? '¿Reactivar empleado?' : '¿Dar de baja a este empleado?' ?>')">
                    <?= $e['estatus']==='baja_definitiva' ? 'Reactivar' : 'Dar baja' ?>
                  </button>
                </form>
                <?php if ($e['estatus'] === 'baja_definitiva'): ?>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="del_empleado">
                  <input type="hidden" name="id"     value="<?= (int)$e['id'] ?>">
                  <button type="submit" class="btn btn-danger btn-sm" title="Eliminar definitivamente (incluye su expediente)"
                          onclick="return confirm(<?= h(json_encode('¿Eliminar permanentemente a ' . $nomCompleto . ' y todo su expediente? Esta acción no se puede deshacer.', JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP)) ?>)">
                    Eliminar
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="empty" id="search-empty" hidden>Ningún empleado coincide con la búsqueda.</div>
    <?php endif; ?>
  </div>

<?php endif; ?>

</div><!-- /body -->

<?php if (!$showForm && $regOk): ?>
<dialog id="liga-dialog">
  <div class="liga-head">
    <h3>Liga de registro de empleados</h3>
    <button type="button" class="btn btn-ghost btn-sm" id="liga-cerrar" aria-label="Cerrar">✕</button>
  </div>
  <div class="liga-body">
    <span class="liga-estado <?= $regActiva ? 'on' : '' ?>"><i></i><?= $regActiva ? 'Activa' : 'Desactivada' ?></span>
    <p>Compártela con el personal ya dado de alta. Para activar su cuenta cada empleado necesita su
       <strong>número de empleado</strong> y el <strong>teléfono o correo</strong> registrado en su ficha
       (si tiene correo, además recibe un código). Captura sus datos, su selfie y su contraseña, y queda
       <strong>pendiente</strong> hasta que apruebes su selfie aquí.</p>
    <?php if ($regActiva): ?>
      <div class="liga-url">
        <input type="text" class="vf-inp" id="liga-url" readonly value="<?= h($regUrl) ?>">
        <button type="button" class="btn btn-primary" id="liga-copiar">Copiar</button>
      </div>
      <a class="btn btn-ghost" target="_blank" rel="noopener noreferrer"
         href="https://wa.me/?text=<?= rawurlencode('Activa tu cuenta del portal de empleados de ' . appConfig('empresa_nombre', 'la empresa') . ' (necesitas tu número de empleado): ' . $regUrl) ?>">
        Compartir por WhatsApp
      </a>
    <?php endif ?>
    <div class="liga-acts">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="reg_liga">
        <input type="hidden" name="op" value="<?= $regActiva ? 'desactivar' : 'activar' ?>">
        <button type="submit" class="btn <?= $regActiva ? 'btn-danger' : 'btn-primary' ?>"><?= $regActiva ? 'Desactivar liga' : 'Activar liga' ?></button>
      </form>
      <?php if ($regActiva): ?>
      <form method="post" onsubmit="return confirm('¿Generar una liga nueva? La actual dejará de funcionar para quien la tenga.')">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="reg_liga">
        <input type="hidden" name="op" value="rotar">
        <button type="submit" class="btn btn-ghost">Generar liga nueva</button>
      </form>
      <?php endif ?>
    </div>
    <p style="font-size:12px;color:#94a3b8">Si la liga se compartió de más, genera una nueva o desactívala.
       Acceso al portal para quien ya está activo: <strong><?= h(appBaseUrl() . '/portal/login.php') ?></strong></p>
  </div>
</dialog>

<?php
$invEmpresa = appConfig('empresa_nombre', 'la empresa');
$invClave   = appConfig('registro_clave');
$invConMail = 0;
foreach ($porInvitar as $pi) if (empleadoCorreo($pi) !== '') $invConMail++;
?>
<dialog id="inv-dialog">
  <div class="liga-head">
    <h3>Invitar a registrarse en el portal</h3>
    <button type="button" class="btn btn-ghost btn-sm" id="inv-cerrar" aria-label="Cerrar">✕</button>
  </div>
  <form method="post" class="inv-form" id="inv-form">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="action" value="inv_correo">
    <div class="inv-top">
      <?php if (isset($_GET['invitar'])): ?>
        <?php foreach ($flash['ok'] ?? [] as $m): ?><div class="alert alert-ok" style="margin:0">✓ <?= h($m) ?></div><?php endforeach ?>
        <?php foreach ($flash['err'] ?? [] as $m): ?><div class="alert alert-err" style="margin:0">✗ <?= h($m) ?></div><?php endforeach ?>
      <?php endif ?>
      <p>Estos son los empleados que <strong>aún no tienen acceso</strong> al portal. Cada uno recibe su liga personal
         (con su número de empleado ya escrito) para tomarse la selfie y crear su <strong>contraseña y/o PIN</strong>.
         Al terminar quedan <strong>por aprobar</strong> aquí mismo.</p>
      <?php if (!$regActiva): ?>
        <div class="alert alert-err" style="margin:0">La liga de registro está desactivada: las invitaciones no funcionarán hasta que la actives.
          <button type="submit" form="inv-activar" class="btn btn-primary btn-sm" style="margin-left:8px">Activar liga</button></div>
      <?php elseif (!$invTrack): ?>
        <p class="muted" style="font-size:12px;color:#94a3b8">Para llevar el registro de a quién ya invitaste ejecuta <code>db/migrate_invitaciones_v1.sql</code>.</p>
      <?php endif ?>
      <?php if ($porInvitar): ?>
      <div class="inv-tools">
        <input type="search" id="inv-search" placeholder="Buscar empleado…" aria-label="Buscar empleado">
        <label style="display:flex;gap:6px;align-items:center;white-space:nowrap"><input type="checkbox" id="inv-all"> Seleccionar todos con correo</label>
      </div>
      <?php endif ?>
    </div>

    <div class="inv-list">
      <?php if (!$porInvitar): ?>
        <div class="empty">🎉 Todos los empleados activos ya tienen acceso al portal o un registro en revisión.</div>
      <?php else: ?>
      <table>
        <thead><tr><th style="width:36px"></th><th>Empleado</th><th class="col-mail">Correo</th><th>WhatsApp</th><th>Última invitación</th></tr></thead>
        <tbody>
        <?php foreach ($porInvitar as $pi):
          $piNombre = trim($pi['nombre'] . ' ' . $pi['apellido_paterno'] . ' ' . ($pi['apellido_materno'] ?? ''));
          $piMail   = empleadoCorreo($pi);
          $piWa     = $regActiva ? whatsappUrl((string)$pi['telefono'], invitacionTextoWhatsapp($pi, registroUrlPersonal($invClave, (string)$pi['numero_empleado']), $invEmpresa)) : '';
          $piUlt    = $invTrack && $pi['invitacion_fecha'] ? fdt($pi['invitacion_fecha']) . ' · ' . str_replace('+', ' y ', (string)$pi['invitacion_canal']) : '';
        ?>
          <tr data-search="<?= h(mb_strtolower($piNombre . ' ' . $pi['numero_empleado'] . ' ' . $pi['telefono'] . ' ' . $piMail)) ?>">
            <td><input type="checkbox" name="ids[]" value="<?= (int)$pi['id'] ?>" class="inv-chk" <?= $piMail === '' || !$regActiva ? 'disabled' : '' ?> aria-label="Seleccionar <?= h($piNombre) ?>"></td>
            <td><strong><?= h($piNombre) ?></strong><div class="muted"><?= h($pi['numero_empleado']) ?><?= $pi['registro_estado'] === 'rechazado' ? ' · registro rechazado' : '' ?></div></td>
            <td class="col-mail"><?= $piMail !== '' ? h($piMail) : '<span class="muted">Sin correo</span>' ?></td>
            <td>
              <?php if ($piWa !== ''): ?>
                <a class="inv-wa <?= $invTrack && str_contains((string)$pi['invitacion_canal'], 'whatsapp') ? 'sent' : '' ?>" href="<?= h($piWa) ?>" target="_blank" rel="noopener noreferrer" data-id="<?= (int)$pi['id'] ?>">
                  <?= $invTrack && str_contains((string)$pi['invitacion_canal'], 'whatsapp') ? '✓ Reenviar' : 'Enviar' ?>
                </a>
              <?php else: ?>
                <span class="muted"><?= $regActiva ? 'Sin teléfono válido' : '—' ?></span>
              <?php endif ?>
            </td>
            <td class="muted inv-ult"><?= $piUlt !== '' ? h($piUlt) : 'Nunca' ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
      <?php endif ?>
    </div>

    <?php if ($porInvitar): ?>
    <div class="inv-foot">
      <span class="muted" style="font-size:12px;color:#94a3b8"><?= count($porInvitar) ?> sin acceso · <?= $invConMail ?> con correo.
        WhatsApp se abre uno por uno desde tu sesión de WhatsApp Web o del celular.</span>
      <span class="spacer"></span>
      <button type="submit" class="btn btn-primary" id="inv-enviar" disabled>Enviar correo a seleccionados</button>
    </div>
    <?php endif ?>
  </form>
  <form method="post" id="inv-activar" hidden>
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
    <input type="hidden" name="action" value="reg_liga">
    <input type="hidden" name="op" value="activar">
    <input type="hidden" name="volver" value="invitar">
  </form>
</dialog>
<script>
(function () {
  const dlg = document.getElementById('inv-dialog');
  document.getElementById('inv-btn').addEventListener('click', () => dlg.showModal());
  document.getElementById('inv-cerrar').addEventListener('click', () => dlg.close());
  if (new URLSearchParams(location.search).has('invitar')) dlg.showModal();

  const chks = Array.from(dlg.querySelectorAll('.inv-chk:not([disabled])'));
  const all = document.getElementById('inv-all'), btn = document.getElementById('inv-enviar');
  function sync() {
    const n = chks.filter(c => c.checked).length;
    if (btn) { btn.disabled = n === 0; btn.textContent = n ? 'Enviar correo a ' + n + ' seleccionado(s)' : 'Enviar correo a seleccionados'; }
    if (all) all.checked = n > 0 && chks.every(c => c.checked || c.closest('tr').hidden);
  }
  chks.forEach(c => c.addEventListener('change', sync));
  all && all.addEventListener('change', () => { chks.forEach(c => { if (!c.closest('tr').hidden) c.checked = all.checked; }); sync(); });

  const q = document.getElementById('inv-search');
  q && q.addEventListener('input', function () {
    const v = this.value.trim().toLowerCase();
    dlg.querySelectorAll('tbody tr[data-search]').forEach(r => { r.hidden = v !== '' && !r.dataset.search.includes(v); });
  });

  document.getElementById('inv-form').addEventListener('submit', function (e) {
    const n = chks.filter(c => c.checked).length;
    if (!n || !confirm('¿Enviar la invitación por correo a ' + n + ' empleado(s)?')) { e.preventDefault(); return; }
    btn.disabled = true; btn.textContent = 'Enviando…';
  });

  /* WhatsApp: se abre en otra pestaña y se registra la fecha de la invitación */
  dlg.querySelectorAll('a.inv-wa').forEach(function (a) {
    a.addEventListener('click', function () {
      const fd = new FormData();
      fd.append('csrf_token', <?= json_encode($csrf) ?>);
      fd.append('action', 'inv_wa');
      fd.append('id', a.dataset.id);
      fetch('empleados.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json()).then(function (d) {
          a.classList.add('sent'); a.textContent = '✓ Reenviar';
          const ult = a.closest('tr').querySelector('.inv-ult');
          if (ult && d.ok) ult.textContent = d.fecha + ' · whatsapp';
        }).catch(() => {});
    });
  });
})();
</script>
<script>
(function () {
  const dlg = document.getElementById('liga-dialog');
  document.getElementById('liga-btn').addEventListener('click', () => dlg.showModal());
  document.getElementById('liga-cerrar').addEventListener('click', () => dlg.close());
  const copiar = document.getElementById('liga-copiar');
  copiar && copiar.addEventListener('click', function () {
    const inp = document.getElementById('liga-url');
    (navigator.clipboard ? navigator.clipboard.writeText(inp.value) : Promise.reject())
      .catch(() => { inp.select(); document.execCommand('copy'); })
      .finally(() => { copiar.textContent = '¡Copiada!'; setTimeout(() => copiar.textContent = 'Copiar', 2000); });
  });
  if (new URLSearchParams(location.search).has('liga')) dlg.showModal();
})();
</script>
<?php endif ?>
<script src="assets/img-compress.js?v=2"></script>
<?php if ($showForm): ?><script src="assets/foto-referencia.js?v=3"></script><?php endif ?>
<script>
/* ── Directorio: búsqueda instantánea y fila clicable ── */
(function () {
  const input = document.getElementById('emp-search');
  if (input) {
    const rows  = Array.from(document.querySelectorAll('tbody tr[data-search]'));
    const empty = document.getElementById('search-empty');
    input.addEventListener('input', function () {
      const q = this.value.trim().toLowerCase();
      let visibles = 0;
      rows.forEach(r => { const ok = !q || r.dataset.search.includes(q); r.hidden = !ok; if (ok) visibles++; });
      if (empty) empty.hidden = visibles > 0;
    });
  }
  document.querySelectorAll('tbody tr[data-href]').forEach(function (tr) {
    tr.addEventListener('click', function (e) {
      if (e.target.closest('a,button,form,input,select')) return;
      location.href = tr.dataset.href;
    });
  });
})();

/* ── Ficha: pestañas ── */
(function () {
  const tabs = document.querySelectorAll('.tab[data-tab]');
  if (!tabs.length) return;
  const panels  = document.querySelectorAll('.panel[data-panel]');
  const tabIn   = document.getElementById('tab-input');
  const saveBar = document.getElementById('save-bar');

  function show(name) {
    tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === name));
    panels.forEach(p => p.classList.toggle('active', p.dataset.panel === name));
    if (tabIn) tabIn.value = name;
    if (saveBar) saveBar.hidden = name === 'expediente';
    const url = new URL(location.href);
    url.searchParams.set('tab', name);
    history.replaceState(null, '', url);
  }
  tabs.forEach(t => t.addEventListener('click', () => { if (!t.disabled) show(t.dataset.tab); }));
  show(<?= json_encode($tab) ?>);

  // Un campo obligatorio en otra pestaña: mostrar esa pestaña para que el navegador marque el error
  const form = document.getElementById('emp-form');
  form && form.addEventListener('invalid', function (e) {
    const panel = e.target.closest('.panel[data-panel]');
    if (panel && !panel.classList.contains('active')) show(panel.dataset.panel);
  }, true);
})();

/* ── Departamento / puesto ── */
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
        if (!data.ok) { cpStatus.textContent = 'C.P. no encontrado en el catálogo'; return; }
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
