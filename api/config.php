<?php
/**
 * Configuración de la conexión a la base de datos.
 * Valores por defecto de XAMPP: usuario "root" sin contraseña.
 * Si en clase o en el hosting usáis otros datos, cambiadlos aquí.
 */
return [
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
