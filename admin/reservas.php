<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
exigirLogin();
$pdo = db();

$ESTADOS = [
    'pendiente'     => 'Pendiente',
    'confirmada'    => 'Confirmada',
    'completada'    => 'Completada',
    'cancelada'     => 'Cancelada',
    'no_presentado' => 'No se presentó',
];

/* =========================================================
   ACCIONES (POST)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    comprobarCsrf();
    $accion = (string) ($_POST['accion'] ?? '');
    $volver = in_array($_POST['volver'] ?? '', ['index.php', 'reservas.php'], true) ? $_POST['volver'] : 'reservas.php';
    if ($volver === 'reservas.php' && !empty($_POST['qs'])) {
        $volver .= '?' . http_build_query(array_intersect_key((array) json_decode((string) $_POST['qs'], true), array_flip(['desde', 'estado', 'q'])));
    }

    /* --- Nueva reserva por teléfono --- */
    if ($accion === 'crear') {
        $nombre   = trim((string) ($_POST['nombre'] ?? ''));
        $telefono = preg_replace('/\s+/', '', (string) ($_POST['telefono'] ?? ''));
        $email    = strtolower(trim((string) ($_POST['email'] ?? ''))) ?: null;
        $fecha    = fechaValida((string) ($_POST['fecha'] ?? ''));
        $hora     = (string) ($_POST['hora'] ?? '');
        $personas = filter_var($_POST['personas'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);
        $zona     = filter_var($_POST['zona_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        $notas    = trim((string) ($_POST['comentarios'] ?? '')) ?: null;

        if (mb_strlen($nombre) < 2 || !preg_match('/^\+?[0-9]{9,14}$/', $telefono) || !$fecha
            || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora) || $personas === false
            || ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            flash('error', 'Revisa los datos: nombre, teléfono de 9 cifras, fecha, hora y personas son obligatorios.');
            redirigir('reservas.php?nueva=1');
        }

        $pdo->beginTransaction();
        try {
            if ($email) {
                // Cliente con email: se crea o se actualiza, y se busca su id por el email
                $pdo->prepare('INSERT INTO clientes (nombre, telefono, email) VALUES (?, ?, ?)
                               ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), telefono = VALUES(telefono)')
                    ->execute([$nombre, $telefono, $email]);
                $buscar = $pdo->prepare('SELECT id FROM clientes WHERE email = ?');
                $buscar->execute([$email]);
                $clienteId = (int) $buscar->fetchColumn();
            } else {
                $pdo->prepare('INSERT INTO clientes (nombre, telefono) VALUES (?, ?)')->execute([$nombre, $telefono]);
                $clienteId = (int) $pdo->lastInsertId();
            }

            $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            do {
                $codigo = '';
                for ($i = 0; $i < 6; $i++) $codigo .= $alfabeto[random_int(0, 31)];
                $s = $pdo->prepare('SELECT 1 FROM reservas WHERE codigo = ?');
                $s->execute([$codigo]);
            } while ($s->fetchColumn());

            $pdo->prepare('INSERT INTO reservas (codigo, cliente_id, zona_id, fecha, hora, personas, comentarios) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$codigo, $clienteId, $zona, $fecha->format('Y-m-d'), $hora, $personas, $notas]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (PDOException $ex) {
            $pdo->rollBack();
            if ($ex->getCode() === '45000') {
                flash('error', 'No se puede reservar ese día: es una fecha pasada o el restaurante está cerrado.');
                redirigir('reservas.php?nueva=1');
            }
            throw $ex;
        }

        // Intentamos asignar mesa; si no hay, se queda pendiente para resolverlo a mano
        try {
            $call = $pdo->prepare('CALL sp_asignar_mesa(?)'); $call->execute([$id]); $call->closeCursor();
            flash('ok', "Reserva $codigo creada y confirmada con mesa.");
        } catch (PDOException) {
            flash('info', "Reserva $codigo creada, pero no hay mesa libre a esa hora. Queda pendiente.");
        }
        redirigir('reservas.php?desde=' . $fecha->format('Y-m-d'));
    }

    /* --- Cambios de estado --- */
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id) {
        if ($accion === 'confirmar') {
            try {
                $call = $pdo->prepare('CALL sp_asignar_mesa(?)'); $call->execute([$id]); $call->closeCursor();
                flash('ok', 'Mesa asignada. La reserva queda confirmada.');
            } catch (PDOException $ex) {
                if ($ex->getCode() !== '45000') throw $ex;
                flash('error', 'No hay ninguna mesa libre para ese grupo a esa hora.');
            }
        } elseif (in_array($accion, ['cancelada', 'completada', 'no_presentado'], true)) {
            $pdo->prepare('UPDATE reservas SET estado = ? WHERE id = ?')->execute([$accion, $id]);
            flash('ok', 'Reserva marcada como «' . $ESTADOS[$accion] . '».');
        }
    }
    redirigir($volver);
}

/* =========================================================
   LISTADO CON FILTROS (GET)
   ========================================================= */
$desde  = fechaValida((string) ($_GET['desde'] ?? '')) ?? new DateTimeImmutable('today');
$estado = array_key_exists($_GET['estado'] ?? '', $ESTADOS) ? $_GET['estado'] : '';
$q      = trim((string) ($_GET['q'] ?? ''));

$sql = "SELECT r.id, r.codigo, r.fecha, TIME_FORMAT(r.hora, '%H:%i') AS hora, r.personas, r.estado, r.comentarios,
               c.nombre, c.telefono, c.email, z.nombre AS zona, m.numero AS mesa
        FROM reservas r
        JOIN clientes c ON c.id = r.cliente_id
        LEFT JOIN zonas z ON z.id = r.zona_id
        LEFT JOIN mesas m ON m.id = r.mesa_id
        WHERE r.fecha >= ?";
$params = [$desde->format('Y-m-d')];
if ($estado) {
    $sql .= ' AND r.estado = ?';
    $params[] = $estado;
}
if ($q !== '') {
    $sql .= ' AND (c.nombre LIKE ? OR c.telefono LIKE ? OR r.codigo = ?)';
    array_push($params, "%$q%", "%$q%", strtoupper($q));
}
$sql .= ' ORDER BY r.fecha, r.hora LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservas = $stmt->fetchAll();

// Agrupar por día para mostrarlas como en una agenda
$porDia = [];
foreach ($reservas as $r) {
    $porDia[$r['fecha']][] = $r;
}

$zonas = $pdo->query('SELECT id, nombre FROM zonas ORDER BY id')->fetchAll();
$qs = json_encode(['desde' => $desde->format('Y-m-d'), 'estado' => $estado, 'q' => $q]);

cabecera('Reservas', 'reservas');
?>
<header class="page-head">
    <h1>Reservas</h1>
    <a class="btn btn-primary" href="?nueva=1#nueva">Añadir reserva por teléfono</a>
</header>

<?php if (isset($_GET['nueva'])): ?>
<section class="panel" id="nueva">
    <h2>Nueva reserva por teléfono</h2>
    <form method="post" class="form form-grid">
        <?= campoCsrf() ?><input type="hidden" name="accion" value="crear">
        <label>Nombre <input name="nombre" required></label>
        <label>Teléfono <input name="telefono" type="tel" required pattern="\+?[0-9 ]{9,15}"></label>
        <label><span>Email <span class="muted">(opcional)</span></span><input name="email" type="email"></label>
        <label>Fecha <input name="fecha" type="date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required></label>
        <label>Hora <input name="hora" type="time" step="900" required></label>
        <label>Personas <input name="personas" type="number" min="1" max="20" value="2" required></label>
        <label>Zona
            <select name="zona_id"><?php foreach ($zonas as $z): ?><option value="<?= (int) $z['id'] ?>"><?= e($z['nombre']) ?></option><?php endforeach; ?></select>
        </label>
        <label class="span-2">Notas <input name="comentarios" maxlength="500"></label>
        <div class="span-all form-actions">
            <button class="btn btn-primary" type="submit">Guardar reserva</button>
            <a class="btn btn-quiet" href="reservas.php">Cancelar</a>
        </div>
    </form>
</section>
<?php endif; ?>

<form class="filters" method="get">
    <label>Desde <input type="date" name="desde" value="<?= e($desde->format('Y-m-d')) ?>"></label>
    <label>Estado
        <select name="estado">
            <option value="">Todos</option>
            <?php foreach ($ESTADOS as $k => $v): ?><option value="<?= $k ?>" <?= $estado === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
        </select>
    </label>
    <label class="grow">Buscar <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nombre, teléfono o código"></label>
    <button class="btn" type="submit">Filtrar</button>
</form>

<?php if (!$porDia): ?>
    <div class="empty">
        <h2>No hay reservas con estos filtros</h2>
        <p>Cambia la fecha o el estado, o <a href="reservas.php">quita los filtros</a>.</p>
    </div>
<?php endif; ?>

<?php foreach ($porDia as $fecha => $lista): $pax = array_sum(array_map(fn($r) => in_array($r['estado'], ['cancelada', 'no_presentado']) ? 0 : $r['personas'], $lista)); ?>
<section class="panel">
    <h2 class="day-title"><?= e(ucfirst(fechaLarga($fecha))) ?> <span class="muted"><?= count($lista) ?> <?= count($lista) === 1 ? 'reserva' : 'reservas' ?> · <?= $pax ?> <?= $pax === 1 ? 'comensal' : 'comensales' ?></span></h2>
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>Hora</th><th>Código</th><th>Cliente</th><th>Pax</th><th>Zona · Mesa</th><th>Notas</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead>
        <tbody>
        <?php foreach ($lista as $r): $activa = in_array($r['estado'], ['pendiente', 'confirmada'], true); ?>
            <tr class="<?= $activa ? '' : 'row-dim' ?>">
                <td class="num"><strong><?= e($r['hora']) ?></strong></td>
                <td class="code"><?= e($r['codigo']) ?></td>
                <td><?= e($r['nombre']) ?><br><a class="muted small" href="tel:<?= e($r['telefono']) ?>"><?= e($r['telefono']) ?></a></td>
                <td class="num"><?= (int) $r['personas'] ?></td>
                <td><?= e($r['zona'] ?? '—') ?> · <?= $r['mesa'] ? 'Mesa ' . (int) $r['mesa'] : '<em>sin mesa</em>' ?></td>
                <td class="notes"><?= e($r['comentarios'] ?? '') ?></td>
                <td><span class="pill pill-<?= e($r['estado']) ?>"><?= e($ESTADOS[$r['estado']]) ?></span></td>
                <td class="actions">
                    <?php if ($activa): ?>
                    <form method="post">
                        <?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><input type="hidden" name="qs" value="<?= e($qs) ?>">
                        <?php if ($r['estado'] === 'pendiente'): ?>
                            <button class="btn btn-small" name="accion" value="confirmar">Asignar mesa</button>
                        <?php else: ?>
                            <button class="btn btn-small" name="accion" value="completada">Han llegado</button>
                        <?php endif; ?>
                        <button class="btn btn-small btn-quiet" name="accion" value="cancelada" onclick="return confirm('¿Cancelar la reserva <?= e($r['codigo']) ?>?')">Cancelar</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>
<?php endforeach; ?>
<?php pie();
