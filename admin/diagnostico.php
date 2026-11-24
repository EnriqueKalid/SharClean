<?php
/**
 * Sharclean – Diagnóstico TEMPORAL de la página Empleados.
 * Solo para usuarios del panel con permiso de Empleados.
 * BORRAR este archivo del servidor en cuanto se resuelva el error.
 *
 *  ?            → versiones, archivos que no coinciden con el repositorio y migraciones
 *  ?probar=1    → carga empleados.php mostrando el error real en pantalla
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
requirePermiso('empleados');

/* ── Modo prueba: ejecutar empleados.php y mostrar el error fatal ── */
if (isset($_GET['probar'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            echo '<pre style="background:#fee2e2;color:#991b1b;padding:16px;margin:16px;border-radius:8px;white-space:pre-wrap;font-size:14px">'
               . 'ERROR FATAL: ' . htmlspecialchars($e['message'] . "\nArchivo: " . $e['file'] . ':' . $e['line'], ENT_QUOTES, 'UTF-8')
               . '</pre>';
        }
    });
    unset($_GET['probar']);
    try {
        require __DIR__ . '/empleados.php';
    } catch (Throwable $t) {
        echo '<pre style="background:#fee2e2;color:#991b1b;padding:16px;margin:16px;border-radius:8px;white-space:pre-wrap;font-size:14px">'
           . htmlspecialchars(get_class($t) . ': ' . $t->getMessage() . "\nArchivo: " . $t->getFile() . ':' . $t->getLine()
               . "\n\n" . $t->getTraceAsString(), ENT_QUOTES, 'UTF-8')
           . '</pre>';
    }
    exit;
}

/* ── Modo resumen ── */
$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$raiz = dirname(__DIR__);

// Huella de cada archivo en el repositorio (commit ebb5fd6), sin distinguir saltos de línea Windows/Linux
$esperado = [
    'admin/empleados.php'    => 'c5bc0c6a547b8e6f810418ac262190ae',
    'admin/auth.php'         => '8ef816a4bcc4910c0813ccdf46f9ac71',
    'admin/_nav.php'         => 'a8ad2cb13d3a9ec86c377fe277f9eaf7',
    'admin/assets/admin.css' => '60aa1f9e04d6f801316e50607c0cb2df',
    'db/registro.php'        => '8d370690088af630d68b5ceeec74d9f8',
    'db/mailer.php'          => 'f6cc5a98f05cddd92ef7e044dba79510',
    'db/expediente.php'      => '317dbbb55b46070b5b317e9b2ad10e3c',
    'db/foto_upload.php'     => 'b4e937dcbea280f6a06c03c969d6164b',
    'db/csrf.php'            => '315bb25053135314bf2944e8ac143346',
    'db/security.php'        => 'a19f435ffee1c44b443dd12b5fb51d34',
    'db/app_config.php'      => '9b9769587f44aa82a1314395c1f911b7',
    'db/config.php'          => 'e7014d42985cb2e7398b9dea29cc2bea',
];

$db = getAdminDB();
$col = function (string $tabla, string $c) use ($db): bool {
    $r = $db->query("SHOW COLUMNS FROM `$tabla` LIKE '" . $db->real_escape_string($c) . "'");
    return $r && $r->num_rows > 0;
};
$tabla = function (string $t) use ($db): bool {
    $r = $db->query("SHOW TABLES LIKE '" . $db->real_escape_string($t) . "'");
    return $r && $r->num_rows > 0;
};
$checks = [
    'migrate_checkin_v1 (empleados.portal_pin_hash)'        => $col('empleados', 'portal_pin_hash'),
    'migrate_registro_v1 (empleados.registro_estado)'       => $col('empleados', 'registro_estado'),
    'migrate_registro_v1 (tabla intentos_acceso)'           => $tabla('intentos_acceso'),
    'migrate_invitaciones_v1 (empleados.invitacion_fecha)'  => $col('empleados', 'invitacion_fecha'),
    'migrate_expediente_v1 (tabla documento_tipos)'         => $tabla('documento_tipos'),
    'migrate_codigos_postales_v1 (tabla codigos_postales)'  => $tabla('codigos_postales'),
];
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Diagnóstico</title>
<style>
body{font-family:system-ui,sans-serif;background:#f8fafc;color:#1e293b;margin:0;padding:24px}
.wrap{max-width:860px;margin:auto}
table{width:100%;border-collapse:collapse;background:#fff;margin:12px 0 28px;border-radius:10px;overflow:hidden}
td,th{padding:9px 12px;border-bottom:1px solid #e2e8f0;text-align:left;font-size:14px}
th{background:#f1f5f9}.ok{color:#15803d;font-weight:700}.bad{color:#b91c1c;font-weight:700}
.btn{display:inline-block;background:#0284c7;color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none;font-weight:700}
.warn{background:#fffbeb;border:1px solid #fde68a;padding:12px 16px;border-radius:10px;color:#92400e}
</style></head><body><div class="wrap">
<h1>Diagnóstico de Empleados</h1>
<p class="warn">Página temporal. Bórrala del servidor (<code>admin/diagnostico.php</code>) cuando se resuelva el error.</p>

<p><strong>PHP:</strong> <?= $e(PHP_VERSION) ?> · <strong>MySQL/MariaDB:</strong> <?= $e($db->server_info) ?></p>

<h2>Archivos</h2>
<table><tr><th>Archivo</th><th>Estado</th></tr>
<?php foreach ($esperado as $f => $md5):
    $ruta = "$raiz/$f";
    if (!is_file($ruta))                                            { $st = '<span class="bad">NO EXISTE — súbelo</span>'; }
    elseif (md5(str_replace("\r", '', (string)file_get_contents($ruta))) === $md5) { $st = '<span class="ok">Igual al repositorio</span>'; }
    else { $st = '<span class="bad">DIFERENTE — sube la versión nueva</span> <small>(' . $e(date('d/m/Y H:i', (int)filemtime($ruta))) . ')</small>'; }
?>
  <tr><td><code><?= $e($f) ?></code></td><td><?= $st ?></td></tr>
<?php endforeach ?>
</table>

<h2>Migraciones</h2>
<table><tr><th>Migración</th><th>Estado</th></tr>
<?php foreach ($checks as $n => $ok): ?>
  <tr><td><?= $e($n) ?></td><td><?= $ok ? '<span class="ok">Ejecutada</span>' : '<span class="bad">Pendiente</span>' ?></td></tr>
<?php endforeach ?>
</table>

<h2>Prueba</h2>
<p>Carga la página de Empleados mostrando el error real en lugar del "Error 500":</p>
<p><a class="btn" href="diagnostico.php?probar=1">Probar Empleados</a></p>
</div></body></html>
