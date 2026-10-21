<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
$yo = exigirLogin('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    comprobarCsrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $email  = strtolower(trim((string) ($_POST['email'] ?? '')));
        $clave  = (string) ($_POST['password'] ?? '');
        $rol    = ($_POST['rol'] ?? '') === 'admin' ? 'admin' : 'sala';

        if (mb_strlen($nombre) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || strlen($clave) < 8 || !preg_match('/[0-9]/', $clave) || !preg_match('/[a-zA-Z]/', $clave)) {
            flash('error', 'Revisa los datos. La contraseña necesita al menos 8 caracteres, con letras y números.');
        } else {
            try {
                $pdo->prepare('INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)')
                    ->execute([$nombre, $email, password_hash($clave, PASSWORD_DEFAULT), $rol]);
                flash('ok', "$nombre ya puede entrar al panel.");
            } catch (PDOException $ex) {
                if ($ex->getCode() !== '23000') throw $ex;
                flash('error', 'Ya existe un usuario con ese email.');
            }
        }
    }

    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id && $accion === 'activo') {
        if ($id === $yo['id']) {
            flash('error', 'No puedes desactivar tu propia cuenta.');
        } else {
            $pdo->prepare('UPDATE usuarios SET activo = NOT activo WHERE id = ?')->execute([$id]);
            flash('ok', 'Acceso actualizado.');
        }
    }
    redirigir('usuarios.php');
}

$usuarios = $pdo->query('SELECT id, nombre, email, rol, activo, ultimo_acceso FROM usuarios ORDER BY activo DESC, nombre')->fetchAll();

cabecera('Equipo', 'usuarios');
?>
<header class="page-head">
    <div>
        <h1>Equipo</h1>
        <p class="muted">Sala solo ve las reservas. Administración gestiona además la carta, las opiniones, los mensajes y el equipo.</p>
    </div>
</header>

<section class="panel">
    <div class="table-wrap">
    <table class="table">
        <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Último acceso</th><th><span class="sr-only">Acciones</span></th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr class="<?= $u['activo'] ? '' : 'row-dim' ?>">
                <td><?= e($u['nombre']) ?><?= (int) $u['id'] === $yo['id'] ? ' <span class="muted">(tú)</span>' : '' ?></td>
                <td><?= e($u['email']) ?></td>
                <td><?= $u['rol'] === 'admin' ? 'Administración' : 'Sala' ?></td>
                <td><?= $u['ultimo_acceso'] ? e((new DateTime($u['ultimo_acceso']))->format('d/m/Y H:i')) : '<span class="muted">Nunca</span>' ?></td>
                <td class="actions">
                    <?php if ((int) $u['id'] !== $yo['id']): ?>
                    <form method="post"><?= campoCsrf() ?><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <button class="btn btn-small btn-quiet" name="accion" value="activo"><?= $u['activo'] ? 'Quitar acceso' : 'Dar acceso' ?></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<section class="panel">
    <h2>Añadir a alguien del equipo</h2>
    <form method="post" class="form form-grid">
        <?= campoCsrf() ?><input type="hidden" name="accion" value="crear">
        <label>Nombre <input name="nombre" required></label>
        <label>Email <input name="email" type="email" required></label>
        <label>Contraseña inicial <input name="password" type="password" minlength="8" autocomplete="new-password" required></label>
        <label>Rol
            <select name="rol"><option value="sala">Sala</option><option value="admin">Administración</option></select>
        </label>
        <div class="span-all form-actions"><button class="btn btn-primary" type="submit">Crear acceso</button></div>
    </form>
</section>
<?php pie();
