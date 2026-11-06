<?php
/**
 * Funciones comunes de la API:
 *  - db():        conexión PDO única a MySQL
 *  - config():    lee config.php
 *  - responder(): devuelve JSON y termina
 *  - horasDelDia(): calcula las horas que se ofrecen un día concreto
 */
declare(strict_types=1);

date_default_timezone_set('Europe/Madrid');

/* ---------- Idioma (?lang=en) ---------- */

/** Idioma pedido por la web: 'en' con ?lang=en; cualquier otra cosa, 'es'. */
function idioma(): string
{
    return ($_GET['lang'] ?? '') === 'en' ? 'en' : 'es';
}

/**
 * Columna en el idioma pedido. En inglés usa la columna _en y, si está vacía,
 * la española: col('p.nombre') → COALESCE(NULLIF(p.nombre_en, ''), p.nombre)
 * Solo se llama con nombres de columna escritos en el código, nunca con datos.
 */
function col(string $columna): string
{
    return idioma() === 'en' ? "COALESCE(NULLIF({$columna}_en, ''), $columna)" : $columna;
}

/** Mensaje para el cliente en su idioma. */
function tr(string $es, string $en): string
{
    return idioma() === 'en' ? $en : $es;
}

function config(string $clave)
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config[$clave] ?? null;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            config('db_host'), config('db_port'), config('db_name')
        );
        $pdo = new PDO($dsn, config('db_user'), config('db_pass'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // los errores lanzan excepciones
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,                  // consultas preparadas reales
        ]);
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    }
    return $pdo;
}

function responder(array $datos, int $codigo = 200): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $json = json_encode($datos, JSON_UNESCAPED_UNICODE);
    // Con PHP-FPM, Apache no sabe cuánto mide la respuesta si no se lo decimos:
    // sin Content-Length los clientes HTTP/1.0 no pueden reutilizar la conexión
    header('Content-Length: ' . strlen($json));
    echo $json;
    // Con PHP-FPM la respuesta se entrega ya y lo de despues() (emails) sigue
    // en segundo plano: el cliente no espera a que salga ningún correo
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    exit;
}

/** Deja una tarea (enviar un email...) para cuando ya se haya respondido. */
function despues(callable $tarea): void
{
    register_shutdown_function(function () use ($tarea) {
        try {
            $tarea();
        } catch (Throwable $e) {
            error_log('Tarea en segundo plano: ' . $e->getMessage());
        }
    });
}

/* =========================================================
   REDIS (opcional)
   Base de datos en memoria. La usamos para tres cosas:
     1. Caché de la carta y del horario (lo que más se pide y casi no cambia).
     2. Límite de peticiones y bloqueo del login (contadores atómicos que
        caducan solos).
     3. Sesiones del panel (admin/inc/bootstrap.php).
   Todo tiene plan B: si Redis no está instalado, no está activado o no
   responde, se usa lo de siempre (MariaDB y archivos temporales).
   ========================================================= */

/** Conexión a Redis, o null si no se puede usar. Se intenta una sola vez por petición. */
function redis(): ?Redis
{
    static $conexion = false;
    if ($conexion !== false) return $conexion;
    $conexion = null;

    $cfg = config('redis');
    if (empty($cfg['activo']) || !class_exists('Redis')) return null;
    try {
        $r = new Redis();
        if (!$r->connect($cfg['host'], (int) $cfg['puerto'], 0.3)) return null; // 0,3 s como mucho
        if (($cfg['password'] ?? '') !== '' && !$r->auth($cfg['password'])) return null;
        $r->setOption(Redis::OPT_PREFIX, $cfg['prefijo'] ?? 'sabor:');
        $conexion = $r;
    } catch (Throwable $e) {
        error_log('[REDIS] ' . $e->getMessage()); // queda anotado, pero la web sigue
    }
    return $conexion;
}

/**
 * Caché de una respuesta: si está en Redis se devuelve sin tocar MariaDB;
 * si no, se genera con $generar y se guarda $segundos. La cabecera X-Cache
 * dice si ha venido de la caché (HIT) o de la base de datos (MISS).
 */
