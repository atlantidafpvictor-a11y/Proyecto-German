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
// 'unsafe-eval': las plantillas de GoAccess (Hogan.js) se compilan con new Function().
// Es seguro aquí porque el marco está aislado (sandbox) y no puede conectarse a nada.
header("Content-Security-Policy: default-src 'none'; script-src 'unsafe-inline' 'unsafe-eval'; style-src 'unsafe-inline'; img-src data:; font-src data:; connect-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; sandbox allow-scripts");
header('Content-Type: text/html; charset=utf-8');

// En un marco aislado no hay localStorage (da error al usarlo) y GoAccess guarda ahí
// sus preferencias. Se le da uno que solo vive en memoria mientras la página está abierta.
$almacen = <<<'JS'
<script>
(function () {
    function memoria() {
        var d = {};
        return {
            getItem: function (k) { return Object.prototype.hasOwnProperty.call(d, k) ? d[k] : null; },
            setItem: function (k, v) { d[k] = String(v); },
            removeItem: function (k) { delete d[k]; },
            clear: function () { d = {}; },
            key: function (i) { return Object.keys(d)[i] || null; },
            get length() { return Object.keys(d).length; }
        };
    }
    ['localStorage', 'sessionStorage'].forEach(function (n) {
        try { window[n].getItem('x'); }
        catch (e) { var m = memoria(); Object.defineProperty(window, n, { configurable: true, get: function () { return m; } }); }
    });
})();
</script>
JS;
$html = (string) file_get_contents($informe);
// El arreglo va lo primero de todo, antes de los scripts de GoAccess
echo preg_replace('/<head[^>]*>/i', '$0' . $almacen, $html, 1) ?? $html;
