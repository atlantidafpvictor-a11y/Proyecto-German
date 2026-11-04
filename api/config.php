<?php
/**
 * Configuración de la conexión a la base de datos.
 *
 * Valores por defecto de XAMPP: usuario "root" sin contraseña.
 *
 * En un servidor (Ubuntu) NO cambiéis este archivo: cread al lado uno
 * llamado config.local.php con solo los valores que cambian, por ejemplo:
 *
 *     <?php
 *     return [
 *         'db_user' => 'sabor_web',
 *         'db_pass' => 'la-contraseña-del-servidor',
 *     ];
 *
 * config.local.php está en .gitignore, así que la contraseña nunca se sube a GitHub.
 */
$config = [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => 'sabor_mediterraneo',
    'db_user' => 'root',
    'db_pass' => '',

    // Minutos que una reserva ocupa la mesa
    'duracion_reserva' => 90,
    // Cada cuántos minutos se ofrece una hora para reservar
    'intervalo_horas' => 30,
    // Última reserva: X minutos antes del cierre
    'margen_cierre' => 60,
    // Hasta cuántos días por adelantado se puede reservar
    'dias_max_reserva' => 90,
    // Máximo de personas por reserva online (grupos mayores, por teléfono)
    'max_personas_online' => 8,
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_merge($config, require $local);
}

return $config;
