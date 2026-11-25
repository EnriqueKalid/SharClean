<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requirePermiso('empleados');
require_once __DIR__ . '/../db/csrf.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fetchAll($result): array {
    $rows = [];
    if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}

/* Columnas esperadas en el CSV — mismo set de campos que el alta manual
   (numero_empleado es opcional: si se deja vacío se autogenera EMP-XXXX) */
const CSV_COLUMNAS = [
    'numero_empleado','nombre','apellido_paterno','apellido_materno',
    'fecha_nacimiento','sexo','estado_civil','curp','rfc','nss',
    'telefono','email','email_corporativo',
    'direccion','colonia','ciudad','estado_residencia','cp',
    'departamento_id','puesto_id','puesto_nombre',
    'tipo_contrato','fecha_ingreso','fecha_fin_contrato',
    'salario_base','periodo_pago','banco','clabe_bancaria',
    'estatus','fecha_baja','motivo_baja','aplicacion_id','notas',
];
const CSV_REQUERIDAS = ['nombre', 'apellido_paterno', 'fecha_ingreso'];

const SEXOS            = ['M','F','NB'];
const ESTADOS_CIVILES  = ['soltero','casado','union_libre','divorciado','viudo'];
const TIPOS_CONTRATO   = ['indeterminado','determinado','por_obra','confianza','honorarios'];
const PERIODOS_PAGO    = ['semanal','quincenal','mensual'];
const ESTATUSES        = ['activo','vacaciones','incapacidad','baja_temporal','baja_definitiva'];

/** Órdenes de fecha que puede tener el archivo (lo elige quien importa; nunca se adivina). */
const ORDENES_FECHA = [
    'dmy' => 'DD/MM/AAAA (México, ej. 25/03/1990)',
    'mdy' => 'MM/DD/AAAA (Estados Unidos, ej. 03/25/1990)',
];

/**
 * Convierte una fecha del CSV a 'Y-m-d' según el orden ELEGIDO ($orden = dmy | mdy).
 * Antes se adivinaba por la forma ("05/03/1990" → 5 de marzo, pero "5/3/1990" → 3 de mayo):
 * la misma fecha quedaba distinta según la PC que exportó el archivo.
 *  · AAAA-MM-DD siempre se acepta (no es ambigua).
 *  · Con / - o . y con o sin ceros: 5/3/1990, 05-03-1990, 5.3.1990.
 *  · Número de serie de Excel (ej. 32937) cuando la celda se exportó sin formato.
 *  · Año de 2 dígitos: se rechaza (¿1990 o 2090?).
 * Devuelve '' si está vacía, false si es inválida o contradice el orden elegido, o 'Y-m-d'.
 */
function parseFechaCsv(string $s, string $orden = 'dmy')
{
    $s = trim($s);
    if ($s === '') return '';
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $s, $m)) {
        [$y, $mes, $dia] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    } elseif (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/', $s, $m)) {
        [$a, $b, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        [$dia, $mes] = $orden === 'mdy' ? [$b, $a] : [$a, $b];
    } elseif (preg_match('/^\d{5}$/', $s) && (int)$s > 0) {            // serie de Excel (días desde 1899-12-30)
        return (new DateTime('1899-12-30'))->modify('+' . (int)$s . ' days')->format('Y-m-d');
    } else {
        return false;
    }
    return checkdate($mes, $dia, $y) && $y >= 1900 ? sprintf('%04d-%02d-%02d', $y, $mes, $dia) : false;
}

/** "25 mar 1990": para mostrar en el resultado cómo se interpretó cada fecha. */
function fechaLegible(?string $ymd): string
{
    if (!$ymd) return '—';
    static $m = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    [$y, $mes, $d] = array_map('intval', explode('-', $ymd));
    return "$d {$m[$mes - 1]} $y";
}

