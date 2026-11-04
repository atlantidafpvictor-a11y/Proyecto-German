<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
$u = exigirLogin();
$pdo = db();

$hoy = $pdo->query(
    "SELECT COUNT(*) AS reservas, COALESCE(SUM(personas), 0) AS comensales
     FROM reservas WHERE fecha = CURRENT_DATE AND estado IN ('pendiente', 'confirmada', 'completada')"
)->fetch();
$pendientes = (int) $pdo->query("SELECT COUNT(*) FROM reservas WHERE estado = 'pendiente' AND fecha >= CURRENT_DATE")->fetchColumn();
$plazas     = (int) $pdo->query('SELECT COALESCE(SUM(capacidad), 0) FROM mesas WHERE activa = 1')->fetchColumn();
$semana     = $pdo->query(
    "SELECT COALESCE(SUM(personas), 0) FROM reservas
     WHERE fecha BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL 6 DAY AND estado IN ('pendiente', 'confirmada')"
)->fetchColumn();

$servicio = $pdo->query('SELECT * FROM vista_reservas_hoy')->fetchAll();
$idPorCodigo = $pdo->query("SELECT codigo, id FROM reservas WHERE fecha = CURRENT_DATE")->fetchAll(PDO::FETCH_KEY_PAIR);

// Comensales de los próximos 14 días (incluye los días sin reservas)
$ocupacion = $pdo->query(
    'SELECT fecha, comensales FROM vista_ocupacion_diaria
     WHERE fecha BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL 13 DAY'
)->fetchAll(PDO::FETCH_KEY_PAIR);
$dias = [];
$max = 1;
for ($i = 0; $i < 14; $i++) {
    $f = (new DateTimeImmutable('today'))->modify("+$i days");
    $n = (int) ($ocupacion[$f->format('Y-m-d')] ?? 0);
    $dias[] = ['fecha' => $f, 'n' => $n];
    $max = max($max, $n);
}

$estados = ['pendiente' => 'Pendiente', 'confirmada' => 'Confirmada'];

cabecera('Hoy', 'inicio');
?>
<header class="page-head">
    <div>
        <?php $h = (int) date('G'); $saludo = $h >= 6 && $h < 13 ? 'Buenos días' : ($h >= 13 && $h < 21 ? 'Buenas tardes' : 'Buenas noches'); ?>
        <h1><?= $saludo ?>, <?= e(explode(' ', $u['nombre'])[0]) ?></h1>
        <p class="muted">Hoy es <?= e(fechaLarga('today')) ?>.</p>
    </div>
    <a class="btn btn-primary" href="reservas.php?nueva=1">Añadir reserva por teléfono</a>
</header>

<section class="stats" aria-label="Resumen">
    <div class="stat"><p class="stat-n"><?= (int) $hoy['reservas'] ?></p><p>reservas hoy</p></div>
    <div class="stat"><p class="stat-n"><?= (int) $hoy['comensales'] ?><small> / <?= $plazas * 2 ?></small></p><p>comensales hoy (plazas en 2 turnos)</p></div>
    <div class="stat"><p class="stat-n"><?= (int) $semana ?></p><p>comensales en los próximos 7 días</p></div>
    <a class="stat stat-link <?= $pendientes ? 'stat-alert' : '' ?>" href="reservas.php?estado=pendiente"><p class="stat-n"><?= $pendientes ?></p><p>reservas sin mesa asignada</p></a>
</section>

<section class="panel">
    <h2>Servicio de hoy</h2>
    <?php if (!$servicio): ?>
        <p class="empty-inline">No hay reservas para hoy todavía.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>Hora</th><th>Cliente</th><th>Pax</th><th>Zona · Mesa</th><th>Notas</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead>
        <tbody>
        <?php foreach ($servicio as $r): $id = $idPorCodigo[$r['codigo']] ?? 0; ?>
            <tr>
                <td class="num"><strong><?= e(substr($r['hora'], 0, 5)) ?></strong></td>
                <td><?= e($r['cliente']) ?><br><a class="muted small" href="tel:<?= e($r['telefono']) ?>"><?= e($r['telefono']) ?></a></td>
                <td class="num"><?= (int) $r['personas'] ?></td>
                <td><?= e($r['zona'] ?? '—') ?> · <?= $r['mesa'] ? 'Mesa ' . (int) $r['mesa'] : '<em>sin mesa</em>' ?></td>
                <td class="notes"><?= e($r['comentarios'] ?? '') ?></td>
                <td><span class="pill pill-<?= e($r['estado']) ?>"><?= e($estados[$r['estado']] ?? $r['estado']) ?></span></td>
                <td class="actions">
                    <form method="post" action="reservas.php">
                        <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="volver" value="index.php">
                        <?php if ($r['estado'] === 'pendiente'): ?>
                            <button class="btn btn-small" name="accion" value="confirmar">Asignar mesa</button>
                        <?php else: ?>
                            <button class="btn btn-small" name="accion" value="completada">Han llegado</button>
                            <button class="btn btn-small btn-quiet" name="accion" value="no_presentado">No vinieron</button>
                        <?php endif; ?>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Comensales reservados · próximas dos semanas</h2>
    <div class="bars" role="img" aria-label="Gráfico de comensales por día">
        <?php foreach ($dias as $d): ?>
            <div class="bar <?= (int) $d['fecha']->format('N') === 1 ? 'bar-closed' : '' ?>" title="<?= e(fechaLarga($d['fecha']->format('Y-m-d'))) ?>: <?= $d['n'] ?> comensales">
                <span class="bar-n"><?= $d['n'] ?: '' ?></span>
                <span class="bar-fill" style="height: <?= round($d['n'] / $max * 100) ?>%"></span>
                <span class="bar-label"><?= ['L', 'M', 'X', 'J', 'V', 'S', 'D'][(int) $d['fecha']->format('N') - 1] ?><br><?= $d['fecha']->format('j') ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php pie();
