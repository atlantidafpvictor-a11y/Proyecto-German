<?php
/** Crear (sin ?id) o editar (?id=3) un evento, y ver o quitar sus inscritos. */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
exigirLogin('admin');
$pdo = db();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT) ?: null;

$evento = [
    'titulo' => '', 'descripcion' => '', 'fecha' => '', 'hora' => '20:30',
    'plazas' => 20, 'precio' => '', 'imagen' => '', 'publicado' => 1,
];
$ocupadas = 0;

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM eventos WHERE id = ?');
    $stmt->execute([$id]);
    $fila = $stmt->fetch() ?: redirigir('eventos.php');
    $evento = $fila + ['hora' => substr($fila['fecha'], 11, 5)];
    $evento['fecha'] = substr($fila['fecha'], 0, 10);

    $stmt = $pdo->prepare('SELECT COALESCE(SUM(plazas), 0) FROM inscripciones_evento WHERE evento_id = ?');
    $stmt->execute([$id]);
    $ocupadas = (int) $stmt->fetchColumn();
}

$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    comprobarCsrf();

    /* --- Quitar a alguien de la lista de inscritos --- */
    if (($_POST['accion'] ?? '') === 'quitar' && $id) {
        $cliente = filter_var($_POST['cliente_id'] ?? null, FILTER_VALIDATE_INT);
        if ($cliente) {
            $pdo->prepare('DELETE FROM inscripciones_evento WHERE evento_id = ? AND cliente_id = ?')->execute([$id, $cliente]);
            flash('ok', 'Inscripción eliminada. Sus plazas vuelven a estar libres.');
        }
        redirigir("evento.php?id=$id#inscritos");
    }

    /* --- Guardar el evento --- */
    $evento = [
        'titulo'      => trim((string) ($_POST['titulo'] ?? '')),
        'descripcion' => trim((string) ($_POST['descripcion'] ?? '')),
        'fecha'       => (string) ($_POST['fecha'] ?? ''),
        'hora'        => (string) ($_POST['hora'] ?? ''),
        'plazas'      => (string) ($_POST['plazas'] ?? ''),
        'precio'      => str_replace(',', '.', trim((string) ($_POST['precio'] ?? ''))),
        'imagen'      => trim((string) ($_POST['imagen'] ?? '')),
        'publicado'   => isset($_POST['publicado']) ? 1 : 0,
    ];

    $fecha = fechaValida($evento['fecha']);
    $plazas = filter_var($evento['plazas'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);

    if (mb_strlen($evento['titulo']) < 3 || mb_strlen($evento['titulo']) > 100) $errores[] = 'El título debe tener entre 3 y 100 caracteres.';
    if (mb_strlen($evento['descripcion']) < 10) $errores[] = 'Escribe una descripción de al menos 10 caracteres.';
    if (!$fecha) $errores[] = 'Elige una fecha.';
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $evento['hora'])) $errores[] = 'Elige una hora.';
    if ($plazas === false) $errores[] = 'Las plazas deben ser un número entre 1 y 500.';
    elseif ($plazas < $ocupadas) $errores[] = "Ya hay $ocupadas plazas ocupadas: no puedes dejar menos plazas que eso.";
    if (!is_numeric($evento['precio']) || $evento['precio'] < 0 || $evento['precio'] > 9999) $errores[] = 'El precio no es válido.';
    if ($evento['imagen'] !== '' && !filter_var($evento['imagen'], FILTER_VALIDATE_URL)) $errores[] = 'La imagen debe ser una dirección web (https://…).';
    if (!$id && $fecha && new DateTimeImmutable($evento['fecha'] . ' ' . $evento['hora']) < new DateTimeImmutable()) {
        $errores[] = 'La fecha del evento ya ha pasado.';
    }

    if (!$errores) {
        $valores = [
            $evento['titulo'], $evento['descripcion'], $evento['fecha'] . ' ' . $evento['hora'] . ':00',
            $plazas, $evento['precio'], $evento['imagen'] ?: null, $evento['publicado'],
        ];
        if ($id) {
            $pdo->prepare('UPDATE eventos SET titulo = ?, descripcion = ?, fecha = ?, plazas = ?, precio = ?, imagen = ?, publicado = ? WHERE id = ?')
                ->execute([...$valores, $id]);
        } else {
            $pdo->prepare('INSERT INTO eventos (titulo, descripcion, fecha, plazas, precio, imagen, publicado) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute($valores);
        }
        flash('ok', '«' . $evento['titulo'] . '» guardado.');
        redirigir('eventos.php');
    }
}