/** Busca un empleado existente que choque por CURP, RFC o número de empleado. */
function buscarDuplicado(mysqli $db, string $curp, string $rfc, string $numero): ?array
{
    $conds = []; $types = ''; $params = [];
    if ($curp !== '')   { $conds[] = 'curp = ?';            $types .= 's'; $params[] = $curp;   }
    if ($rfc !== '')    { $conds[] = 'rfc = ?';             $types .= 's'; $params[] = $rfc;    }
    if ($numero !== '') { $conds[] = 'numero_empleado = ?'; $types .= 's'; $params[] = $numero; }
    if (!$conds) return null;

    $stmt = $db->prepare('SELECT numero_empleado, curp, rfc FROM empleados WHERE ' . implode(' OR ', $conds) . ' LIMIT 1');
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->bind_result($exNumero, $exCurp, $exRfc);
    $found = null;
    if ($stmt->fetch()) {
        $campo = 'numero_empleado';
        if ($curp !== '' && $exCurp === $curp) $campo = 'curp';
        elseif ($rfc !== '' && $exRfc === $rfc) $campo = 'rfc';
        $found = ['campo' => $campo, 'numero_empleado' => $exNumero];
    }
    $stmt->close();
    return $found;
}

$db   = getAdminDB();
$csrf = generateCsrfToken();

/* ── Descarga de plantilla CSV ────────────────────────────── */
if (($_GET['plantilla'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="plantilla_empleados.csv"');
    echo "\xEF\xBB\xBF";
    echo implode(',', CSV_COLUMNAS) . "\n";
    $ejemplo = [
        '', 'Juan', 'Pérez', 'García',
        '1990-05-12', 'M', 'soltero', 'PEGJ900512HDFRRN09', 'PEGJ900512AB1', '12345678901',
        '5512345678', 'juan.perez@correo.com', 'juan.perez@sharclean.com.mx',
        'Calle Falsa 123', 'Centro', 'CDMX', 'Ciudad de México', '06000',
        '1', '1', 'Supervisor de limpieza',
        'indeterminado', '2024-01-15', '',
        '12000', 'quincenal', 'BBVA', '012345678901234567',
        'activo', '', '', '', 'Notas opcionales',
    ];
    echo implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $ejemplo)) . "\n";
    exit;
}

/* ── Catálogos para validar departamento_id / puesto_id ───── */
$deptIds    = array_map('intval', array_column(fetchAll($db->query('SELECT id FROM departamentos')), 'id'));
/* puesto_id => departamento_id (para auto-asignar depto si el CSV lo omite) */
$puestoMap  = [];
foreach (fetchAll($db->query('SELECT id, departamento_id FROM puestos')) as $p) {
    $puestoMap[(int)$p['id']] = (int)$p['departamento_id'];
}
$puestoIds = array_keys($puestoMap);

$resultado = null; // ['importados'=>int,'omitidos'=>[...],'errores'=>[...]] o ['error'=>string]

