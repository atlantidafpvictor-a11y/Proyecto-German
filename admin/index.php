<?php
/** Inicio del panel: cómo va el día de un vistazo. */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
$u = exigirLogin();
$esAdmin = $u['rol'] === 'admin';
$pdo = db();

/* ---------- Cifras del día ---------- */
$hoy = $pdo->query(
    "SELECT COUNT(*) AS reservas, COALESCE(SUM(personas), 0) AS comensales
     FROM reservas WHERE fecha = CURRENT_DATE AND estado IN ('pendiente', 'confirmada', 'completada')"
)->fetch();
$pendientes = (int) $pdo->query("SELECT COUNT(*) FROM reservas WHERE estado = 'pendiente' AND fecha >= CURRENT_DATE")->fetchColumn();
$plazas     = (int) $pdo->query('SELECT COALESCE(SUM(capacidad), 0) FROM mesas WHERE activa = 1')->fetchColumn();
$semana     = (int) $pdo->query(
    "SELECT COALESCE(SUM(personas), 0) FROM reservas
     WHERE fecha BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL 6 DAY AND estado IN ('pendiente', 'confirmada')"
)->fetchColumn();
$capacidadDia = max(1, $plazas * 2); // dos turnos
$ocupHoy = min(100, (int) round((int) $hoy['comensales'] / $capacidadDia * 100));

/* ---------- ¿Abierto ahora? (tabla horarios) ---------- */
$stmt = $pdo->prepare(
    "SELECT turno, TIME_FORMAT(apertura, '%H:%i') AS apertura, TIME_FORMAT(cierre, '%H:%i') AS cierre
     FROM horarios WHERE dia_semana = ? AND apertura IS NOT NULL ORDER BY apertura"
);
$stmt->execute([(int) date('N')]);
$turnos = $stmt->fetchAll();
$aMin = fn(string $h): int => ((int) substr($h, 0, 2) ?: 24) * 60 + (int) substr($h, 3, 2);
$ahora = (int) date('G') * 60 + (int) date('i');
$abierto = null;
$proximo = null;
foreach ($turnos as $t) {
    if ($ahora >= $aMin($t['apertura']) && $ahora < $aMin($t['cierre'])) $abierto = $t;
    elseif (!$proximo && $ahora < $aMin($t['apertura'])) $proximo = $t;
}
$estadoTexto = $abierto ? 'Abierto · servicio de ' . $abierto['turno'] . ' hasta las ' . $abierto['cierre']
    : ($proximo ? 'Cerrado ahora · abrimos a las ' . $proximo['apertura'] : ($turnos ? 'Servicio de hoy terminado' : 'Hoy cerramos'));

/* ---------- Servicio de hoy, separado en comida y cena ---------- */
$servicio = $pdo->query('SELECT * FROM vista_reservas_hoy')->fetchAll();
$idPorCodigo = $pdo->query('SELECT codigo, id FROM reservas WHERE fecha = CURRENT_DATE')->fetchAll(PDO::FETCH_KEY_PAIR);
$porTurno = ['comida' => [], 'cena' => []];
foreach ($servicio as $r) {
    $porTurno[substr($r['hora'], 0, 5) < '17:00' ? 'comida' : 'cena'][] = $r;
}
$estados = ['pendiente' => 'Sin mesa', 'confirmada' => 'Confirmada', 'completada' => 'Han llegado'];

/* ---------- Próximas dos semanas ---------- */
$ocupacion = $pdo->query(
    'SELECT fecha, comensales FROM vista_ocupacion_diaria
     WHERE fecha BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL 13 DAY'
)->fetchAll(PDO::FETCH_KEY_PAIR);
$cerradosSemana = array_map('intval', $pdo->query('SELECT DISTINCT dia_semana FROM horarios GROUP BY dia_semana HAVING MAX(apertura) IS NULL')->fetchAll(PDO::FETCH_COLUMN));
$dias = [];
$max = 1;
for ($i = 0; $i < 14; $i++) {
    $f = (new DateTimeImmutable('today'))->modify("+$i days");
    $n = (int) ($ocupacion[$f->format('Y-m-d')] ?? 0);
    $dias[] = ['fecha' => $f, 'n' => $n];
    $max = max($max, $n);
}

