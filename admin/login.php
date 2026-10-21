<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

if (usuario()) {
    redirigir('index.php');
}

// Si todavía no hay ningún usuario, hay que crear el primero
if ((int) db()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() === 0) {
    redirigir('instalar.php');
}

$error = '';
$email = '';

// Bloqueo sencillo contra fuerza bruta: 5 intentos fallidos = 5 minutos de espera
$_SESSION['intentos'] ??= 0;
$bloqueadoHasta = $_SESSION['bloqueado_hasta'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    comprobarCsrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $clave = (string) ($_POST['password'] ?? '');

    if ($bloqueadoHasta > time()) {
        $error = 'Demasiados intentos. Espera ' . ceil(($bloqueadoHasta - time()) / 60) . ' minutos.';
    } else {
        $stmt = db()->prepare('SELECT id, nombre, email, password_hash, rol FROM usuarios WHERE email = ? AND activo = 1');
        $stmt->execute([strtolower($email)]);
        $u = $stmt->fetch();

        if ($u && password_verify($clave, $u['password_hash'])) {
            session_regenerate_id(true); // nuevo id de sesión: evita robo de sesión
            $_SESSION['usuario'] = ['id' => (int) $u['id'], 'nombre' => $u['nombre'], 'email' => $u['email'], 'rol' => $u['rol']];
            $_SESSION['intentos'] = 0;
            db()->prepare('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?')->execute([$u['id']]);

            // Si el hash es antiguo, se actualiza al algoritmo actual
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($clave, PASSWORD_DEFAULT), $u['id']]);
            }
            redirigir('index.php');
        }

        $_SESSION['intentos']++;
        if ($_SESSION['intentos'] >= 5) {
            $_SESSION['bloqueado_hasta'] = time() + 300;
            $_SESSION['intentos'] = 0;
        }
        usleep(500000); // medio segundo de espera en cada fallo
        $error = 'El email o la contraseña no son correctos.';
    }
}

cabecera('Entrar', '');
?>
<div class="auth">
    <svg class="auth-mark" viewBox="0 0 40 48" aria-hidden="true"><path d="M2 47V20C2 10 10 2 20 2s18 8 18 18v27" fill="none" stroke="currentColor" stroke-width="3"/><path d="M20 14l3.5 6.5L30 24l-6.5 3.5L20 34l-3.5-6.5L10 24l6.5-3.5z" fill="currentColor"/></svg>
    <h1>Panel del restaurante</h1>
    <p class="muted">Acceso solo para el equipo de Sabor Mediterráneo.</p>

    <?php if ($error): ?><p class="flash flash-error" role="alert"><?= e($error) ?></p><?php endif; ?>

    <form method="post" class="form">
        <?= campoCsrf() ?>
        <label>Email
            <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
        </label>
        <label>Contraseña
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button class="btn btn-primary" type="submit">Entrar</button>
    </form>
    <a class="muted small" href="../index.html">Volver a la web</a>
</div>
<?php pie();