/* ── POST: procesar archivo ───────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'importar') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); exit('Token inválido.'); }
    $ordenFechas = array_key_exists($_POST['orden_fechas'] ?? '', ORDENES_FECHA) ? $_POST['orden_fechas'] : 'dmy';
    $fmtTxt      = $ordenFechas === 'mdy' ? 'MM/DD/AAAA' : 'DD/MM/AAAA';
    $muestras    = [];   // cómo se interpretaron las fechas, para revisarlas en el resultado

    if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
        $resultado = ['error' => 'No se pudo subir el archivo. Verifica que sea un CSV válido.'];
    } elseif (strtolower(pathinfo($_FILES['csv']['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $resultado = ['error' => 'El archivo debe tener extensión .csv'];
    } else {
        $handle = fopen($_FILES['csv']['tmp_name'], 'r');
        $first  = $handle ? fgets($handle) : false;

        if ($first === false) {
            $resultado = ['error' => 'El archivo está vacío.'];
        } else {
            $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
            $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
            $header = array_map(fn($c) => strtolower(trim($c)), str_getcsv($first, $delim));
            $idx    = array_flip($header);

            $faltantes = array_diff(CSV_REQUERIDAS, array_keys($idx));
            if ($faltantes) {
                $resultado = ['error' => 'Faltan columnas obligatorias en el encabezado: ' . implode(', ', $faltantes)];
            } else {
                $insStmt = $db->prepare(
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
                $b = array_fill(0, 33, null);
                $insStmt->bind_param(
                    'ssssssssssssssssssiissssdssssssis',
                    $b[0],$b[1],$b[2],$b[3],$b[4],$b[5],$b[6],$b[7],$b[8],$b[9],
                    $b[10],$b[11],$b[12],$b[13],$b[14],$b[15],$b[16],$b[17],
                    $b[18],$b[19],$b[20],$b[21],$b[22],$b[23],
                    $b[24],$b[25],$b[26],$b[27],
                    $b[28],$b[29],$b[30],$b[31],$b[32]
                );
                $updNumStmt = $db->prepare('UPDATE empleados SET numero_empleado=? WHERE id=?');

                $importados = 0;
                $omitidos   = [];
                $errores    = [];
                $numFila    = 1; // la fila 1 es el encabezado

                $get = function (array $cols, array $idx, string $col) {
                    return isset($idx[$col], $cols[$idx[$col]]) ? trim((string)$cols[$idx[$col]]) : '';
                };

                while (($cols = fgetcsv($handle, 0, $delim)) !== false) {
                    $numFila++;
                    if (count(array_filter($cols, fn($c) => trim((string)$c) !== '')) === 0) continue;

                    $rowErrors = [];

                    $nombre            = $get($cols, $idx, 'nombre');
                    $ap_pat            = $get($cols, $idx, 'apellido_paterno');
                    $fecha_ingreso_raw = $get($cols, $idx, 'fecha_ingreso');
                    $quien             = trim("$nombre $ap_pat") ?: "(fila $numFila)";

                    if ($nombre === '' || $ap_pat === '' || $fecha_ingreso_raw === '') {
                        $errores[] = "Fila $numFila — $quien: nombre, apellido_paterno y fecha_ingreso son obligatorios.";
                        continue;
                    }

                    $fecha_ingreso = parseFechaCsv($fecha_ingreso_raw, $ordenFechas);
                    if ($fecha_ingreso === false) {
                        $errores[] = "Fila $numFila — $quien: fecha_ingreso \"$fecha_ingreso_raw\" no es una fecha válida en formato $fmtTxt (o AAAA-MM-DD). Revisa el formato elegido al importar.";
                        continue;
                    }

                    $ap_mat = $get($cols, $idx, 'apellido_materno') ?: null;

                    $fecha_nacimiento = parseFechaCsv($get($cols, $idx, 'fecha_nacimiento'), $ordenFechas);
                    if ($fecha_nacimiento === false) { $rowErrors[] = "fecha_nacimiento no es válida en formato $fmtTxt (se dejó vacía)"; $fecha_nacimiento = null; }
                    elseif ($fecha_nacimiento === '') $fecha_nacimiento = null;

                    $sexo_raw = $get($cols, $idx, 'sexo');
                    $sexo = null;
                    if ($sexo_raw !== '') {
                        if (in_array($sexo_raw, SEXOS, true)) $sexo = $sexo_raw;
                        else $rowErrors[] = "sexo '$sexo_raw' inválido (use M, F o NB)";
                    }

                    $edo_civil_raw = strtolower($get($cols, $idx, 'estado_civil'));
                    $edo_civil = null;
                    if ($edo_civil_raw !== '') {
                        if (in_array($edo_civil_raw, ESTADOS_CIVILES, true)) $edo_civil = $edo_civil_raw;
                        else $rowErrors[] = "estado_civil '$edo_civil_raw' inválido";
                    }

                    $curp = strtoupper($get($cols, $idx, 'curp')) ?: null;
                    $rfc  = strtoupper($get($cols, $idx, 'rfc'))  ?: null;
                    $nss  = $get($cols, $idx, 'nss') ?: null;

                    $telefono   = $get($cols, $idx, 'telefono') ?: null;
                    $email      = $get($cols, $idx, 'email') ?: null;
                    $email_corp = $get($cols, $idx, 'email_corporativo') ?: null;

                    $direccion = $get($cols, $idx, 'direccion') ?: null;
                    $colonia   = $get($cols, $idx, 'colonia') ?: null;
                    $ciudad    = $get($cols, $idx, 'ciudad') ?: 'CDMX';
                    $edo_res   = $get($cols, $idx, 'estado_residencia') ?: null;
                    $cp        = $get($cols, $idx, 'cp') ?: null;

                    $dept_raw = $get($cols, $idx, 'departamento_id');
                    $dept_id  = null;
                    if ($dept_raw !== '') {
                        if (ctype_digit($dept_raw) && in_array((int)$dept_raw, $deptIds, true)) $dept_id = (int)$dept_raw;
                        else $rowErrors[] = "departamento_id '$dept_raw' no existe en el catálogo de departamentos";
                    }

                    $puesto_raw = $get($cols, $idx, 'puesto_id');
                    $puesto_id  = null;
                    if ($puesto_raw !== '') {
                        if (ctype_digit($puesto_raw) && in_array((int)$puesto_raw, $puestoIds, true)) $puesto_id = (int)$puesto_raw;
                        else $rowErrors[] = "puesto_id '$puesto_raw' no existe en el catálogo de puestos";
                    }

                    /* Si tiene puesto pero no departamento, resolverlo desde el catálogo */
                    if ($puesto_id !== null && $dept_id === null) {
                        $dept_id = $puestoMap[$puesto_id] ?? null;
                    }

                    $puesto_nom = $get($cols, $idx, 'puesto_nombre') ?: null;

                    $tipo_raw = $get($cols, $idx, 'tipo_contrato');
                    $tipo_contrato = 'indeterminado';
                    if ($tipo_raw !== '') {
                        if (in_array($tipo_raw, TIPOS_CONTRATO, true)) $tipo_contrato = $tipo_raw;
                        else $rowErrors[] = "tipo_contrato '$tipo_raw' inválido";
                    }

                    $fecha_fin = parseFechaCsv($get($cols, $idx, 'fecha_fin_contrato'), $ordenFechas);
                    if ($fecha_fin === false) { $rowErrors[] = "fecha_fin_contrato no es válida en formato $fmtTxt (se dejó vacía)"; $fecha_fin = null; }
                    elseif ($fecha_fin === '') $fecha_fin = null;

                    $sal_raw = str_replace([',', ' ', '$'], '', $get($cols, $idx, 'salario_base'));
                    $salario = null;
                    if ($sal_raw !== '') {
                        if (is_numeric($sal_raw)) $salario = (float)$sal_raw;
                        else $rowErrors[] = "salario_base '$sal_raw' no es numérico";
                    }

                    $periodo_raw = $get($cols, $idx, 'periodo_pago');
                    $periodo = 'quincenal';
                    if ($periodo_raw !== '') {
                        if (in_array($periodo_raw, PERIODOS_PAGO, true)) $periodo = $periodo_raw;
                        else $rowErrors[] = "periodo_pago '$periodo_raw' inválido";
                    }

                    $banco = $get($cols, $idx, 'banco') ?: null;
                    $clabe = $get($cols, $idx, 'clabe_bancaria') ?: null;

                    $estatus_raw = $get($cols, $idx, 'estatus');
                    $estatus = 'activo';
                    if ($estatus_raw !== '') {
                        if (in_array($estatus_raw, ESTATUSES, true)) $estatus = $estatus_raw;
                        else $rowErrors[] = "estatus '$estatus_raw' inválido";
                    }

                    $fecha_baja = parseFechaCsv($get($cols, $idx, 'fecha_baja'), $ordenFechas);
                    if ($fecha_baja === false) { $rowErrors[] = "fecha_baja no es válida en formato $fmtTxt (se dejó vacía)"; $fecha_baja = null; }
                    elseif ($fecha_baja === '') $fecha_baja = null;

                    $motivo_baja = $get($cols, $idx, 'motivo_baja') ?: null;

                    $aplicacion_raw = $get($cols, $idx, 'aplicacion_id');
                    $aplicacion_id  = ($aplicacion_raw !== '' && ctype_digit($aplicacion_raw)) ? (int)$aplicacion_raw : null;

                    $notas = $get($cols, $idx, 'notas') ?: null;

                    $numero_csv = $get($cols, $idx, 'numero_empleado');

                    if ($rowErrors) {
                        $errores[] = "Fila $numFila — $quien: " . implode('; ', $rowErrors);
                        continue;
                    }

                    $dup = buscarDuplicado($db, (string)$curp, (string)$rfc, $numero_csv);
                    if ($dup) {
                        $omitidos[] = "Fila $numFila — $quien: ya existe un empleado con el mismo {$dup['campo']} (registrado como {$dup['numero_empleado']}).";
                        continue;
                    }

                    if ($numero_csv !== '') {
                        $numero = $numero_csv;
                    } else {
                        $max    = (int)$db->query('SELECT COALESCE(MAX(id),0) FROM empleados')->fetch_row()[0];
                        $numero = 'EMP-' . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
                    }

                    $vals = [
                        $numero,$nombre,$ap_pat,$ap_mat,
                        $fecha_nacimiento,$sexo,$edo_civil,$curp,$rfc,$nss,
                        $telefono,$email,$email_corp,
                        $direccion,$colonia,$ciudad,$edo_res,$cp,
                        $dept_id,$puesto_id,$puesto_nom,
                        $tipo_contrato,$fecha_ingreso,$fecha_fin,
                        $salario,$periodo,$banco,$clabe,
                        $estatus,$fecha_baja,$motivo_baja,$aplicacion_id,$notas,
                    ];
                    foreach ($vals as $i => $val) $b[$i] = $val;

                    if ($insStmt->execute()) {
                        if ($numero_csv === '') {
                            $newId = $db->insert_id;
                            $final = 'EMP-' . str_pad((string)$newId, 4, '0', STR_PAD_LEFT);
                            $updNumStmt->bind_param('si', $final, $newId);
                            $updNumStmt->execute();
                        }
                        $importados++;
                        if (count($muestras) < 10) {
                            $muestras[] = "Fila $numFila — $quien: ingreso " . fechaLegible($fecha_ingreso)
                                        . ($fecha_nacimiento ? ', nacimiento ' . fechaLegible($fecha_nacimiento) : '');
                        }
                    } else {
                        $errores[] = "Fila $numFila — $quien: error al guardar (" . ($db->error ?: 'desconocido') . ").";
                    }
                }

                $resultado = ['importados' => $importados, 'omitidos' => $omitidos, 'errores' => $errores,
                              'muestras' => $muestras, 'formato' => ORDENES_FECHA[$ordenFechas]];
            }
        }
        if ($handle) fclose($handle);
    }
}

