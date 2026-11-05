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
    // Hasta cuántas horas antes puede el cliente cancelar online
    'horas_min_cancelacion' => 2,

    // Informe que deja cada 5 minutos scripts/monitor.sh (solo en el servidor Ubuntu)
    'estado_servidor' => '/var/lib/sabor-mediterraneo/estado.json',

    // Redis (opcional): caché de la carta, límite de peticiones y sesiones del panel.
    // Desactivado por defecto: se activa en config.local.php del servidor, con su contraseña.
    // Si Redis no está o no responde, la web sigue funcionando igual (sin caché).
    'redis' => [
        'activo'   => false,
        'host'     => '127.0.0.1',
        'puerto'   => 6379,
        'password' => '',
        'prefijo'  => 'sabor:',   // todas las claves empiezan así, por si Redis se comparte
        'cache_segundos' => 600,  // la caché de la carta caduca sola a los 10 min (y se borra al editar)
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_merge($config, require $local);
}

return $config;
