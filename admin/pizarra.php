<?php
/**
 * Pizarra del día: lo que ha llegado esta mañana y sale en la portada.
 * La puede tocar cualquier persona del equipo (también la de sala),
 * porque quien sabe qué se ha acabado es quien está sirviendo.
 */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
exigirLogin();
$pdo = db();

try {
    $pdo->query('SELECT 1 FROM pizarra LIMIT 1');
} catch (PDOException) {
    cabecera('Pizarra del día', 'pizarra');
    echo '<div class="empty"><h2>Falta la tabla de la pizarra</h2><p>Ejecuta <code>database/migraciones/007_pizarra.sql</code> y vuelve a entrar.</p></div>';
    pie();
    exit;
}

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$plato = ['nombre' => '', 'nombre_en' => '', 'origen' => '', 'origen_en' => '', 'precio' => ''];
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM pizarra WHERE id = ?');
    $stmt->execute([$id]);
    $plato = $stmt->fetch() ?: redirigir('pizarra.php');
}

$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    comprobarCsrf();
    $accion = (string) ($_POST['accion'] ?? 'guardar');
    $fila = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT) ?: null;

    if ($accion === 'nuevo-dia') {
        // Por la mañana: todo vuelve a estar disponible
        $pdo->exec('UPDATE pizarra SET agotado = 0 WHERE agotado = 1');
        flash('ok', 'Pizarra lista para hoy: ya no hay nada marcado como agotado.');
        redirigir('pizarra.php');
    }
    if ($fila && $accion === 'agotado') {
        $pdo->prepare('UPDATE pizarra SET agotado = NOT agotado WHERE id = ?')->execute([$fila]);
        flash('ok', 'Cambiado. En la web se ve al momento.');
        // Desde el inicio del panel se vuelve al inicio (solo se aceptan estas dos páginas)
        redirigir(($_POST['volver'] ?? '') === 'index.php' ? 'index.php' : 'pizarra.php');
    }
    if ($fila && $accion === 'destacar') {
        // Solo uno destacado: se hace en una transacción para no dejar dos ni ninguno a medias
        $pdo->beginTransaction();
        $pdo->exec('UPDATE pizarra SET destacado = 0 WHERE destacado = 1');
        $pdo->prepare('UPDATE pizarra SET destacado = 1, visible = 1 WHERE id = ?')->execute([$fila]);
        $pdo->commit();
        flash('ok', 'Ahora sale en «Hoy en la cocina» de la portada.');
        redirigir('pizarra.php');
    }
    if ($fila && $accion === 'visible') {
        $pdo->prepare('UPDATE pizarra SET visible = NOT visible WHERE id = ?')->execute([$fila]);
        // Un plato oculto no puede seguir en la portada
        $pdo->exec('UPDATE pizarra SET destacado = 0 WHERE visible = 0 AND destacado = 1');
        flash('ok', 'Visibilidad cambiada.');
        redirigir('pizarra.php');
    }
    if ($fila && $accion === 'borrar') {
        $pdo->prepare('DELETE FROM pizarra WHERE id = ?')->execute([$fila]);
        flash('ok', 'Quitado de la pizarra.');
        redirigir('pizarra.php');
    }
    if ($fila && in_array($accion, ['subir', 'bajar'], true)) {
        // Se renumera todo en orden 10, 20, 30... y se intercambia con el vecino
        $ids = $pdo->query('SELECT id FROM pizarra ORDER BY orden, id')->fetchAll(PDO::FETCH_COLUMN);
        $i = array_search($fila, array_map('intval', $ids), true);
        $j = $accion === 'subir' ? $i - 1 : $i + 1;
        if ($i !== false && isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            $stmt = $pdo->prepare('UPDATE pizarra SET orden = ? WHERE id = ?');
            $pdo->beginTransaction();
            foreach ($ids as $n => $pid) $stmt->execute([($n + 1) * 10, $pid]);
            $pdo->commit();
        }
        redirigir('pizarra.php');
    }

    /* --- Guardar (nuevo o editado) --- */
    $plato = [
        'nombre'    => trim((string) ($_POST['nombre'] ?? '')),
        'nombre_en' => trim((string) ($_POST['nombre_en'] ?? '')),
        'origen'    => trim((string) ($_POST['origen'] ?? '')),
        'origen_en' => trim((string) ($_POST['origen_en'] ?? '')),
        'precio'    => str_replace(',', '.', trim((string) ($_POST['precio'] ?? ''))),
    ];
    if (mb_strlen($plato['nombre']) < 3 || mb_strlen($plato['nombre']) > 100) $errores[] = 'El nombre debe tener entre 3 y 100 caracteres.';
    if (mb_strlen($plato['origen']) > 60 || mb_strlen($plato['origen_en']) > 60) $errores[] = 'El origen no puede pasar de 60 caracteres.';
    if (mb_strlen($plato['nombre_en']) > 100) $errores[] = 'El nombre en inglés no puede pasar de 100 caracteres.';
    $precio = $plato['precio'] === '' ? null : filter_var($plato['precio'], FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0.5, 'max_range' => 999]]);
    if ($precio === false) $errores[] = 'El precio debe ser un número (por ejemplo 18,50) o quedarse vacío si es según mercado.';

    if (!$errores) {
        $valores = [$plato['nombre'], $plato['nombre_en'] ?: null, $plato['origen'] ?: null, $plato['origen_en'] ?: null, $precio];
        if ($fila) {
            $pdo->prepare('UPDATE pizarra SET nombre = ?, nombre_en = ?, origen = ?, origen_en = ?, precio = ? WHERE id = ?')
                ->execute([...$valores, $fila]);
            flash('ok', 'Guardado.');
        } else {
            $orden = (int) $pdo->query('SELECT COALESCE(MAX(orden), 0) + 10 FROM pizarra')->fetchColumn();
            $pdo->prepare('INSERT INTO pizarra (nombre, nombre_en, origen, origen_en, precio, orden) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([...$valores, $orden]);
            flash('ok', 'Añadido a la pizarra. Ya se ve en la web.');
        }
        redirigir('pizarra.php');
    }
    $id = $fila;
}

