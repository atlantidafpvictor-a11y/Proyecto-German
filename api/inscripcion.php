<?php
/**
 * POST api/inscripcion.php   (cuerpo JSON)
 * {
 *   "evento_id": 1, "plazas": 2,
 *   "nombre": "Ana García", "telefono": "600123456", "email": "ana@correo.com"
 * }
 *
 * Apunta a un cliente a un evento sin pasarse nunca de las plazas.
 *
 * Problema: si quedan 2 plazas y dos personas piden 2 a la vez, las dos
 * podrían ver "quedan 2" y apuntarse. Solución: dentro de una transacción,
 * SELECT ... FOR UPDATE bloquea la fila del evento hasta el COMMIT, así que
 * la segunda petición espera a que termine la primera y ya ve "quedan 0".
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

const MAX_PLAZAS_POR_PERSONA = 6;

ejecutar(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['ok' => false, 'error' => 'Usa POST para apuntarte a un evento.'], 405);
    }
    limitar('inscribir', 8, 900); // 8 inscripciones cada 15 minutos por IP

    $d = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($d)) {
        responder(['ok' => false, 'error' => tr('Los datos enviados no son válidos.', 'The data sent is not valid.')], 400);
    }
    if (!empty($d['web'])) { // campo trampa anti-bots
        responder(['ok' => true, 'libres' => 0]);
    }

    /* ---------- Validación ---------- */
    $eventoId = filter_var($d['evento_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $plazas   = filter_var($d['plazas'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => MAX_PLAZAS_POR_PERSONA]]);
    $nombre   = trim(texto($d, 'nombre'));
    $telefono = preg_replace('/\s+/', '', texto($d, 'telefono'));
    $email    = strtolower(trim(texto($d, 'email')));

    $errores = [];
    if (!$eventoId)                                          $errores['evento'] = tr('Elige un evento.', 'Please choose an event.');
    if ($plazas === false)                                   $errores['e-plazas'] = 'Puedes reservar de 1 a ' . MAX_PLAZAS_POR_PERSONA . ' plazas.';
    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100)  $errores['e-nombre'] = tr('Escribe tu nombre y apellidos.', 'Please enter your full name.');
    if (!preg_match('/^\+?[0-9]{9,14}$/', $telefono))        $errores['e-telefono'] = tr('Escribe un teléfono de 9 cifras.', 'Please enter a valid phone number.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) $errores['e-email'] = tr('Revisa el email.', 'Please check your email address.');

    if ($errores) {
        responder(['ok' => false, 'error' => reset($errores), 'campos' => $errores], 422);
    }

    /* ---------- Inscripción en una transacción ---------- */
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // 1. Bloquear el evento mientras contamos plazas
        $stmt = $pdo->prepare('SELECT id, titulo, fecha, plazas FROM eventos WHERE id = ? AND publicado = 1 AND fecha >= NOW() FOR UPDATE');
        $stmt->execute([$eventoId]);
        $evento = $stmt->fetch();
        if (!$evento) {
            $pdo->rollBack();
            responder(['ok' => false, 'error' => tr('Este evento ya no está disponible.', 'This event is no longer available.')], 404);
        }

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(plazas), 0) FROM inscripciones_evento WHERE evento_id = ?');
        $stmt->execute([$eventoId]);
        $libres = (int) $evento['plazas'] - (int) $stmt->fetchColumn();

        if ($libres <= 0) {
            $pdo->rollBack();
            responder(['ok' => false, 'error' => tr('Lo sentimos, ya no quedan plazas.', 'Sorry, there are no places left.'), 'libres' => 0], 409);
        }
        if ($plazas > $libres) {
            $pdo->rollBack();
            responder(['ok' => false, 'error' => $libres === 1 ? tr('Solo queda 1 plaza.', 'Only 1 place left.') : tr("Solo quedan $libres plazas.", "Only $libres places left."), 'campos' => ['e-plazas' => tr("Elige como mucho $libres.", "Choose $libres at most.")], 'libres' => $libres], 409);
        }

        // 2. Crear el cliente si es nuevo (sin tocar los datos de uno que ya existe) y obtener su id
        $pdo->prepare('INSERT INTO clientes (nombre, telefono, email) VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE id = id')
            ->execute([$nombre, $telefono, $email]);
        $buscar = $pdo->prepare('SELECT id FROM clientes WHERE email = ?');
        $buscar->execute([$email]);
        $clienteId = (int) $buscar->fetchColumn();

        // 3. ¿Ya estaba apuntado? (la clave primaria es evento + cliente)
        $ya = $pdo->prepare('SELECT plazas FROM inscripciones_evento WHERE evento_id = ? AND cliente_id = ?');
        $ya->execute([$eventoId, $clienteId]);
        if (($antes = $ya->fetchColumn()) !== false) {
            $pdo->rollBack();
            responder(['ok' => false, 'error' => (int) $antes === 1
                ? tr('Ya estás apuntado con 1 plaza. Si quieres cambiarlo, llámanos al 958 000 000.', 'You are already signed up with 1 place. To change it, call us on +34 958 000 000.')
                : tr("Ya estás apuntado con $antes plazas. Si quieres cambiarlo, llámanos al 958 000 000.", "You are already signed up with $antes places. To change it, call us on +34 958 000 000.")], 409);
        }

        // 4. Guardar la inscripción
        $pdo->prepare('INSERT INTO inscripciones_evento (evento_id, cliente_id, plazas) VALUES (?, ?, ?)')
            ->execute([$eventoId, $clienteId, $plazas]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    responder([
        'ok'     => true,
        'evento' => $evento['titulo'],
        'fecha'  => substr($evento['fecha'], 0, 10),
        'hora'   => substr($evento['fecha'], 11, 5),
        'plazas' => $plazas,
        'libres' => $libres - $plazas,
    ], 201);
});
