<?php
/**
 * Gestión de la galería: subir fotos, cambiar título y categoría,
 * ordenarlas, ocultarlas y borrarlas.
 *
 * La subida segura de archivos está en inc/subidas.php.
 */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/subidas.php';
exigirLogin('admin');
$pdo = db();

const CATEGORIAS = ['local' => 'El local', 'platos' => 'Platos', 'eventos' => 'Eventos'];


// ¿Existe la tabla? Si no, hay que ejecutar la migración
try {
    $pdo->query('SELECT 1 FROM galeria LIMIT 1');
} catch (PDOException $e) {
    if ($e->getCode() !== '42S02') throw $e;
    cabecera('Galería', 'galeria');
    echo '<div class="empty"><h2>Falta crear la tabla de la galería</h2><p>Importa <code>database/migraciones/001_galeria.sql</code> en phpMyAdmin (o en el servidor: <code>sudo mysql sabor_mediterraneo &lt; database/migraciones/001_galeria.sql</code>) y recarga esta página.</p></div>';
    pie();
    exit;
}

/* =========================================================
   ACCIONES
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si el archivo supera el límite de PHP (post_max_size), $_POST llega vacío
    if (!$_POST && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('error', 'La foto es demasiado grande. El máximo son 6 MB.');
        redirigir('galeria.php');
    }
    comprobarCsrf();
    $accion = $_POST['accion'] ?? '';
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);

    if ($accion === 'subir') {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $categoria = array_key_exists($_POST['categoria'] ?? '', CATEGORIAS) ? $_POST['categoria'] : 'platos';
        $url = trim((string) ($_POST['url'] ?? ''));

        if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 120) {
            flash('error', 'Escribe un título de entre 3 y 120 caracteres: se usa como descripción de la foto.');
            redirigir('galeria.php');
        }

        if (hayArchivo('foto')) {
            try {
                $imagen = guardarFoto($_FILES['foto'], 'galeria');
            } catch (RuntimeException $ex) {
                flash('error', $ex->getMessage());
                redirigir('galeria.php');
            }
        } elseif ($url !== '') {
            if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
                flash('error', 'La dirección de la foto debe empezar por https://');
                redirigir('galeria.php');
            }
            $imagen = $url;
        } else {
            flash('error', 'Elige una foto de tu ordenador o pega su dirección web.');
            redirigir('galeria.php');
        }

        $orden = (int) $pdo->query('SELECT COALESCE(MAX(orden), 0) + 1 FROM galeria')->fetchColumn();
        $pdo->prepare('INSERT INTO galeria (titulo, imagen, categoria, orden) VALUES (?, ?, ?, ?)')
            ->execute([$titulo, $imagen, $categoria, $orden]);
        flash('ok', "Foto «{$titulo}» añadida a la galería.");
    }

    elseif ($id && $accion === 'guardar') {
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $categoria = $_POST['categoria'] ?? '';
        if (mb_strlen($titulo) >= 3 && mb_strlen($titulo) <= 120 && array_key_exists($categoria, CATEGORIAS)) {
            $pdo->prepare('UPDATE galeria SET titulo = ?, categoria = ? WHERE id = ?')->execute([$titulo, $categoria, $id]);
            flash('ok', 'Foto actualizada.');
        } else {
            flash('error', 'El título debe tener entre 3 y 120 caracteres.');
        }
    }

    elseif ($id && $accion === 'visible') {
        $pdo->prepare('UPDATE galeria SET visible = 1 - visible WHERE id = ?')->execute([$id]);
        flash('ok', 'Visibilidad actualizada.');
    }

    elseif ($id && in_array($accion, ['arriba', 'abajo'], true)) {
        // Se renumeran todas y se intercambia la foto con su vecina
        $ids = array_map('intval', $pdo->query('SELECT id FROM galeria ORDER BY orden, id')->fetchAll(PDO::FETCH_COLUMN));
        $pos = array_search($id, $ids, true);
        $otra = $accion === 'arriba' ? $pos - 1 : $pos + 1;
        if ($pos !== false && isset($ids[$otra])) {
            [$ids[$pos], $ids[$otra]] = [$ids[$otra], $ids[$pos]];
            $pdo->beginTransaction();
            $upd = $pdo->prepare('UPDATE galeria SET orden = ? WHERE id = ?');
            foreach ($ids as $i => $fid) $upd->execute([$i + 1, $fid]);
            $pdo->commit();
        }
    }

    elseif ($id && $accion === 'borrar') {
        $stmt = $pdo->prepare('SELECT imagen FROM galeria WHERE id = ?');
        $stmt->execute([$id]);
        $imagen = (string) $stmt->fetchColumn();
        $pdo->prepare('DELETE FROM galeria WHERE id = ?')->execute([$id]);
        borrarSubida($imagen); // si era un archivo subido, se borra también del disco
        flash('ok', 'Foto eliminada.');
    }

    redirigir('galeria.php');
}

$fotos = $pdo->query('SELECT * FROM galeria ORDER BY orden, id')->fetchAll();
$total = count($fotos);

cabecera('Galería', 'galeria');
?>
<header class="page-head">
    <div>
        <h1>Galería</h1>
        <p class="muted"><?= $total ?> fotos. Las visibles aparecen en la web en este mismo orden.</p>
    </div>
</header>

<section class="panel">
    <h2>Añadir una foto</h2>
    <form method="post" enctype="multipart/form-data" class="form form-grid upload-form">
        <?= campoCsrf() ?><input type="hidden" name="accion" value="subir">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= SUBIDA_MAX_BYTES ?>">
        <label class="span-2 dropzone">
            <span>Foto <span class="muted">(JPG, PNG o WEBP, máximo 6 MB)</span></span>
            <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" id="foto-input">
            <img class="upload-preview" id="upload-preview" alt="" hidden>
        </label>
        <label><span>…o su dirección web <span class="muted">(opcional)</span></span><input type="url" name="url" placeholder="https://"></label>
        <label class="span-2">Título <input name="titulo" maxlength="120" placeholder="Por ejemplo: Terraza al atardecer" required></label>
        <label>Categoría
            <select name="categoria"><?php foreach (CATEGORIAS as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select>
        </label>
        <div class="span-all form-actions"><button class="btn btn-primary" type="submit">Subir foto</button></div>
    </form>
</section>

<?php if (!$fotos): ?>
    <div class="empty"><h2>La galería está vacía</h2><p>Sube la primera foto con el formulario de arriba.</p></div>
<?php else: ?>
<section class="gallery-admin">
    <?php foreach ($fotos as $i => $f): ?>
    <article class="card gallery-card <?= $f['visible'] ? '' : 'row-dim' ?>">
        <div class="gallery-thumb">
            <img src="<?= e(miniatura($f['imagen'])) ?>" alt="<?= e($f['titulo']) ?>" loading="lazy">
            <span class="gallery-pos"><?= $i + 1 ?></span>
            <?php if (!$f['visible']): ?><span class="pill pill-cancelada gallery-hidden">Oculta</span><?php endif; ?>
        </div>
        <form method="post" class="gallery-edit">
            <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
            <input name="titulo" value="<?= e($f['titulo']) ?>" maxlength="120" aria-label="Título">
            <select name="categoria" aria-label="Categoría">
                <?php foreach (CATEGORIAS as $k => $v): ?><option value="<?= $k ?>" <?= $f['categoria'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
            </select>
            <div class="actions">
                <button class="btn btn-small" name="accion" value="guardar">Guardar</button>
                <button class="btn btn-small btn-quiet" name="accion" value="arriba" <?= $i === 0 ? 'disabled' : '' ?> aria-label="Mover antes">↑</button>
                <button class="btn btn-small btn-quiet" name="accion" value="abajo" <?= $i === $total - 1 ? 'disabled' : '' ?> aria-label="Mover después">↓</button>
                <button class="btn btn-small btn-quiet" name="accion" value="visible"><?= $f['visible'] ? 'Ocultar' : 'Mostrar' ?></button>
                <button class="btn btn-small btn-danger" name="accion" value="borrar" onclick="return confirm('¿Eliminar esta foto de la galería?')">Eliminar</button>
            </div>
        </form>
    </article>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<script>
// Vista previa de la foto elegida antes de subirla
document.getElementById('foto-input').addEventListener('change', (e) => {
    const img = document.getElementById('upload-preview');
    const file = e.target.files[0];
    if (!file) { img.hidden = true; return; }
    img.src = URL.createObjectURL(file);
    img.hidden = false;
});
</script>
<?php pie();
