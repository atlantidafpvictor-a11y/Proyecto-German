<?php
/**
 * Avisos por email: confirmación, recordatorio, cambio y cancelación de
 * reservas, y la lista de espera. No es una página: lo usan otros archivos.
 *
 * El correo se construye a mano en formato MIME (sin librerías):
 *   multipart/mixed
 *   ├─ multipart/alternative
 *   │   ├─ text/plain   (para quien no ve HTML)
 *   │   └─ text/html    (la versión bonita)
 *   └─ text/calendar    (reserva.ics: "añadir al calendario", si toca)
 * y se entrega con mail() a Postfix, que lo envía por Gmail.
 */
declare(strict_types=1);
require_once __DIR__ . '/db.php';

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

/* =========================================================
   1. ENVÍO
   ========================================================= */
function enviarCorreo(string $para, string $asunto, string $html, string $texto, ?array $adjunto = null): bool
{
    // Un email con saltos de línea podría colar cabeceras nuevas (inyección de cabeceras)
    if (!filter_var($para, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $para . $asunto)) return false;

    $original = $para;
    $desvio = (string) config('correo_desvio');
    if ($desvio !== '') {
        $asunto = "[Para $para] $asunto";
        $para = $desvio;
    }
    if (!config('correo_activo')) {
        // Modo pruebas: no sale, pero queda guardado para verlo en el panel → Correos enviados
        registrarCorreo($original, $asunto, $html, 'pruebas');
        return false;
    }

    $remitente = (string) config('correo_remitente');
    $nombre = mb_encode_mimeheader((string) config('correo_nombre'), 'UTF-8', 'B');
    $limite1 = 'mix_' . bin2hex(random_bytes(8));
    $limite2 = 'alt_' . bin2hex(random_bytes(8));
    $b64 = fn(string $t): string => chunk_split(base64_encode($t), 76, "\r\n");

    $cabeceras = implode("\r\n", [
        "From: $nombre <$remitente>",
        "Reply-To: $remitente",
        'MIME-Version: 1.0',
        "Content-Type: multipart/mixed; boundary=\"$limite1\"",
        'X-Mailer: Sabor Mediterraneo',
    ]);
    $cuerpo = "--$limite1\r\n"
        . "Content-Type: multipart/alternative; boundary=\"$limite2\"\r\n\r\n"
        . "--$limite2\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($texto)
        . "--$limite2\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($html)
        . "--$limite2--\r\n";
    if ($adjunto) {
        [$archivo, $tipo, $contenido] = $adjunto;
        $cuerpo .= "--$limite1\r\nContent-Type: $tipo; charset=UTF-8; name=\"$archivo\"\r\n"
            . "Content-Disposition: attachment; filename=\"$archivo\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . $b64($contenido);
    }
    $cuerpo .= "--$limite1--\r\n";

    $ok = mail($para, mb_encode_mimeheader($asunto, 'UTF-8', 'B'), $cuerpo, $cabeceras, '-f' . $remitente);
    if (!$ok) error_log("No se pudo entregar el correo a Postfix ($para: $asunto)");
    registrarCorreo($original, $asunto, $html, $ok ? 'enviado' : 'error');
    return $ok;
}

/** Guarda una copia del email (panel → Correos enviados). Si falla, no afecta al envío. */
function registrarCorreo(string $para, string $asunto, string $html, string $estado): void
{
    try {
        db()->prepare('INSERT INTO correos_enviados (para, asunto, html, estado) VALUES (?, ?, ?, ?)')
            ->execute([mb_substr($para, 0, 120), mb_substr($asunto, 0, 200), $html, $estado]);
    } catch (PDOException) {
        // Sin la migración 008 no hay registro
    }
}

/* =========================================================
   2. PLANTILLA (estilos en línea: es lo que entienden Gmail y Outlook)
   ========================================================= */
