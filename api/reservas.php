<?php
/**
 * POST api/reservas.php   (cuerpo JSON)
 * {
 *   "nombre": "Ana García", "telefono": "600123456", "email": "ana@correo.com",
 *   "fecha": "2026-10-24", "hora": "14:00", "personas": 4,
 *   "zona_id": 1, "comentarios": "Una persona celíaca"
 * }
 *
 * Pasos:
 *  1. Valida todos los datos en el servidor (nunca nos fiamos del navegador).
 *  2. Crea el cliente o actualiza sus datos si el email ya existía.
 *  3. Inserta la reserva (el trigger trg_reservas_validar comprueba la fecha).
 *  4. Llama a sp_asignar_mesa: si no queda mesa, se deshace todo.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

ejecutar(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['ok' => false, 'error' => 'Usa POST para crear una reserva.'], 405);
    }

    $d = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($d)) {
        responder(['ok' => false, 'error' => 'Los datos enviados no son válidos.'], 400);
    }

    // Campo trampa: los humanos no lo ven, los bots lo rellenan
    if (!empty($d['web'])) {
        responder(['ok' => true, 'codigo' => 'XXXXXX']);
    }

    /* ---------- 1. Validación ---------- */
    $nombre      = trim((string) ($d['nombre'] ?? ''));
    $telefono    = preg_replace('/\s+/', '', (string) ($d['telefono'] ?? ''));
    $email       = trim((string) ($d['email'] ?? ''));
    $fecha       = fechaValida((string) ($d['fecha'] ?? ''));
    $hora        = (string) ($d['hora'] ?? '');
    $personas    = filter_var($d['personas'] ?? null, FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1, 'max_range' => (int) config('max_personas_online')]]);
    $zonaId      = filter_var($d['zona_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
    $comentarios = trim((string) ($d['comentarios'] ?? ''));

    $errores = [];
    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) $errores['nombre'] = 'Escribe tu nombre y apellidos.';
    if (!preg_match('/^\+?[0-9]{9,14}$/', $telefono))        $errores['telefono'] = 'Escribe un teléfono de 9 cifras.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) $errores['email'] = 'Revisa el email.';
    if (!$fecha)                                             $errores['fecha'] = 'Elige un día.';
    if ($personas === false)                                 $errores['personas'] = 'Para más de ' . config('max_personas_online') . ' personas, llámanos.';
    if (mb_strlen($comentarios) > 500)                       $errores['comentarios'] = 'Máximo 500 caracteres.';

    if ($fecha) {
        $hoy = new DateTimeImmutable('today');
        if ($fecha < $hoy || $fecha > $hoy->modify('+' . config('dias_max_reserva') . ' days')) {
            $errores['fecha'] = 'Solo aceptamos reservas desde hoy hasta dentro de ' . config('dias_max_reserva') . ' días.';
        } elseif (!in_array($hora, array_column(horasDelDia($fecha), 'hora'), true)) {
            $errores['hora'] = 'Esa hora no está disponible ese día.';
        } elseif ($fecha == $hoy && aMinutos($hora) <= (int) date('G') * 60 + (int) date('i') + 60) {
            $errores['hora'] = 'Para hoy, reserva con al menos una hora de antelación.';
        }
    }

    if ($errores) {
        responder(['ok' => false, 'error' => reset($errores), 'campos' => $errores], 422);
    }

    /* ---------- 2-4. Guardado en una transacción ---------- */
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Inserta el cliente; si el email ya existe, actualiza su nombre y teléfono
        $pdo->prepare(
            'INSERT INTO clientes (nombre, telefono, email) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), telefono = VALUES(telefono)'
        )->execute([$nombre, $telefono, strtolower($email)]);

        // Buscamos su id por el email: funciona tanto si es nuevo como si ya existía
        $buscar = $pdo->prepare('SELECT id FROM clientes WHERE email = ?');
        $buscar->execute([strtolower($email)]);
        $clienteId = (int) $buscar->fetchColumn();

        $codigo = generarCodigo($pdo);

        $stmt = $pdo->prepare(
            'INSERT INTO reservas (codigo, cliente_id, zona_id, fecha, hora, personas, comentarios)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$codigo, $clienteId, $zonaId, $fecha->format('Y-m-d'), $hora, $personas, $comentarios ?: null]);
        $reservaId = (int) $pdo->lastInsertId();

        // Asigna la mesa y deja la reserva confirmada
        $call = $pdo->prepare('CALL sp_asignar_mesa(?)');
        $call->execute([$reservaId]);
        $call->closeCursor(); // libera la conexión tras un CALL

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        // SQLSTATE 45000 = error lanzado a propósito por nuestro trigger o procedimiento
        if ($e->getCode() === '45000') {
            $mensaje = str_contains($e->getMessage(), 'mesas')
                ? 'Esa hora se acaba de completar. Elige otra, por favor.'
                : 'No se puede reservar ese día.';
            responder(['ok' => false, 'error' => $mensaje], 409);
        }
        throw $e;
    }

    responder(['ok' => true, 'codigo' => $codigo], 201);
});

/** Código de 6 caracteres sin letras que se confundan (0/O, 1/I). */
function generarCodigo(PDO $pdo): string
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $existe = $pdo->prepare('SELECT 1 FROM reservas WHERE codigo = ?');
    do {
        $codigo = '';
        for ($i = 0; $i < 6; $i++) {
            $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }
        $existe->execute([$codigo]);
    } while ($existe->fetchColumn());
    return $codigo;
}
