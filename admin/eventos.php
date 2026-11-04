<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
exigirLogin('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    comprobarCsrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $accion = $_POST['accion'] ?? '';

    if ($id && $accion === 'publicado') {
        $pdo->prepare('UPDATE eventos SET publicado = 1 - publicado WHERE id = ?')->execute([$id]);
        flash('ok', 'Visibilidad del evento actualizada.');
    } elseif ($id && $accion === 'borrar') {
        // Las inscripciones se borran solas (ON DELETE CASCADE)
        $pdo->prepare('DELETE FROM eventos WHERE id = ?')->execute([$id]);
        flash('ok', 'Evento eliminado junto con sus inscripciones.');
    }
    redirigir('eventos.php');
}

$eventos = $pdo->query(
    'SELECT e.*, COALESCE(SUM(i.plazas), 0) AS ocupadas, COUNT(i.cliente_id) AS inscripciones
     FROM eventos e
     LEFT JOIN inscripciones_evento i ON i.evento_id = e.id
     GROUP BY e.id
     ORDER BY e.fecha >= NOW() DESC, e.fecha'
)->fetchAll();

$ahora = new DateTimeImmutable();
$proximos = array_filter($eventos, fn($e) => new DateTimeImmutable($e['fecha']) >= $ahora);
$pasados  = array_filter($eventos, fn($e) => new DateTimeImmutable($e['fecha']) < $ahora);

cabecera('Eventos', 'eventos');

function filaEvento(array $e, bool $pasado): void
{
    $plazas = (int) $e['plazas'];
    $ocupadas = (int) $e['ocupadas'];
    $pct = $plazas ? round($ocupadas / $plazas * 100) : 0;
    $fecha = new DateTimeImmutable($e['fecha']);
    ?>
    <li class="<?= !$e['publicado'] || $pasado ? 'row-dim' : '' ?>">
        <div class="dish-admin-main">
            <p class="dish-admin-name">
                <?= e($e['titulo']) ?>
                <?php if (!$e['publicado']): ?><span class="pill pill-cancelada">Oculto</span><?php endif; ?>
                <?php if (!$pasado && $ocupadas >= $plazas): ?><span class="pill pill-pendiente">Completo</span><?php endif; ?>
            </p>
            <p class="muted small"><?= e(ucfirst(fechaLarga($fecha->format('Y-m-d')))) ?> · <?= $fecha->format('H:i') ?> · <?= euro($e['precio']) ?> por persona</p>
            <div class="seats-admin" title="<?= $pct ?> % ocupado">
                <span class="seats-admin-bar"><span style="width: <?= $pct ?>%"></span></span>
                <span class="small"><strong><?= $ocupadas ?></strong> de <?= $plazas ?> plazas · <?= (int) $e['inscripciones'] ?> <?= (int) $e['inscripciones'] === 1 ? 'inscripción' : 'inscripciones' ?> ·<?= euro($ocupadas * (float) $e['precio']) ?> previstos</span>
            </div>
        </div>
        <form method="post" class="actions">
            <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
            <a class="btn btn-small" href="evento.php?id=<?= (int) $e['id'] ?>">Editar y ver inscritos</a>
            <?php if (!$pasado): ?>
                <button class="btn btn-small btn-quiet" name="accion" value="publicado"><?= $e['publicado'] ? 'Ocultar de la web' : 'Publicar en la web' ?></button>
            <?php endif; ?>
            <button class="btn btn-small btn-danger" name="accion" value="borrar" onclick="return confirm('¿Eliminar «<?= e($e['titulo']) ?>» y sus <?= (int) $e['inscripciones'] ?> inscripciones? No se puede deshacer.')">Eliminar</button>
        </form>
    </li>
    <?php
}
?>
<header class="page-head">
    <div>
        <h1>Eventos</h1>
        <p class="muted">Los eventos publicados y con fecha futura aparecen en la web, en «Próximos eventos».</p>
    </div>
    <a class="btn btn-primary" href="evento.php">Crear evento</a>
</header>

<section class="panel">
    <h2>Próximos</h2>
    <?php if (!$proximos): ?>
        <p class="empty-inline">No hay eventos programados. <a href="evento.php">Crea el primero</a>.</p>
    <?php else: ?>
        <ul class="dish-admin"><?php foreach ($proximos as $e) filaEvento($e, false); ?></ul>
    <?php endif; ?>
</section>

<?php if ($pasados): ?>
<section class="panel">
    <h2>Ya celebrados</h2>
    <ul class="dish-admin"><?php foreach ($pasados as $e) filaEvento($e, true); ?></ul>
</section>
<?php endif; ?>
<?php pie();
