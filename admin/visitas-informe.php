<?php
/**
 * Devuelve el informe HTML de GoAccess para el marco de visitas.php.
 * Solo Administración. Se sirve con una política de seguridad propia:
 * - "sandbox allow-scripts": el informe se ejecuta como si fuera de otra web,
 *   sin acceso a las cookies del panel ni a sus páginas.
 * - sin conexiones a ningún sitio (connect-src 'none'): no puede enviar datos fuera.
 */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
exigirLogin('admin');

$informe = '/var/lib/sabor-mediterraneo/visitas.html';
if (!is_readable($informe)) {
    http_response_code(404);
    exit('Todavía no hay informe de visitas.');
}

header_remove('X-Frame-Options');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src data:; font-src data:; connect-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; sandbox allow-scripts");
header('Content-Type: text/html; charset=utf-8');
readfile($informe);
