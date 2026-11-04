<?php
/**
 * POST api/contacto.php   (cuerpo JSON)
 * { "nombre": "...", "email": "...", "asunto": "...", "mensaje": "..." }
 * Guarda el mensaje en la tabla mensajes_contacto para leerlo desde el panel.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

ejecutar(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['ok' => false, 'error' => 'Usa POST para enviar un mensaje.'], 405);
    }
    limitar('contactar', 5, 900); // 5 mensajes cada 15 minutos por IP

    $d = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($d)) {
        responder(['ok' => false, 'error' => tr('Los datos enviados no son válidos.', 'The data sent is not valid.')], 400);
    }
    if (!empty($d['web'])) { // campo trampa anti-bots
        responder(['ok' => true]);
    }

    $nombre  = trim(texto($d, 'nombre'));
    $email   = trim(texto($d, 'email'));
    $asunto  = trim(texto($d, 'asunto'));
    $mensaje = trim(texto($d, 'mensaje'));

    $errores = [];
    if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100)  $errores['c-nombre'] = tr('Escribe tu nombre.', 'Please enter your name.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))           $errores['c-email'] = tr('Revisa el email.', 'Please check your email address.');
    if (mb_strlen($asunto) < 3 || mb_strlen($asunto) > 120)   $errores['c-asunto'] = tr('Escribe un asunto.', 'Please enter a subject.');
    if (mb_strlen($mensaje) < 10 || mb_strlen($mensaje) > 2000) $errores['c-mensaje'] = tr('El mensaje debe tener entre 10 y 2000 caracteres.', 'The message must be between 10 and 2000 characters.');

    if ($errores) {
        responder(['ok' => false, 'error' => reset($errores), 'campos' => $errores], 422);
    }

    db()->prepare('INSERT INTO mensajes_contacto (nombre, email, asunto, mensaje) VALUES (?, ?, ?, ?)')
        ->execute([$nombre, strtolower($email), $asunto, $mensaje]);

    responder(['ok' => true], 201);
});
