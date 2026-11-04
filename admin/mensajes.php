<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
exigirLogin('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    comprobarCsrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $accion = $_POST['accion'] ?? '';
    if ($id && $accion === 'leido') {
        $pdo->prepare('UPDATE mensajes_contacto SET leido = NOT leido WHERE id = ?')->execute([$id]);
    } elseif ($id && $accion === 'borrar') {
        $pdo->prepare('DELETE FROM mensajes_contacto WHERE id = ?')->execute([$id]);
        flash('ok', 'Mensaje eliminado.');
    }
    redirigir('mensajes.php');
}

$mensajes = $pdo->query('SELECT * FROM mensajes_contacto ORDER BY leido ASC, fecha DESC LIMIT 100')->fetchAll();

cabecera('Mensajes', 'mensajes');
?>
<header class="page-head">
    <div>
        <h1>Mensajes</h1>
        <p class="muted">Lo que llega desde el formulario de contacto de la web.</p>
    </div>
</header>

<?php if (!$mensajes): ?>
    <div class="empty"><h2>Bandeja vacía</h2><p>Cuando alguien escriba desde la web, el mensaje aparecerá aquí.</p></div>
<?php endif; ?>

<section class="cards cards-wide">
<?php foreach ($mensajes as $m): ?>
    <article class="card <?= $m['leido'] ? 'row-dim' : 'card-pending' ?>">
        <h2 class="card-title"><?= e($m['asunto']) ?></h2>
        <p class="muted small"><?= e($m['nombre']) ?> · <a href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . $m['asunto']) ?>"><?= e($m['email']) ?></a> · <?= e((new DateTime($m['fecha']))->format('d/m/Y H:i')) ?></p>
        <p class="message"><?= nl2br(e($m['mensaje'])) ?></p>
        <form method="post" class="actions">
            <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <a class="btn btn-small" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . $m['asunto']) ?>">Responder por email</a>
            <button class="btn btn-small btn-quiet" name="accion" value="leido"><?= $m['leido'] ? 'Marcar como no leído' : 'Marcar como leído' ?></button>
            <button class="btn btn-small btn-danger" name="accion" value="borrar" data-confirm="¿Eliminar este mensaje?">Eliminar</button>
        </form>
    </article>
<?php endforeach; ?>
</section>
<?php pie();
