<?php
/**
 * POST api/lista-espera.php   (cuerpo JSON)
 * { "fecha": "2026-11-07", "turno": "cena", "personas": 4,
 *   "nombre": "Ana García", "telefono": "600123456", "email": "ana@correo.com" }
 *
 * Apunta a alguien a la lista de espera de un día y turno. Si ya estaba
 * apuntado (mismo email, día y turno) se actualizan sus datos.
 * Cuando alguien cancela y queda sitio, avisarListaEspera() (avisos.php)
 * le manda un email con un enlace para reservar.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';
require __DIR__ . '/avisos.php';

ejecutar(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['ok' => false, 'error' => 'Usa POST.'], 405);
    }
    limitar('espera', 6, 900); // 6 cada 15 minutos por IP

    $d = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($d)) {
        responder(['ok' => false, 'error' => tr('Los datos enviados no son válidos.', 'The data sent is not valid.')], 400);
    }
    if (!empty($d['web'])) responder(['ok' => true]); // campo trampa para bots

    $fecha    = fechaValida(texto($d, 'fecha'));
    $turno    = in_array($d['turno'] ?? '', ['comida', 'cena'], true) ? $d['turno'] : null;
    $personas = filter_var($d['personas'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => (int) config('max_personas_online')]]);
    $nombre   = trim(texto($d, 'nombre'));
    $telefono = preg_replace('/\s+/', '', texto($d, 'telefono'));
    $email    = strtolower(trim(texto($d, 'email')));

    $errores = [];
    $hoy = new DateTimeImmutable('today');
    if (!$fecha || $fecha < $hoy || $fecha > $hoy->modify('+' . config('dias_max_reserva') . ' days')) {
        $errores['fecha'] = tr('Elige un día válido.', 'Please choose a valid day.');
    } elseif (!$turno || !in_array($turno, array_column(horasDelDia($fecha), 'turno'), true)) {
        $errores['turno'] = tr('Ese día no abrimos en ese turno.', 'We are not open at that sitting that day.');
    }
    if ($personas === false) $errores['personas'] = tr('Para grupos grandes, llámanos.', 'For large groups, please call us.');
    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) $errores['w-nombre'] = tr('Escribe tu nombre y apellidos.', 'Please enter your full name.');
    if (!preg_match('/^\+?[0-9]{9,14}$/', $telefono)) $errores['w-telefono'] = tr('Escribe un teléfono de 9 cifras.', 'Please enter a valid phone number.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) $errores['w-email'] = tr('Revisa el email.', 'Please check your email address.');
    if ($errores) {
        responder(['ok' => false, 'error' => reset($errores), 'campos' => $errores], 422);
    }

    $pdo = db();
    $pdo->prepare(
        "INSERT INTO lista_espera (fecha, turno, personas, nombre, telefono, email, idioma)
         VALUES (?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE personas = VALUES(personas), nombre = VALUES(nombre), telefono = VALUES(telefono),
                                 idioma = VALUES(idioma), estado = 'esperando', avisado_en = NULL"
    )->execute([$fecha->format('Y-m-d'), $turno, $personas, $nombre, $telefono, $email, idioma()]);

    $entrada = ['fecha' => $fecha->format('Y-m-d'), 'turno' => $turno, 'personas' => $personas, 'email' => $email, 'idioma' => idioma()];
    despues(fn() => correoEsperaApuntado($entrada));

    responder(['ok' => true]);
});