function enCache(string $clave, callable $generar): array
{
    $r = redis();
    if ($r) {
        try {
            $guardado = $r->get('cache:' . $clave);
            if (is_string($guardado) && is_array($datos = json_decode($guardado, true))) {
                header('X-Cache: HIT');
                return $datos;
            }
        } catch (Throwable $e) {
            error_log('[REDIS] ' . $e->getMessage());
        }
    }
    $datos = $generar();
    if ($r) {
        try {
            $r->setex('cache:' . $clave, (int) (config('redis')['cache_segundos'] ?? 600), json_encode($datos, JSON_UNESCAPED_UNICODE));
        } catch (Throwable $e) {
            error_log('[REDIS] ' . $e->getMessage());
        }
    }
    header('X-Cache: ' . ($r ? 'MISS' : 'OFF'));
    return $datos;
}

/** Borra toda la caché (se llama cuando el panel cambia algo). */
function vaciarCache(): void
{
    $r = redis();
    if (!$r) return;
    try {
        $prefijo = config('redis')['prefijo'] ?? 'sabor:';
        $r->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
        $it = null;
        // SCAN recorre las claves poco a poco, sin bloquear Redis (KEYS lo bloquearía)
        while ($claves = $r->scan($it, $prefijo . 'cache:*', 100)) {
            foreach ($claves as $c) $r->unlink(substr($c, strlen($prefijo)));
        }
    } catch (Throwable $e) {
        error_log('[REDIS] ' . $e->getMessage());
    }
}

/**
 * Límite de peticiones por IP (rate limiting).
 *
 * Evita que un bot llene la base de datos de reservas falsas o pruebe
 * miles de códigos de reserva: cada IP puede hacer como mucho $max
 * peticiones de un tipo cada $ventana segundos. Si se pasa, responde
 * 429 Too Many Requests con la cabecera Retry-After.
 *
 * Se guarda en un archivo pequeño por IP en la carpeta temporal del
 * sistema (no hace falta tabla ni Redis). flock() evita que dos
 * peticiones a la vez escriban el archivo al mismo tiempo.
 * La IP se guarda como hash, nunca en claro (protección de datos).
 */
function limitar(string $accion, int $max, int $ventana): void
{
    $espera = redis() ? limitarRedis(claveIp($accion), $max, $ventana) : contador(claveIp($accion), $ventana, function (array $marcas) use ($max, $ventana): array {
        if (count($marcas) >= $max) {
            return [$marcas, max(1, $marcas[0] + $ventana - time())];
        }
        $marcas[] = time();
        return [$marcas, 0];
    });
    if ($espera) {
        header('Retry-After: ' . $espera);
        responder(['ok' => false, 'error' => tr('Has hecho demasiados intentos seguidos. Espera ' . ceil($espera / 60) . ' min o llámanos al 958 000 000.', 'Too many attempts in a row. Please wait ' . ceil($espera / 60) . ' min or call us on +34 958 000 000.')], 429);
    }
}

/** Clave del contador para esta IP. */
function claveIp(string $accion): string
{
    return $accion . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'cli');
}

/**
 * Lee las marcas de tiempo (de los últimos $ventana segundos) guardadas para
 * $clave, se las pasa a $fn y guarda las que $fn devuelva.
 * $fn devuelve [marcas nuevas, resultado]; contador() devuelve el resultado.
 */
function contador(string $clave, int $ventana, callable $fn): mixed
{
    $dir = sys_get_temp_dir() . '/sabor-limites';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $fh = @fopen($dir . '/' . hash('sha256', $clave) . '.json', 'c+');
    if (!$fh) return $fn([])[1]; // si no se puede escribir, no bloqueamos a nadie

    flock($fh, LOCK_EX);
    $limite = time() - $ventana;
    $marcas = json_decode(stream_get_contents($fh) ?: '[]', true) ?: [];
    $marcas = array_values(array_filter($marcas, fn($t) => is_int($t) && $t > $limite));
    [$nuevas, $resultado] = $fn($marcas);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode(array_values($nuevas)));
    flock($fh, LOCK_UN);
    fclose($fh);
    return $resultado;
}

