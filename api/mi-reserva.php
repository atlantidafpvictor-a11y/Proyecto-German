<?php
/**
 * POST api/mi-reserva.php   (cuerpo JSON)
 *
 * Consultar:  { "codigo": "K7Q2XB", "telefono": "600111222" }
 * Cancelar:   { "codigo": "K7Q2XB", "telefono": "600111222", "accion": "cancelar" }
 *
 * Para ver o cancelar una reserva hay que saber DOS cosas: el código y el
 * teléfono con el que se hizo. Así nadie puede cancelar la reserva de otro
 * probando códigos al azar.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

ejecutar(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['ok' => false, 'error' => 'Usa POST para consultar una reserva.'], 405);
    }

    $d = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($d)) {
        responder(['ok' => false, 'error' => 'Los datos enviados no son válidos.'], 400);
    }

    $codigo   = strtoupper(trim((string) ($d['codigo'] ?? '')));
    $telefono = soloDigitos((string) ($d['telefono'] ?? ''));
    $accion   = ($d['accion'] ?? '') === 'cancelar' ? 'cancelar' : 'consultar';

    if (!preg_match('/^[A-Z0-9]{6}$/', $codigo)) {
        responder(['ok' => false, 'error' => 'El código tiene 6 letras y números, por ejemplo K7Q2XB.', 'campo' => 'm-codigo'], 422);
    }
    if (strlen($telefono) < 9) {
        responder(['ok' => false, 'error' => 'Escribe el teléfono con el que hiciste la reserva.', 'campo' => 'm-telefono'], 422);
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT r.id, r.codigo, r.fecha, TIME_FORMAT(r.hora, '%H:%i') AS hora, r.personas, r.estado,
                c.nombre, c.telefono, z.nombre AS zona
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
        responder(['ok' => false, 'error' => 'No encontramos ninguna reserva con ese código y teléfono. Revisa los dos datos.'], 404);
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
    }

    responder(['ok' => true, 'reserva' => publica($r, $puede, $motivo)]);
});

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
    if ($r['estado'] === 'cancelada')     return [false, 'Esta reserva ya está cancelada.'];
    if ($r['estado'] === 'completada')    return [false, 'Esta reserva ya se ha celebrado.'];
    if ($r['estado'] === 'no_presentado') return [false, 'Esta reserva ya ha pasado.'];

    $horas = (int) (config('horas_min_cancelacion') ?? 2);
    $cuando = new DateTimeImmutable($r['fecha'] . ' ' . $r['hora']);
    $limite = (new DateTimeImmutable())->modify("+$horas hours");
    if ($cuando < $limite) {
        return [false, "Solo se puede cancelar online hasta $horas horas antes. Llámanos al 958 000 000."];
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
        'motivo'         => $motivo,
    ];
}
