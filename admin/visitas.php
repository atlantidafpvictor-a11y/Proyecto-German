<?php
/**
 * Visitas a la web: el informe que genera GoAccess cada 15 minutos
 * (scripts/visitas.sh) a partir del registro de Apache.
 *
 * El informe lleva su propio JavaScript, así que no se mete en esta página:
 * se carga en un marco aislado (visitas-informe.php, con "sandbox") que no
 * puede leer las cookies ni tocar nada del panel.
 */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
exigirLogin('admin');

const INFORME_VISITAS = '/var/lib/sabor-mediterraneo/visitas.html';
$hay = is_readable(INFORME_VISITAS);

cabecera('Visitas a la web', 'visitas');
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Estadísticas</p>
        <h1>Visitas a la web</h1>
        <p class="muted">Calculadas en el propio servidor a partir del registro de Apache, sin Google Analytics ni cookies.
            Las IPs se guardan anonimizadas y no se cuentan los robots de los buscadores.
            <?php if ($hay): ?>Actualizado: <?= e(date('d/m/Y H:i', (int) filemtime(INFORME_VISITAS))) ?> (se renueva cada 15 minutos).<?php endif; ?></p>
    </div>
</header>

<?php if (!$hay): ?>
    <div class="empty">
        <h2>Todavía no hay informe de visitas</h2>
        <p>En el servidor: <code>sudo apt install goaccess</code>, instalar <code>scripts/visitas.sh</code> y su tarea de cron<br>(guía del servidor, apartado 11l).</p>
    </div>
<?php else: ?>
    <iframe class="report-frame" src="visitas-informe.php" sandbox="allow-scripts" title="Informe de visitas generado por GoAccess"></iframe>
<?php endif; ?>
<?php pie();