/* ---------- Columna de la derecha: pizarra, cosas por atender y próximo evento ---------- */
$pizarra = [];
try {
    $pizarra = $pdo->query('SELECT id, nombre, agotado, destacado FROM pizarra WHERE visible = 1 ORDER BY orden, id')->fetchAll();
} catch (PDOException) { // la tabla llega con la migración 007
}
$porAtender = [];
if ($esAdmin) {
    $cuenta = function (string $sql) use ($pdo): int {
        try { return (int) $pdo->query($sql)->fetchColumn(); } catch (PDOException) { return 0; }
    };
    $porAtender = [
        ['presupuestos.php', 'presupuestos', 'Presupuestos nuevos', $cuenta("SELECT COUNT(*) FROM presupuestos WHERE estado = 'nueva'")],
        ['mensajes.php', 'mensajes', 'Mensajes sin leer', $cuenta('SELECT COUNT(*) FROM mensajes_contacto WHERE leido = 0')],
        ['opiniones.php', 'opiniones', 'Opiniones por revisar', $cuenta('SELECT COUNT(*) FROM opiniones WHERE aprobada = 0')],
    ];
}
$evento = $pdo->query(
    "SELECT e.id, e.titulo, e.fecha, e.plazas, COALESCE(SUM(i.plazas), 0) AS ocupadas
     FROM eventos e LEFT JOIN inscripciones_evento i ON i.evento_id = e.id
     WHERE e.publicado = 1 AND e.fecha >= CURRENT_DATE
     GROUP BY e.id, e.titulo, e.fecha, e.plazas ORDER BY e.fecha LIMIT 1"
)->fetch();
$meses = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

$h = (int) date('G');
$saludo = $h >= 6 && $h < 13 ? 'Buenos días' : ($h >= 13 && $h < 21 ? 'Buenas tardes' : 'Buenas noches');

cabecera('Hoy', 'inicio');
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Resumen del día</p>
        <h1><?= $saludo ?>, <?= e(explode(' ', $u['nombre'])[0]) ?></h1>
        <p class="open-pill<?= $abierto ? ' is-open' : '' ?>"><?= e($estadoTexto) ?></p>
    </div>
    <div class="actions">
        <a class="btn" href="pizarra.php"><?= icono('pizarra') ?>Pizarra del día</a>
        <a class="btn btn-primary" href="reservas.php?nueva=1"><?= icono('telefono') ?>Reserva por teléfono</a>
    </div>
</header>

<?php
// Aviso si el servidor tiene algún problema (solo lo ve Administración)
$informe = $esAdmin ? estadoServidor() : null;
if ($informe && ($informe['viejo'] || $informe['estado'] !== 'ok')):
    $malos = array_filter($informe['checks'], fn($c) => $c['estado'] !== 'ok');
    $texto = $informe['viejo']
        ? 'La monitorización no envía datos desde hace ' . $informe['minutos'] . ' minutos.'
        : implode(' · ', array_map(fn($c) => $c['nombre'] . ': ' . $c['detalle'], $malos));
?>
    <p class="flash flash-<?= $informe['estado'] === 'error' || $informe['viejo'] ? 'error' : 'info' ?> server-alert" role="status">
        <span><strong>Servidor:</strong> <?= e($texto) ?></span>
        <a href="estado.php">Ver el estado del servidor</a>
    </p>
<?php endif; ?>

