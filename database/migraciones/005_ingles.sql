-- =========================================================
--  MIGRACIÓN 005 · Web en inglés   (5 de noviembre de 2026)
--
--  Añade columnas _en (nombre_en, descripcion_en, titulo_en) a la carta,
--  los alérgenos, las zonas, los servicios, los eventos y la galería, y
--  rellena las traducciones del contenido de ejemplo.
--
--  Si una traducción falta (por ejemplo, un plato nuevo sin inglés), la
--  web en inglés muestra el texto en español: nunca sale vacío.
--  Solo rellena lo que está sin traducir: no pisa traducciones hechas
--  desde el panel. Se puede ejecutar varias veces sin problema.
--
--  XAMPP:   phpMyAdmin → base sabor_mediterraneo → Importar → este archivo
--  Ubuntu:  sudo mysql sabor_mediterraneo < database/migraciones/005_ingles.sql
-- =========================================================

USE sabor_mediterraneo;

ALTER TABLE categorias
    ADD COLUMN IF NOT EXISTS nombre_en VARCHAR(50) NULL COMMENT 'Traducción al inglés' AFTER nombre,
    ADD COLUMN IF NOT EXISTS descripcion_en VARCHAR(255) NULL COMMENT 'Traducción al inglés' AFTER descripcion;
ALTER TABLE platos
    ADD COLUMN IF NOT EXISTS nombre_en VARCHAR(100) NULL COMMENT 'Traducción al inglés' AFTER nombre,
    ADD COLUMN IF NOT EXISTS descripcion_en VARCHAR(255) NULL COMMENT 'Traducción al inglés' AFTER descripcion;
ALTER TABLE alergenos
    ADD COLUMN IF NOT EXISTS nombre_en VARCHAR(40) NULL COMMENT 'Traducción al inglés' AFTER nombre;
ALTER TABLE zonas
    ADD COLUMN IF NOT EXISTS nombre_en VARCHAR(40) NULL COMMENT 'Traducción al inglés' AFTER nombre;
ALTER TABLE servicios
    ADD COLUMN IF NOT EXISTS nombre_en VARCHAR(60) NULL COMMENT 'Traducción al inglés' AFTER nombre,
    ADD COLUMN IF NOT EXISTS descripcion_en VARCHAR(255) NULL COMMENT 'Traducción al inglés' AFTER descripcion;
ALTER TABLE eventos
    ADD COLUMN IF NOT EXISTS titulo_en VARCHAR(100) NULL COMMENT 'Traducción al inglés' AFTER titulo,
    ADD COLUMN IF NOT EXISTS descripcion_en TEXT NULL COMMENT 'Traducción al inglés' AFTER descripcion;
ALTER TABLE galeria
    ADD COLUMN IF NOT EXISTS titulo_en VARCHAR(120) NULL COMMENT 'Traducción al inglés' AFTER titulo;

