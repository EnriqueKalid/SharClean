<?php
/**
 * Sharclean Portal – Autocompletar Colonia/Ciudad/Estado durante el autorregistro.
 * Solo responde a quien ya se identificó en portal/registro.php (no es un servicio público).
 */
declare(strict_types=1);
require_once __DIR__ . '/_boot.php';
require_once __DIR__ . '/../db/cp_lookup.php';

header('Content-Type: application/json; charset=utf-8');
if (($_SESSION['reg']['step'] ?? '') !== 'datos') {
    http_response_code(403);
    echo json_encode(['ok' => false]);
    exit;
}
$r = buscarCodigoPostal(getDB(), (string)($_GET['cp'] ?? ''));
echo json_encode($r ? ['ok' => true] + $r : ['ok' => false]);
