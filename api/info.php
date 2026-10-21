<?php
/**
 * GET api/info.php
 * Horario semanal, servicios, opiniones aprobadas y próximos días cerrados.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

ejecutar(function () {
    $pdo = db();

    $horarios = $pdo->query(
        "SELECT dia_semana, turno,
                TIME_FORMAT(apertura, '%H:%i') AS apertura,
                TIME_FORMAT(cierre, '%H:%i')   AS cierre
         FROM horarios ORDER BY dia_semana, turno"
    )->fetchAll();

    $servicios = $pdo->query(
        'SELECT nombre, descripcion, precio_desde FROM servicios WHERE activo = 1 ORDER BY orden, id'
    )->fetchAll();

    $opiniones = $pdo->query(
        'SELECT nombre, puntuacion, comentario, fecha
         FROM opiniones WHERE aprobada = 1
         ORDER BY fecha DESC LIMIT 3'
    )->fetchAll();

    $cerrados = $pdo->query(
        'SELECT fecha, motivo FROM dias_cerrados WHERE fecha >= CURRENT_DATE ORDER BY fecha'
    )->fetchAll();

    responder([
        'horarios'      => array_map(fn($h) => [
            'dia_semana' => (int) $h['dia_semana'],
            'turno'      => $h['turno'],
            'apertura'   => $h['apertura'],
            'cierre'     => $h['cierre'],
        ], $horarios),
        'servicios'     => array_map(fn($s) => [
            'nombre'       => $s['nombre'],
            'descripcion'  => $s['descripcion'],
            'precio_desde' => $s['precio_desde'] !== null ? (float) $s['precio_desde'] : null,
        ], $servicios),
        'opiniones'     => array_map(fn($o) => [
            'nombre'     => $o['nombre'],
            'puntuacion' => (int) $o['puntuacion'],
            'comentario' => $o['comentario'],
            'fecha'      => $o['fecha'],
        ], $opiniones),
        'dias_cerrados' => $cerrados,
    ]);
});
