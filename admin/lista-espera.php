<?php
/**
 * Lista de espera: quién espera mesa para los próximos días.
 * Si alguien cancela, la web les avisa por email sola (api/avisos.php);
 * aquí el personal ve la lista para llamarles o darles una mesa a mano.
 */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
exigirLogin();
$pdo = db();

try {
    $pdo->query('SELECT 1 FROM lista_espera LIMIT 1');
} catch (PDOException) {
    cabecera('Lista de espera', 'espera');
    echo '<div class="empty"><h2>Falta la tabla de la lista de espera</h2><p>Ejecuta <code>database/migraciones/008_correo_lista_espera.sql</code> y vuelve a entrar.</p></div>';
    pie();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    comprobarCsrf();
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $accion = (string) ($_POST['accion'] ?? '');
    if ($id && $accion === 'atendido') {
        $pdo->prepare("UPDATE lista_espera SET estado = 'atendido' WHERE id = ?")->execute([$id]);
        flash('ok', 'Marcado como atendido.');
    } elseif ($id && $accion === 'esperando') {
        $pdo->prepare("UPDATE lista_espera SET estado = 'esperando', avisado_en = NULL WHERE id = ?")->execute([$id]);
        flash('ok', 'Vuelve a estar en espera.');
    } elseif ($id && $accion === 'borrar') {
        $pdo->prepare('DELETE FROM lista_espera WHERE id = ?')->execute([$id]);
        flash('ok', 'Quitado de la lista.');
    }
    redirigir('lista-espera.php');
}

$filas = $pdo->query(
    "SELECT * FROM lista_espera WHERE fecha >= CURRENT_DATE
     ORDER BY fecha, FIELD(turno, 'comida', 'cena'), FIELD(estado, 'esperando', 'avisado', 'atendido'), creado_en"
)->fetchAll();
$porDia = [];
foreach ($filas as $f) $porDia[$f['fecha']][] = $f;
$ESTADOS = ['esperando' => ['Esperando', 'pendiente'], 'avisado' => ['Avisado por email', 'confirmada'], 'atendido' => ['Atendido', 'completada']];

cabecera('Lista de espera', 'espera');
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Servicio</p>
        <h1>Lista de espera</h1>
        <p class="muted">Clientes que querían mesa en un turno completo. Cuando alguien cancela y queda sitio para su grupo, la web les manda un email con un enlace para reservar (se la queda quien reserve primero).</p>
    </div>
</header>

<?php if (!$porDia): ?>
    <div class="empty"><h2>Nadie en espera</h2><p>Cuando un turno se llene, los clientes podrán apuntarse desde la web.</p></div>
<?php endif; ?>

<?php foreach ($porDia as $fecha => $lista): ?>
<section class="panel">
    <h2 class="day-title"><?= e(ucfirst(fechaLarga($fecha))) ?> <span class="muted"><?= count($lista) ?> en la lista</span></h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Turno</th><th>Cliente</th><th class="num">Personas</th><th>Contacto</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead>
            <tbody>
            <?php foreach ($lista as $f): [$txt, $pill] = $ESTADOS[$f['estado']]; ?>
                <tr<?= $f['estado'] === 'atendido' ? ' class="row-dim"' : '' ?>>
                    <td><?= $f['turno'] === 'cena' ? 'Cena' : 'Comida' ?></td>
                    <td><strong><?= e($f['nombre']) ?></strong><br><span class="muted small">Apuntado <?= e(date('d/m H:i', strtotime($f['creado_en']))) ?><?= $f['idioma'] === 'en' ? ' · inglés' : '' ?></span></td>
                    <td class="num"><?= (int) $f['personas'] ?></td>
                    <td><a href="tel:<?= e($f['telefono']) ?>"><?= e($f['telefono']) ?></a><br><a class="small" href="mailto:<?= e($f['email']) ?>"><?= e($f['email']) ?></a></td>
                    <td><span class="pill pill-<?= $pill ?>"><?= $txt ?></span><?php if ($f['avisado_en']): ?><br><span class="muted small"><?= e(date('d/m H:i', strtotime($f['avisado_en']))) ?></span><?php endif; ?></td>
                    <td class="actions">
                        <form method="post">
                            <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                            <?php if ($f['estado'] !== 'atendido'): ?>
                                <button class="btn btn-small" name="accion" value="atendido">Atendido</button>
                            <?php else: ?>
                                <button class="btn btn-small btn-quiet" name="accion" value="esperando">Volver a esperar</button>
                            <?php endif; ?>
                            <button class="btn btn-small btn-danger" name="accion" value="borrar" data-confirm="¿Quitar a <?= e($f['nombre']) ?> de la lista?">Quitar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endforeach; ?>
<?php pie();
