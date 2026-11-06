<?php
/**
 * Se incluye al principio de cada página del panel.
 * Sesión segura, protección CSRF, control de acceso y plantilla común.
 */
declare(strict_types=1);

require __DIR__ . '/../../api/db.php';

/* ---------- Sesión ---------- */
// Con Redis activo, las sesiones del panel se guardan en Redis en vez de en
// archivos: así funcionarían igual con varios servidores web detrás de uno.
$cfgRedis = config('redis');
if (!empty($cfgRedis['activo']) && extension_loaded('redis') && redis()) {
    ini_set('session.save_handler', 'redis');
    ini_set('session.save_path', sprintf(
        'tcp://%s:%d?auth=%s&prefix=%s',
        $cfgRedis['host'], (int) $cfgRedis['puerto'], rawurlencode((string) $cfgRedis['password']), ($cfgRedis['prefijo'] ?? 'sabor:') . 'sesion:'
    ));
    ini_set('session.gc_maxlifetime', '3600'); // Redis borra sola la sesión pasada 1 hora
}
// Cualquier cambio hecho desde el panel (un plato, un horario, una opinión…)
// vacía la caché de la web al terminar, para que se vea al momento.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    register_shutdown_function('vaciarCache');
}
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
const ADMIN_VERSION = '20261112';
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
    $nuevas = 0;
    $espera = 0;
    if ($u) {
        try {
            $pendientes = (int) db()->query("SELECT COUNT(*) FROM reservas WHERE estado = 'pendiente' AND fecha >= CURRENT_DATE")->fetchColumn();
            $sinLeer    = (int) db()->query('SELECT COUNT(*) FROM mensajes_contacto WHERE leido = 0')->fetchColumn();
        } catch (PDOException) {
        }
        try {
            $nuevas = (int) db()->query("SELECT COUNT(*) FROM presupuestos WHERE estado = 'nueva'")->fetchColumn();
        } catch (PDOException) { // la tabla llega con la migración 006
        }
        try {
            $espera = (int) db()->query("SELECT COUNT(*) FROM lista_espera WHERE estado = 'esperando' AND fecha >= CURRENT_DATE")->fetchColumn();
        } catch (PDOException) { // la tabla llega con la migración 008
        }
    }
    $servidor = null;
    if ($u && $u['rol'] === 'admin') {
        $informe = estadoServidor();
        if ($informe) $servidor = $informe['viejo'] ? 'error' : $informe['estado'];
    }
    $enlace = function (string $href, string $id, string $texto, int $badge = 0, ?string $punto = null) use ($activa): string {
        $extra = $badge ? '<span class="badge">' . $badge . '</span>' : '';
        if ($punto && $punto !== 'ok') $extra = '<span class="nav-dot nav-dot-' . e($punto) . '" aria-label="Hay avisos"></span>';
        return '<a href="' . $href . '"' . ($activa === $id ? ' aria-current="page"' : '') . '>'
            . icono($id) . '<span class="nav-text">' . $texto . '</span>' . $extra . '</a>';
    };
    $iniciales = $u ? mb_strtoupper(implode('', array_map(fn($p) => mb_substr($p, 0, 1), array_slice(explode(' ', trim($u['nombre'])), 0, 2)))) : '';
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#172C66">
    <title><?= e($titulo) ?> · Panel Sabor Mediterráneo</title>
    <link rel="icon" href="../favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="admin.css?v=<?= ADMIN_VERSION ?>">
    <script src="admin.js?v=<?= ADMIN_VERSION ?>" defer></script>
</head>
<body class="<?= $u ? 'with-nav' : 'no-nav' ?>">
<?php if ($u): ?>
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="index.php">
            <svg viewBox="0 0 40 48" aria-hidden="true"><path d="M2 47V20C2 10 10 2 20 2s18 8 18 18v27" fill="none" stroke="currentColor" stroke-width="3"/><path d="M20 14l3.5 6.5L30 24l-6.5 3.5L20 34l-3.5-6.5L10 24l6.5-3.5z" fill="currentColor"/></svg>
            <span>Sabor<br>Mediterráneo<small>Panel del restaurante</small></span>
        </a>
        <nav aria-label="Panel">
            <p class="nav-group">Servicio</p>
            <?= $enlace('index.php', 'inicio', 'Hoy') ?>
            <?= $enlace('reservas.php', 'reservas', 'Reservas', $pendientes) ?>
            <?= $enlace('pizarra.php', 'pizarra', 'Pizarra del día') ?>
            <?= $enlace('lista-espera.php', 'espera', 'Lista de espera', $espera) ?>
            <?php if ($u['rol'] === 'admin'): ?>
                <p class="nav-group">La web</p>
                <?= $enlace('carta.php', 'carta', 'Carta') ?>
                <?= $enlace('galeria.php', 'galeria', 'Galería') ?>
                <?= $enlace('eventos.php', 'eventos', 'Eventos') ?>
                <?= $enlace('opiniones.php', 'opiniones', 'Opiniones') ?>
                <?= $enlace('qr.php', 'qr', 'Códigos QR') ?>
                <p class="nav-group">Clientes</p>
                <?= $enlace('presupuestos.php', 'presupuestos', 'Presupuestos', $nuevas) ?>
                <?= $enlace('mensajes.php', 'mensajes', 'Mensajes', $sinLeer) ?>
                <?= $enlace('correos.php', 'correos', 'Correos enviados') ?>
                <p class="nav-group">Sistema</p>
                <?= $enlace('usuarios.php', 'usuarios', 'Equipo') ?>
                <?= $enlace('visitas.php', 'visitas', 'Visitas a la web') ?>
                <?= $enlace('estado.php', 'estado', 'Estado del servidor', 0, $servidor) ?>
            <?php endif; ?>
        </nav>
        <div class="me">
            <span class="avatar" aria-hidden="true"><?= e($iniciales) ?></span>
            <p><strong><?= e($u['nombre']) ?></strong><span><?= $u['rol'] === 'admin' ? 'Administración' : 'Sala' ?></span></p>
            <form method="post" action="logout.php"><?= campoCsrf() ?><button type="submit" class="icon-btn" aria-label="Cerrar sesión" title="Cerrar sesión"><?= icono('salir') ?></button></form>
        </div>
    </aside>
    <div class="scrim" id="scrim" hidden></div>
