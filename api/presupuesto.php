<?php
/**
 * POST api/presupuesto.php   (cuerpo JSON)
 * {
 *   "tipo": "cumpleanos", "personas": 25, "fecha": "2026-12-12", "turno": "cena",
 *   "nombre": "Ana García", "telefono": "600123456", "email": "ana@correo.com",
 *   "comentarios": "Somos 25 y uno es celíaco"
 * }
 * Guarda una solicitud de presupuesto para una celebración. El equipo la
 * ve en el panel (Presupuestos) y responde por teléfono o email.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

const TIPOS = ['cumpleanos', 'empresa', 'familiar', 'grupo', 'otro'];
const TURNOS = ['comida', 'cena', 'indiferente'];

ejecutar(function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responder(['ok' => false, 'error' => tr('Usa POST para pedir un presupuesto.', 'Use POST to request a quote.')], 405);
    }
    limitar('presupuesto', 5, 900); // 5 solicitudes cada 15 minutos por IP

    $d = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($d)) {
        responder(['ok' => false, 'error' => tr('Los datos enviados no son válidos.', 'The data sent is not valid.')], 400);
    }
    if (!empty($d['web'])) { // campo trampa anti-bots
        responder(['ok' => true], 201);
    }

    $tipo        = texto($d, 'tipo');
    $turno       = texto($d, 'turno') ?: 'indiferente';
    $personas    = filter_var($d['personas'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 6, 'max_range' => 200]]);
    $fechaTexto  = texto($d, 'fecha');
    $fecha       = $fechaTexto === '' ? null : fechaValida($fechaTexto);
    $nombre      = trim(texto($d, 'nombre'));
    $telefono    = preg_replace('/\s+/', '', texto($d, 'telefono'));
    $email       = strtolower(trim(texto($d, 'email')));
    $comentarios = trim(texto($d, 'comentarios'));

    $errores = [];
    if (!in_array($tipo, TIPOS, true))   $errores['p-tipo'] = tr('Elige el tipo de celebración.', 'Please choose the type of celebration.');
    if (!in_array($turno, TURNOS, true)) $errores['p-turno'] = tr('Elige comida, cena o indiferente.', 'Please choose lunch, dinner or either.');
    if ($personas === false)             $errores['p-personas'] = tr('Indica entre 6 y 200 personas. Para menos, reserva mesa directamente.', 'Enter between 6 and 200 guests. For fewer, book a table directly.');
    if ($fechaTexto !== '') {
        $hoy = new DateTimeImmutable('today');
        if (!$fecha || $fecha < $hoy || $fecha > $hoy->modify('+1 year')) {
            $errores['p-fecha'] = tr('La fecha debe estar entre hoy y dentro de un año (o déjala vacía).', 'The date must be between today and one year from now (or leave it empty).');
        }
    }
    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) $errores['p-nombre'] = tr('Escribe tu nombre y apellidos.', 'Please enter your full name.');
    if (!preg_match('/^\+?[0-9]{9,14}$/', $telefono))        $errores['p-telefono'] = tr('Escribe un teléfono de 9 cifras.', 'Please enter a valid phone number.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) $errores['p-email'] = tr('Revisa el email.', 'Please check your email address.');
    if (mb_strlen($comentarios) > 1000)                      $errores['p-comentarios'] = tr('Máximo 1000 caracteres.', '1000 characters maximum.');

    if ($errores) {
        responder(['ok' => false, 'error' => reset($errores), 'campos' => $errores], 422);
    }

    db()->prepare(
        'INSERT INTO presupuestos (tipo, personas, fecha, turno, nombre, telefono, email, comentarios, idioma)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([$tipo, $personas, $fecha?->format('Y-m-d'), $turno, $nombre, $telefono, $email, $comentarios ?: null, idioma()]);

    responder(['ok' => true], 201);
});
