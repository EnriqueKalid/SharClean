<?php
/**
 * Sharclean Admin – Autocompletar Colonia/Municipio/Estado a partir del C.P.
 * Usage: admin/cp_lookup.php?cp=56570
 * Llama a copomex.com del lado del servidor para no exponer el token en el navegador.
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

$token = $_ENV['COPOMEX_TOKEN'] ?? '';
if ($token === '') {
    echo json_encode(['ok' => false]);
    exit;
}

$url = 'https://api.copomex.com/query/info_cp/' . $cp . '?token=' . urlencode($token) . '&type=simplified';

$context = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
$raw     = @file_get_contents($url, false, $context);
$data    = $raw ? json_decode($raw, true) : null;

if (!$data || !empty($data['error']) || empty($data['response'])) {
    echo json_encode(['ok' => false]);
    exit;
}

$r = $data['response'];

echo json_encode([
    'ok'       => true,
    'colonias' => array_values((array)($r['asentamiento'] ?? [])),
    'municipio' => (string)($r['municipio'] ?? ''),
    'estado'    => (string)($r['estado'] ?? ''),
    'ciudad'    => (string)($r['ciudad'] ?? ''),
]);
