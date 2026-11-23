<?php
/**
 * Sharclean – Búsqueda en el catálogo local de códigos postales (SEPOMEX)
 * Usado por admin/cp_lookup.php (alta de empleados) y portal/cp_lookup.php (autorregistro).
 */

declare(strict_types=1);

/**
 * @return array{colonias:string[], municipio:string, estado:string, ciudad:string}|null
 */
function buscarCodigoPostal(mysqli $db, string $cp): ?array
{
    if (!preg_match('/^\d{5}$/', $cp)) return null;
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
    if ($first === null) return null;
    return [
        'colonias'  => array_values(array_unique($colonias)),
        'municipio' => $first['municipio'],
        'estado'    => $first['estado'],
        'ciudad'    => $first['ciudad'],
    ];
}
