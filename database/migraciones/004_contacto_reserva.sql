-- =========================================================
--  MIGRACIÓN 004 · Datos de contacto guardados en cada reserva   (5 de noviembre de 2026)
--
--  Problema que arregla: el nombre y el teléfono solo estaban en la tabla
--  clientes. Si alguien hacía una reserva con el email de otra persona,
--  sobrescribía su nombre y su teléfono, y el cliente real ya no podía
--  consultar ni cancelar SU reserva (se pide código + teléfono).
--
--  Ahora cada reserva guarda el nombre y el teléfono con los que se hizo,
--  y la web pública ya no modifica los datos de un cliente que ya existe.
--  Las reservas que ya existían se rellenan con los datos actuales del cliente.
--  No borra nada. Se puede ejecutar varias veces sin problema.
--
--  XAMPP:   phpMyAdmin → base sabor_mediterraneo → Importar → este archivo
--  Ubuntu:  sudo mysql sabor_mediterraneo < database/migraciones/004_telefono_reserva.sql
-- =========================================================

USE sabor_mediterraneo;

-- IF NOT EXISTS (de MariaDB): si la columna ya existe, no da error
ALTER TABLE reservas
    ADD COLUMN IF NOT EXISTS nombre VARCHAR(100) NULL
        COMMENT 'Nombre con el que se hizo la reserva'
        AFTER cliente_id,
    ADD COLUMN IF NOT EXISTS telefono VARCHAR(15) NULL
        COMMENT 'Teléfono con el que se hizo: sirve para consultarla o cancelarla'
        AFTER nombre;

UPDATE reservas r
  JOIN clientes c ON c.id = r.cliente_id
   SET r.nombre = COALESCE(r.nombre, c.nombre),
       r.telefono = COALESCE(r.telefono, c.telefono)
 WHERE r.nombre IS NULL OR r.telefono IS NULL;

-- La vista del servicio de hoy usa los datos guardados en la reserva
CREATE OR REPLACE VIEW vista_reservas_hoy AS
SELECT  r.codigo,
        r.hora,
        r.personas,
        COALESCE(r.nombre, cl.nombre)     AS cliente,
        COALESCE(r.telefono, cl.telefono) AS telefono,
        z.nombre    AS zona,
        m.numero    AS mesa,
        r.estado,
        r.comentarios
FROM reservas r
JOIN clientes cl   ON cl.id = r.cliente_id
LEFT JOIN zonas z  ON z.id = r.zona_id
LEFT JOIN mesas m  ON m.id = r.mesa_id
WHERE r.fecha = CURRENT_DATE
  AND r.estado IN ('pendiente', 'confirmada')
ORDER BY r.hora;

-- Comprobación: debe salir 0
SELECT COUNT(*) AS reservas_sin_datos FROM reservas WHERE nombre IS NULL OR telefono IS NULL;
