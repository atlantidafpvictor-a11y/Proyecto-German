-- =========================================================
--  MIGRACIÓN 006 · Maridajes y presupuestos de celebraciones   (5 de noviembre de 2026)
--
--  1. platos.maridaje_id: el vino o la bebida que recomendamos con cada
--     plato. Es una clave foránea a la propia tabla platos (los vinos
--     también son platos de la carta, en la categoría Bodega), así el
--     maridaje sale con su nombre, su precio y su traducción.
--  2. Tabla presupuestos: solicitudes del formulario de celebraciones
--     (cumpleaños, empresa, familia, grupos) que se gestionan en el panel.
--
--  No borra nada. Se puede ejecutar varias veces sin problema.
--
--  XAMPP:   phpMyAdmin → base sabor_mediterraneo → Importar → este archivo
--  Ubuntu:  sudo mysql sabor_mediterraneo < database/migraciones/006_maridajes_presupuestos.sql
-- =========================================================

USE sabor_mediterraneo;

ALTER TABLE platos
    ADD COLUMN IF NOT EXISTS maridaje_id INT UNSIGNED NULL
        COMMENT 'Vino o bebida de la carta que recomendamos con este plato' AFTER disponible;

-- La clave foránea solo se crea si todavía no existe
SET @existe = (SELECT COUNT(*) FROM information_schema.table_constraints
               WHERE constraint_schema = DATABASE() AND table_name = 'platos' AND constraint_name = 'fk_platos_maridaje');
SET @sql = IF(@existe = 0,
    'ALTER TABLE platos ADD CONSTRAINT fk_platos_maridaje FOREIGN KEY (maridaje_id) REFERENCES platos (id) ON DELETE SET NULL',
    'SELECT ''La clave foránea fk_platos_maridaje ya existía'' AS aviso');
PREPARE paso FROM @sql;
EXECUTE paso;
DEALLOCATE PREPARE paso;

-- Maridajes de la carta de ejemplo (solo si el plato aún no tiene uno)
UPDATE platos p JOIN platos v ON v.nombre = 'Vino blanco de Granada' SET p.maridaje_id = v.id WHERE p.nombre = 'Salmorejo con jamón y huevo' AND p.maridaje_id IS NULL;
UPDATE platos p JOIN platos v ON v.nombre = 'Vino blanco de Granada' SET p.maridaje_id = v.id WHERE p.nombre = 'Remojón granadino' AND p.maridaje_id IS NULL;
UPDATE platos p JOIN platos v ON v.nombre = 'Cerveza de barril' SET p.maridaje_id = v.id WHERE p.nombre = 'Berenjenas fritas con miel de caña' AND p.maridaje_id IS NULL;
UPDATE platos p JOIN platos v ON v.nombre = 'Cerveza de barril' SET p.maridaje_id = v.id WHERE p.nombre = 'Croquetas caseras de puchero' AND p.maridaje_id IS NULL;
UPDATE platos p JOIN platos v ON v.nombre = 'Vino blanco de Granada' SET p.maridaje_id = v.id WHERE p.nombre = 'Arroz negro con alioli' AND p.maridaje_id IS NULL;
UPDATE platos p JOIN platos v ON v.nombre = 'Vino blanco de Granada' SET p.maridaje_id = v.id WHERE p.nombre = 'Arroz de verduras de la huerta' AND p.maridaje_id IS NULL;
UPDATE platos p JOIN platos v ON v.nombre = 'Vino tinto de la Contraviesa' SET p.maridaje_id = v.id WHERE p.nombre = 'Arroz meloso de pato y setas' AND p.maridaje_id IS NULL;
UPDATE platos p JOIN platos v ON v.nombre = 'Vino blanco de Granada' SET p.maridaje_id = v.id WHERE p.nombre = 'Lubina a la sal' AND p.maridaje_id IS NULL;
UPDATE platos p JOIN platos v ON v.nombre = 'Vino tinto de la Contraviesa' SET p.maridaje_id = v.id WHERE p.nombre = 'Carrillada ibérica al vino tinto' AND p.maridaje_id IS NULL;
UPDATE platos p JOIN platos v ON v.nombre = 'Vino tinto de la Contraviesa' SET p.maridaje_id = v.id WHERE p.nombre = 'Cordero segureño al horno' AND p.maridaje_id IS NULL;
UPDATE platos p JOIN platos v ON v.nombre = 'Vino blanco de Granada' SET p.maridaje_id = v.id WHERE p.nombre = 'Pasta fresca al pesto de albahaca' AND p.maridaje_id IS NULL;

CREATE TABLE IF NOT EXISTS presupuestos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo        ENUM('cumpleanos', 'empresa', 'familiar', 'grupo', 'otro') NOT NULL,
    personas    SMALLINT UNSIGNED NOT NULL,
    fecha       DATE NULL COMMENT 'Fecha aproximada; puede faltar si aún no la saben',
    turno       ENUM('comida', 'cena', 'indiferente') NOT NULL DEFAULT 'indiferente',
    nombre      VARCHAR(100) NOT NULL,
    telefono    VARCHAR(15)  NOT NULL,
    email       VARCHAR(120) NOT NULL,
    comentarios VARCHAR(1000) NULL,
    idioma      CHAR(2) NOT NULL DEFAULT 'es' COMMENT 'Idioma en el que hay que responder',
    estado      ENUM('nueva', 'respondida', 'aceptada', 'descartada') NOT NULL DEFAULT 'nueva',
    creada_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_presupuestos_personas CHECK (personas BETWEEN 6 AND 200),
    INDEX idx_presupuestos_estado (estado, creada_en)
) ENGINE = InnoDB;

-- Comprobación
SELECT (SELECT COUNT(*) FROM platos WHERE maridaje_id IS NOT NULL) AS platos_con_maridaje,
       (SELECT COUNT(*) FROM presupuestos) AS presupuestos;