$inscritos = [];
if ($id) {
    $stmt = $pdo->prepare(
        'SELECT c.id AS cliente_id, c.nombre, c.telefono, c.email, i.plazas, i.fecha
         FROM inscripciones_evento i
         JOIN clientes c ON c.id = i.cliente_id
         WHERE i.evento_id = ?
         ORDER BY i.fecha'
    );
    $stmt->execute([$id]);
    $inscritos = $stmt->fetchAll();
}

cabecera($id ? 'Editar evento' : 'Nuevo evento', 'eventos');
?>
<header class="page-head">
    <div>
        <a class="muted small" href="eventos.php">← Volver a eventos</a>
        <h1><?= $id ? 'Editar «' . e($evento['titulo']) . '»' : 'Nuevo evento' ?></h1>
    </div>
</header>

<?php foreach ($errores as $err): ?><p class="flash flash-error"><?= e($err) ?></p><?php endforeach; ?>

<form method="post" class="panel form form-grid">
    <?= campoCsrf() ?>
    <label class="span-all">Título <input name="titulo" value="<?= e($evento['titulo']) ?>" maxlength="100" placeholder="Por ejemplo: Cata de aceites de Granada" required></label>
    <label class="span-all">Descripción <textarea name="descripcion" rows="3" required><?= e($evento['descripcion']) ?></textarea></label>
    <label>Fecha <input type="date" name="fecha" value="<?= e($evento['fecha']) ?>" required></label>
    <label>Hora <input type="time" name="hora" value="<?= e($evento['hora']) ?>" step="900" required></label>
    <label>Plazas <input type="number" name="plazas" min="<?= max(1, $ocupadas) ?>" max="500" value="<?= e((string) $evento['plazas']) ?>" required></label>
    <label>Precio por persona (€) <input name="precio" inputmode="decimal" value="<?= e((string) $evento['precio']) ?>" required></label>
    <label class="span-2"><span>Foto (URL) <span class="muted">(opcional)</span></span><input type="url" name="imagen" value="<?= e((string) $evento['imagen']) ?>" placeholder="https://"></label>
    <fieldset class="span-all checks">
        <legend>Visibilidad</legend>
        <label class="check"><input type="checkbox" name="publicado" <?= $evento['publicado'] ? 'checked' : '' ?>> Publicado en la web</label>
    </fieldset>
    <div class="span-all form-actions">
        <button class="btn btn-primary" type="submit">Guardar evento</button>
        <a class="btn btn-quiet" href="eventos.php">Cancelar</a>
    </div>
</form>

<?php if ($id): ?>
<section class="panel" id="inscritos">
    <h2 class="day-title">Inscritos <span class="muted"><?= $ocupadas ?> de <?= (int) $evento['plazas'] ?> plazas · <?= euro($ocupadas * (float) $evento['precio']) ?> previstos</span></h2>
    <?php if (!$inscritos): ?>
        <p class="empty-inline">Todavía no se ha apuntado nadie.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Nombre</th><th>Teléfono</th><th>Email</th><th>Plazas</th><th>Apuntado el</th><th><span class="sr-only">Acciones</span></th></tr></thead>
            <tbody>
            <?php foreach ($inscritos as $i): ?>
                <tr>
                    <td><?= e($i['nombre']) ?></td>
                    <td><a href="tel:<?= e($i['telefono']) ?>"><?= e($i['telefono']) ?></a></td>
                    <td><a href="mailto:<?= e($i['email']) ?>"><?= e($i['email']) ?></a></td>
                    <td class="num"><strong><?= (int) $i['plazas'] ?></strong></td>
                    <td class="muted small"><?= e((new DateTime($i['fecha']))->format('d/m/Y H:i')) ?></td>
                    <td class="actions">
                        <form method="post">
                            <?= campoCsrf() ?><input type="hidden" name="accion" value="quitar"><input type="hidden" name="cliente_id" value="<?= (int) $i['cliente_id'] ?>">
                            <button class="btn btn-small btn-danger" data-confirm="¿Quitar a <?= e($i['nombre']) ?> de la lista? Sus plazas quedarán libres.">Quitar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php pie();
