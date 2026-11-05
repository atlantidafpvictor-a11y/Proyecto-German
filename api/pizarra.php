<?php
/**
 * GET api/pizarra.php[?lang=en]
 * Pizarra del día: lo que ha llegado esta mañana. Lo agotado sigue
 * saliendo (tachado en la web) para que se vea que había y voló.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

ejecutar(function () {
    try {
        // En caché como la carta: el panel la vacía al guardar cualquier cambio
        responder(enCache('pizarra:' . idioma(), function (): array {
            $filas = db()->query(
                'SELECT id, ' . col('nombre') . ' AS nombre, ' . col('origen') . ' AS origen, precio, agotado, destacado
                 FROM pizarra WHERE visible = 1 ORDER BY agotado, orden, id'
            )->fetchAll();
            $hora = db()->query('SELECT MAX(actualizado_en) FROM pizarra WHERE visible = 1')->fetchColumn();

            return [
                'pizarra' => array_map(fn(array $p) => [
                    'id'        => (int) $p['id'],
                    'nombre'    => $p['nombre'],
                    'origen'    => $p['origen'],
                    'precio'    => $p['precio'] !== null ? (float) $p['precio'] : null,
                    'agotado'   => (bool) $p['agotado'],
                    'destacado' => (bool) $p['destacado'],
                ], $filas),
                // ISO 8601 con zona: el navegador decide si es "hoy" o no
                'actualizado' => $hora ? (new DateTime($hora))->format(DATE_ATOM) : null,
            ];
        }));
    } catch (PDOException $e) {
        // 42S02 = la tabla no existe: falta la migración 007
        if ($e->getCode() === '42S02') {
            responder(['pizarra' => [], 'actualizado' => null, 'aviso' => 'Falta la tabla pizarra: ejecuta database/migraciones/007_pizarra.sql']);
        }
        throw $e;
    }
});
