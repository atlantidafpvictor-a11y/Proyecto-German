<?php
/**
 * Subida segura de fotos, compartida por la galería y la carta.
 *
 * Nunca hay que fiarse del archivo que llega:
 *  1. Tamaño máximo de 6 MB.
 *  2. getimagesize() comprueba que el contenido es de verdad una imagen
 *     JPG, PNG o WEBP (la extensión del nombre se puede falsear).
 *  3. Se guarda con un nombre aleatorio y la extensión la ponemos nosotros
 *     según el tipo real: nadie puede subir "foto.php".
 *  4. La carpeta uploads/ tiene un .htaccess que impide ejecutar código.
 */
declare(strict_types=1);

const SUBIDA_MAX_BYTES = 6 * 1024 * 1024;
const SUBIDA_TIPOS = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

/** ¿Han elegido un archivo en el campo $campo del formulario? */
function hayArchivo(string $campo): bool
{
    return isset($_FILES[$campo]) && $_FILES[$campo]['error'] !== UPLOAD_ERR_NO_FILE;
}

/**
 * Valida y guarda la foto subida en uploads/$subcarpeta.
 * Devuelve la ruta relativa (p. ej. "uploads/platos/3fa1….jpg").
 * Si algo falla lanza RuntimeException con un mensaje para mostrar al usuario.
 */
function guardarFoto(array $archivo, string $subcarpeta): string
{
    $errores = [
        UPLOAD_ERR_INI_SIZE  => 'La foto es demasiado grande para el servidor.',
        UPLOAD_ERR_FORM_SIZE => 'La foto es demasiado grande. El máximo son 6 MB.',
        UPLOAD_ERR_PARTIAL   => 'La foto no se ha subido entera. Inténtalo otra vez.',
    ];
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException($errores[$archivo['error']] ?? 'No se ha podido subir la foto.');
    }
    if (!is_uploaded_file($archivo['tmp_name']) || $archivo['size'] > SUBIDA_MAX_BYTES) {
        throw new RuntimeException('La foto es demasiado grande. El máximo son 6 MB.');
    }

    $info = @getimagesize($archivo['tmp_name']);
    if (!$info || !isset(SUBIDA_TIPOS[$info[2]])) {
        throw new RuntimeException('Ese archivo no es una imagen JPG, PNG o WEBP.');
    }

    $relativa = 'uploads/' . $subcarpeta;
    $disco = dirname(__DIR__, 2) . '/' . $relativa;
    if (!is_dir($disco)) @mkdir($disco, 0775, true);
    if (!is_dir($disco) || !is_writable($disco)) {
        throw new RuntimeException("La carpeta $relativa no existe o no tiene permiso de escritura. En Ubuntu: sudo chown -R www-data:www-data /var/www/sabor-mediterraneo/uploads");
    }

    $nombre = bin2hex(random_bytes(12)) . '.' . SUBIDA_TIPOS[$info[2]];
    if (!move_uploaded_file($archivo['tmp_name'], "$disco/$nombre")) {
        throw new RuntimeException('No se ha podido guardar la foto en el servidor.');
    }
    return "$relativa/$nombre";
}

/** Borra del disco una foto subida desde el panel. Las de img/ (del proyecto) nunca se tocan. */
function borrarSubida(?string $imagen): void
{
    if ($imagen && preg_match('#^uploads/(galeria|platos)/[a-f0-9]{24}\.(jpg|png|webp)$#', $imagen)) {
        $ruta = dirname(__DIR__, 2) . '/' . $imagen;
        if (is_file($ruta)) unlink($ruta);
    }
}

/** ¿Es una ruta de imagen válida? Una dirección https o una ruta del proyecto (img/… o uploads/…). */
function imagenValida(string $imagen): bool
{
    if (str_starts_with($imagen, 'https://')) return (bool) filter_var($imagen, FILTER_VALIDATE_URL);
    return (bool) preg_match('#^(img|uploads)/[a-z0-9_\-/]+\.(jpe?g|png|webp)$#i', $imagen) && !str_contains($imagen, '..');
}

/** Dirección para ver la foto desde el panel (las rutas locales están un nivel por encima de admin/). */
function miniatura(?string $imagen, int $ancho = 480): string
{
    if (!$imagen) return '../img/portada.webp';
    if (str_starts_with($imagen, 'https://images.unsplash.com/') && !str_contains($imagen, '?')) {
        return $imagen . "?auto=format&fit=crop&w=$ancho&q=70";
    }
    return str_starts_with($imagen, 'http') ? $imagen : '../' . $imagen;
}
