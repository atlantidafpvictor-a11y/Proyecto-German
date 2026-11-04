-- =========================================================
--  MIGRACIÓN 002 · Fotos propias del restaurante   (4 de noviembre de 2026)
--
--  Cambia las fotos de ejemplo de internet por las fotos del proyecto
--  (carpeta img/). Solo toca las fotos de ejemplo: si ya habéis puesto
--  una foto vuestra a un plato o habéis subido fotos a la galería,
--  se conservan. No borra reservas, clientes ni usuarios.
--  Se puede ejecutar varias veces sin problema.
--  Después hay que ejecutar la 003 (las fotos pasaron a formato WebP).
--
--  XAMPP:   phpMyAdmin → base sabor_mediterraneo → Importar → este archivo
--  Ubuntu:  sudo mysql sabor_mediterraneo < database/migraciones/002_fotos_propias.sql
-- =========================================================

USE sabor_mediterraneo;

-- Por si todavía no se había ejecutado la migración 001
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

-- 1. Cada plato con su foto
UPDATE platos SET imagen = 'img/platos/salmorejo.jpg' WHERE id = 1 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');
UPDATE platos SET imagen = 'img/platos/remojon.jpg' WHERE id = 2 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');
UPDATE platos SET imagen = 'img/platos/berenjenas.jpg' WHERE id = 3 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');
UPDATE platos SET imagen = 'img/platos/croquetas.jpg' WHERE id = 4 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');
UPDATE platos SET imagen = 'img/platos/arroz-negro.jpg' WHERE id = 5 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');
UPDATE platos SET imagen = 'img/platos/arroz-verduras.jpg' WHERE id = 6 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');
UPDATE platos SET imagen = 'img/platos/arroz-pato.jpg' WHERE id = 7 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');
UPDATE platos SET imagen = 'img/platos/lubina.jpg' WHERE id = 8 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');
UPDATE platos SET imagen = 'img/platos/carrillada.jpg' WHERE id = 9 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');
UPDATE platos SET imagen = 'img/platos/cordero.jpg' WHERE id = 10 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');
UPDATE platos SET imagen = 'img/platos/pasta-pesto.jpg' WHERE id = 11 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');
UPDATE platos SET imagen = 'img/platos/piononos.jpg' WHERE id = 12 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');
UPDATE platos SET imagen = 'img/platos/tarta-queso.jpg' WHERE id = 13 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');
UPDATE platos SET imagen = 'img/platos/fruta.jpg' WHERE id = 14 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');
UPDATE platos SET imagen = 'img/platos/vino-tinto.jpg' WHERE id = 15 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');
UPDATE platos SET imagen = 'img/platos/vino-blanco.jpg' WHERE id = 16 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');
UPDATE platos SET imagen = 'img/platos/cerveza.jpg' WHERE id = 17 AND (imagen IS NULL OR imagen LIKE 'https://images.unsplash.com/%');

-- 2. Galería: se quitan las fotos de ejemplo de internet...
DELETE FROM galeria WHERE imagen LIKE 'https://images.unsplash.com/%';

-- ...y se añaden las del restaurante (solo si aún no están)
INSERT INTO galeria (titulo, imagen, categoria, orden)
SELECT * FROM (
    SELECT 'Nuestra fachada en el centro de Granada' AS titulo, 'img/galeria/fachada.jpg' AS imagen, 'local' AS categoria, 1 AS orden UNION ALL
    SELECT 'Terraza con vistas a la Alhambra', 'img/galeria/terraza.jpg', 'local', 2 UNION ALL
    SELECT 'El comedor', 'img/comedor.jpg', 'local', 3 UNION ALL
    SELECT 'Arroz negro con alioli', 'img/platos/arroz-negro.jpg', 'platos', 4 UNION ALL
    SELECT 'Emplatando en la cocina', 'img/galeria/cocina.jpg', 'local', 5 UNION ALL
    SELECT 'Pan con tomate y aceite de la tierra', 'img/galeria/aceite.jpg', 'platos', 6 UNION ALL
    SELECT 'Carrillada ibérica al vino tinto', 'img/platos/carrillada.jpg', 'platos', 7 UNION ALL
    SELECT 'Noche de cata de vinos', 'img/galeria/cata-vinos.jpg', 'eventos', 8 UNION ALL
    SELECT 'Mesa lista para la cena', 'img/galeria/detalle-mesa.jpg', 'local', 9 UNION ALL
    SELECT 'Remojón granadino', 'img/platos/remojon.jpg', 'platos', 10 UNION ALL
    SELECT 'Celebraciones en el reservado', 'img/galeria/celebracion.jpg', 'eventos', 11 UNION ALL
    SELECT 'Postres caseros', 'img/galeria/postres.jpg', 'platos', 12
) AS fotos
WHERE NOT EXISTS (SELECT 1 FROM galeria WHERE imagen LIKE 'img/%');
