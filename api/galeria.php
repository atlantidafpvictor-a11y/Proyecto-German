<?php
/**
 * GET api/galeria.php
 * Fotos visibles de la galería, en el orden elegido en el panel.
 * Para cada foto se dan dos tamaños: "mini" para el mosaico y "grande" para el visor.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

ejecutar(function () {
    try {
        $fotos = db()->query(
            'SELECT id, titulo, imagen, categoria FROM galeria WHERE visible = 1 ORDER BY orden, id'
        )->fetchAll();
    } catch (PDOException $e) {
        // 42S02 = la tabla no existe: falta ejecutar la migración 001_galeria.sql
        if ($e->getCode() === '42S02') {
            responder(['fotos' => [], 'aviso' => 'Falta la tabla galeria: ejecuta database/migraciones/001_galeria.sql']);
        }
        throw $e;
    }

    responder(['fotos' => array_map(fn(array $f) => [
        'id'        => (int) $f['id'],
        'titulo'    => $f['titulo'],
        'categoria' => $f['categoria'],
        'mini'      => tamano($f['imagen'], 800),
        'grande'    => tamano($f['imagen'], 1800),
    ], $fotos)]);
});

/** Las fotos de Unsplash se piden ya recortadas al tamaño justo; las subidas se usan tal cual. */
function tamano(string $imagen, int $ancho): string
{
    if (str_starts_with($imagen, 'https://images.unsplash.com/') && !str_contains($imagen, '?')) {
        return $imagen . "?auto=format&fit=crop&w=$ancho&q=80";
    }
    return $imagen;
}
