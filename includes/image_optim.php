<?php
/**
 * Optimización de imágenes subidas (redimensiona + recomprime en el lugar).
 * Seguro: si falta GD o el archivo no es imagen soportada, no hace nada y
 * deja el original intacto. No toca PDFs.
 *
 * Uso: optimizar_imagen('/ruta/al/archivo.jpg');
 *      optimizar_imagen($ruta, 1600, 75);
 */

if (!function_exists('optimizar_imagen')) {
    /**
     * @param string $ruta    Ruta absoluta del archivo (se sobrescribe).
     * @param int    $maxDim  Lado máximo (px). Si la imagen es mayor, se reduce.
     * @param int    $calidad Calidad JPEG/WebP (1-100).
     * @return bool  true si optimizó; false si no pudo (deja el original).
     */
    function optimizar_imagen(string $ruta, int $maxDim = 1600, int $calidad = 75): bool {
        if (!is_file($ruta) || !function_exists('imagecreatetruecolor')) return false;

        $info = @getimagesize($ruta);
        if ($info === false) return false; // no es imagen
        [$ancho, $alto] = $info;
        $tipo = $info[2]; // IMAGETYPE_*

        switch ($tipo) {
            case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($ruta); break;
            case IMAGETYPE_PNG:  $src = @imagecreatefrompng($ruta); break;
            case IMAGETYPE_WEBP: $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($ruta) : false; break;
            case IMAGETYPE_GIF:  $src = @imagecreatefromgif($ruta); break;
            default: return false; // tipo no soportado (p. ej. PDF)
        }
        if (!$src) return false;

        // Corrige orientación EXIF en JPEG si hay datos
        if ($tipo === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($ruta);
            if (!empty($exif['Orientation'])) {
                switch ((int)$exif['Orientation']) {
                    case 3: $src = imagerotate($src, 180, 0); break;
                    case 6: $src = imagerotate($src, -90, 0); break;
                    case 8: $src = imagerotate($src, 90, 0); break;
                }
                $ancho = imagesx($src); $alto = imagesy($src);
            }
        }

        // Escala si excede el máximo
        $escala = min(1, $maxDim / max($ancho, $alto));
        $nAncho = max(1, (int)round($ancho * $escala));
        $nAlto  = max(1, (int)round($alto * $escala));

        $dst = imagecreatetruecolor($nAncho, $nAlto);
        if ($tipo === IMAGETYPE_PNG || $tipo === IMAGETYPE_WEBP || $tipo === IMAGETYPE_GIF) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparente = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $nAncho, $nAlto, $transparente);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nAncho, $nAlto, $ancho, $alto);

        $ok = false;
        switch ($tipo) {
            case IMAGETYPE_JPEG: $ok = imagejpeg($dst, $ruta, $calidad); break;
            case IMAGETYPE_PNG:  $ok = imagepng($dst, $ruta, 8); break;
            case IMAGETYPE_WEBP: $ok = function_exists('imagewebp') ? imagewebp($dst, $ruta, $calidad) : false; break;
            case IMAGETYPE_GIF:  $ok = imagegif($dst, $ruta); break;
        }

        imagedestroy($src);
        imagedestroy($dst);
        return (bool)$ok;
    }
}
