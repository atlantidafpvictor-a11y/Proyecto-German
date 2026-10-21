<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
exigirLogin('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    comprobarCsrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $accion = $_POST['accion'] ?? '';

    if ($id && $accion === 'disponible') {
        $pdo->prepare('UPDATE platos SET disponible = NOT disponible WHERE id = ?')->execute([$id]);
        flash('ok', 'Disponibilidad actualizada.');
    } elseif ($id && $accion === 'destacado') {
        $pdo->prepare('UPDATE platos SET destacado = NOT destacado WHERE id = ?')->execute([$id]);
        flash('ok', 'Plato recomendado actualizado.');
    } elseif ($id && $accion === 'borrar') {
        $pdo->prepare('DELETE FROM platos WHERE id = ?')->execute([$id]); // plato_alergeno se borra en cascada
        flash('ok', 'Plato eliminado de la carta.');
    }
    redirigir('carta.php');
}

$categorias = $pdo->query('SELECT id, nombre FROM categorias ORDER BY orden, id')->fetchAll();
$platos = $pdo->query(
    "SELECT p.*, GROUP_CONCAT(a.nombre ORDER BY a.id SEPARATOR ', ') AS alergenos
     FROM platos p
     LEFT JOIN plato_alergeno pa ON pa.plato_id = p.id
     LEFT JOIN alergenos a ON a.id = pa.alergeno_id
     GROUP BY p.id
     ORDER BY p.categoria_id, p.id"
)->fetchAll();

$porCategoria = [];
foreach ($platos as $p) {
    $porCategoria[$p['categoria_id']][] = $p;
}

cabecera('Carta', 'carta');
?>
<header class="page-head">
    <div>
        <h1>Carta</h1>
        <p class="muted"><?= count($platos) ?> platos. Los cambios se ven en la web al momento.</p>
    </div>
    <a class="btn btn-primary" href="plato.php">Añadir plato</a>
</header>

<?php foreach ($categorias as $c): ?>
<section class="panel">
    <h2><?= e($c['nombre']) ?></h2>
    <?php if (empty($porCategoria[$c['id']])): ?>
        <p class="empty-inline">Sin platos en esta sección. <a href="plato.php?categoria=<?= (int) $c['id'] ?>">Añade el primero</a>.</p>
    <?php else: ?>
    <ul class="dish-admin">
        <?php foreach ($porCategoria[$c['id']] as $p): ?>
        <li class="<?= $p['disponible'] ? '' : 'row-dim' ?>">
            <div class="dish-admin-main">
                <p class="dish-admin-name">
                    <?= e($p['nombre']) ?>
                    <?php if ($p['destacado']): ?><span class="pill pill-star">Recomendado</span><?php endif; ?>
                    <?php if (!$p['disponible']): ?><span class="pill pill-cancelada">Oculto</span><?php endif; ?>
                </p>
                <p class="muted small"><?= e($p['descripcion']) ?></p>
                <p class="small">
                    <?= $p['vegetariano'] ? '<span class="tag">Vegetariano</span>' : '' ?>
                    <?= $p['vegano'] ? '<span class="tag">Vegano</span>' : '' ?>
                    <?= $p['sin_gluten'] ? '<span class="tag">Sin gluten</span>' : '' ?>
                    <span class="muted">Alérgenos: <?= e($p['alergenos'] ?: 'ninguno') ?></span>
                </p>
            </div>
            <p class="dish-admin-price"><?= euro($p['precio']) ?><?= $p['unidad'] ? ' <small>' . e($p['unidad']) . '</small>' : '' ?></p>
            <form method="post" class="actions">
                <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <a class="btn btn-small" href="plato.php?id=<?= (int) $p['id'] ?>">Editar</a>
                <button class="btn btn-small btn-quiet" name="accion" value="disponible"><?= $p['disponible'] ? 'Ocultar' : 'Mostrar' ?></button>
                <button class="btn btn-small btn-quiet" name="accion" value="destacado"><?= $p['destacado'] ? 'Quitar recomendado' : 'Recomendar' ?></button>
                <button class="btn btn-small btn-danger" name="accion" value="borrar" onclick="return confirm('¿Eliminar «<?= e($p['nombre']) ?>» de la carta? No se puede deshacer.')">Eliminar</button>
            </form>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</section>
<?php endforeach; ?>
<?php pie();
