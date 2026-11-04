-- =========================================================
--  MIGRACIÓN 003 · Fotos en formato WebP   (5 de noviembre de 2026)
--
--  Las fotos del proyecto (carpeta img/) pasan de JPG a WebP: misma
--  calidad visual con menos de la mitad de peso (7,6 MB → 3,2 MB),
--  así la web carga bastante más rápido, sobre todo en el móvil.
--
--  Solo cambia las rutas que empiezan por img/ y acaban en .jpg.
--  Las fotos subidas desde el panel (uploads/) y las direcciones
--  https:// no se tocan. No borra nada.
--  Se puede ejecutar varias veces sin problema.
--
--  Hay que ejecutarla DESPUÉS de la 001 y la 002.
--
--  XAMPP:   phpMyAdmin → base sabor_mediterraneo → Importar → este archivo
--  Ubuntu:  sudo mysql sabor_mediterraneo < database/migraciones/003_fotos_webp.sql
-- =========================================================

USE sabor_mediterraneo;

START TRANSACTION;

UPDATE platos
   SET imagen = CONCAT(LEFT(imagen, CHAR_LENGTH(imagen) - 4), '.webp')
 WHERE imagen LIKE 'img/%.jpg';

UPDATE eventos
   SET imagen = CONCAT(LEFT(imagen, CHAR_LENGTH(imagen) - 4), '.webp')
 WHERE imagen LIKE 'img/%.jpg';

UPDATE galeria
   SET imagen = CONCAT(LEFT(imagen, CHAR_LENGTH(imagen) - 4), '.webp')
 WHERE imagen LIKE 'img/%.jpg';

COMMIT;

-- Comprobación: debe salir 0 en las tres columnas
SELECT
    (SELECT COUNT(*) FROM platos  WHERE imagen LIKE 'img/%.jpg') AS platos_jpg,
    (SELECT COUNT(*) FROM eventos WHERE imagen LIKE 'img/%.jpg') AS eventos_jpg,
    (SELECT COUNT(*) FROM galeria WHERE imagen LIKE 'img/%.jpg') AS galeria_jpg;