function plantillaCorreo(string $lang, string $titulo, string $intro, array $filas = [], ?array $boton = null, string $nota = ''): array
{
    $h = fn(string $t): string => htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
    $web = rtrim((string) config('url_web'), '/') . '/';

    $tabla = '';
    foreach ($filas as [$k, $v]) {
        $tabla .= '<tr><td style="padding:10px 0;border-bottom:1px solid #E5E2D8;color:#5A6155;font-size:14px;width:40%">' . $h($k)
            . '</td><td style="padding:10px 0;border-bottom:1px solid #E5E2D8;font-weight:bold;font-size:15px">' . $h($v) . '</td></tr>';
    }
    $btn = $boton ? '<p style="margin:28px 0 8px"><a href="' . $h($boton[1]) . '" style="display:inline-block;background:#23408E;color:#ffffff;text-decoration:none;font-weight:bold;padding:14px 26px;border-radius:999px">' . $h($boton[0]) . '</a></p>' : '';
    $pie = $lang === 'en'
        ? 'Sabor Mediterráneo · Granada city centre · +34 958 000 000'
        : 'Sabor Mediterráneo · Centro de Granada · 958 000 000';

    $html = '<!DOCTYPE html><html lang="' . $lang . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"></head>'
        . '<body style="margin:0;background:#F6F5F1;font-family:Helvetica,Arial,sans-serif;color:#1C2318">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#F6F5F1"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden">'
        . '<tr><td style="background:#172C66;padding:22px 28px;color:#ffffff;font-family:Georgia,serif;font-size:22px">Sabor Mediterráneo'
        . '<div style="height:4px;margin-top:14px;background:repeating-linear-gradient(90deg,#C98A2E 0 12px,#23408E 12px 24px)"></div></td></tr>'
        . '<tr><td style="padding:28px">'
        . '<h1 style="margin:0 0 12px;font-family:Georgia,serif;font-weight:normal;font-size:26px;color:#23408E">' . $h($titulo) . '</h1>'
        . '<p style="margin:0 0 18px;font-size:16px;line-height:1.55">' . $h($intro) . '</p>'
        . ($tabla ? '<table role="presentation" width="100%" cellspacing="0" cellpadding="0">' . $tabla . '</table>' : '')
        . $btn
        . ($nota ? '<p style="margin:18px 0 0;font-size:14px;line-height:1.5;color:#5A6155">' . $h($nota) . '</p>' : '')
        . '</td></tr><tr><td style="padding:18px 28px;background:#F6F5F1;font-size:12px;color:#5A6155">' . $h($pie)
        . '<br><a href="' . $h($web) . '" style="color:#23408E">' . $h($web) . '</a></td></tr>'
        . '</table></td></tr></table></body></html>';

    $texto = "$titulo\n\n$intro\n\n";
    foreach ($filas as [$k, $v]) $texto .= "$k: $v\n";
    if ($boton) $texto .= "\n{$boton[0]}: {$boton[1]}\n";
    if ($nota) $texto .= "\n$nota\n";
    $texto .= "\n--\n$pie\n$web\n";

    return [$html, $texto];
}

/* =========================================================
   3. RESERVAS
   ========================================================= */

/** Datos de una reserva para los emails (con el email del cliente y la zona). */
function datosReserva(PDO $pdo, int $id): ?array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT r.id, r.codigo, r.fecha, TIME_FORMAT(r.hora, '%H:%i') AS hora, r.personas, r.estado,
                    COALESCE(r.nombre, c.nombre) AS nombre, c.email, r.idioma, z.nombre AS zona, z.nombre_en AS zona_en
             FROM reservas r JOIN clientes c ON c.id = r.cliente_id LEFT JOIN zonas z ON z.id = r.zona_id
             WHERE r.id = ?"
        );
        $stmt->execute([$id]);
    } catch (PDOException) {
        // Sin la migración 008 (columna idioma) o sin zonas en inglés: versión sencilla
        $stmt = $pdo->prepare(
            "SELECT r.id, r.codigo, r.fecha, TIME_FORMAT(r.hora, '%H:%i') AS hora, r.personas, r.estado,
                    COALESCE(r.nombre, c.nombre) AS nombre, c.email, 'es' AS idioma, z.nombre AS zona, NULL AS zona_en
             FROM reservas r JOIN clientes c ON c.id = r.cliente_id LEFT JOIN zonas z ON z.id = r.zona_id
             WHERE r.id = ?"
        );
        $stmt->execute([$id]);
    }
    return $stmt->fetch() ?: null;
}

function fechaEmail(string $fecha, string $lang): string
{
    $d = new DateTimeImmutable($fecha);
    if ($lang === 'en') return $d->format('l j F Y');
    $dias = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return $dias[(int) $d->format('N') - 1] . ' ' . $d->format('j') . ' de ' . $meses[(int) $d->format('n') - 1] . ' de ' . $d->format('Y');
}

