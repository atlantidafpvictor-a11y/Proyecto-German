<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

// Solo por POST y con token, para que un enlace externo no pueda cerrar la sesión
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    comprobarCsrf();
    $_SESSION = [];
    session_destroy();
    session_start();
    flash('info', 'Has cerrado la sesión.');
}
redirigir('login.php');
