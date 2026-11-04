<?php
/**
 * GET api/eventos.php[?lang=en]
 * Próximos eventos publicados, con las plazas que quedan libres.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

ejecutar(function () {
    $eventos = db()->query(
        "SELECT e.id, " . col('e.titulo') . " AS titulo, " . col('e.descripcion') . " AS descripcion, e.fecha, e.plazas, e.precio, e.imagen,
                COALESCE(SUM(i.plazas), 0) AS ocupadas
         FROM eventos e
         LEFT JOIN inscripciones_evento i ON i.evento_id = e.id
         WHERE e.publicado = 1 AND e.fecha >= NOW()
         GROUP BY e.id
         ORDER BY e.fecha
         LIMIT 6"
    )->fetchAll();

    responder(['eventos' => array_map(fn(array $e) => eventoPublico($e), $eventos)]);
});

/** Convierte una fila de la BD en lo que necesita la web. */
function eventoPublico(array $e): array
{
    $libres = max(0, (int) $e['plazas'] - (int) $e['ocupadas']);
    return [
        'id'          => (int) $e['id'],
        'titulo'      => $e['titulo'],
        'descripcion' => $e['descripcion'],
        'fecha'       => substr($e['fecha'], 0, 10),
        'hora'        => substr($e['fecha'], 11, 5),
        'plazas'      => (int) $e['plazas'],
        'libres'      => $libres,
        'precio'      => (float) $e['precio'],
        'imagen'      => $e['imagen'],
    ];
}