/** Archivo de calendario (.ics) para "añadir a mi calendario". */
function icsReserva(array $r, string $lang): string
{
    $tz = new DateTimeZone(date_default_timezone_get());
    $inicio = new DateTimeImmutable($r['fecha'] . ' ' . $r['hora'], $tz);
    $fin = $inicio->modify('+' . (int) config('duracion_reserva') . ' minutes');
    $utc = fn(DateTimeImmutable $d): string => $d->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    $esc = fn(string $t): string => str_replace([',', ';', "\n"], ['\\,', '\;', '\\n'], $t);
    $titulo = $lang === 'en' ? 'Table at Sabor Mediterráneo' : 'Mesa en Sabor Mediterráneo';
    $desc = ($lang === 'en' ? 'Booking code' : 'Código de reserva') . ': ' . $r['codigo'] . ' · ' . $r['personas'] . ($lang === 'en' ? ' guests' : ' personas');
    return implode("\r\n", [
        'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Sabor Mediterraneo//Reservas//ES', 'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . $r['codigo'] . '@sabormediterraneo',
        'DTSTAMP:' . $utc(new DateTimeImmutable()),
        'DTSTART:' . $utc($inicio), 'DTEND:' . $utc($fin),
        'SUMMARY:' . $esc($titulo), 'DESCRIPTION:' . $esc($desc), 'LOCATION:' . $esc('Sabor Mediterráneo, Granada'),
        'BEGIN:VALARM', 'TRIGGER:-PT2H', 'ACTION:DISPLAY', 'DESCRIPTION:' . $esc($titulo), 'END:VALARM',
        'END:VEVENT', 'END:VCALENDAR', '',
    ]);
}

/**
 * Email de una reserva.
 * $tipo: confirmacion | recordatorio | cambio | cancelacion
 */
function correoReserva(PDO $pdo, int $reservaId, string $tipo): bool
{
    $r = datosReserva($pdo, $reservaId);
    if (!$r || !$r['email']) return false;
    $en = $r['idioma'] === 'en';
    $lang = $en ? 'en' : 'es';
    $nombre = explode(' ', trim($r['nombre']))[0];
    $web = rtrim((string) config('url_web'), '/') . '/';
    $gestionar = $web . '?reserva=' . rawurlencode($r['codigo']) . ($en ? '&lang=en' : '') . '#reservas';
    $zona = $en ? ($r['zona_en'] ?: $r['zona']) : $r['zona'];

    $filas = [
        [$en ? 'Booking code' : 'Código de reserva', $r['codigo']],
        [$en ? 'Date' : 'Día', fechaEmail($r['fecha'], $lang)],
        [$en ? 'Time' : 'Hora', $r['hora']],
        [$en ? 'Guests' : 'Personas', (string) $r['personas']],
    ];
    if ($zona) $filas[] = [$en ? 'Area' : 'Zona', $zona];
    $botonGestion = [$en ? 'Manage my booking' : 'Gestionar mi reserva', $gestionar];
    $horasCancel = (int) (config('horas_min_cancelacion') ?? 2);

    switch ($tipo) {
        case 'confirmacion':
            $asunto = $en ? "Booking confirmed · {$r['codigo']}" : "Reserva confirmada · {$r['codigo']}";
            [$html, $texto] = plantillaCorreo($lang,
                $en ? "See you soon, $nombre!" : "¡Te esperamos, $nombre!",
                $en ? 'Your table is booked. Keep this email: you will need the code and your phone number to change or cancel.' : 'Tu mesa está reservada. Guarda este email: con el código y tu teléfono puedes cambiarla o cancelarla.',
                $filas, $botonGestion,
                $en ? "You can change or cancel online up to $horasCancel hours before. The attached file adds the booking to your calendar." : "Puedes cambiarla o cancelarla desde la web hasta $horasCancel horas antes. El archivo adjunto la añade a tu calendario.");
            return enviarCorreo($r['email'], $asunto, $html, $texto, ['reserva.ics', 'text/calendar', icsReserva($r, $lang)]);

        case 'recordatorio':
            $asunto = $en ? "Tomorrow at {$r['hora']} · Sabor Mediterráneo" : "Mañana a las {$r['hora']} · Sabor Mediterráneo";
            [$html, $texto] = plantillaCorreo($lang,
                $en ? "See you tomorrow, $nombre" : "Nos vemos mañana, $nombre",
                $en ? 'A quick reminder of your booking. If you cannot make it, please let us know so we can give the table to someone on the waiting list.' : 'Te recordamos tu reserva. Si al final no puedes venir, avísanos y se la damos a alguien de la lista de espera.',
                $filas, $botonGestion);
            return enviarCorreo($r['email'], $asunto, $html, $texto);

        case 'cambio':
            $asunto = $en ? "Booking changed · {$r['codigo']}" : "Reserva cambiada · {$r['codigo']}";
            [$html, $texto] = plantillaCorreo($lang,
                $en ? 'Your booking has been changed' : 'Hemos cambiado tu reserva',
                $en ? 'These are the new details. Your code stays the same.' : 'Estos son los datos nuevos. Tu código sigue siendo el mismo.',
                $filas, $botonGestion);
            return enviarCorreo($r['email'], $asunto, $html, $texto, ['reserva.ics', 'text/calendar', icsReserva($r, $lang)]);

        case 'cancelacion':
            $asunto = $en ? "Booking cancelled · {$r['codigo']}" : "Reserva cancelada · {$r['codigo']}";
            [$html, $texto] = plantillaCorreo($lang,
                $en ? 'Your booking has been cancelled' : 'Tu reserva está cancelada',
                $en ? "Thanks for letting us know, $nombre. We hope to see you another day." : "Gracias por avisarnos, $nombre. Esperamos verte otro día.",
                $filas, [$en ? 'Book another day' : 'Reservar otro día', $web . ($en ? '?lang=en' : '') . '#reservas']);
            return enviarCorreo($r['email'], $asunto, $html, $texto);
    }
    return false;
}

