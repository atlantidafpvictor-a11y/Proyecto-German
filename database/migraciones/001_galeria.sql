-- =========================================================
--  MIGRACIÓN 001 · Galería de fotos           (4 de noviembre de 2026)
--
--  Para bases de datos que ya existían antes de la galería.
--  Añade la tabla "galeria" y las fotos de ejemplo SIN tocar
--  nada más: las reservas, clientes y usuarios se conservan.
--  Se puede ejecutar varias veces sin problema.
--
--  XAMPP:   phpMyAdmin → base sabor_mediterraneo → Importar → este archivo
--  Ubuntu:  sudo mysql sabor_mediterraneo < database/migraciones/001_galeria.sql
-- =========================================================

USE sabor_mediterraneo;

-- Fotos de la galería. "imagen" guarda una dirección web (https://...)
-- o la ruta de un archivo subido desde el panel (uploads/galeria/...).
CREATE TABLE IF NOT EXISTS galeria (
    id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo    VARCHAR(120) NOT NULL,
    imagen    VARCHAR(255) NOT NULL,
    categoria ENUM('local', 'platos', 'eventos') NOT NULL DEFAULT 'platos',
    orden     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    visible   BOOLEAN NOT NULL DEFAULT TRUE,
    subida_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_galeria_orden (visible, orden)
) ENGINE = InnoDB;

INSERT INTO galeria (titulo, imagen, categoria, orden)
SELECT * FROM (
    SELECT 'Nuestro comedor'                 AS titulo, 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4' AS imagen, 'local'  AS categoria, 1 AS orden UNION ALL
    SELECT 'Mesa servida para compartir',               'https://images.unsplash.com/photo-1414235077428-338989a2e8c0', 'local',  2 UNION ALL
    SELECT 'Remojón granadino',                         'https://images.unsplash.com/photo-1512621776951-a57141f2eefd', 'platos', 3 UNION ALL
    SELECT 'Arroz negro con alioli',                    'https://images.unsplash.com/photo-1476224203421-9ac39bcb3327', 'platos', 4 UNION ALL
    SELECT 'Verduras de la Vega',                       'https://images.unsplash.com/photo-1547592180-85f173990554',   'platos', 5 UNION ALL
    SELECT 'Carrillada ibérica al vino tinto',          'https://images.unsplash.com/photo-1544025162-d76694265947',   'platos', 6 UNION ALL
    SELECT 'Pasta fresca al pesto',                     'https://images.unsplash.com/photo-1551183053-bf91a1d81141',   'platos', 7 UNION ALL
    SELECT 'Postres caseros',                           'https://images.unsplash.com/photo-1488477181946-6428a0291777', 'platos', 8
) AS fotos
WHERE NOT EXISTS (SELECT 1 FROM galeria);
