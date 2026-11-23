<?php
/**
 * Sharclean – Procesamiento seguro de imágenes subidas
 *
 * 1. Valida el contenido REAL del archivo (firma binaria), no la extensión ni el
 *    Content-Type que manda el navegador.
 * 2. Limita tamaño y dimensiones (evita "bombas" de descompresión).
 * 3. Si GD está disponible, re-codifica la imagen a JPEG: descarta EXIF (GPS, datos
 *    del dispositivo) y cualquier contenido incrustado (archivos "políglota"
 *    imagen+script), corrige la rotación y la reduce al tamaño útil.
 * 4. Guarda con nombre aleatorio no adivinable dentro de uploads/ (bloqueado por
 *    .htaccess); solo se sirve vía scripts protegidos.
 *
 * El navegador ya comprime antes de subir (admin/assets/img-compress.js); esto es
 * la garantía del lado del servidor.
 */

declare(strict_types=1);

/**
 * @param int  $maxSide lado mayor del resultado en px (o lado del cuadrado si $square)
 * @param bool $square  recorte cuadrado centrado
 * @return array{ok:bool, filename?:string, bytes?:int, error?:string}
 */
function procesarImagen(array $file, string $destDir, string $prefix, int $maxSide, bool $square = false, int $maxBytes = 15 * 1024 * 1024): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'No se recibió la imagen.'];
    }
    if ($file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'La imagen no debe superar ' . (int)($maxBytes / 1048576) . ' MB.'];
    }

    $info  = @getimagesize($file['tmp_name']);
    $tipos = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($tipos[$info[2]])) {
        return ['ok' => false, 'error' => 'El archivo no es una imagen JPG, PNG o WEBP válida.'];
    }
    [$w, $h] = $info;
    if ($w < 64 || $h < 64 || $w * $h > 40_000_000) {
        return ['ok' => false, 'error' => 'Dimensiones de imagen no válidas (mínimo 64×64 px).'];
    }

    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $base = $prefix . bin2hex(random_bytes(12));

    $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    $loader  = $loaders[$info[2]];

    if (function_exists('imagecreatetruecolor') && function_exists($loader)) {
        $src = @$loader($file['tmp_name']);
        if (!$src) return ['ok' => false, 'error' => 'No se pudo leer la imagen.'];

        // Fotos de celular sin pasar por el navegador: aplicar la rotación EXIF
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $rot = [3 => 180, 6 => -90, 8 => 90][(int)(@exif_read_data($file['tmp_name'])['Orientation'] ?? 1)] ?? 0;
            if ($rot !== 0 && ($r = imagerotate($src, $rot, 0))) {
                imagedestroy($src);
                $src = $r;
                [$w, $h] = [imagesx($src), imagesy($src)];
            }
        }

        if ($square) {
            $side = min($w, $h);
            [$sx, $sy, $sw, $sh] = [(int)(($w - $side) / 2), (int)(($h - $side) / 2), $side, $side];
            $nw = $nh = min($maxSide, $side);
        } else {
            [$sx, $sy, $sw, $sh] = [0, 0, $w, $h];
            $scale = min(1, $maxSide / max($w, $h));
            $nw = max(1, (int)round($w * $scale));
            $nh = max(1, (int)round($h * $scale));
        }

        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));   // fondo blanco para PNG transparentes
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $nw, $nh, $sw, $sh);

        $filename = $base . '.jpg';
        $ok = imagejpeg($dst, $destDir . '/' . $filename, 85);
        imagedestroy($src);
        imagedestroy($dst);
        return $ok
            ? ['ok' => true, 'filename' => $filename, 'bytes' => (int)filesize($destDir . '/' . $filename)]
            : ['ok' => false, 'error' => 'No se pudo guardar la imagen.'];
    }

    // Sin GD: se guarda el original ya validado, con extensión según su firma real
    if ($file['size'] > 3 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'La imagen no debe superar 3 MB.'];
    }
    $filename = $base . '.' . $tipos[$info[2]];
    return move_uploaded_file($file['tmp_name'], $destDir . '/' . $filename)
        ? ['ok' => true, 'filename' => $filename, 'bytes' => (int)$file['size']]
        : ['ok' => false, 'error' => 'No se pudo guardar la imagen.'];
}

/** Foto de perfil del usuario admin: cuadrada 320×320. */
function procesarFotoPerfil(array $file, string $destDir, string $prefix): array
{
    return procesarImagen($file, $destDir, $prefix, 320, true);
}

/**
 * Foto de referencia del empleado para la verificación facial del check-in.
 * 800 px de lado mayor: face-api recorta el rostro a 150×150, así que no se pierde
 * precisión y el portal descarga ~100 KB en lugar de varios MB.
 */
function procesarFotoReferencia(array $file, string $destDir): array
{
    return procesarImagen($file, $destDir, 'ref_', 800);
}

/**
 * Logo del sistema. Es PÚBLICO (se muestra en el login antes de iniciar sesión),
 * por eso va en images/brand/ y no en uploads/. Mismas validaciones que las fotos;
 * con GD se re-codifica conservando la transparencia y se limita a 600×200 px.
 *
 * @return array{ok:bool, filename?:string, error?:string}
 */
function procesarLogo(array $file, string $destDir): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'No se recibió el logo.'];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'El logo no debe superar 2 MB.'];
    }
    $info  = @getimagesize($file['tmp_name']);
    $tipos = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($tipos[$info[2]]) || $info[0] * $info[1] > 40_000_000) {
        return ['ok' => false, 'error' => 'Formato de logo no permitido. Use PNG, JPG o WEBP.'];
    }

    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    $filename = 'logo_' . bin2hex(random_bytes(6)) . '.png';
    $loaders  = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];

    if (function_exists('imagecreatetruecolor') && function_exists($loaders[$info[2]])) {
        $src = @$loaders[$info[2]]($file['tmp_name']);
        if (!$src) return ['ok' => false, 'error' => 'No se pudo leer el logo.'];
        [$w, $h] = $info;
        $scale = min(1, 600 / $w, 200 / $h);
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = imagepng($dst, $destDir . '/' . $filename, 9);
        imagedestroy($src);
        imagedestroy($dst);
        return $ok ? ['ok' => true, 'filename' => $filename] : ['ok' => false, 'error' => 'No se pudo guardar el logo.'];
    }

    $filename = 'logo_' . bin2hex(random_bytes(6)) . '.' . $tipos[$info[2]];
    return move_uploaded_file($file['tmp_name'], $destDir . '/' . $filename)
        ? ['ok' => true, 'filename' => $filename]
        : ['ok' => false, 'error' => 'No se pudo guardar el logo.'];
}
