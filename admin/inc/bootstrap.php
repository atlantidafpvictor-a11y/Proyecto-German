<?php
/**
 * Se incluye al principio de cada página del panel.
 * Sesión segura, protección CSRF, control de acceso y plantilla común.
 */
declare(strict_types=1);

require __DIR__ . '/../../api/db.php';

/* ---------- Sesión ---------- */
ini_set('session.use_strict_mode', '1'); // rechaza identificadores de sesión inventados (session fixation)
session_name('sm_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,                       // JavaScript no puede leer la cookie
    'samesite' => 'Strict',                   // no se envía desde otras webs
    'secure'   => !empty($_SERVER['HTTPS']),  // solo por HTTPS si está disponible
]);
session_start();

// Cierra la sesión tras 30 minutos sin actividad
if (isset($_SESSION['ultima_actividad']) && time() - $_SESSION['ultima_actividad'] > 1800) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['flash'] = ['tipo' => 'info', 'texto' => 'Tu sesión ha caducado. Vuelve a entrar.'];
}
$_SESSION['ultima_actividad'] = time();

// Si algo falla (por ejemplo, MySQL apagado), mostramos una página clara en vez de un error técnico
set_exception_handler(function (Throwable $e) {
    error_log('[ADMIN] ' . $e->getMessage());
    http_response_code(500);
    $esBd = $e instanceof PDOException;
    echo '<!DOCTYPE html><html lang="es"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Error · Panel</title><link rel="stylesheet" href="admin.css"><body class="no-nav"><main class="main"><div class="empty">'
       . '<h2>' . ($esBd ? 'No se puede conectar con la base de datos' : 'Algo ha fallado') . '</h2>'
       . '<p>' . ($esBd ? 'Comprueba que MySQL está arrancado en XAMPP y que has importado <code>database/restaurante.sql</code>. Los datos de conexión están en <code>api/config.php</code>.' : 'Vuelve a intentarlo en unos segundos.') . '</p>'
       . '</div></main></body></html>';
});

/* ---------- Cabeceras de seguridad ----------
   El .htaccess de la raíz pone las de toda la web; estas son las del panel,
   más estrictas, y funcionan aunque Apache no tenga mod_headers. */
const ADMIN_VERSION = '20261106';
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('Cache-Control: no-store'); // los datos del panel nunca se guardan en la caché del navegador

/* ---------- Utilidades ---------- */

