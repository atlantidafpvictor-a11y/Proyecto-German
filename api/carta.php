<?php
/**
 * GET api/carta.php[?lang=en]
 * GET api/carta.php
 * Devuelve las categorías activas, los platos disponibles (con sus alérgenos)
 * y la lista de alérgenos.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

ejecutar(function () {
    $pdo = db();

    $categorias = $pdo->query(
        'SELECT id, ' . col('nombre') . ' AS nombre, ' . col('descripcion') . ' AS descripcion FROM categorias WHERE activa = 1 ORDER BY orden, id'
    )->fetchAll();

    $alergenos = $pdo->query('SELECT id, ' . col('nombre') . ' AS nombre FROM alergenos ORDER BY id')->fetchAll();

    // GROUP_CONCAT junta los id de alérgenos de cada plato en un texto "1,3,7"
    $platos = $pdo->query(
        'SELECT p.id, p.categoria_id, ' . col('p.nombre') . ' AS nombre, ' . col('p.descripcion') . ' AS descripcion, p.precio, p.unidad, p.imagen,
                p.vegetariano, p.vegano, p.sin_gluten, p.destacado,
                GROUP_CONCAT(pa.alergeno_id ORDER BY pa.alergeno_id) AS alergenos
         FROM platos p
         JOIN categorias c ON c.id = p.categoria_id AND c.activa = 1
         LEFT JOIN plato_alergeno pa ON pa.plato_id = p.id
         WHERE p.disponible = 1
         GROUP BY p.id
         ORDER BY p.categoria_id, p.destacado DESC, p.id'
    )->fetchAll();

    // Convertimos tipos para que JavaScript reciba números y booleanos de verdad
    $platos = array_map(function (array $p): array {
        $imagen = $p['imagen'] ?: 'img/portada.webp';
        if (str_starts_with($imagen, 'https://images.unsplash.com/') && !str_contains($imagen, '?')) {
            $imagen .= '?auto=format&fit=crop&w=900&q=80';
        }
        return [
            'id'           => (int) $p['id'],
            'categoria_id' => (int) $p['categoria_id'],
            'nombre'       => $p['nombre'],
            'descripcion'  => $p['descripcion'],
            'precio'       => (float) $p['precio'],
            'unidad'       => $p['unidad'],
            'imagen'       => $imagen,
            'vegetariano'  => (bool) $p['vegetariano'],
            'vegano'       => (bool) $p['vegano'],
            'sin_gluten'   => (bool) $p['sin_gluten'],
            'destacado'    => (bool) $p['destacado'],
            'alergenos'    => $p['alergenos'] ? array_map('intval', explode(',', $p['alergenos'])) : [],
        ];
    }, $platos);

    responder([
        'categorias' => array_map(fn($c) => ['id' => (int) $c['id'], 'nombre' => $c['nombre'], 'descripcion' => $c['descripcion']], $categorias),
        'alergenos'  => array_map(fn($a) => ['id' => (int) $a['id'], 'nombre' => $a['nombre']], $alergenos),
        'platos'     => $platos,
    ]);
});