/**
 * Ventana deslizante en Redis: cada petición es un elemento de un "sorted set"
 * cuya puntuación es su hora. En UNA operación atómica (MULTI) se borran los
 * de fuera de la ventana, se añade el nuevo y se cuentan. Si se pasa del
 * máximo, se quita el que se acaba de añadir y se bloquea.
 * Devuelve los segundos de espera (0 = puede pasar).
 */
function limitarRedis(string $clave, int $max, int $ventana): int
{
    $r = redis();
    $k = 'lim:' . hash('sha256', $clave);
    $ahora = microtime(true);
    $yo = sprintf('%.6f-%s', $ahora, bin2hex(random_bytes(4)));
    try {
        [, , $total] = $r->multi()
            ->zRemRangeByScore($k, '-inf', (string) ($ahora - $ventana))
            ->zAdd($k, $ahora, $yo)
            ->zCard($k)
            ->expire($k, $ventana)
            ->exec();
        if ($total <= $max) return 0;
        $r->zRem($k, $yo);
        $primero = $r->zRange($k, 0, 0, true);
        return max(1, (int) ceil(reset($primero) + $ventana - $ahora));
    } catch (Throwable $e) {
        error_log('[REDIS] ' . $e->getMessage());
        return 0; // si Redis falla a mitad, no bloqueamos a nadie
    }
}

/** Segundos que faltan para poder volver a intentarlo (0 = no está bloqueado). */
function segundosBloqueado(string $clave, int $max, int $ventana): int
{
    if ($r = redis()) {
        try {
            $k = 'lim:' . hash('sha256', $clave);
            $ahora = microtime(true);
            $r->zRemRangeByScore($k, '-inf', (string) ($ahora - $ventana));
            if ($r->zCard($k) < $max) return 0;
            $primero = $r->zRange($k, 0, 0, true);
            return max(1, (int) ceil(reset($primero) + $ventana - $ahora));
        } catch (Throwable $e) {
            error_log('[REDIS] ' . $e->getMessage());
        }
    }
    return contador($clave, $ventana, fn(array $m): array => [$m, count($m) >= $max ? max(1, $m[0] + $ventana - time()) : 0]);
}

function anotarFallo(string $clave, int $ventana): void
{
    if ($r = redis()) {
        try {
            $k = 'lim:' . hash('sha256', $clave);
            $r->multi()->zAdd($k, microtime(true), uniqid('', true))->expire($k, $ventana)->exec();
            return;
        } catch (Throwable $e) {
            error_log('[REDIS] ' . $e->getMessage());
        }
    }
    contador($clave, $ventana, fn(array $m): array => [[...$m, time()], null]);
}

function olvidarFallos(string $clave): void
{
    if ($r = redis()) {
        try {
            $r->del('lim:' . hash('sha256', $clave));
        } catch (Throwable $e) {
            error_log('[REDIS] ' . $e->getMessage());
        }
    }
    contador($clave, 1, fn(): array => [[], null]);
}

/**
 * Cerrojo con nombre de MySQL/MariaDB (GET_LOCK).
 *
 * Problema: si quedan una mesa libre y dos personas reservan a la vez, las
 * dos consultas pueden ver la mesa libre y quedarse con la misma.
 * Solución: todo lo que asigna mesas se ejecuta dentro de este cerrojo, de
 * uno en uno. La segunda petición espera (máximo 10 s) a que la primera
 * termine y, al entrar, ya ve la mesa ocupada.
 *
 * El cerrojo se pide ANTES de empezar la transacción: así la transacción
 * "ve" la base de datos tal y como la dejó la petición anterior.
 * Si el script termina de golpe, MySQL lo suelta solo al cerrar la conexión.
 */
