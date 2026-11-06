<?php
/**
 * El HTML de un correo guardado, para el marco de correos.php.
 * Aislado (sandbox, sin scripts): aunque un nombre de cliente llevara HTML
 * raro, aquí no puede ejecutar nada ni tocar el panel.
 */
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
exigirLogin('admin');

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$stmt = db()->prepare('SELECT html FROM correos_enviados WHERE id = ?');
$stmt->execute([$id ?: 0]);
$html = $stmt->fetchColumn();
if ($html === false) { http_response_code(404); exit('No existe ese correo.'); }

header_remove('X-Frame-Options');
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data: https:; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; sandbox");
header('Content-Type: text/html; charset=utf-8');
echo $html;