<section class="stats" aria-label="Resumen">
    <div class="stat">
        <span class="stat-ico"><?= icono('reservas') ?></span>
        <p class="stat-label">Reservas hoy</p>
        <p class="stat-n"><?= (int) $hoy['reservas'] ?></p>
        <p class="stat-meta"><?= count($porTurno['comida']) ?> en comida · <?= count($porTurno['cena']) ?> en cena</p>
    </div>
    <div class="stat">
        <span class="stat-ico"><?= icono('personas') ?></span>
        <p class="stat-label">Comensales hoy</p>
        <p class="stat-n"><?= (int) $hoy['comensales'] ?><small> / <?= $capacidadDia ?></small></p>
        <div class="stat-bar" role="progressbar" aria-label="Ocupación de hoy" aria-valuenow="<?= $ocupHoy ?>" aria-valuemin="0" aria-valuemax="100"><span style="width: <?= $ocupHoy ?>%"></span></div>
        <p class="stat-meta"><?= $ocupHoy ?> % de las plazas de los dos turnos</p>
    </div>
    <div class="stat">
        <span class="stat-ico"><?= icono('eventos') ?></span>
        <p class="stat-label">Próximos 7 días</p>
        <p class="stat-n"><?= $semana ?></p>
        <p class="stat-meta">comensales con reserva</p>
    </div>
    <a class="stat stat-link <?= $pendientes ? 'stat-alert' : '' ?>" href="reservas.php?estado=pendiente">
        <span class="stat-ico"><?= icono($pendientes ? 'aviso' : 'reservas') ?></span>
        <p class="stat-label">Sin mesa asignada</p>
        <p class="stat-n"><?= $pendientes ?></p>
        <p class="stat-meta"><?= $pendientes ? 'Pulsa para asignarles mesa' : 'Todo asignado' ?></p>
    </a>
</section>

