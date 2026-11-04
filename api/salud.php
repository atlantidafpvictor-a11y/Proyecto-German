<?php
/**
 * GET api/salud.php
 * Comprobación de salud ("health check"): responde 200 si PHP y la base de
 * datos funcionan, y 503 si no. La usan scripts/monitor.sh y cualquier
 * servicio externo de vigilancia. A propósito no dice versiones ni datos
 * internos: es pública.
 */
declare(strict_types=1);
require __DIR__ . '/db.php';

try {
    $bd = (int) db()->query('SELECT 1')->fetchColumn() === 1;
} catch (Throwable $e) {
    error_log('[SALUD] ' . $e->getMessage());
    $bd = false;
}
responder(['ok' => $bd, 'bd' => $bd, 'hora' => date('c')], $bd ? 200 : 503);
