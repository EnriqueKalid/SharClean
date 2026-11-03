<?php
/**
 * Sharclean Admin – Autocompletar Colonia/Municipio/Estado a partir del C.P.
 * Usage: admin/cp_lookup.php?cp=56570
 * Consulta la tabla local `codigos_postales` (catálogo SEPOMEX importado desde
 * Configuración → Códigos postales). Sin llamadas a servicios de terceros.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/auth.php';
requirePermiso('empleados');

$cp = $_GET['cp'] ?? '';

if (!preg_match('/^\d{5}$/', $cp)) {
    echo json_encode(['ok' => false]);
    exit;
}

$db  = getAdminDB();
$res = $db->query("SELECT asentamiento, municipio, estado, ciudad
                   FROM codigos_postales WHERE cp='$cp' ORDER BY asentamiento");

$colonias = [];
$first    = null;
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $first ??= $row;
        $colonias[] = $row['asentamiento'];
    }
}

if ($first === null) {
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode([
    'ok'        => true,
    'colonias'  => array_values(array_unique($colonias)),
    'municipio' => $first['municipio'],
    'estado'    => $first['estado'],
    'ciudad'    => $first['ciudad'],
]);