$lista = $pdo->query('SELECT * FROM pizarra ORDER BY orden, id')->fetchAll();
$hora = $pdo->query('SELECT MAX(actualizado_en) FROM pizarra')->fetchColumn();
$agotados = count(array_filter($lista, fn($p) => $p['agotado']));

cabecera('Pizarra del día', 'pizarra');
?>
<header class="page-head">
    <div>
        <h1>Pizarra del día</h1>
        <p class="muted">Lo que ha llegado esta mañana. Sale en la portada de la web; lo agotado aparece tachado.
            <?php if ($hora): ?>Último cambio: <?= e((new DateTime($hora))->format('d/m/Y H:i')) ?>.<?php endif; ?></p>
    </div>
    <?php if ($agotados): ?>
    <form method="post">
        <?= campoCsrf() ?>
        <input type="hidden" name="accion" value="nuevo-dia">
        <button class="btn" data-confirm="¿Quitar el «agotado» de todo para empezar el día?">Empezar el día (<?= $agotados ?> agotados)</button>
    </form>
    <?php endif; ?>
</header>

<?php foreach ($errores as $err): ?><p class="flash flash-error"><?= e($err) ?></p><?php endforeach; ?>

<form method="post" class="panel form form-grid" action="pizarra.php<?= $id ? '?id=' . (int) $id : '' ?>">
    <?= campoCsrf() ?>
    <?php if ($id): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>
    <h2 class="span-all day-title"><?= $id ? 'Editar' : 'Añadir a la pizarra' ?></h2>
    <label class="span-2">Plato <input name="nombre" value="<?= e((string) $plato['nombre']) ?>" maxlength="100" placeholder="Por ejemplo: Gallo pedro a la plancha" required></label>
    <label><span>Origen <span class="muted">(opcional)</span></span><input name="origen" value="<?= e((string) $plato['origen']) ?>" maxlength="60" placeholder="Lonja de Motril"></label>
    <label><span>Precio <span class="muted">(vacío = según mercado)</span></span><input name="precio" value="<?= e((string) $plato['precio']) ?>" inputmode="decimal" placeholder="18,50"></label>
    <fieldset class="span-all lang-fields">
        <legend>En inglés <span class="muted">(opcional)</span></legend>
        <label>Plato <input name="nombre_en" value="<?= e((string) $plato['nombre_en']) ?>" maxlength="100" placeholder="Grilled John Dory"></label>
        <label>Origen <input name="origen_en" value="<?= e((string) $plato['origen_en']) ?>" maxlength="60" placeholder="Motril fish market"></label>
    </fieldset>
    <div class="span-all form-actions">
        <button class="btn btn-primary" type="submit" name="accion" value="guardar"><?= $id ? 'Guardar cambios' : 'Añadir' ?></button>
        <?php if ($id): ?><a class="btn btn-quiet" href="pizarra.php">Cancelar</a><?php endif; ?>
    </div>
