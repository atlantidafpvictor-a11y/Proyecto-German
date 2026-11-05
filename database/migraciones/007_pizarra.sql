-- =========================================================
--  MIGRACIÓN 007 · Pizarra del día   (5 de noviembre de 2026)
--
--  Lo que ha llegado esa mañana (pescado de la lonja, verdura de la
--  Vega...). La cocina lo escribe desde el panel → "Pizarra del día"
--  y sale en la portada de la web. Cuando algo se acaba se marca como
--  agotado y en la web aparece tachado al momento.
--
--  El plato marcado como "destacado" es el que sale en la tarjeta
--  "Hoy en la cocina" de la portada (solo puede haber uno).
--
--  No borra nada. Se puede ejecutar varias veces sin problema.
--
--  XAMPP:   phpMyAdmin → base sabor_mediterraneo → Importar → este archivo
--  Ubuntu:  sudo mysql sabor_mediterraneo < database/migraciones/007_pizarra.sql
-- =========================================================

USE sabor_mediterraneo;

CREATE TABLE IF NOT EXISTS pizarra (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(100) NOT NULL,
    nombre_en     VARCHAR(100) NULL COMMENT 'Traducción al inglés (si falta se usa el español)',
    origen        VARCHAR(60)  NULL COMMENT 'De dónde viene: Lonja de Motril, Vega de Granada...',
    origen_en     VARCHAR(60)  NULL,
    precio        DECIMAL(6, 2) NULL COMMENT 'Vacío = según mercado',
    agotado       BOOLEAN NOT NULL DEFAULT FALSE,
    destacado     BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Sale en "Hoy en la cocina" de la portada',
    visible       BOOLEAN NOT NULL DEFAULT TRUE,
    orden         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_pizarra_precio CHECK (precio IS NULL OR precio > 0),
    INDEX idx_pizarra_orden (visible, orden)
) ENGINE = InnoDB;

-- Pizarra de ejemplo (solo si está vacía)
INSERT INTO pizarra (nombre, nombre_en, origen, origen_en, precio, destacado, orden)
SELECT * FROM (
    SELECT 'Gallo pedro a la plancha' AS nombre, 'Grilled John Dory' AS nombre_en, 'Lonja de Motril' AS origen, 'Motril fish market' AS origen_en, 19.50 AS precio, 1 AS destacado, 1 AS orden
    UNION ALL SELECT 'Habitas tiernas con jamón', 'Baby broad beans with ham', 'Vega de Granada', 'Vega de Granada', 9.00, 0, 2
    UNION ALL SELECT 'Tomate de colgar aliñado', 'Dressed hanging tomatoes', 'Huerta de la Vega', 'Vega market gardens', 6.50, 0, 3
    UNION ALL SELECT 'Choto al ajillo', 'Kid goat with garlic', 'Sierra de Huétor', 'Sierra de Huétor', 18.00, 0, 4
) AS ejemplo
WHERE NOT EXISTS (SELECT 1 FROM pizarra);
