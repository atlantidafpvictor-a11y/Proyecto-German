<?php
/**
 * Correos enviados: copia de cada email que ha mandado la web (los últimos 60 días).
 * En "modo pruebas" (sin Gmail configurado) los correos no salen, pero se ven aquí
 * tal cual le llegarían al cliente.
 */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
exigirLogin('admin');
$pdo = db();

$correos = [];
try {
    $correos = $pdo->query('SELECT id, para, asunto, estado, creado_en FROM correos_enviados ORDER BY id DESC LIMIT 100')->fetchAll();
} catch (PDOException) {
    cabecera('Correos enviados', 'correos');
    echo '<div class="empty"><h2>Falta la tabla de correos</h2><p>Ejecuta <code>database/migraciones/008_correo_lista_espera.sql</code> y vuelve a entrar.</p></div>';
    pie();
    exit;
}
$ver = filter_var($_GET['ver'] ?? null, FILTER_VALIDATE_INT) ?: ($correos[0]['id'] ?? null);
$ESTADOS = ['enviado' => ['Enviado', 'completada'], 'pruebas' => ['Modo pruebas', 'pendiente'], 'error' => ['Error', 'cancelada']];

cabecera('Correos enviados', 'correos');
?>
<header class="page-head">
    <div>
        <p class="eyebrow">Clientes</p>
        <h1>Correos enviados</h1>
        <p class="muted">Confirmaciones, recordatorios, cancelaciones y avisos de la lista de espera.
        <?php if (!config('correo_activo')): ?><br><strong>Modo pruebas:</strong> el envío por Gmail no está activado, así que los correos no salen, pero aquí se ven tal cual le llegarían al cliente.<?php endif; ?></p>
    </div>
</header>

<?php if (!$correos): ?>
    <div class="empty"><h2>Todavía no hay correos</h2><p>Haz una reserva de prueba en la web y aparecerá aquí el email de confirmación.</p></div>
<?php else: ?>
<div class="mail-layout">
    <section class="panel mail-list">
        <ul class="mini-list">
        <?php foreach ($correos as $c): [$txt, $pill] = $ESTADOS[$c['estado']]; ?>
            <li<?= (int) $c['id'] === (int) $ver ? ' class="is-current"' : '' ?>>
                <a class="mail-link" href="correos.php?ver=<?= (int) $c['id'] ?>">
                    <strong><?= e($c['asunto']) ?></strong>
                    <span class="muted small"><?= e($c['para']) ?> · <?= e(date('d/m H:i', strtotime($c['creado_en']))) ?></span>
                </a>
                <span class="pill pill-<?= $pill ?>"><?= $txt ?></span>
            </li>
        <?php endforeach; ?>
        </ul>
    </section>
    <iframe class="report-frame mail-frame" src="correo-ver.php?id=<?= (int) $ver ?>" sandbox title="Vista previa del correo"></iframe>
</div>
<?php endif; ?>
<?php pie();
