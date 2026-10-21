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

    $d = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($d)) {
        responder(['ok' => false, 'error' => 'Los datos enviados no son válidos.'], 400);
    }
    if (!empty($d['web'])) { // campo trampa anti-bots
        responder(['ok' => true]);
    }

    $nombre  = trim((string) ($d['nombre'] ?? ''));
    $email   = trim((string) ($d['email'] ?? ''));
    $asunto  = trim((string) ($d['asunto'] ?? ''));
    $mensaje = trim((string) ($d['mensaje'] ?? ''));

    $errores = [];
    if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100)  $errores['c-nombre'] = 'Escribe tu nombre.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))           $errores['c-email'] = 'Revisa el email.';
    if (mb_strlen($asunto) < 3 || mb_strlen($asunto) > 120)   $errores['c-asunto'] = 'Escribe un asunto.';
    if (mb_strlen($mensaje) < 10 || mb_strlen($mensaje) > 2000) $errores['c-mensaje'] = 'El mensaje debe tener entre 10 y 2000 caracteres.';

    if ($errores) {
        responder(['ok' => false, 'error' => reset($errores), 'campos' => $errores], 422);
    }

    db()->prepare('INSERT INTO mensajes_contacto (nombre, email, asunto, mensaje) VALUES (?, ?, ?, ?)')
        ->execute([$nombre, strtolower($email), $asunto, $mensaje]);

    responder(['ok' => true], 201);
});