$db->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Importar empleados – Sharclean Admin</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f1f5f9;color:#1e293b;font-size:14px;}
a{color:inherit;text-decoration:none;}
.body{max-width:900px;margin:0 auto;padding:24px 16px;}
.card{background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.07);overflow:hidden;margin-bottom:20px;}
.card-header{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #f1f5f9;}
.card-header h2{font-size:15px;font-weight:700;color:#1e293b;}
.card-body{padding:20px;}
.btn-sm{padding:7px 14px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;border:none;white-space:nowrap;display:inline-block;}
.btn-primary{background:#0284C7;color:#fff;}
.btn-primary:hover{background:#0369a1;}
.btn-muted{background:#e2e8f0;color:#475569;}
.btn-muted:hover{background:#cbd5e1;}
.btn-outline{background:#fff;color:#0284C7;border:1.5px solid #bae6fd;}
.btn-outline:hover{background:#f0f9ff;}
.hint{font-size:13px;color:#64748b;line-height:1.6;margin-bottom:14px;}
.hint code{background:#f1f5f9;padding:1px 6px;border-radius:4px;font-size:12px;}
.fmt-fecha{display:inline-flex;gap:8px;align-items:center;font-size:13px;color:#334155;margin:0 12px 0 4px;}
.fmt-fecha select{padding:6px 8px;border:1.5px solid #e2e8f0;border-radius:6px;font:inherit;font-size:13px;}
.hint ul{margin:6px 0 6px 20px;}
input[type=file]{display:block;margin:14px 0;font-size:13px;}
.alert{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:13px;}
.alert-success{background:#dcfce7;color:#166534;border:1px solid #bbf7d0;}
.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}
.summary{display:flex;gap:14px;margin-bottom:16px;flex-wrap:wrap;}
.summary div{flex:1;min-width:140px;background:#f8fafc;border-radius:8px;padding:14px;text-align:center;}
.summary .num{font-size:26px;font-weight:800;}
.summary .lbl{font-size:12px;color:#64748b;margin-top:2px;}
.c-ok{color:#166534}.c-warn{color:#854d0e}.c-err{color:#991b1b}
.detail-list{font-size:12px;color:#475569;background:#f8fafc;border-radius:8px;padding:12px 16px;margin-top:8px;max-height:260px;overflow-y:auto;}
.detail-list li{margin-bottom:5px;}
.detail-title{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-top:14px;}
</style>
<link rel="stylesheet" href="assets/admin.css?v=3">
</head>
<body>

<?php $navTitle = '👥 Empleados'; $activeModule = 'empleados'; require __DIR__ . '/_nav.php'; ?>

<div class="body">

  <div class="card">
    <div class="card-header">
      <h2>📤 Importar empleados desde CSV</h2>
      <a href="empleados.php" class="btn-sm btn-muted">← Volver a Empleados</a>
    </div>
    <div class="card-body">
      <div class="hint">
        Sube un archivo CSV con un empleado por fila. La primera fila debe contener
        los nombres de columna exactamente como en la plantilla.
        <ul>
          <li>Columnas obligatorias: <code>nombre</code>, <code>apellido_paterno</code>, <code>fecha_ingreso</code>.</li>
          <li><strong>Fechas:</strong> elige abajo cómo vienen en tu archivo. Excel las guarda según el idioma de la computadora: en una PC en inglés suelen quedar como <code>MM/DD/AAAA</code>. Si tienes duda, abre el CSV con el Bloc de notas y fíjate en una fecha con día mayor a 12.</li>
          <li><code>numero_empleado</code> es opcional — si se deja vacío, se genera automáticamente (EMP-XXXX).</li>
          <li><code>departamento_id</code> y <code>puesto_id</code> deben ser los IDs numéricos de los catálogos de Departamentos y Puestos (no el nombre).</li>
          <li>Filas con CURP, RFC o número de empleado que ya existan en el sistema se omiten y se reportan al final — no se sobrescriben registros existentes.</li>
        </ul>
        <a href="?plantilla=1" class="btn-sm btn-outline">⬇ Descargar plantilla CSV</a>
      </div>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="action" value="importar">
        <input type="file" name="csv" accept=".csv,text/csv" required>
        <label class="fmt-fecha">Las fechas del archivo vienen como:
          <select name="orden_fechas">
            <?php foreach (ORDENES_FECHA as $k => $l): ?>
              <option value="<?= $k ?>" <?= ($_POST['orden_fechas'] ?? 'dmy') === $k ? 'selected' : '' ?>><?= h($l) ?></option>
            <?php endforeach ?>
          </select>
        </label>
        <button type="submit" class="btn-sm btn-primary">Importar archivo</button>
      </form>
    </div>
  </div>

  <?php if ($resultado !== null): ?>
  <div class="card">
    <div class="card-header"><h2>Resultado de la importación</h2></div>
    <div class="card-body">
      <?php if (isset($resultado['error'])): ?>
        <div class="alert alert-error">✗ <?= h($resultado['error']) ?></div>
      <?php else: ?>
        <?php if ($resultado['importados'] > 0): ?>
          <div class="alert alert-success">✓ Se importaron <?= $resultado['importados'] ?> empleado(s) correctamente.</div>
        <?php endif ?>

        <div class="summary">
          <div><div class="num c-ok"><?= $resultado['importados'] ?></div><div class="lbl">Importados</div></div>
          <div><div class="num c-warn"><?= count($resultado['omitidos']) ?></div><div class="lbl">Omitidos (duplicados)</div></div>
          <div><div class="num c-err"><?= count($resultado['errores']) ?></div><div class="lbl">Con error</div></div>
        </div>

        <?php if ($resultado['muestras']): ?>
          <div class="detail-title">Revisa que las fechas se leyeron bien (formato elegido: <?= h($resultado['formato']) ?>)</div>
          <ul class="detail-list">
            <?php foreach ($resultado['muestras'] as $msg): ?><li><?= h($msg) ?></li><?php endforeach ?>
          </ul>
          <p style="font-size:13px;color:#64748b;margin:6px 0 14px">Si salen al revés (día y mes cambiados), corrige esos empleados en su ficha o bórralos e impórtalos de nuevo con el otro formato.</p>
        <?php endif ?>

        <?php if ($resultado['omitidos']): ?>
          <div class="detail-title">Omitidos por duplicado</div>
          <ul class="detail-list">
            <?php foreach ($resultado['omitidos'] as $msg): ?><li><?= h($msg) ?></li><?php endforeach ?>
          </ul>
        <?php endif ?>

        <?php if ($resultado['errores']): ?>
          <div class="detail-title">Filas con error (no importadas)</div>
          <ul class="detail-list">
            <?php foreach ($resultado['errores'] as $msg): ?><li><?= h($msg) ?></li><?php endforeach ?>
          </ul>
        <?php endif ?>
      <?php endif ?>
    </div>
  </div>
  <?php endif ?>

</div>
</body>
</html>