function conCerrojo(string $nombre, callable $fn): mixed
{
    $pdo = db();
    $pedir = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $pedir->execute(['sabor_' . $nombre]);
    if ((int) $pedir->fetchColumn() !== 1) {
        throw new RuntimeException("El cerrojo «{$nombre}» sigue ocupado tras 10 segundos");
    }
    try {
        return $fn($pdo);
    } finally {
        $soltar = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $soltar->execute(['sabor_' . $nombre]);
        $soltar->closeCursor();
    }
}

/** Ejecuta la función de un endpoint y convierte cualquier fallo en un JSON de error. */
function ejecutar(callable $endpoint): void
{
    try {
        $endpoint();
    } catch (PDOException $e) {
        error_log('[API] ' . $e->getMessage());
        responder(['ok' => false, 'error' => tr('No hemos podido conectar con el sistema de reservas. Llámanos al 958 000 000.', 'We could not reach the booking system. Please call us on +34 958 000 000.')], 500);
    } catch (Throwable $e) {
        error_log('[API] ' . $e->getMessage());
        responder(['ok' => false, 'error' => tr('Ha ocurrido un error inesperado.', 'Something went wrong. Please try again.')], 500);
    }
}

/**
 * Lee un campo de texto del JSON recibido. Si alguien manda un objeto o una
 * lista en lugar de texto (p. ej. {"nombre": {"a": 1}}), se trata como vacío
 * en vez de convertirlo en la palabra "Array".
 */
function texto(array $datos, string $campo): string
{
    $v = $datos[$campo] ?? '';
    return is_scalar($v) ? (string) $v : '';
}

/**
 * Comprueba que se puede reservar ese día a esa hora (la usan crear y cambiar
 * reserva, así las reglas son las mismas). Devuelve [campo, mensaje] si hay
 * un problema, o null si está todo bien.
 */
function problemaFechaHora(?DateTimeImmutable $fecha, string $hora): ?array
{
    if (!$fecha) return ['fecha', tr('Elige un día.', 'Please choose a day.')];
    $hoy = new DateTimeImmutable('today');
    if ($fecha < $hoy || $fecha > $hoy->modify('+' . config('dias_max_reserva') . ' days')) {
        $dias = (int) config('dias_max_reserva');
        return ['fecha', tr("Solo aceptamos reservas desde hoy hasta dentro de $dias días.", "We take bookings from today up to $dias days ahead.")];
    }
    if (!in_array($hora, array_column(horasDelDia($fecha), 'hora'), true)) {
        return ['hora', tr('Esa hora no está disponible ese día.', 'That time is not available that day.')];
    }
    if ($fecha == $hoy && aMinutos($hora) <= (int) date('G') * 60 + (int) date('i') + 60) {
        return ['hora', tr('Para hoy, reserva con al menos una hora de antelación.', 'For today, please book at least one hour in advance.')];
    }
    return null;
}

/**
 * ¿Tiene este cliente otra reserva activa ese día a menos de 3 horas?
 * Devuelve su hora ("14:00") o null. $excluir = id de la reserva que se está cambiando.
 */
function reservaSolapada(PDO $pdo, int $clienteId, DateTimeImmutable $fecha, string $hora, int $excluir = 0): ?string
{
    $stmt = $pdo->prepare(
        "SELECT TIME_FORMAT(hora, '%H:%i') FROM reservas
         WHERE cliente_id = ? AND fecha = ? AND id <> ? AND estado IN ('pendiente', 'confirmada')"
    );
    $stmt->execute([$clienteId, $fecha->format('Y-m-d'), $excluir]);
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $otra) {
        if (abs(aMinutos($otra) - aMinutos($hora)) < 180) return $otra;
    }
    return null;
}

/** Valida una fecha en formato AAAA-MM-DD. */
function fechaValida(string $fecha): ?DateTimeImmutable
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
    return ($d && $d->format('Y-m-d') === $fecha) ? $d : null;
}

