<?php
/**
 * Crea el primer usuario administrador.
 * Solo funciona mientras la tabla "usuarios" esté vacía; después se desactiva sola.
 */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

if ((int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0) {
    flash('info', 'El panel ya está instalado. Entra con tu usuario.');
    redirigir('login.php');
}

$errores = [];
$datos = ['nombre' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    comprobarCsrf();
    $datos['nombre'] = trim((string) ($_POST['nombre'] ?? ''));
    $datos['email']  = strtolower(trim((string) ($_POST['email'] ?? '')));
    $clave  = (string) ($_POST['password'] ?? '');
    $clave2 = (string) ($_POST['password2'] ?? '');

    if (mb_strlen($datos['nombre']) < 2)                  $errores[] = 'Escribe tu nombre.';
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) $errores[] = 'El email no es válido.';
    if (strlen($clave) < 8 || !preg_match('/[0-9]/', $clave) || !preg_match('/[a-zA-Z]/', $clave)) {
        $errores[] = 'La contraseña necesita al menos 8 caracteres, con letras y números.';
    }
    if ($clave !== $clave2) $errores[] = 'Las dos contraseñas no coinciden.';

    if (!$errores) {
        db()->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, 'admin')")
            ->execute([$datos['nombre'], $datos['email'], password_hash($clave, PASSWORD_DEFAULT)]);
        flash('ok', 'Administrador creado. Ya puedes entrar.');
        redirigir('login.php');
    }
}

cabecera('Instalación', '');
?>
<div class="auth">
    <svg class="auth-mark" viewBox="0 0 40 48" aria-hidden="true"><path d="M2 47V20C2 10 10 2 20 2s18 8 18 18v27" fill="none" stroke="currentColor" stroke-width="3"/><path d="M20 14l3.5 6.5L30 24l-6.5 3.5L20 34l-3.5-6.5L10 24l6.5-3.5z" fill="currentColor"/></svg>
    <h1>Crea la cuenta de administración</h1>
    <p class="muted">Es el primer acceso al panel. Esta pantalla desaparece en cuanto exista un usuario.</p>

    <?php foreach ($errores as $err): ?><p class="flash flash-error"><?= e($err) ?></p><?php endforeach; ?>

    <form method="post" class="form">
        <?= campoCsrf() ?>
        <label>Nombre <input name="nombre" value="<?= e($datos['nombre']) ?>" required autofocus></label>
        <label>Email <input type="email" name="email" value="<?= e($datos['email']) ?>" autocomplete="username" required></label>
        <label>Contraseña <input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
        <label>Repite la contraseña <input type="password" name="password2" autocomplete="new-password" minlength="8" required></label>
        <button class="btn btn-primary" type="submit">Crear administrador</button>
    </form>
</div>
<?php pie();
