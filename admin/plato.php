<?php
/** Crear (sin ?id) o editar (?id=5) un plato y sus alérgenos. */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/subidas.php';
exigirLogin('admin');
$pdo = db();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;
$categorias = $pdo->query('SELECT id, nombre FROM categorias ORDER BY orden, id')->fetchAll();
$alergenos  = $pdo->query('SELECT id, nombre FROM alergenos ORDER BY id')->fetchAll();

$plato = [
    'categoria_id' => (int) ($_GET['categoria'] ?? $categorias[0]['id'] ?? 1),
    'nombre' => '', 'descripcion' => '', 'precio' => '', 'unidad' => '', 'imagen' => '',
    'vegetariano' => 0, 'vegano' => 0, 'sin_gluten' => 0, 'destacado' => 0, 'disponible' => 1,
];
$marcados = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM platos WHERE id = ?');
    $stmt->execute([$id]);
    $plato = $stmt->fetch() ?: redirigir('carta.php');
    $stmt = $pdo->prepare('SELECT alergeno_id FROM plato_alergeno WHERE plato_id = ?');
    $stmt->execute([$id]);
    $marcados = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

$imagenAnterior = $plato['imagen'] ?? null; // para borrar la foto vieja si se cambia

$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si la foto supera el límite de PHP, el formulario llega vacío
    if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('error', 'La foto es demasiado grande. El máximo son 6 MB.');
        redirigir($id ? "plato.php?id=$id" : 'plato.php');
    }
    comprobarCsrf();
    $plato = [
        'categoria_id' => (int) ($_POST['categoria_id'] ?? 0),
        'nombre'       => trim((string) ($_POST['nombre'] ?? '')),
        'descripcion'  => trim((string) ($_POST['descripcion'] ?? '')),
        'precio'       => str_replace(',', '.', trim((string) ($_POST['precio'] ?? ''))),
        'unidad'       => trim((string) ($_POST['unidad'] ?? '')),
        'imagen'       => trim((string) ($_POST['imagen'] ?? '')),
        'vegetariano'  => isset($_POST['vegetariano']) ? 1 : 0,
        'vegano'       => isset($_POST['vegano']) ? 1 : 0,
        'sin_gluten'   => isset($_POST['sin_gluten']) ? 1 : 0,
        'destacado'    => isset($_POST['destacado']) ? 1 : 0,
        'disponible'   => isset($_POST['disponible']) ? 1 : 0,
    ];
    if ($plato['vegano']) $plato['vegetariano'] = 1; // un plato vegano siempre es vegetariano
    $idsValidos = array_map('intval', array_column($alergenos, 'id'));
    $marcados = array_values(array_intersect(array_map('intval', (array) ($_POST['alergenos'] ?? [])), $idsValidos));

    if (mb_strlen($plato['nombre']) < 3 || mb_strlen($plato['nombre']) > 100) $errores[] = 'El nombre debe tener entre 3 y 100 caracteres.';
    if (mb_strlen($plato['descripcion']) < 5 || mb_strlen($plato['descripcion']) > 255) $errores[] = 'La descripción debe tener entre 5 y 255 caracteres.';
    if (!is_numeric($plato['precio']) || $plato['precio'] < 0 || $plato['precio'] > 9999) $errores[] = 'El precio no es válido.';
    if (!in_array($plato['categoria_id'], array_map('intval', array_column($categorias, 'id')), true)) $errores[] = 'Elige una categoría.';
    if ($plato['imagen'] !== '' && !imagenValida($plato['imagen'])) $errores[] = 'La foto debe ser una dirección https://… o una ruta del proyecto como img/platos/salmorejo.jpg.';
    if ($plato['sin_gluten'] && in_array(1, $marcados, true)) $errores[] = 'Un plato sin gluten no puede tener marcado el alérgeno «Gluten».';

    // La foto se sube solo cuando todo lo demás está bien, para no dejar archivos sueltos
    $fotoNueva = null;
    if (!$errores && hayArchivo('foto')) {
        try {
            $fotoNueva = guardarFoto($_FILES['foto'], 'platos');
            $plato['imagen'] = $fotoNueva;
        } catch (RuntimeException $ex) {
            $errores[] = $ex->getMessage();
        }
    }

    if (!$errores) {
        $valores = [
            $plato['categoria_id'], $plato['nombre'], $plato['descripcion'], $plato['precio'],
            $plato['unidad'] ?: null, $plato['imagen'] ?: null,
            $plato['vegetariano'], $plato['vegano'], $plato['sin_gluten'], $plato['destacado'], $plato['disponible'],
        ];
        try {
            $pdo->beginTransaction();
            if ($id) {
                $pdo->prepare('UPDATE platos SET categoria_id = ?, nombre = ?, descripcion = ?, precio = ?, unidad = ?, imagen = ?,
                               vegetariano = ?, vegano = ?, sin_gluten = ?, destacado = ?, disponible = ? WHERE id = ?')
                    ->execute([...$valores, $id]);
                $pdo->prepare('DELETE FROM plato_alergeno WHERE plato_id = ?')->execute([$id]);
            } else {
                $pdo->prepare('INSERT INTO platos (categoria_id, nombre, descripcion, precio, unidad, imagen,
                               vegetariano, vegano, sin_gluten, destacado, disponible) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute($valores);
                $id = (int) $pdo->lastInsertId();
            }
            $ins = $pdo->prepare('INSERT INTO plato_alergeno (plato_id, alergeno_id) VALUES (?, ?)');
            foreach (array_unique($marcados) as $a) {
                $ins->execute([$id, $a]);
            }
            $pdo->commit();
            if ($imagenAnterior !== ($plato['imagen'] ?: null)) borrarSubida($imagenAnterior);
            flash('ok', '«' . $plato['nombre'] . '» guardado.');
            redirigir('carta.php');
        } catch (PDOException $ex) {
            $pdo->rollBack();
            borrarSubida($fotoNueva); // la foto recién subida ya no se usa
            if ($ex->getCode() !== '23000') throw $ex; // 23000 = nombre repetido
            $errores[] = 'Ya existe un plato con ese nombre.';
        }
    }
}

