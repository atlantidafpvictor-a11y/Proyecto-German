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
