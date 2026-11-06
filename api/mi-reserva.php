<?php
/**
 * POST api/mi-reserva.php   (cuerpo JSON)
 *
 * Consultar:  { "codigo": "K7Q2XB", "telefono": "600111222" }
 * Cancelar:   { "codigo": "K7Q2XB", "telefono": "600111222", "accion": "cancelar" }
 * Cambiar:    { "codigo": "K7Q2XB", "telefono": "600111222", "accion": "cambiar",
 *               "fecha": "2026-11-20", "hora": "21:00", "personas": 4 }
 *
 * Para ver, cambiar o cancelar una reserva hay que saber DOS cosas: el código
 * y el teléfono con el que se hizo (guardado en la propia reserva). Así nadie
 * puede tocar la reserva de otro probando códigos al azar.
 *
 * Al cambiarla se busca mesa para el nuevo día y hora DENTRO de una
 * transacción: si no queda, se deshace y la reserva sigue como estaba
 * (nunca se pierde la mesa que ya se tenía).
 */
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/avisos.php';

ejecutar(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['ok' => false, 'error' => 'Usa POST para consultar una reserva.'], 405);
    }
    limitar('consultar', 20, 900); // 20 consultas cada 15 minutos: impide probar códigos a lo loco

    $d = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($d)) {
        responder(['ok' => false, 'error' => tr('Los datos enviados no son válidos.', 'The data sent is not valid.')], 400);
    }

    $codigo   = strtoupper(trim(texto($d, 'codigo')));
    $telefono = soloDigitos(texto($d, 'telefono'));
    $accion   = in_array($d['accion'] ?? '', ['cancelar', 'cambiar'], true) ? $d['accion'] : 'consultar';

    if (!preg_match('/^[A-Z0-9]{6}$/', $codigo)) {
        responder(['ok' => false, 'error' => tr('El código tiene 6 letras y números, por ejemplo K7Q2XB.', 'The code has 6 letters and numbers, for example K7Q2XB.'), 'campo' => 'm-codigo'], 422);
    }
    if (strlen($telefono) < 9) {
        responder(['ok' => false, 'error' => tr('Escribe el teléfono con el que hiciste la reserva.', 'Enter the phone number you booked with.'), 'campo' => 'm-telefono'], 422);
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT r.id, r.cliente_id, r.codigo, r.fecha, TIME_FORMAT(r.hora, '%H:%i') AS hora, r.personas, r.estado,
                COALESCE(r.nombre, c.nombre) AS nombre, COALESCE(r.telefono, c.telefono) AS telefono, " . col('z.nombre') . " AS zona
         FROM reservas r
         JOIN clientes c ON c.id = r.cliente_id
         LEFT JOIN zonas z ON z.id = r.zona_id
         WHERE r.codigo = ?"
    );
    $stmt->execute([$codigo]);
    $r = $stmt->fetch();

    // Mismo mensaje si no existe el código o si el teléfono no coincide:
    // así no se puede averiguar qué códigos existen.
    if (!$r || !mismoTelefono($telefono, soloDigitos($r['telefono']))) {
        usleep(400000); // frena a quien pruebe muchas combinaciones
        responder(['ok' => false, 'error' => tr('No encontramos ninguna reserva con ese código y teléfono. Revisa los dos datos.', 'We could not find a booking with that code and phone number. Please check both.')], 404);
    }

    [$puede, $motivo] = puedeCancelar($r);

    if ($accion === 'cancelar') {
        if (!$puede) {
            responder(['ok' => false, 'error' => $motivo, 'reserva' => publica($r, false, $motivo)], 409);
        }
        $pdo->prepare("UPDATE reservas SET estado = 'cancelada' WHERE id = ? AND estado IN ('pendiente', 'confirmada')")
            ->execute([$r['id']]);
        $r['estado'] = 'cancelada';
        [$puede, $motivo] = puedeCancelar($r);
        // Después de responder: email al cliente y aviso a la lista de espera de ese turno
        $libre = $r;
        despues(function () use ($libre) {
            $pdo = db();
            correoReserva($pdo, (int) $libre['id'], 'cancelacion');
            avisarListaEspera($pdo, $libre['fecha'], turnoDe(new DateTimeImmutable($libre['fecha']), $libre['hora']));
        });
    }

    if ($accion === 'cambiar') {
        if (!$puede) {
            responder(['ok' => false, 'error' => (idioma() === 'es' ? str_replace('cancelar', 'cambiar', $motivo) : $motivo), 'reserva' => publica($r, false, $motivo)], 409);
        }
        $antes = $r;
        $r = cambiar($pdo, $r, $d);
        [$puede, $motivo] = puedeCancelar($r);
        // El hueco que deja la reserva antigua puede servir a alguien de la lista de espera
        despues(function () use ($antes) {
            $pdo = db();
            correoReserva($pdo, (int) $antes['id'], 'cambio');
            avisarListaEspera($pdo, $antes['fecha'], turnoDe(new DateTimeImmutable($antes['fecha']), $antes['hora']));
        });
        responder(['ok' => true, 'cambiada' => true, 'reserva' => publica($r, $puede, $motivo)]);
    }

    responder(['ok' => true, 'reserva' => publica($r, $puede, $motivo)]);
});

