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
require_once __DIR__ . '/../db/cp_lookup.php';

$r = buscarCodigoPostal(getAdminDB(), (string)($_GET['cp'] ?? ''));
echo json_encode($r ? ['ok' => true] + $r : ['ok' => false]);