</form>

<section class="panel">
    <h2 class="day-title">En la pizarra <span class="muted"><?= count($lista) ?> platos</span></h2>
    <?php if (!$lista): ?>
        <p class="empty-inline">La pizarra está vacía: en la portada no se muestra la sección hasta que añadas algo.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Plato</th><th>Origen</th><th class="num">Precio</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead>
            <tbody>
            <?php foreach ($lista as $n => $p): ?>
                <tr<?= $p['visible'] ? '' : ' class="muted"' ?>>
                    <td>
                        <strong<?= $p['agotado'] ? ' style="text-decoration: line-through"' : '' ?>><?= e($p['nombre']) ?></strong>
                        <?php if ($p['destacado']): ?> <span class="pill pill-confirmada">En portada</span><?php endif; ?>
                    </td>
                    <td><?= e((string) $p['origen']) ?></td>
                    <td class="num" style="white-space: nowrap"><?= $p['precio'] !== null ? e(euro((float) $p['precio'])) : '<span class="muted">S/M</span>' ?></td>
                    <td>
                        <?php if (!$p['visible']): ?><span class="pill">Oculto</span>
                        <?php elseif ($p['agotado']): ?><span class="pill pill-pendiente">Agotado</span>
                        <?php else: ?><span class="pill pill-confirmada">Hay</span><?php endif; ?>
                    </td>
                    <td class="actions">
                        <form method="post">
                            <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                            <button class="btn btn-small<?= $p['agotado'] ? '' : ' btn-quiet' ?>" name="accion" value="agotado"><?= $p['agotado'] ? 'Vuelve a haber' : 'Se ha acabado' ?></button>
                            <?php if (!$p['destacado']): ?><button class="btn btn-small btn-quiet" name="accion" value="destacar">Poner en portada</button><?php endif; ?>
                            <button class="btn btn-small btn-quiet" name="accion" value="visible"><?= $p['visible'] ? 'Ocultar' : 'Mostrar' ?></button>
                            <?php if ($n > 0): ?><button class="btn btn-small btn-quiet" name="accion" value="subir" aria-label="Subir">↑</button><?php endif; ?>
                            <?php if ($n < count($lista) - 1): ?><button class="btn btn-small btn-quiet" name="accion" value="bajar" aria-label="Bajar">↓</button><?php endif; ?>
                            <a class="btn btn-small btn-quiet" href="pizarra.php?id=<?= (int) $p['id'] ?>">Editar</a>
                            <button class="btn btn-small btn-danger" name="accion" value="borrar" data-confirm="¿Quitar «<?= e($p['nombre']) ?>» de la pizarra?">Quitar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php pie();