<div class="dash">
    <div>
        <section class="panel">
            <div class="panel-head">
                <h2>Servicio de hoy</h2>
                <a href="reservas.php">Ver todas las reservas</a>
            </div>
            <?php if (!$servicio): ?>
                <p class="empty-inline">No hay reservas para hoy todavía.</p>
            <?php endif; ?>
            <?php foreach (['comida' => 'Comida', 'cena' => 'Cena'] as $clave => $nombre): if (!$porTurno[$clave]) continue; ?>
            <div class="turno">
                <p class="turno-title"><?= $nombre ?> <span class="muted"><?= count($porTurno[$clave]) ?> mesas · <?= array_sum(array_column($porTurno[$clave], 'personas')) ?> comensales</span></p>
                <ul class="service-list">
                <?php foreach ($porTurno[$clave] as $r): $id = $idPorCodigo[$r['codigo']] ?? 0; ?>
                    <li class="service-item<?= $r['estado'] === 'completada' ? ' is-done' : '' ?>">
                        <span class="service-hora"><?= e(substr($r['hora'], 0, 5)) ?></span>
                        <div class="service-who">
                            <strong><?= e($r['cliente']) ?></strong>
                            <p class="service-meta">
                                <span><?= (int) $r['personas'] ?> pax</span>
                                <span><?= e($r['zona'] ?? '—') ?> · <?= $r['mesa'] ? 'Mesa ' . (int) $r['mesa'] : '<em>sin mesa</em>' ?></span>
                                <a href="tel:<?= e($r['telefono']) ?>"><?= e($r['telefono']) ?></a>
                            </p>
                        </div>
                        <div class="service-actions">
                            <span class="pill pill-<?= e($r['estado']) ?>"><?= e($estados[$r['estado']] ?? $r['estado']) ?></span>
                            <?php if ($id && $r['estado'] !== 'completada'): ?>
                            <form method="post" action="reservas.php">
                                <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="volver" value="index.php">
                                <?php if ($r['estado'] === 'pendiente'): ?>
                                    <button class="btn btn-small btn-primary" name="accion" value="confirmar">Asignar mesa</button>
                                <?php else: ?>
                                    <button class="btn btn-small" name="accion" value="completada">Han llegado</button>
                                    <button class="btn btn-small btn-quiet" name="accion" value="no_presentado">No vinieron</button>
                                <?php endif; ?>
                            </form>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($r['comentarios'])): ?><p class="service-notes"><?= e($r['comentarios']) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </section>

        <section class="panel">
            <div class="panel-head">
                <h2>Comensales reservados · próximas dos semanas</h2>
            </div>
            <div class="bars" role="img" aria-label="Gráfico de comensales por día">
                <?php foreach ($dias as $i => $d):
                    $cerrado = in_array((int) $d['fecha']->format('N'), $cerradosSemana, true);
                    $nivel = $d['n'] / $capacidadDia;
                    $clase = $i === 0 ? 'bar-hoy' : ($cerrado ? 'bar-closed' : ($nivel >= .6 ? '' : ($nivel >= .3 ? 'bar-medio' : 'bar-bajo')));
                ?>
                    <div class="bar <?= $clase ?>" title="<?= e(fechaLarga($d['fecha']->format('Y-m-d'))) ?>: <?= $d['n'] ?> comensales<?= $cerrado ? ' (cerrado)' : '' ?>">
                        <span class="bar-n"><?= $d['n'] ?: '' ?></span>
                        <span class="bar-fill" style="height: <?= round($d['n'] / $max * 100) ?>%"></span>
                        <span class="bar-label"><?= $i === 0 ? 'Hoy' : ['L', 'M', 'X', 'J', 'V', 'S', 'D'][(int) $d['fecha']->format('N') - 1] ?><br><?= $d['fecha']->format('j') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="bars-legend"><span class="l-hoy">Hoy</span><span>Lleno (60 % o más)</span><span class="l-medio">Medio</span><span class="l-bajo">Flojo</span></p>
        </section>
    </div>

    <aside class="dash-side">
        <section class="panel">
            <div class="panel-head">
                <h2>Pizarra de hoy</h2>
                <a href="pizarra.php">Editar</a>
            </div>
            <?php if (!$pizarra): ?>
                <p class="empty-inline">La pizarra está vacía. <a href="pizarra.php">Añade lo que ha llegado hoy</a>.</p>
            <?php else: ?>
            <ul class="mini-list">
                <?php foreach ($pizarra as $p): ?>
                <li>
                    <span class="grow<?= $p['agotado'] ? ' out' : '' ?>"><?= e($p['nombre']) ?><?= $p['destacado'] ? ' <span class="pill pill-star">Portada</span>' : '' ?></span>
                    <form method="post" action="pizarra.php">
                        <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="volver" value="index.php">
                        <button class="btn btn-small<?= $p['agotado'] ? '' : ' btn-quiet' ?>" name="accion" value="agotado"><?= $p['agotado'] ? 'Vuelve a haber' : 'Se acabó' ?></button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <?php if ($porAtender): ?>
        <section class="panel">
            <div class="panel-head"><h2>Por atender</h2></div>
            <ul class="mini-list">
                <?php foreach ($porAtender as [$href, $ico, $texto, $n]): ?>
                <li><a class="todo-link" href="<?= $href ?>"><span class="todo-n<?= $n ? ' is-hot' : '' ?>"><?= $n ?></span><?= e($texto) ?><?= icono('flecha') ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <section class="panel">
            <div class="panel-head">
                <h2>Próximo evento</h2>
                <?php if ($esAdmin): ?><a href="eventos.php">Todos</a><?php endif; ?>
            </div>
            <?php if (!$evento): ?>
                <p class="empty-inline">No hay eventos programados.</p>
            <?php else: $fe = new DateTimeImmutable($evento['fecha']); $pct = min(100, (int) round($evento['ocupadas'] / max(1, $evento['plazas']) * 100)); ?>
            <div class="event-mini">
                <p class="event-mini-date"><strong><?= $fe->format('j') ?></strong><span><?= $meses[(int) $fe->format('n') - 1] ?></span></p>
                <div>
                    <h3><?= e($evento['titulo']) ?></h3>
                    <p class="muted small"><?= e(ucfirst(fechaLarga($evento['fecha']))) ?> · <?= $fe->format('H:i') ?></p>
                    <div class="seats-admin">
                        <span class="seats-admin-bar"><span style="width: <?= $pct ?>%"></span></span>
                        <span class="small"><strong><?= (int) $evento['ocupadas'] ?></strong> de <?= (int) $evento['plazas'] ?> plazas</span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </section>
    </aside>
</div>
<?php pie();
