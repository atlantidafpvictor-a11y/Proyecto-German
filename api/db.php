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
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
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
    $espera = contador(claveIp($accion), $ventana, function (array $marcas) use ($max, $ventana): array {
        if (count($marcas) >= $max) {
            return [$marcas, max(1, $marcas[0] + $ventana - time())];
        }
        $marcas[] = time();
        return [$marcas, 0];
    });
    if ($espera) {
        header('Retry-After: ' . $espera);
        responder(['ok' => false, 'error' => 'Has hecho demasiados intentos seguidos. Espera ' . ceil($espera / 60) . ' min o llámanos al 958 000 000.'], 429);
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

/** Segundos que faltan para poder volver a intentarlo (0 = no está bloqueado). */
function segundosBloqueado(string $clave, int $max, int $ventana): int
{
    return contador($clave, $ventana, fn(array $m): array => [$m, count($m) >= $max ? max(1, $m[0] + $ventana - time()) : 0]);
}

function anotarFallo(string $clave, int $ventana): void
{
    contador($clave, $ventana, fn(array $m): array => [[...$m, time()], null]);
}

function olvidarFallos(string $clave): void
{
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
        responder(['ok' => false, 'error' => 'No hemos podido conectar con el sistema de reservas. Llámanos al 958 000 000.'], 500);
    } catch (Throwable $e) {
        error_log('[API] ' . $e->getMessage());
        responder(['ok' => false, 'error' => 'Ha ocurrido un error inesperado.'], 500);
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

function aMinutos(string $hhmm): int
{
    [$h, $m] = array_map('intval', explode(':', $hhmm));
    return ($h === 0 ? 24 : $h) * 60 + $m;
}
