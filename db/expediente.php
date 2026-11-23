<?php
/**
 * Sharclean RRHH – Expediente digital del empleado
 *
 * Archivos en uploads/expedientes/{empleado_id}/doc_<aleatorio>.<ext> (bloqueado por
 * uploads/.htaccess); se sirven solo vía admin/empleado_documento.php.
 * Se aceptan PDF (validado por su firma binaria) e imágenes JPG/PNG/WEBP, que se
 * re-codifican a JPEG ≤ 2000 px (legible para INE/comprobantes y ~300 KB).
 */

declare(strict_types=1);

require_once __DIR__ . '/foto_upload.php';

const EXPEDIENTE_CATEGORIAS = ['Identidad y fiscales', 'Domicilio y bancarios', 'Laborales', 'Salud y antecedentes', 'Otros'];
const EXPEDIENTE_PDF_MAX    = 10 * 1024 * 1024;

function expedienteDisponible(mysqli $db): bool
{
    return (bool)$db->query('SELECT 1 FROM documento_tipos LIMIT 1');
}

function expedienteDir(int $empId): string
{
    return __DIR__ . '/../uploads/expedientes/' . $empId;
}

/** Tipos de documento ordenados por categoría (en el orden de EXPEDIENTE_CATEGORIAS) y orden. */
function expedienteTipos(mysqli $db, bool $soloActivos = true): array
{
    $cats  = implode(',', array_map(fn($c) => "'" . $db->real_escape_string($c) . "'", EXPEDIENTE_CATEGORIAS));
    $where = $soloActivos ? 'WHERE activo=1' : '';
    $res   = $db->query("SELECT * FROM documento_tipos $where
                         ORDER BY FIELD(categoria, $cats) = 0, FIELD(categoria, $cats), categoria, orden, nombre");
    $rows = [];
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}

function expedienteDocumentos(mysqli $db, int $empId): array
{
    $res = $db->query("SELECT d.*, u.nombre AS subido_por_nombre
                       FROM empleado_documentos d
                       LEFT JOIN admin_usuarios u ON u.id = d.subido_por
                       WHERE d.empleado_id = $empId
                       ORDER BY d.created_at");
    $rows = [];
    if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
    return $rows;
}

/**
 * Avance del expediente: documentos obligatorios (activos) con al menos un archivo.
 * @return array{total:int, por_empleado:array<int,int>}
 */
function expedienteAvance(mysqli $db): array
{
    $res   = $db->query('SELECT COUNT(*) FROM documento_tipos WHERE activo=1 AND obligatorio=1');
    $total = $res ? (int)$res->fetch_row()[0] : 0;
    $por   = [];
    $res   = $db->query('SELECT d.empleado_id, COUNT(DISTINCT d.tipo_id) AS n
                         FROM empleado_documentos d
                         JOIN documento_tipos t ON t.id = d.tipo_id AND t.activo = 1 AND t.obligatorio = 1
                         GROUP BY d.empleado_id');
    if ($res) while ($r = $res->fetch_assoc()) $por[(int)$r['empleado_id']] = (int)$r['n'];
    return ['total' => $total, 'por_empleado' => $por];
}

/**
 * Valida y guarda un archivo del expediente.
 * @return array{ok:bool, error?:string}
 */
function guardarDocumentoEmpleado(mysqli $db, int $empId, ?int $tipoId, ?string $nombre, array $file, int $userId): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $tooBig = in_array($file['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
        return ['ok' => false, 'error' => $tooBig ? 'El archivo excede el tamaño máximo del servidor.' : 'No se recibió el archivo.'];
    }

    $original = trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', basename((string)$file['name'])));
    $original = function_exists('mb_substr') ? mb_substr($original, 0, 200) : substr($original, 0, 200);
    $dir      = expedienteDir($empId);
    $esPdf    = (string)@file_get_contents($file['tmp_name'], false, null, 0, 5) === '%PDF-';

    if ($esPdf) {
        if ($file['size'] > EXPEDIENTE_PDF_MAX) {
            return ['ok' => false, 'error' => "«{$original}»: el PDF no debe superar 10 MB."];
        }
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $archivo = 'doc_' . bin2hex(random_bytes(12)) . '.pdf';
        if (!move_uploaded_file($file['tmp_name'], "$dir/$archivo")) {
            return ['ok' => false, 'error' => "«{$original}»: no se pudo guardar."];
        }
        $mime  = 'application/pdf';
        $bytes = (int)$file['size'];
    } else {
        $r = procesarImagen($file, $dir, 'doc_', 2000);
        if (!$r['ok']) {
            return ['ok' => false, 'error' => "«{$original}»: " . ($r['error'] === 'El archivo no es una imagen JPG, PNG o WEBP válida.'
                ? 'formato no permitido. Use PDF, JPG, PNG o WEBP.' : $r['error'])];
        }
        $archivo = $r['filename'];
        $mime    = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($archivo, PATHINFO_EXTENSION)];
        $bytes   = (int)$r['bytes'];
    }

    $tipoSql = $tipoId ? (string)$tipoId : 'NULL';
    $nomSql  = ($nombre !== null && $nombre !== '') ? "'" . $db->real_escape_string(substr($nombre, 0, 150)) . "'" : 'NULL';
    $userSql = $userId > 0 ? (string)$userId : 'NULL';
    $ok = $db->query(sprintf(
        "INSERT INTO empleado_documentos (empleado_id, tipo_id, nombre, archivo, nombre_original, mime, bytes, subido_por)
         VALUES (%d, %s, %s, '%s', '%s', '%s', %d, %s)",
        $empId, $tipoSql, $nomSql, $db->real_escape_string($archivo), $db->real_escape_string($original),
        $db->real_escape_string($mime), $bytes, $userSql
    ));
    if (!$ok) {
        @unlink("$dir/$archivo");
        return ['ok' => false, 'error' => "«{$original}»: no se pudo registrar en la base de datos."];
    }
    return ['ok' => true];
}

function eliminarDocumentoEmpleado(mysqli $db, int $docId, int $empId): bool
{
    $res = $db->query("SELECT archivo FROM empleado_documentos WHERE id=$docId AND empleado_id=$empId LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    if (!$row) return false;
    $path = expedienteDir($empId) . '/' . basename($row['archivo']);
    if (is_file($path)) unlink($path);
    return (bool)$db->query("DELETE FROM empleado_documentos WHERE id=$docId AND empleado_id=$empId");
}

/** Borra todos los archivos y registros del expediente (al eliminar al empleado). */
function eliminarExpedienteCompleto(mysqli $db, int $empId): void
{
    $dir = expedienteDir($empId);
    if (is_dir($dir)) {
        foreach (glob("$dir/doc_*") ?: [] as $f) unlink($f);
        @rmdir($dir);
    }
    $db->query("DELETE FROM empleado_documentos WHERE empleado_id=$empId");
}
