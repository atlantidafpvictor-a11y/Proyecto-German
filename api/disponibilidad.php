<?php
/**
 * GET api/disponibilidad.php?fecha=2026-10-24&personas=4[&excluir=K7Q2XB]
 * Devuelve las horas del día y si queda alguna mesa libre para ese grupo.
 * "excluir" = código de una reserva que se está cambiando: su propia mesa
 * cuenta como libre (si no, al moverla media hora parecería que no hay sitio).
 *
 * El cálculo está en horasLibres() (db.php): lo usa también la lista de espera.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

ejecutar(function () {
    $fecha    = fechaValida((string) ($_GET['fecha'] ?? ''));
    $personas = filter_var($_GET['personas'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);

    if (!$fecha || $personas === false) {
        responder(['ok' => false, 'error' => tr('Indica una fecha (AAAA-MM-DD) y un número de personas válido.', 'Send a date (YYYY-MM-DD) and a valid number of guests.')], 400);
    }

    $hoy = new DateTimeImmutable('today');
    if ($fecha < $hoy || $fecha > $hoy->modify('+' . config('dias_max_reserva') . ' days')) {
        responder(['ok' => true, 'cerrado' => false, 'slots' => []]);
    }

    $horas = horasDelDia($fecha);
    if (!$horas) {
        responder(['ok' => true, 'cerrado' => true, 'slots' => []]);
    }

    $slots = horasLibres(db(), $fecha, $personas, (string) ($_GET['excluir'] ?? ''));

    responder(['ok' => true, 'cerrado' => false, 'slots' => $slots]);
});
