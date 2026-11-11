<?php
/**
 * Comprime una imagen subida para que ocupe el menor espacio posible.
 * - Limita el tamaño maximo (ancho/alto) a 800 px.
 * - La convierte a formato WebP (buen peso/calidad, soportado por navegadores modernos).
 * - Devuelve el nombre de archivo guardado, o null si la imagen no es valida.
 */
function comprimirImagen(string $rutaOrigen, string $destinoDir, int $maxLado = 800, int $calidad = 72): ?string
{
    if (!is_dir($destinoDir)) {
        mkdir($destinoDir, 0775, true);
    }

    $info = @getimagesize($rutaOrigen);
    if ($info === false) {
        return null;
    }

    $ancho = $info[0];
    $alto = $info[1];
    if ($ancho < 1 || $alto < 1) {
        return null;
    }

    $origen = match ($info['mime']) {
        'image/jpeg' => @imagecreatefromjpeg($rutaOrigen),
        'image/png'  => @imagecreatefrompng($rutaOrigen),
        'image/webp' => @imagecreatefromwebp($rutaOrigen),
        'image/gif'  => @imagecreatefromgif($rutaOrigen),
        default => false,
    };
    if ($origen === false) {
        return null;
    }

    $escala = min(1.0, $maxLado / max($ancho, $alto));
    $nuevoAncho = max(1, (int) round($ancho * $escala));
    $nuevoAlto = max(1, (int) round($alto * $escala));

    $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
    // conserva la transparencia de PNG y GIF
    imagealphablending($destino, false);
    imagesavealpha($destino, true);
    imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
    imagedestroy($origen);

    $nombre = bin2hex(random_bytes(16)) . '.webp';
    $rutaDestino = rtrim($destinoDir, '/\\') . DIRECTORY_SEPARATOR . $nombre;

    $ok = imagewebp($destino, $rutaDestino, $calidad);
    imagedestroy($destino);

    return $ok ? $nombre : null;
}

/** Borra la imagen de un producto si existe (ignora errores si ya no esta). */
function borrarImagen(?string $nombreArchivo, string $dir = __DIR__ . '/../assets/productos'): void
{
    if ($nombreArchivo === null || $nombreArchivo === '') {
        return;
    }
    $ruta = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . basename($nombreArchivo);
    if (is_file($ruta)) {
        @unlink($ruta);
    }
}

/** Ruta publica de la imagen de un producto, o cadena vacia si no tiene. */
function rutaImagen(?string $nombreArchivo): string
{
    return ($nombreArchivo !== null && $nombreArchivo !== '')
        ? 'assets/productos/' . rawurlencode($nombreArchivo)
        : '';
}
