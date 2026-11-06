<?php
/**
 * Recordatorio por email el día antes de cada reserva.
 * Lo ejecuta cron (scripts/servidor/sabor-recordatorios.cron); a mano:
 *     sudo -u www-data php /var/www/sabor-mediterraneo/scripts/recordatorios.php
 * Registro:  journalctl -t sabor-recordatorios
 *
 * Solo para reservas de mañana confirmadas o pendientes que aún no tengan
 * recordatorio (columna recordatorio_en): aunque cron lo lance varias veces
 * al día, cada cliente recibe uno solo.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../api/db.php';
require __DIR__ . '/../api/avisos.php';

$pdo = db();
$manana = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$stmt = $pdo->prepare(
    "SELECT id, codigo FROM reservas
     WHERE fecha = ? AND estado IN ('pendiente', 'confirmada') AND recordatorio_en IS NULL"
);
$stmt->execute([$manana]);
$marcar = $pdo->prepare('UPDATE reservas SET recordatorio_en = NOW() WHERE id = ?');

$enviados = 0;
foreach ($stmt as $r) {
    if (correoReserva($pdo, (int) $r['id'], 'recordatorio') || !config('correo_activo')) {
        $marcar->execute([$r['id']]);
        $enviados++;
    }
}
// Limpieza: los correos guardados en el panel se borran a los 60 días
try {
    $pdo->exec('DELETE FROM correos_enviados WHERE creado_en < NOW() - INTERVAL 60 DAY');
} catch (PDOException) {
}
// En "modo pruebas" (sin Gmail) también se marcan: el correo queda guardado en el panel
echo date('Y-m-d H:i') . " · Recordatorios para el $manana: $enviados enviados\n";