/* =========================================================
   4. LISTA DE ESPERA
   ========================================================= */

/** Email al apuntarse a la lista de espera. */
function correoEsperaApuntado(array $e): bool
{
    $en = $e['idioma'] === 'en';
    $lang = $en ? 'en' : 'es';
    $turno = $e['turno'] === 'cena' ? ($en ? 'dinner' : 'cena') : ($en ? 'lunch' : 'comida');
    [$html, $texto] = plantillaCorreo($lang,
        $en ? "You're on the waiting list" : 'Estás en la lista de espera',
        $en ? 'As soon as a table frees up for your group, we will email you a link to book it. First to book gets it.' : 'En cuanto quede libre una mesa para tu grupo te mandamos un email con un enlace para reservarla. Se la queda quien reserve primero.',
        [[$en ? 'Date' : 'Día', fechaEmail($e['fecha'], $lang)], [$en ? 'Sitting' : 'Turno', $turno], [$en ? 'Guests' : 'Personas', (string) $e['personas']]]);
    return enviarCorreo($e['email'], $en ? 'Waiting list · Sabor Mediterráneo' : 'Lista de espera · Sabor Mediterráneo', $html, $texto);
}

/**
 * Tras una cancelación o un cambio: avisa a quien esté esperando ese día y
 * turno si ahora hay mesa para su grupo. Se avisa a todos los que quepan;
 * se queda la mesa quien reserve primero (la reserva sigue el proceso normal,
 * con cerrojo, así que nunca se dan dos veces).
 * @return int cuántas personas se han avisado
 */
function avisarListaEspera(PDO $pdo, string $fecha, string $turno): int
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM lista_espera WHERE fecha = ? AND turno = ? AND estado = 'esperando' ORDER BY creado_en");
        $stmt->execute([$fecha, $turno]);
        $esperando = $stmt->fetchAll();
    } catch (PDOException) {
        return 0; // sin la migración 008
    }
    if (!$esperando) return 0;

    $dia = new DateTimeImmutable($fecha);
    $web = rtrim((string) config('url_web'), '/') . '/';
    $marcar = $pdo->prepare("UPDATE lista_espera SET estado = 'avisado', avisado_en = NOW() WHERE id = ? AND estado = 'esperando'");
    $avisados = 0;
    $cache = [];
    foreach ($esperando as $e) {
        $n = (int) $e['personas'];
        $cache[$n] ??= array_values(array_filter(horasLibres($pdo, $dia, $n), fn($h) => $h['turno'] === $turno && $h['libre']));
        if (!$cache[$n]) continue;

        $en = $e['idioma'] === 'en';
        $lang = $en ? 'en' : 'es';
        $horas = implode(', ', array_column(array_slice($cache[$n], 0, 6), 'hora'));
        $enlace = $web . '?fecha=' . $fecha . '&personas=' . $n . ($en ? '&lang=en' : '') . '#reservas';
        [$html, $texto] = plantillaCorreo($lang,
            $en ? 'A table has just freed up!' : '¡Se ha liberado una mesa!',
            $en ? "Good news, {$e['nombre']}: someone has cancelled and there is now room for your group. The first person to book gets it, so don't wait too long." : "Buenas noticias, {$e['nombre']}: alguien ha cancelado y ahora hay sitio para tu grupo. Se la queda quien reserve primero, así que no tardes mucho.",
            [[$en ? 'Date' : 'Día', fechaEmail($fecha, $lang)], [$en ? 'Free times' : 'Horas libres', $horas], [$en ? 'Guests' : 'Personas', (string) $n]],
            [$en ? 'Book now' : 'Reservar ahora', $enlace]);
        if (enviarCorreo($e['email'], $en ? 'A table is free · Sabor Mediterráneo' : 'Hay mesa libre · Sabor Mediterráneo', $html, $texto) || !config('correo_activo')) {
            // Sin correo activado se marca igual: el personal lo ve en el panel y llama
            $marcar->execute([$e['id']]);
            $avisados++;
        }
    }
    return $avisados;
}