cabecera($id ? 'Editar plato' : 'Nuevo plato', 'carta');
?>
<header class="page-head">
    <div>
        <a class="muted small" href="carta.php">← Volver a la carta</a>
        <h1><?= $id ? 'Editar «' . e($plato['nombre']) . '»' : 'Nuevo plato' ?></h1>
    </div>
</header>

<?php foreach ($errores as $err): ?><p class="flash flash-error"><?= e($err) ?></p><?php endforeach; ?>

<form method="post" class="panel form form-grid" enctype="multipart/form-data">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= SUBIDA_MAX_BYTES ?>">
    <?= campoCsrf() ?>
    <label class="span-2">Nombre <input name="nombre" value="<?= e($plato['nombre']) ?>" maxlength="100" required></label>
    <label>Categoría
        <select name="categoria_id">
            <?php foreach ($categorias as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= (int) $plato['categoria_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="span-all">Descripción <textarea name="descripcion" rows="2" maxlength="255" required><?= e($plato['descripcion']) ?></textarea></label>
    <label>Precio (€) <input name="precio" inputmode="decimal" value="<?= e((string) $plato['precio']) ?>" required></label>
    <label><span>Unidad <span class="muted">(opcional)</span></span><input name="unidad" value="<?= e($plato['unidad']) ?>" placeholder="p.p., copa, ración" maxlength="10"></label>

    <div class="span-all photo-field">
        <img class="photo-current" id="foto-preview" src="<?= e(miniatura($plato['imagen'] ?: null)) ?>" alt="Foto del plato">
        <div class="form">
            <label class="dropzone"><span>Cambiar foto <span class="muted">(JPG, PNG o WEBP, máximo 6 MB)</span></span>
                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" id="foto-input">
            </label>
            <label><span>…o ruta / dirección de la foto <span class="muted">(opcional)</span></span>
                <input name="imagen" value="<?= e((string) $plato['imagen']) ?>" placeholder="img/platos/salmorejo.jpg o https://…">
            </label>
        </div>
    </div>

    <fieldset class="span-all checks">
        <legend>Etiquetas</legend>
        <?php foreach (['vegetariano' => 'Vegetariano', 'vegano' => 'Vegano', 'sin_gluten' => 'Sin gluten', 'destacado' => 'Recomendado', 'disponible' => 'Visible en la carta'] as $k => $t): ?>
            <label class="check"><input type="checkbox" name="<?= $k ?>" <?= $plato[$k] ? 'checked' : '' ?>> <?= $t ?></label>
        <?php endforeach; ?>
    </fieldset>

    <fieldset class="span-all checks">
        <legend>Alérgenos que contiene</legend>
        <?php foreach ($alergenos as $a): ?>
            <label class="check"><input type="checkbox" name="alergenos[]" value="<?= (int) $a['id'] ?>" <?= in_array((int) $a['id'], $marcados, true) ? 'checked' : '' ?>> <?= e($a['nombre']) ?></label>
        <?php endforeach; ?>
    </fieldset>

    <div class="span-all form-actions">
        <button class="btn btn-primary" type="submit">Guardar plato</button>
        <a class="btn btn-quiet" href="carta.php">Cancelar</a>
    </div>
</form>
<script>
// Vista previa de la foto elegida antes de guardar
document.getElementById('foto-input').addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (file) document.getElementById('foto-preview').src = URL.createObjectURL(file);
});
</script>
<?php pie();
