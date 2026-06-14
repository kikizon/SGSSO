<?php
/**
 * image_helper.php — Compresión y redimensionado de imágenes (GD).
 *
 * Reduce el peso de las fotos subidas (sobre todo desde celular, que llegan a
 * 3-8 MB) redimensionando el lado mayor a un máximo y recomprimiendo, sin
 * perder utilidad visual. Opera EN EL LUGAR: sobrescribe el archivo recibido.
 *
 * Uso típico tras mover el archivo subido a su destino final:
 *     comprimir_imagen(UPLOAD_DIR . $nombre);
 *
 * Soporta JPEG, PNG, WebP y GIF. Si GD no está disponible o el archivo no es
 * una imagen válida, no falla la subida: simplemente deja el archivo intacto.
 */

if (!function_exists('comprimir_imagen')) {

    /**
     * @param string $ruta    Ruta absoluta del archivo en disco.
     * @param int    $maxLado Lado máximo en px para el lado mayor (default 1200).
     * @param int    $calidad Calidad JPEG/WebP 0-100 (default 82).
     * @return bool  true si se procesó o no hacía falta; false si hubo error.
     */
    function comprimir_imagen($ruta, $maxLado = 1200, $calidad = 82)
    {
        if (!is_file($ruta) || !function_exists('imagecreatetruecolor')) {
            return false;
        }
        $info = @getimagesize($ruta);
        if ($info === false) {
            return false;
        }
        $w = $info[0];
        $h = $info[1];
        $mime = $info['mime'] ?? '';

        switch ($mime) {
            case 'image/jpeg':
                $src = @imagecreatefromjpeg($ruta);
                break;
            case 'image/png':
                $src = @imagecreatefrompng($ruta);
                break;
            case 'image/webp':
                $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($ruta) : null;
                break;
            case 'image/gif':
                $src = @imagecreatefromgif($ruta);
                break;
            default:
                return false;
        }
        if (!$src) {
            return false;
        }

        // Corrige orientación según EXIF (fotos de celular rotadas)
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($ruta);
            if (!empty($exif['Orientation'])) {
                if ($exif['Orientation'] == 3)      $src = imagerotate($src, 180, 0);
                elseif ($exif['Orientation'] == 6)  $src = imagerotate($src, -90, 0);
                elseif ($exif['Orientation'] == 8)  $src = imagerotate($src, 90, 0);
                $w = imagesx($src);
                $h = imagesy($src);
            }
        }

        // Escala solo si excede el lado máximo
        $escala = min(1, $maxLado / max($w, $h));
        if ($escala < 1) {
            $nw = max(1, (int)round($w * $escala));
            $nh = max(1, (int)round($h * $escala));
            $dst = imagecreatetruecolor($nw, $nh);
            // Preserva transparencia en formatos que la soportan
            if (in_array($mime, ['image/png', 'image/webp', 'image/gif'], true)) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
            }
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
        }

        // Reescribe con compresión
        $ok = false;
        switch ($mime) {
            case 'image/jpeg':
                $ok = imagejpeg($src, $ruta, $calidad);
                break;
            case 'image/webp':
                $ok = function_exists('imagewebp') ? imagewebp($src, $ruta, $calidad) : imagejpeg($src, $ruta, $calidad);
                break;
            case 'image/png':
                $ok = imagepng($src, $ruta, 6); // 0 (sin compresión) a 9
                break;
            case 'image/gif':
                $ok = imagegif($src, $ruta);
                break;
        }
        imagedestroy($src);
        return $ok;
    }
}