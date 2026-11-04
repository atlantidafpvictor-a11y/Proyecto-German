<?php
/**
 * Códigos QR para las mesas: cada QR abre carta.html?mesa=N en el móvil
 * del cliente (carta con precios y alérgenos, en español o inglés).
 * Los QR se dibujan en el navegador (admin/vendor/qrcode.js, licencia MIT),
 * sin enviar nada a servicios de fuera.
 */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
exigirLogin('admin');

$mesas = db()->query(
    'SELECT m.numero, m.capacidad, z.nombre AS zona
     FROM mesas m JOIN zonas z ON z.id = m.zona_id
     WHERE m.activa = 1 ORDER BY m.numero'
)->fetchAll();

cabecera('Códigos QR', 'qr');
?>
<header class="page-head no-print">
    <div>
        <h1>Códigos QR de las mesas</h1>
        <p class="muted">Cada código abre la carta en el móvil del cliente, con su número de mesa, precios y alérgenos. Imprímelos y ponlos en un portamenús.</p>
    </div>
    <button type="button" class="btn btn-primary" id="qr-imprimir">Imprimir</button>
</header>

<section class="panel no-print">
    <form class="form form-grid" id="qr-form">
        <label class="span-2">Dirección de la web
            <input type="url" id="qr-base" required>
            <span class="muted small">Se rellena sola con la dirección actual. Cámbiala si la web tiene un dominio propio.</span>
        </label>
        <label>Imprimir
            <select id="qr-que">
                <option value="todas">Todas las mesas y el QR de la entrada</option>
                <option value="entrada">Solo el QR de la entrada</option>
            </select>
        </label>
    </form>
</section>

<section class="qr-grid" id="qr-grid">
    <article class="qr-card" data-mesa="">
        <p class="qr-mesa">Carta</p>
        <p class="qr-zona">Escaparate o entrada</p>
        <div class="qr-code"></div>
        <p class="qr-texto">Escanea para ver la carta<br><span>Scan to see the menu</span></p>
        <p class="qr-url"></p>
    </article>
    <?php foreach ($mesas as $m): ?>
    <article class="qr-card" data-mesa="<?= (int) $m['numero'] ?>">
        <p class="qr-mesa">Mesa <?= (int) $m['numero'] ?></p>
        <p class="qr-zona"><?= e($m['zona']) ?> · <?= (int) $m['capacidad'] ?> personas</p>
        <div class="qr-code"></div>
        <p class="qr-texto">Escanea para ver la carta<br><span>Scan to see the menu</span></p>
        <p class="qr-url"></p>
    </article>
    <?php endforeach; ?>
</section>

<script src="vendor/qrcode.js?v=<?= ADMIN_VERSION ?>" defer></script>
<script src="qr.js?v=<?= ADMIN_VERSION ?>" defer></script>
<?php pie();
