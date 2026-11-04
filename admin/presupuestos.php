<?php
/** Solicitudes de presupuesto de celebraciones (formulario de la web). */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
exigirLogin('admin');
$pdo = db();

$TIPOS   = ['cumpleanos' => 'Cumpleaños o aniversario', 'empresa' => 'Comida o cena de empresa', 'familiar' => 'Celebración familiar', 'grupo' => 'Grupo de amigos', 'otro' => 'Otro'];
$TURNOS  = ['comida' => 'Comida', 'cena' => 'Cena', 'indiferente' => 'Comida o cena'];
$ESTADOS = ['nueva' => 'Nueva', 'respondida' => 'Respondida', 'aceptada' => 'Aceptada', 'descartada' => 'Descartada'];

// ¿Existe la tabla? Si no, falta la migración 006
try {
    $pdo->query('SELECT 1 FROM presupuestos LIMIT 1');
} catch (PDOException $e) {
    if ($e->getCode() !== '42S02') throw $e;
    cabecera('Presupuestos', 'presupuestos');
    echo '<div class="empty"><h2>Falta crear la tabla de presupuestos</h2><p>Importa <code>database/migraciones/006_maridajes_presupuestos.sql</code> en phpMyAdmin (o en el servidor: <code>sudo mysql sabor_mediterraneo &lt; database/migraciones/006_maridajes_presupuestos.sql</code>) y recarga esta página.</p></div>';
    pie();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    comprobarCsrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $accion = (string) ($_POST['accion'] ?? '');
    if ($id && array_key_exists($accion, $ESTADOS)) {
        $pdo->prepare('UPDATE presupuestos SET estado = ? WHERE id = ?')->execute([$accion, $id]);
        flash('ok', 'Solicitud marcada como «' . $ESTADOS[$accion] . '».');
    } elseif ($id && $accion === 'borrar') {
        $pdo->prepare('DELETE FROM presupuestos WHERE id = ?')->execute([$id]);
        flash('ok', 'Solicitud eliminada.');
    }
    redirigir('presupuestos.php' . (isset($_POST['filtro']) && array_key_exists($_POST['filtro'], $ESTADOS) ? '?estado=' . $_POST['filtro'] : ''));
}

$filtro = array_key_exists($_GET['estado'] ?? '', $ESTADOS) ? $_GET['estado'] : '';
$sql = 'SELECT * FROM presupuestos' . ($filtro ? ' WHERE estado = ?' : '') . " ORDER BY estado = 'nueva' DESC, creada_en DESC LIMIT 200";
$stmt = $pdo->prepare($sql);
$stmt->execute($filtro ? [$filtro] : []);
$lista = $stmt->fetchAll();
$cuenta = $pdo->query('SELECT estado, COUNT(*) FROM presupuestos GROUP BY estado')->fetchAll(PDO::FETCH_KEY_PAIR);

cabecera('Presupuestos', 'presupuestos');
?>
<header class="page-head">
    <div>
        <h1>Presupuestos de celebraciones</h1>
        <p class="muted">Solicitudes del formulario «Celebraciones» de la web. Responde por teléfono o email y marca cómo ha quedado cada una.</p>
    </div>
</header>

<nav class="filters" aria-label="Filtrar por estado">
    <a class="btn btn-small <?= $filtro === '' ? 'btn-primary' : 'btn-quiet' ?>" href="presupuestos.php">Todas (<?= array_sum($cuenta) ?>)</a>
    <?php foreach ($ESTADOS as $k => $v): ?>
        <a class="btn btn-small <?= $filtro === $k ? 'btn-primary' : 'btn-quiet' ?>" href="?estado=<?= $k ?>"><?= $v ?> (<?= (int) ($cuenta[$k] ?? 0) ?>)</a>
    <?php endforeach; ?>
</nav>

<?php if (!$lista): ?>
    <div class="empty"><h2>No hay solicitudes<?= $filtro ? ' en este estado' : ' todavía' ?></h2><p>Cuando alguien pida presupuesto desde la web aparecerá aquí.</p></div>
<?php else: ?>
<section class="quote-list">
    <?php foreach ($lista as $p):
        $fecha = $p['fecha'] ? ucfirst(fechaLarga($p['fecha'])) : 'Fecha sin decidir';
        $asunto = rawurlencode(($p['idioma'] === 'en' ? 'Your celebration at Sabor Mediterráneo' : 'Tu celebración en Sabor Mediterráneo') . ' (' . $p['personas'] . ')');
    ?>
    <article class="panel quote <?= $p['estado'] === 'nueva' ? 'quote-new' : '' ?>">
        <header class="quote-head">
            <div>
                <h2><?= e($TIPOS[$p['tipo']] ?? $p['tipo']) ?> · <?= (int) $p['personas'] ?> personas</h2>
                <p class="muted small"><?= e($fecha) ?> · <?= e($TURNOS[$p['turno']] ?? $p['turno']) ?> · recibida el <?= e((new DateTime($p['creada_en']))->format('d/m/Y H:i')) ?><?= $p['idioma'] === 'en' ? ' · <strong>responder en inglés</strong>' : '' ?></p>
            </div>
            <span class="pill pill-<?= $p['estado'] === 'nueva' ? 'pendiente' : ($p['estado'] === 'aceptada' ? 'confirmada' : ($p['estado'] === 'descartada' ? 'cancelada' : 'completada')) ?>"><?= e($ESTADOS[$p['estado']]) ?></span>
        </header>
        <p><strong><?= e($p['nombre']) ?></strong> · <a href="tel:<?= e($p['telefono']) ?>"><?= e($p['telefono']) ?></a> · <a href="mailto:<?= e($p['email']) ?>?subject=<?= $asunto ?>"><?= e($p['email']) ?></a></p>
        <?php if ($p['comentarios']): ?><p class="quote-notes"><?= nl2br(e($p['comentarios'])) ?></p><?php endif; ?>
        <form method="post" class="actions">
            <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="filtro" value="<?= e($filtro) ?>">
            <?php foreach (['respondida' => 'Marcar respondida', 'aceptada' => 'Aceptada', 'descartada' => 'Descartar'] as $k => $t): if ($p['estado'] === $k) continue; ?>
                <button class="btn btn-small <?= $k === 'aceptada' ? '' : 'btn-quiet' ?>" name="accion" value="<?= $k ?>"><?= $t ?></button>
            <?php endforeach; ?>
            <button class="btn btn-small btn-danger" name="accion" value="borrar" data-confirm="¿Eliminar la solicitud de <?= e($p['nombre']) ?>?">Eliminar</button>
        </form>
    </article>
    <?php endforeach; ?>
</section>
<?php endif; ?>
<?php pie();