/** Escapa texto para mostrarlo en HTML (evita XSS). */
function e(?string $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

function euro($n): string
{
    return number_format((float) $n, 2, ',', '.') . ' €';
}

function fechaLarga(string $fecha): string
{
    $dias  = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $d = new DateTimeImmutable($fecha);
    return $dias[(int) $d->format('N') - 1] . ' ' . $d->format('j') . ' de ' . $meses[(int) $d->format('n') - 1];
}

function redirigir(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $tipo, string $texto): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

/* ---------- CSRF: un token secreto por sesión en cada formulario ---------- */
function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function campoCsrf(): string
{
    return '<input type="hidden" name="csrf" value="' . csrfToken() . '">';
}

function comprobarCsrf(): void
{
    if (!hash_equals(csrfToken(), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Formulario caducado. Vuelve atrás y recarga la página.');
    }
}

/* ---------- Estado del servidor (lo escribe scripts/monitor.sh cada 5 min) ---------- */

/**
 * Lee el último informe del servidor. null si no existe (XAMPP o monitorización
 * sin instalar). Añade 'minutos' (antigüedad) y marca 'viejo' si tiene más de
 * 15 minutos: eso significa que cron o el script han dejado de funcionar.
 */
function estadoServidor(): ?array
{
    $ruta = (string) config('estado_servidor');
    if ($ruta === '' || !is_readable($ruta)) return null;
    $datos = json_decode((string) file_get_contents($ruta), true);
    if (!is_array($datos) || !isset($datos['checks'])) return null;
    $datos['minutos'] = (int) floor((time() - (int) strtotime((string) $datos['generado'])) / 60);
    $datos['viejo'] = $datos['minutos'] > 15;
    return $datos;
}

/* ---------- Control de acceso ---------- */
/**
 * Usuario identificado, comprobado contra la base de datos en cada petición:
 * si un administrador le quita el acceso o le cambia el rol, se aplica al
 * momento, sin esperar a que caduque su sesión.
 */
function usuario(): ?array
{
    static $comprobado = false;
    if (!$comprobado && isset($_SESSION['usuario'])) {
        $comprobado = true;
        $stmt = db()->prepare('SELECT nombre, email, rol FROM usuarios WHERE id = ? AND activo = 1');
        $stmt->execute([$_SESSION['usuario']['id']]);
        $fila = $stmt->fetch();
        if (!$fila) {
            unset($_SESSION['usuario']);
            session_regenerate_id(true);
            $_SESSION['flash'] = ['tipo' => 'error', 'texto' => 'Tu acceso al panel ya no está activo.'];
        } else {
            $_SESSION['usuario'] = ['id' => (int) $_SESSION['usuario']['id']] + $fila;
        }
    }
    return $_SESSION['usuario'] ?? null;
}

/** Obliga a estar identificado. Si se pasa 'admin', además exige ese rol. */
function exigirLogin(?string $rol = null): array
{
    $u = usuario();
    if (!$u) {
        redirigir('login.php');
    }
    if ($rol === 'admin' && $u['rol'] !== 'admin') {
        http_response_code(403);
        cabecera('Sin permiso', '');
        echo '<div class="empty"><h2>No tienes permiso para ver esta página</h2><p>Pide a un administrador que te dé acceso.</p><a class="btn" href="index.php">Volver al inicio</a></div>';
        pie();
        exit;
    }
    return $u;
}

/* ---------- Plantilla ---------- */
function cabecera(string $titulo, string $activa): void
{
    $u = usuario();
    $pendientes = 0;
    $sinLeer = 0;
    if ($u) {
        try {
            $pendientes = (int) db()->query("SELECT COUNT(*) FROM reservas WHERE estado = 'pendiente' AND fecha >= CURRENT_DATE")->fetchColumn();
            $sinLeer    = (int) db()->query('SELECT COUNT(*) FROM mensajes_contacto WHERE leido = 0')->fetchColumn();
        } catch (PDOException) {
        }
    }
    $enlace = function (string $href, string $id, string $texto, int $badge = 0) use ($activa): string {
        $b = $badge ? ' <span class="badge">' . $badge . '</span>' : '';
        return '<a href="' . $href . '"' . ($activa === $id ? ' aria-current="page"' : '') . '>' . $texto . $b . '</a>';
    };
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title><?= e($titulo) ?> · Panel Sabor Mediterráneo</title>
    <link rel="icon" href="../favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="admin.css?v=<?= ADMIN_VERSION ?>">
    <script src="admin.js?v=<?= ADMIN_VERSION ?>" defer></script>
</head>
<body class="<?= $u ? 'with-nav' : 'no-nav' ?>">
<?php if ($u): ?>
    <aside class="sidebar">
        <a class="brand" href="index.php">
            <svg viewBox="0 0 40 48" aria-hidden="true"><path d="M2 47V20C2 10 10 2 20 2s18 8 18 18v27" fill="none" stroke="currentColor" stroke-width="3"/><path d="M20 14l3.5 6.5L30 24l-6.5 3.5L20 34l-3.5-6.5L10 24l6.5-3.5z" fill="currentColor"/></svg>
            <span>Sabor<br>Mediterráneo</span>
        </a>
        <nav aria-label="Panel">
            <?= $enlace('index.php', 'inicio', 'Hoy') ?>
            <?= $enlace('reservas.php', 'reservas', 'Reservas', $pendientes) ?>
            <?php if ($u['rol'] === 'admin'): ?>
                <?= $enlace('carta.php', 'carta', 'Carta') ?>
                <?= $enlace('qr.php', 'qr', 'Códigos QR') ?>
                <?= $enlace('eventos.php', 'eventos', 'Eventos') ?>
                <?= $enlace('galeria.php', 'galeria', 'Galería') ?>
                <?= $enlace('opiniones.php', 'opiniones', 'Opiniones') ?>
                <?= $enlace('mensajes.php', 'mensajes', 'Mensajes', $sinLeer) ?>
                <?= $enlace('usuarios.php', 'usuarios', 'Equipo') ?>
                <?= $enlace('estado.php', 'estado', 'Estado del servidor') ?>
            <?php endif; ?>
        </nav>
        <div class="me">
            <p><strong><?= e($u['nombre']) ?></strong><br><span><?= $u['rol'] === 'admin' ? 'Administración' : 'Sala' ?></span></p>
            <a href="../index.html" target="_blank" rel="noopener">Ver la web</a>
            <form method="post" action="logout.php"><?= campoCsrf() ?><button type="submit" class="link">Cerrar sesión</button></form>
        </div>
    </aside>
<?php endif; ?>
    <main class="main">
        <?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
            <p class="flash flash-<?= e($f['tipo']) ?>" role="status"><?= e($f['texto']) ?></p>
        <?php endif; ?>
<?php
}

function pie(): void
{
    echo "\n    </main>\n</body>\n</html>\n";
}
