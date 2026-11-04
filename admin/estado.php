<?php
/**
 * Estado del servidor: lo que comprueba scripts/monitor.sh cada 5 minutos
 * (servicios, disco, copias, certificado, fail2ban, actualizaciones) más
 * unas comprobaciones en directo de PHP y la base de datos.
 */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
exigirLogin('admin');
$pdo = db();

/* ---------- Comprobaciones en directo (también funcionan en XAMPP) ---------- */
$tam = fn(float $b): string => $b >= 1073741824 ? number_format($b / 1073741824, 1, ',', '.') . ' GB'
                             : ($b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : number_format($b / 1024, 0, ',', '.') . ' KB');

$raiz = dirname(__DIR__);
$libre = @disk_free_space($raiz) ?: 0;
$total = @disk_total_space($raiz) ?: 1;

$subidas = 0; $nSubidas = 0;
if (is_dir("$raiz/uploads")) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$raiz/uploads", FilesystemIterator::SKIP_DOTS)) as $f) {
        if ($f->isFile() && $f->getFilename()[0] !== '.') { $subidas += $f->getSize(); $nSubidas++; }
    }
}

// Versión y tamaño: si el usuario de la web no puede leer information_schema, se muestra igualmente el resto
try {
    $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    $tamBd = (float) $pdo->query(
        "SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.tables WHERE table_schema = DATABASE()"
    )->fetchColumn();
} catch (PDOException) {
    $version = $version ?? 'desconocida';
    $tamBd = 0;
}
$cuentas = $pdo->query(
    "SELECT (SELECT COUNT(*) FROM reservas) AS reservas, (SELECT COUNT(*) FROM clientes) AS clientes,
            (SELECT COUNT(*) FROM platos) AS platos, (SELECT COUNT(*) FROM galeria) AS fotos"
)->fetch();

$enDirecto = [
    ['PHP', 'ok', PHP_VERSION . ' · ' . (php_sapi_name() === 'apache2handler' ? 'módulo de Apache' : php_sapi_name())],
    ['Base de datos', 'ok', (stripos($version, 'mariadb') !== false ? 'MariaDB ' : 'MySQL ') . preg_replace('/-.*$/', '', $version) . ($tamBd ? ' · ' . $tam($tamBd) : '')],
    ['Datos guardados', 'ok', "{$cuentas['reservas']} reservas, {$cuentas['clientes']} clientes, {$cuentas['platos']} platos, {$cuentas['fotos']} fotos"],
    ['Espacio libre', $libre / $total < 0.1 ? 'error' : ($libre / $total < 0.2 ? 'aviso' : 'ok'), $tam($libre) . ' libres de ' . $tam($total)],
    ['Fotos subidas', 'ok', "$nSubidas archivos · " . $tam($subidas)],
    ['Conexión', !empty($_SERVER['HTTPS']) ? 'ok' : 'aviso', !empty($_SERVER['HTTPS']) ? 'Cifrada (HTTPS)' : 'Sin cifrar (HTTP): en el servidor debe ser HTTPS'],
    ['Límite de subida', 'ok', 'Hasta ' . ini_get('upload_max_filesize') . ' por archivo'],
];

$informe = estadoServidor();
$ESTADO = ['ok' => 'Bien', 'aviso' => 'Aviso', 'error' => 'Problema'];

cabecera('Estado del servidor', 'estado');
?>
<header class="page-head">
    <div>
        <h1>Estado del servidor</h1>
        <?php if ($informe): ?>
            <p class="muted">Informe de <strong><?= e($informe['equipo']) ?></strong> de hace <?= $informe['minutos'] < 1 ? 'menos de un minuto' : $informe['minutos'] . ' min' ?>. Encendido desde el <?= e((new DateTime($informe['arranque'] ?: 'now'))->format('d/m/Y H:i')) ?>.</p>
        <?php else: ?>
            <p class="muted">Comprobaciones de la web en directo. El informe completo del sistema solo existe en el servidor Ubuntu.</p>
        <?php endif; ?>
    </div>
    <?php if ($informe): ?>
        <span class="health-big health-<?= $informe['viejo'] ? 'aviso' : e($informe['estado']) ?>"><?= $informe['viejo'] ? 'Sin datos recientes' : e($ESTADO[$informe['estado']] ?? $informe['estado']) ?></span>
    <?php endif; ?>
</header>

<?php if ($informe && $informe['viejo']): ?>
    <p class="flash flash-error">El último informe es de hace <?= (int) $informe['minutos'] ?> minutos: la monitorización ha dejado de ejecutarse. Comprueba en el servidor <code>systemctl status cron</code> y <code>sudo sabor-monitor</code>.</p>
<?php endif; ?>

<section class="panel">
    <h2>Sistema</h2>
    <?php if (!$informe): ?>
        <p class="empty-inline">La monitorización del sistema todavía no está instalada. En el servidor Ubuntu se activa con el apartado 11g de <code>docs/servidor-ubuntu.md</code>: comprueba cada 5 minutos los servicios, el disco, las copias de seguridad, el certificado HTTPS y fail2ban.</p>
    <?php else: ?>
        <ul class="health-list">
            <?php foreach ($informe['checks'] as $c): ?>
            <li class="health-<?= e($c['estado']) ?>">
                <span class="health-dot" aria-hidden="true"></span>
                <span class="health-name"><?= e($c['nombre']) ?></span>
                <span class="health-detail"><?= e($c['detalle']) ?></span>
                <span class="sr-only"><?= e($ESTADO[$c['estado']] ?? $c['estado']) ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Web y base de datos, en directo</h2>
    <ul class="health-list">
        <?php foreach ($enDirecto as [$nombre, $estado, $detalle]): ?>
        <li class="health-<?= $estado ?>">
            <span class="health-dot" aria-hidden="true"></span>
            <span class="health-name"><?= e($nombre) ?></span>
            <span class="health-detail"><?= e($detalle) ?></span>
            <span class="sr-only"><?= $ESTADO[$estado] ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <p class="muted small">Para vigilar la web desde fuera, cualquier servicio de monitorización puede consultar <code>api/salud.php</code>: responde 200 si la web y la base de datos funcionan y 503 si no.</p>
</section>
<?php pie();