<?php endif; ?>
    <main class="main">
        <?php if ($u): ?>
        <div class="topbar">
            <button type="button" class="icon-btn nav-toggle" id="nav-toggle" aria-controls="sidebar" aria-expanded="false" aria-label="Abrir menú"><?= icono('menu') ?></button>
            <p class="topbar-date"><?= e(ucfirst(fechaLarga('today'))) ?> · <span id="reloj"><?= date('H:i') ?></span></p>
            <div class="topbar-actions">
                <a class="btn btn-small" href="reservas.php?nueva=1"><?= icono('mas') ?><span>Reserva</span></a>
                <a class="btn btn-small btn-quiet" href="../index.html" target="_blank" rel="noopener"><?= icono('web') ?><span>Ver la web</span></a>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['flash'])): $f = $_SESSION['flash']; unset($_SESSION['flash']); ?>
            <p class="flash flash-<?= e($f['tipo']) ?>" role="status"><?= e($f['texto']) ?></p>
        <?php endif; ?>
<?php
}

/** Iconos del panel: trazos SVG sencillos, sin librerías externas. */
function icono(string $nombre): string
{
    $trazos = [
        'inicio'       => '<path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
        'reservas'     => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'pizarra'      => '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21l1.5-4M16 21l-1.5-4M7 9h6M7 12h9"/>',
        'carta'        => '<path d="M5 4.5A1.5 1.5 0 0 1 6.5 3H19v15H6.5A1.5 1.5 0 0 0 5 19.5z"/><path d="M5 19.5A1.5 1.5 0 0 0 6.5 21H19v-3M9 7h6M9 10h4"/>',
        'galeria'      => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/>',
        'eventos'      => '<path d="M3 7a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v3a2 2 0 0 0 0 4v3a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-3a2 2 0 0 0 0-4z"/><path d="M15 6v2M15 11v2M15 16v2"/>',
        'opiniones'    => '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.9z"/>',
        'qr'           => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM21 14v.01M14 21h.01M17 21h4v-4"/>',
        'presupuestos' => '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/>',
        'mensajes'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'usuarios'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.6a3.5 3.5 0 0 1 0 6.8M18 14.5a6.5 6.5 0 0 1 3.5 5.5"/>',
        'visitas'      => '<path d="M3 21h18M6 17v-5M11 17V7M16 17v-8M21 17V4"/>',
        'espera'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'correos'      => '<path d="M3 7l9 6 9-6"/><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M15 16h4"/>',
        'estado'       => '<path d="M3 12h4l3-7 4 14 3-7h4"/>',
        'web'          => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
        'salir'        => '<path d="M15 4h4a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-4M10 17l5-5-5-5M15 12H3"/>',
        'menu'         => '<path d="M4 7h16M4 12h16M4 17h10"/>',
        'mas'          => '<path d="M12 5v14M5 12h14"/>',
        'personas'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'telefono'     => '<path d="M6.6 3h3l1.5 4.2-2 1.3a12 12 0 0 0 6.4 6.4l1.3-2 4.2 1.5v3a2 2 0 0 1-2.2 2A17.6 17.6 0 0 1 4.6 5.2 2 2 0 0 1 6.6 3z"/>',
        'flecha'       => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'aviso'        => '<path d="M12 3l9.5 17h-19z"/><path d="M12 10v4M12 17v.01"/>',
    ];
    return '<svg class="ico" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'
        . ($trazos[$nombre] ?? '') . '</svg>';
}

function pie(): void
{
    echo "\n    </main>\n</body>\n</html>\n";
}