/** Cambia día, hora y personas. Si algo falla responde con el error y la reserva queda como estaba. */
function cambiar(PDO $pdo, array $r, array $d): array
{
    $fecha    = fechaValida(texto($d, 'fecha'));
    $hora     = texto($d, 'hora');
    $personas = filter_var($d['personas'] ?? null, FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => (int) config('max_personas_online')]]);

    if ($personas === false) {
        responder(['ok' => false, 'error' => tr('Para más de ' . config('max_personas_online') . ' personas, llámanos.', 'For more than ' . config('max_personas_online') . ' guests, please call us.'), 'campo' => 'c-personas'], 422);
    }
    if ($p = problemaFechaHora($fecha, $hora)) {
        responder(['ok' => false, 'error' => $p[1], 'campo' => 'c-' . $p[0]], 422);
    }
    if ($fecha->format('Y-m-d') === $r['fecha'] && $hora === $r['hora'] && $personas === (int) $r['personas']) {
        responder(['ok' => false, 'error' => tr('Es la misma reserva que ya tienes: cambia el día, la hora o las personas.', 'That is the booking you already have: change the day, the time or the number of guests.')], 422);
    }

    $resultado = conCerrojo('mesas', function (PDO $pdo) use ($r, $fecha, $hora, $personas): ?string {
        $pdo->beginTransaction();
        try {
            if ($otra = reservaSolapada($pdo, (int) $r['cliente_id'], $fecha, $hora, (int) $r['id'])) {
                $pdo->rollBack();
                return tr("Ya tienes otra reserva ese día a las $otra.", "You already have another booking that day at $otra.");
            }
            // Se suelta la mesa actual y se busca una nueva para el nuevo día y hora
            $pdo->prepare("UPDATE reservas SET fecha = ?, hora = ?, personas = ?, mesa_id = NULL, estado = 'pendiente' WHERE id = ?")
                ->execute([$fecha->format('Y-m-d'), $hora, $personas, $r['id']]);
            $call = $pdo->prepare('CALL sp_asignar_mesa(?)');
            $call->execute([$r['id']]);
            $call->closeCursor();
            $pdo->commit();
            return null;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack(); // la reserva vuelve a como estaba, con su mesa
            if ($e->getCode() === '45000') return tr('No queda mesa a esa hora. Tu reserva sigue como estaba: prueba otra hora.', 'There are no tables left at that time. Your booking has not changed: please try another time.');
            throw $e;
        }
    });

    if ($resultado !== null) {
        responder(['ok' => false, 'error' => $resultado], 409);
    }
    return array_merge($r, ['fecha' => $fecha->format('Y-m-d'), 'hora' => $hora, 'personas' => $personas, 'estado' => 'confirmada']);
}

function soloDigitos(string $tel): string
{
    return preg_replace('/\D+/', '', $tel);
}

/** Compara los 9 últimos dígitos, para que "+34 600 111 222" y "600111222" sean iguales. */
function mismoTelefono(string $a, string $b): bool
{
    return strlen($a) >= 9 && strlen($b) >= 9 && hash_equals(substr($b, -9), substr($a, -9));
}

/** @return array{0: bool, 1: string} [¿se puede cancelar?, explicación si no] */
function puedeCancelar(array $r): array
{
    if ($r['estado'] === 'cancelada')     return [false, tr('Esta reserva ya está cancelada.', 'This booking has already been cancelled.')];
    if ($r['estado'] === 'completada')    return [false, tr('Esta reserva ya se ha celebrado.', 'This booking has already taken place.')];
    if ($r['estado'] === 'no_presentado') return [false, tr('Esta reserva ya ha pasado.', 'This booking is in the past.')];

    $horas = (int) (config('horas_min_cancelacion') ?? 2);
    $cuando = new DateTimeImmutable($r['fecha'] . ' ' . $r['hora']);
    $limite = (new DateTimeImmutable())->modify("+$horas hours");
    if ($cuando < $limite) {
        return [false, tr("Solo se puede cancelar online hasta $horas horas antes. Llámanos al 958 000 000.", "Online changes and cancellations close $horas hours before. Please call us on +34 958 000 000.")];
    }
    return [true, ''];
}

/** Solo los datos que el cliente necesita ver (nada de ids internos ni apellidos). */
function publica(array $r, bool $puede, string $motivo): array
{
    return [
        'codigo'         => $r['codigo'],
        'nombre'         => explode(' ', trim($r['nombre']))[0],
        'fecha'          => $r['fecha'],
        'hora'           => $r['hora'],
        'personas'       => (int) $r['personas'],
        'zona'           => $r['zona'],
        'estado'         => $r['estado'],
        'puede_cancelar' => $puede,
        'puede_cambiar'  => $puede, // mismas reglas: activa y con al menos 2 horas de margen
        'motivo'         => $motivo,
    ];
}
