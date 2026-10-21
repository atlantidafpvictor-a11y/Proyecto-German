<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
exigirLogin('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    comprobarCsrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $accion = $_POST['accion'] ?? '';
    if ($id && $accion === 'aprobar') {
        $pdo->prepare('UPDATE opiniones SET aprobada = 1 WHERE id = ?')->execute([$id]);
        flash('ok', 'Opinión publicada en la web.');
    } elseif ($id && $accion === 'ocultar') {
        $pdo->prepare('UPDATE opiniones SET aprobada = 0 WHERE id = ?')->execute([$id]);
        flash('ok', 'Opinión retirada de la web.');
    } elseif ($id && $accion === 'borrar') {
        $pdo->prepare('DELETE FROM opiniones WHERE id = ?')->execute([$id]);
        flash('ok', 'Opinión eliminada.');
    }
    redirigir('opiniones.php');
}

$opiniones = $pdo->query('SELECT * FROM opiniones ORDER BY aprobada ASC, fecha DESC')->fetchAll();
$media = $pdo->query('SELECT ROUND(AVG(puntuacion), 1) AS media, COUNT(*) AS total FROM opiniones')->fetch();

cabecera('Opiniones', 'opiniones');
?>
<header class="page-head">
    <div>
        <h1>Opiniones</h1>
        <p class="muted">Nota media <?= e(str_replace('.', ',', (string) ($media['media'] ?? '–'))) ?> sobre 5 · <?= (int) $media['total'] ?> opiniones. En la web se muestran las tres aprobadas más recientes.</p>
    </div>
</header>

<?php if (!$opiniones): ?>
    <div class="empty"><h2>Todavía no hay opiniones</h2></div>
<?php endif; ?>

<section class="cards">
<?php foreach ($opiniones as $o): ?>
    <article class="card <?= $o['aprobada'] ? '' : 'card-pending' ?>">
        <p class="stars" aria-label="<?= (int) $o['puntuacion'] ?> de 5"><?= str_repeat('★', (int) $o['puntuacion']) . str_repeat('☆', 5 - (int) $o['puntuacion']) ?></p>
        <blockquote><?= e($o['comentario']) ?></blockquote>
        <p class="muted small"><?= e($o['nombre']) ?> · <?= e(fechaLarga($o['fecha'])) ?></p>
        <form method="post" class="actions">
            <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
            <?php if ($o['aprobada']): ?>
                <span class="pill pill-confirmada">Publicada</span>
                <button class="btn btn-small btn-quiet" name="accion" value="ocultar">Retirar</button>
            <?php else: ?>
                <span class="pill pill-pendiente">Sin revisar</span>
                <button class="btn btn-small" name="accion" value="aprobar">Publicar</button>
            <?php endif; ?>
            <button class="btn btn-small btn-danger" name="accion" value="borrar" onclick="return confirm('¿Eliminar esta opinión?')">Eliminar</button>
        </form>
    </article>
<?php endforeach; ?>
</section>
<?php pie();