-- Traducciones del contenido de ejemplo (se buscan por el texto en español)
UPDATE categorias SET nombre_en = 'Starters', descripcion_en = 'To open the appetite and share' WHERE nombre = 'Entrantes' AND nombre_en IS NULL;
UPDATE categorias SET nombre_en = 'Rice dishes', descripcion_en = 'For two people minimum' WHERE nombre = 'Arroces' AND nombre_en IS NULL;
UPDATE categorias SET nombre_en = 'Mains', descripcion_en = 'Meat and fish' WHERE nombre = 'Principales' AND nombre_en IS NULL;
UPDATE categorias SET nombre_en = 'Desserts', descripcion_en = 'Homemade, every day' WHERE nombre = 'Postres' AND nombre_en IS NULL;
UPDATE categorias SET nombre_en = 'Wine & drinks', descripcion_en = 'Wines from Granada and other drinks' WHERE nombre = 'Bodega' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'Salmorejo with ham and egg', descripcion_en = 'Cold tomato cream made with Vega tomatoes, country bread and Montefrío olive oil.' WHERE nombre = 'Salmorejo con jamón y huevo' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'Granada-style remojón', descripcion_en = 'Orange, desalted cod, black olives and spring onion.' WHERE nombre = 'Remojón granadino' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'Fried aubergine with cane honey', descripcion_en = 'Thin and crispy, with cane honey from Frigiliana.' WHERE nombre = 'Berenjenas fritas con miel de caña' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'Homemade stew croquettes', descripcion_en = 'Six pieces, made with the meat from our house stew.' WHERE nombre = 'Croquetas caseras de puchero' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'Black rice with aioli', descripcion_en = 'Motril cuttlefish and its ink. Minimum two people.' WHERE nombre = 'Arroz negro con alioli' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'Garden vegetable rice', descripcion_en = 'Artichoke, green beans, peppers and ñora pepper. Minimum two people.' WHERE nombre = 'Arroz de verduras de la huerta' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'Creamy duck and mushroom rice', descripcion_en = 'Duck confit and seasonal mushrooms from Sierra Nevada.' WHERE nombre = 'Arroz meloso de pato y setas' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'Salt-baked sea bass', descripcion_en = 'A whole fish to share, with roast vegetables.' WHERE nombre = 'Lubina a la sal' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'Iberian pork cheek in red wine', descripcion_en = 'Slow-cooked for six hours, served with mashed potato.' WHERE nombre = 'Carrillada ibérica al vino tinto' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'Roast Segureño lamb', descripcion_en = 'Shoulder of lamb with ''a lo pobre'' potatoes, fried with peppers and onion.' WHERE nombre = 'Cordero segureño al horno' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'Fresh pasta with basil pesto', descripcion_en = 'Made every morning, with pine nuts and cured cheese.' WHERE nombre = 'Pasta fresca al pesto de albahaca' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'Piononos from Santa Fe', descripcion_en = 'Syrup-soaked sponge with toasted custard, the signature sweet of the province.' WHERE nombre = 'Piononos de Santa Fe' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'Baked cheesecake', descripcion_en = 'Creamy inside, with fig jam.' WHERE nombre = 'Tarta de queso al horno' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'Seasonal fruit', descripcion_en = 'Whatever the market brings that day.' WHERE nombre = 'Fruta de temporada' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'Red wine from La Contraviesa', descripcion_en = 'By the glass. High-altitude Tempranillo and Garnacha.' WHERE nombre = 'Vino tinto de la Contraviesa' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'White wine from Granada', descripcion_en = 'By the glass. Vijiriega grape: fresh and aromatic.' WHERE nombre = 'Vino blanco de Granada' AND nombre_en IS NULL;
UPDATE platos SET nombre_en = 'Draught beer', descripcion_en = 'Small glass or bottle, served with a tapa.' WHERE nombre = 'Cerveza de barril' AND nombre_en IS NULL;
UPDATE alergenos SET nombre_en = 'Gluten' WHERE nombre = 'Gluten' AND nombre_en IS NULL;
UPDATE alergenos SET nombre_en = 'Crustaceans' WHERE nombre = 'Crustáceos' AND nombre_en IS NULL;
UPDATE alergenos SET nombre_en = 'Eggs' WHERE nombre = 'Huevo' AND nombre_en IS NULL;
UPDATE alergenos SET nombre_en = 'Fish' WHERE nombre = 'Pescado' AND nombre_en IS NULL;
UPDATE alergenos SET nombre_en = 'Peanuts' WHERE nombre = 'Cacahuetes' AND nombre_en IS NULL;
UPDATE alergenos SET nombre_en = 'Soy' WHERE nombre = 'Soja' AND nombre_en IS NULL;
UPDATE alergenos SET nombre_en = 'Milk' WHERE nombre = 'Lácteos' AND nombre_en IS NULL;
UPDATE alergenos SET nombre_en = 'Tree nuts' WHERE nombre = 'Frutos de cáscara' AND nombre_en IS NULL;
UPDATE alergenos SET nombre_en = 'Celery' WHERE nombre = 'Apio' AND nombre_en IS NULL;
UPDATE alergenos SET nombre_en = 'Mustard' WHERE nombre = 'Mostaza' AND nombre_en IS NULL;
UPDATE alergenos SET nombre_en = 'Sesame' WHERE nombre = 'Sésamo' AND nombre_en IS NULL;
UPDATE alergenos SET nombre_en = 'Sulphites' WHERE nombre = 'Sulfitos' AND nombre_en IS NULL;
UPDATE alergenos SET nombre_en = 'Lupin' WHERE nombre = 'Altramuces' AND nombre_en IS NULL;
UPDATE alergenos SET nombre_en = 'Molluscs' WHERE nombre = 'Moluscos' AND nombre_en IS NULL;
UPDATE zonas SET nombre_en = 'Dining room' WHERE nombre = 'Salón' AND nombre_en IS NULL;
UPDATE zonas SET nombre_en = 'Terrace' WHERE nombre = 'Terraza' AND nombre_en IS NULL;
UPDATE zonas SET nombre_en = 'Private room' WHERE nombre = 'Reservado' AND nombre_en IS NULL;
UPDATE servicios SET nombre_en = 'Set lunch menu', descripcion_en = 'Starter, main course, dessert, bread and a drink. Tuesday to Friday at lunchtime.' WHERE nombre = 'Menú del día' AND nombre_en IS NULL;
UPDATE servicios SET nombre_en = 'Celebrations', descripcion_en = 'Birthdays, communions or company lunches in our private room for 20 people.' WHERE nombre = 'Celebraciones' AND nombre_en IS NULL;
UPDATE servicios SET nombre_en = 'Wine pairing tasting', descripcion_en = 'One Thursday a month: five Granada wines with five dishes from our kitchen.' WHERE nombre = 'Cata con maridaje' AND nombre_en IS NULL;
UPDATE servicios SET nombre_en = 'Takeaway', descripcion_en = 'Rice dishes and stews ready to collect. Order them one hour in advance.' WHERE nombre = 'Para llevar' AND nombre_en IS NULL;
UPDATE eventos SET titulo_en = 'La Contraviesa wine tasting', descripcion_en = 'Five wines from Alpujarra wineries paired with five seasonal dishes.' WHERE titulo = 'Cata de vinos de la Contraviesa' AND titulo_en IS NULL;
UPDATE eventos SET titulo_en = 'Winter pork festival', descripcion_en = 'A special winter menu built around Iberian pork.' WHERE titulo = 'Jornadas de la matanza' AND titulo_en IS NULL;
UPDATE galeria SET titulo_en = 'Our façade in the centre of Granada' WHERE titulo = 'Nuestra fachada en el centro de Granada' AND titulo_en IS NULL;
UPDATE galeria SET titulo_en = 'Terrace with views of the Alhambra' WHERE titulo = 'Terraza con vistas a la Alhambra' AND titulo_en IS NULL;
UPDATE galeria SET titulo_en = 'The dining room' WHERE titulo = 'El comedor' AND titulo_en IS NULL;
UPDATE galeria SET titulo_en = 'Black rice with aioli' WHERE titulo = 'Arroz negro con alioli' AND titulo_en IS NULL;
UPDATE galeria SET titulo_en = 'Plating up in the kitchen' WHERE titulo = 'Emplatando en la cocina' AND titulo_en IS NULL;
UPDATE galeria SET titulo_en = 'Bread with tomato and local olive oil' WHERE titulo = 'Pan con tomate y aceite de la tierra' AND titulo_en IS NULL;
UPDATE galeria SET titulo_en = 'Iberian pork cheek in red wine' WHERE titulo = 'Carrillada ibérica al vino tinto' AND titulo_en IS NULL;
UPDATE galeria SET titulo_en = 'Wine tasting night' WHERE titulo = 'Noche de cata de vinos' AND titulo_en IS NULL;
UPDATE galeria SET titulo_en = 'Table set for dinner' WHERE titulo = 'Mesa lista para la cena' AND titulo_en IS NULL;
UPDATE galeria SET titulo_en = 'Granada-style remojón' WHERE titulo = 'Remojón granadino' AND titulo_en IS NULL;
UPDATE galeria SET titulo_en = 'Celebrations in the private room' WHERE titulo = 'Celebraciones en el reservado' AND titulo_en IS NULL;
UPDATE galeria SET titulo_en = 'Homemade desserts' WHERE titulo = 'Postres caseros' AND titulo_en IS NULL;

-- Comprobación: cuántos platos quedan sin traducir
SELECT COUNT(*) AS platos_sin_ingles FROM platos WHERE nombre_en IS NULL;