/**
 * Horas que se ofrecen un día: cada 30 min desde la apertura hasta
 * 60 min antes del cierre de cada turno. Devuelve [] si el día está cerrado.
 * @return array<int, array{hora: string, turno: string}>
 */
function horasDelDia(DateTimeImmutable $fecha): array
{
    $pdo = db();

    $cerrado = $pdo->prepare('SELECT 1 FROM dias_cerrados WHERE fecha = ?');
    $cerrado->execute([$fecha->format('Y-m-d')]);
    if ($cerrado->fetchColumn()) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT turno, TIME_FORMAT(apertura, '%H:%i') AS apertura, TIME_FORMAT(cierre, '%H:%i') AS cierre
         FROM horarios
         WHERE dia_semana = ? AND apertura IS NOT NULL
         ORDER BY apertura"
    );
    $stmt->execute([(int) $fecha->format('N')]); // N: 1 = lunes ... 7 = domingo

    $horas = [];
    foreach ($stmt as $t) {
        $inicio = aMinutos($t['apertura']);
        $fin = aMinutos($t['cierre']) - (int) config('margen_cierre');
        for ($m = $inicio; $m <= $fin; $m += (int) config('intervalo_horas')) {
            $horas[] = ['hora' => sprintf('%02d:%02d', intdiv($m, 60) % 24, $m % 60), 'turno' => $t['turno']];
        }
    }
    return $horas;
}

/**
 * Horas de un día y si queda alguna mesa libre a cada hora para un grupo.
 * Una hora está libre si hay una mesa activa donde quepa el grupo sin otra
 * reserva a menos de 'duracion_reserva' minutos.
 * $excluir = código de una reserva que se está cambiando (su mesa cuenta como libre).
 * @return list<array{hora: string, turno: string, libre: bool}>
 */
function horasLibres(PDO $pdo, DateTimeImmutable $fecha, int $personas, string $excluir = ''): array
{
    $horas = horasDelDia($fecha);
    if (!$horas) return [];

    $stmt = $pdo->prepare('SELECT id FROM mesas WHERE activa = 1 AND capacidad >= ?');
    $stmt->execute([$personas]);
    $mesas = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $stmt = $pdo->prepare(
        "SELECT mesa_id, TIME_FORMAT(hora, '%H:%i') AS hora
         FROM reservas
         WHERE fecha = ? AND mesa_id IS NOT NULL AND estado IN ('pendiente', 'confirmada') AND codigo <> ?"
    );
    $stmt->execute([$fecha->format('Y-m-d'), strtoupper(substr($excluir, 0, 6))]);
    $ocupadas = $stmt->fetchAll();

    $esHoy    = $fecha->format('Y-m-d') === date('Y-m-d');
    $ahora    = (int) date('G') * 60 + (int) date('i');
    $duracion = (int) config('duracion_reserva');

    $slots = [];
    foreach ($horas as $h) {
        $min = aMinutos($h['hora']);
        // Hoy no se puede reservar para dentro de menos de una hora
        if ($esHoy && $min <= $ahora + 60) {
            $slots[] = $h + ['libre' => false];
            continue;
        }
        $bloqueadas = [];
        foreach ($ocupadas as $r) {
            if (abs(aMinutos($r['hora']) - $min) < $duracion) $bloqueadas[(int) $r['mesa_id']] = true;
        }
        $slots[] = $h + ['libre' => (bool) array_filter($mesas, fn($id) => !isset($bloqueadas[$id]))];
    }
    return $slots;
}

/** Turno (comida / cena) al que pertenece una hora de un día. */
function turnoDe(DateTimeImmutable $fecha, string $hora): string
{
    foreach (horasDelDia($fecha) as $h) {
        if ($h['hora'] === substr($hora, 0, 5)) return $h['turno'];
    }
    return substr($hora, 0, 5) < '17:00' ? 'comida' : 'cena';
}

function aMinutos(string $hhmm): int
{
    [$h, $m] = array_map('intval', explode(':', $hhmm));
    return ($h === 0 ? 24 : $h) * 60 + $m;
}
