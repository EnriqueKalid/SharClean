<?php
/**
 * Sharclean – Importador del Catálogo Nacional de Códigos Postales (SEPOMEX)
 *
 * Archivo fuente: https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/CodigoPostal_Exportar.aspx
 * (Todos los estados, formato TXT). Se acepta el .zip tal cual se descarga o el .txt extraído.
 * Formato: Latin-1, separado por '|', una línea de aviso legal y luego el encabezado
 *   d_codigo|d_asenta|d_tipo_asenta|D_mnpio|d_estado|d_ciudad|...
 *
 * Carga en una tabla temporal y al final la intercambia con RENAME TABLE (atómico),
 * así el autollenado sigue funcionando mientras dura la importación.
 */

declare(strict_types=1);

/**
 * @return array{ok:bool, filas?:int, error?:string}
 */
function importarCatalogoCP(mysqli $db, string $path): array
{
    @set_time_limit(300);

    // ── Abrir el archivo (zip o txt) ────────────────────────────
    $head = (string)@file_get_contents($path, false, null, 0, 2);
    if ($head === 'PK') {
        if (!class_exists('ZipArchive')) {
            return ['ok' => false, 'error' => 'El servidor no soporta archivos .zip. Extraiga el CPdescarga.txt y súbalo directamente.'];
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true || $zip->numFiles < 1) {
            return ['ok' => false, 'error' => 'No se pudo abrir el archivo .zip.'];
        }
        $fh = $zip->getStream($zip->getNameIndex(0));
    } else {
        $fh = fopen($path, 'rb');
    }
    if (!$fh) return ['ok' => false, 'error' => 'No se pudo leer el archivo.'];

    // ── Saltar el aviso legal hasta el encabezado ───────────────
    $headerFound = false;
    while (($line = fgets($fh)) !== false) {
        if (str_starts_with($line, 'd_codigo|')) { $headerFound = true; break; }
    }
    if (!$headerFound) {
        fclose($fh);
        return ['ok' => false, 'error' => 'El archivo no tiene el formato del catálogo de SEPOMEX (no se encontró el encabezado d_codigo|...).'];
    }

    // ── Tabla temporal ──────────────────────────────────────────
    $db->query('DROP TABLE IF EXISTS codigos_postales_tmp');
    if (!$db->query('CREATE TABLE codigos_postales_tmp LIKE codigos_postales')) {
        fclose($fh);
        return ['ok' => false, 'error' => 'No se pudo crear la tabla temporal. ¿Ya se ejecutó migrate_codigos_postales_v1.sql?'];
    }

    $toUtf8 = function_exists('mb_convert_encoding')
        ? fn(string $s): string => mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1')
        : fn(string $s): string => (string)iconv('ISO-8859-1', 'UTF-8', $s);

    $batch = [];
    $total = 0;
    $flush = function () use ($db, &$batch): bool {
        if (!$batch) return true;
        $ok = $db->query('INSERT INTO codigos_postales_tmp
            (cp, asentamiento, tipo_asentamiento, municipio, estado, ciudad) VALUES ' . implode(',', $batch));
        $batch = [];
        return (bool)$ok;
    };

    while (($line = fgets($fh)) !== false) {
        $cols = explode('|', rtrim($line, "\r\n"));
        if (count($cols) < 6 || !preg_match('/^\d{5}$/', $cols[0])) continue;

        $vals = array_map(
            fn($c) => "'" . $db->real_escape_string($toUtf8(trim($c))) . "'",
            array_slice($cols, 0, 6)
        );
        $batch[] = '(' . implode(',', $vals) . ')';
        $total++;

        if (count($batch) >= 1000 && !$flush()) {
            fclose($fh);
            $db->query('DROP TABLE IF EXISTS codigos_postales_tmp');
            return ['ok' => false, 'error' => 'Error al insertar: ' . $db->error];
        }
    }
    fclose($fh);

    if (!$flush() || $total === 0) {
        $db->query('DROP TABLE IF EXISTS codigos_postales_tmp');
        return ['ok' => false, 'error' => $total === 0 ? 'El archivo no contiene registros.' : 'Error al insertar: ' . $db->error];
    }

    // ── Intercambio atómico ─────────────────────────────────────
    $db->query('DROP TABLE IF EXISTS codigos_postales_old');
    if (!$db->query('RENAME TABLE codigos_postales TO codigos_postales_old, codigos_postales_tmp TO codigos_postales')) {
        $db->query('DROP TABLE IF EXISTS codigos_postales_tmp');
        return ['ok' => false, 'error' => 'No se pudo reemplazar el catálogo: ' . $db->error];
    }
    $db->query('DROP TABLE codigos_postales_old');

    return ['ok' => true, 'filas' => $total];
}
