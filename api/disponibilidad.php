<?php
/**
 * GET api/disponibilidad.php?fecha=2026-10-24&personas=4[&excluir=K7Q2XB]
 * Devuelve las horas del día y si queda alguna mesa libre para ese grupo.
 * "excluir" = código de una reserva que se está cambiando: su propia mesa
 * cuenta como libre (si no, al moverla media hora parecería que no hay sitio).
 *
 * Una hora está libre si existe al menos una mesa activa con capacidad
 * suficiente que no tenga otra reserva a menos de 90 minutos.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

ejecutar(function () {
    $fecha    = fechaValida((string) ($_GET['fecha'] ?? ''));
    $personas = filter_var($_GET['personas'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);

    if (!$fecha || $personas === false) {
        responder(['ok' => false, 'error' => 'Indica una fecha (AAAA-MM-DD) y un número de personas válido.'], 400);
    }

    $hoy = new DateTimeImmutable('today');
    if ($fecha < $hoy || $fecha > $hoy->modify('+' . config('dias_max_reserva') . ' days')) {
        responder(['ok' => true, 'cerrado' => false, 'slots' => []]);
    }

    $horas = horasDelDia($fecha);
    if (!$horas) {
        responder(['ok' => true, 'cerrado' => true, 'slots' => []]);
    }

    $pdo = db();

    // Mesas donde cabe el grupo
    $stmt = $pdo->prepare('SELECT id FROM mesas WHERE activa = 1 AND capacidad >= ?');
    $stmt->execute([$personas]);
    $mesas = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    // Reservas de ese día que ya tienen mesa
    $stmt = $pdo->prepare(
        "SELECT mesa_id, TIME_FORMAT(hora, '%H:%i') AS hora
         FROM reservas
         WHERE fecha = ? AND mesa_id IS NOT NULL AND estado IN ('pendiente', 'confirmada') AND codigo <> ?"
    );
    $stmt->execute([$fecha->format('Y-m-d'), strtoupper(substr((string) ($_GET['excluir'] ?? ''), 0, 6))]);
    $ocupadas = $stmt->fetchAll();

    $esHoy    = $fecha == $hoy;
    $ahora    = (int) date('G') * 60 + (int) date('i');
    $duracion = (int) config('duracion_reserva');

    $slots = [];
    foreach ($horas as $h) {
        $min = aMinutos($h['hora']);

        // Hoy no se puede reservar para dentro de menos de una hora
        if ($esHoy && $min <= $ahora + 60) {
            $slots[] = $h + ['libre' => false];
            continue;
        }

        $bloqueadas = [];
        foreach ($ocupadas as $r) {
            if (abs(aMinutos($r['hora']) - $min) < $duracion) {
                $bloqueadas[(int) $r['mesa_id']] = true;
            }
        }
        $libres = array_filter($mesas, fn($id) => !isset($bloqueadas[$id]));
        $slots[] = $h + ['libre' => count($libres) > 0];
    }

    responder(['ok' => true, 'cerrado' => false, 'slots' => $slots]);
});
