-- =========================================================
--  SABOR MEDITERRÁNEO · Base de datos del restaurante
--  Proyecto intermodular 2º SMR
--  Motor: MySQL 8 / MariaDB 10.4+ (XAMPP)
--
--  Cómo importarla:
--    1. Arranca Apache y MySQL en XAMPP.
--    2. Abre http://localhost/phpmyadmin
--    3. Pestaña "Importar" → elige este archivo → "Continuar".
--  El script borra y vuelve a crear la base de datos, así que
--  se puede ejecutar tantas veces como haga falta.
-- =========================================================

DROP DATABASE IF EXISTS sabor_mediterraneo;
CREATE DATABASE sabor_mediterraneo
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE sabor_mediterraneo;

SET time_zone = '+02:00';

-- =========================================================
-- 1. CARTA
-- =========================================================

CREATE TABLE categorias (
    id          TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(50)  NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL,
    orden       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    activa      BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE = InnoDB;

CREATE TABLE platos (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    categoria_id TINYINT UNSIGNED NOT NULL,
    nombre       VARCHAR(100) NOT NULL,
    descripcion  VARCHAR(255) NOT NULL,
    precio       DECIMAL(6,2) NOT NULL,
    unidad       VARCHAR(10)  NULL COMMENT 'p.ej. p.p. (por persona), copa, ración',
    imagen       VARCHAR(255) NULL,
    vegetariano  BOOLEAN NOT NULL DEFAULT FALSE,
    vegano       BOOLEAN NOT NULL DEFAULT FALSE,
    sin_gluten   BOOLEAN NOT NULL DEFAULT FALSE,
    destacado    BOOLEAN NOT NULL DEFAULT FALSE,
    disponible   BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_platos_categoria FOREIGN KEY (categoria_id)
        REFERENCES categorias (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_platos_precio CHECK (precio >= 0),
    CONSTRAINT chk_platos_vegano CHECK (vegano = FALSE OR vegetariano = TRUE),
    UNIQUE KEY uq_platos_nombre (nombre)
) ENGINE = InnoDB;

-- Los 14 alérgenos de declaración obligatoria (Reglamento UE 1169/2011)
CREATE TABLE alergenos (
    id     TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(40) NOT NULL UNIQUE
) ENGINE = InnoDB;

-- Relación N:M entre platos y alérgenos
CREATE TABLE plato_alergeno (
    plato_id    INT UNSIGNED NOT NULL,
    alergeno_id TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (plato_id, alergeno_id),
    CONSTRAINT fk_pa_plato FOREIGN KEY (plato_id)
        REFERENCES platos (id) ON DELETE CASCADE,
    CONSTRAINT fk_pa_alergeno FOREIGN KEY (alergeno_id)
        REFERENCES alergenos (id) ON DELETE CASCADE
) ENGINE = InnoDB;

-- =========================================================
-- 2. LOCAL: zonas, mesas y horario
-- =========================================================

CREATE TABLE zonas (
    id          TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(40) NOT NULL UNIQUE,
    descripcion VARCHAR(255) NULL
) ENGINE = InnoDB;

CREATE TABLE mesas (
    id        SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero    SMALLINT UNSIGNED NOT NULL UNIQUE,
    zona_id   TINYINT UNSIGNED NOT NULL,
    capacidad TINYINT UNSIGNED NOT NULL,
    activa    BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT fk_mesas_zona FOREIGN KEY (zona_id)
        REFERENCES zonas (id) ON DELETE RESTRICT,
    CONSTRAINT chk_mesas_capacidad CHECK (capacidad BETWEEN 1 AND 20)
) ENGINE = InnoDB;

-- dia_semana: 1 = lunes ... 7 = domingo. Si apertura es NULL, ese turno está cerrado.
CREATE TABLE horarios (
    id         TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dia_semana TINYINT UNSIGNED NOT NULL,
    turno      ENUM('comida', 'cena') NOT NULL,
    apertura   TIME NULL,
    cierre     TIME NULL,
    UNIQUE KEY uq_horario (dia_semana, turno),
    CONSTRAINT chk_horarios_dia CHECK (dia_semana BETWEEN 1 AND 7)
) ENGINE = InnoDB;

-- Días especiales: festivos, vacaciones, eventos privados
CREATE TABLE dias_cerrados (
    fecha  DATE PRIMARY KEY,
    motivo VARCHAR(100) NOT NULL
) ENGINE = InnoDB;

-- =========================================================
-- 3. CLIENTES Y RESERVAS
-- =========================================================

CREATE TABLE clientes (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(100) NOT NULL,
    telefono   VARCHAR(15)  NOT NULL,
    email      VARCHAR(120) NOT NULL UNIQUE,
    acepta_publicidad BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

CREATE TABLE reservas (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo      CHAR(6) NOT NULL UNIQUE COMMENT 'Código que ve el cliente, p.ej. K7Q2XB',
    cliente_id  INT UNSIGNED NOT NULL,
    mesa_id     SMALLINT UNSIGNED NULL COMMENT 'Se asigna al confirmar',
    zona_id     TINYINT UNSIGNED NULL COMMENT 'Zona que prefiere el cliente',
    fecha       DATE NOT NULL,
    hora        TIME NOT NULL,
    personas    TINYINT UNSIGNED NOT NULL,
    estado      ENUM('pendiente', 'confirmada', 'cancelada', 'completada', 'no_presentado')
                NOT NULL DEFAULT 'pendiente',
    comentarios VARCHAR(500) NULL,
    creada_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_reservas_cliente FOREIGN KEY (cliente_id)
        REFERENCES clientes (id) ON DELETE RESTRICT,
    CONSTRAINT fk_reservas_mesa FOREIGN KEY (mesa_id)
        REFERENCES mesas (id) ON DELETE SET NULL,
    CONSTRAINT fk_reservas_zona FOREIGN KEY (zona_id)
        REFERENCES zonas (id) ON DELETE SET NULL,
    CONSTRAINT chk_reservas_personas CHECK (personas BETWEEN 1 AND 20),
    INDEX idx_reservas_fecha_hora (fecha, hora),
    INDEX idx_reservas_estado (estado)
) ENGINE = InnoDB;

-- =========================================================
-- 4. SERVICIOS, EVENTOS, OPINIONES Y CONTACTO
-- =========================================================

CREATE TABLE servicios (
    id           TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre       VARCHAR(60)  NOT NULL UNIQUE,
    descripcion  VARCHAR(255) NOT NULL,
    precio_desde DECIMAL(6,2) NULL,
    orden        TINYINT UNSIGNED NOT NULL DEFAULT 0,
    activo       BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE = InnoDB;

CREATE TABLE eventos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo      VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    fecha       DATETIME NOT NULL,
    plazas      SMALLINT UNSIGNED NOT NULL,
    precio      DECIMAL(6,2) NOT NULL,
    imagen      VARCHAR(255) NULL,
    publicado   BOOLEAN NOT NULL DEFAULT TRUE,
    CONSTRAINT chk_eventos_precio CHECK (precio >= 0)
) ENGINE = InnoDB;

-- Inscripciones de clientes a eventos (N:M)
CREATE TABLE inscripciones_evento (
    evento_id  INT UNSIGNED NOT NULL,
    cliente_id INT UNSIGNED NOT NULL,
    plazas     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    fecha      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (evento_id, cliente_id),
    CONSTRAINT fk_ie_evento  FOREIGN KEY (evento_id)  REFERENCES eventos (id)  ON DELETE CASCADE,
    CONSTRAINT fk_ie_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id) ON DELETE CASCADE
) ENGINE = InnoDB;

CREATE TABLE opiniones (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT UNSIGNED NULL,
    nombre     VARCHAR(60) NOT NULL COMMENT 'Nombre público, p.ej. Lucía M.',
    puntuacion TINYINT UNSIGNED NOT NULL,
    comentario VARCHAR(600) NOT NULL,
    fecha      DATE NOT NULL,
    aprobada   BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Solo se publican las aprobadas',
    CONSTRAINT fk_opiniones_cliente FOREIGN KEY (cliente_id)
        REFERENCES clientes (id) ON DELETE SET NULL,
    CONSTRAINT chk_opiniones_puntuacion CHECK (puntuacion BETWEEN 1 AND 5)
) ENGINE = InnoDB;

CREATE TABLE mensajes_contacto (
    id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(100) NOT NULL,
    email   VARCHAR(120) NOT NULL,
    asunto  VARCHAR(120) NOT NULL,
    mensaje TEXT NOT NULL,
    leido   BOOLEAN NOT NULL DEFAULT FALSE,
    fecha   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- =========================================================
-- 5. USUARIOS DEL PANEL DE ADMINISTRACIÓN
--    La contraseña NUNCA se guarda en claro: se guarda el
--    hash que genera password_hash() de PHP (bcrypt).
--    El primer usuario se crea en el paso 4 (panel admin).
-- =========================================================

CREATE TABLE usuarios (
    id            TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(60)  NOT NULL,
    email         VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol           ENUM('admin', 'sala') NOT NULL DEFAULT 'sala',
    activo        BOOLEAN NOT NULL DEFAULT TRUE,
    ultimo_acceso DATETIME NULL,
    creado_en     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;

-- =========================================================
-- 6. VISTAS (consultas guardadas)
-- =========================================================

-- Carta pública con la lista de alérgenos de cada plato
CREATE VIEW vista_carta AS
SELECT  p.id,
        c.nombre  AS categoria,
        p.nombre,
        p.descripcion,
        p.precio,
        p.vegetariano,
        p.sin_gluten,
        p.destacado,
        GROUP_CONCAT(a.nombre ORDER BY a.id SEPARATOR ', ') AS alergenos
FROM platos p
JOIN categorias c          ON c.id = p.categoria_id
LEFT JOIN plato_alergeno pa ON pa.plato_id = p.id
LEFT JOIN alergenos a       ON a.id = pa.alergeno_id
WHERE p.disponible = TRUE AND c.activa = TRUE
GROUP BY p.id, c.nombre, c.orden, p.nombre, p.descripcion, p.precio,
         p.vegetariano, p.sin_gluten, p.destacado
ORDER BY c.orden, p.id;

-- Reservas del día para el personal de sala
CREATE VIEW vista_reservas_hoy AS
SELECT  r.codigo,
        r.hora,
        r.personas,
        cl.nombre   AS cliente,
        cl.telefono,
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

-- Estadísticas: comensales y reservas por día
CREATE VIEW vista_ocupacion_diaria AS
SELECT  fecha,
        COUNT(*)      AS reservas,
        SUM(personas) AS comensales
FROM reservas
WHERE estado IN ('pendiente', 'confirmada', 'completada')
GROUP BY fecha;

-- =========================================================
-- 7. LÓGICA EN LA BASE DE DATOS
-- =========================================================

DELIMITER //

-- Cuántas mesas quedan libres para un grupo en una fecha y hora.
-- Se considera que cada reserva ocupa la mesa 90 minutos.
CREATE FUNCTION fn_mesas_libres(p_fecha DATE, p_hora TIME, p_personas TINYINT)
RETURNS INT
READS SQL DATA
BEGIN
    DECLARE libres INT;
    SELECT COUNT(*) INTO libres
    FROM mesas m
    WHERE m.activa = TRUE
      AND m.capacidad >= p_personas
      AND m.capacidad <= p_personas + 2      -- no dar una mesa de 8 a una pareja
      AND m.id NOT IN (
          SELECT r.mesa_id
          FROM reservas r
          WHERE r.mesa_id IS NOT NULL
            AND r.fecha = p_fecha
            AND r.estado IN ('pendiente', 'confirmada')
            AND ABS(TIME_TO_SEC(TIMEDIFF(r.hora, p_hora))) < 90 * 60
      );
    RETURN libres;
END //

-- Asigna automáticamente la mesa libre más ajustada al grupo,
-- dando preferencia a la zona elegida por el cliente.
CREATE PROCEDURE sp_asignar_mesa(IN p_reserva_id INT UNSIGNED)
BEGIN
    DECLARE v_fecha DATE;
    DECLARE v_hora TIME;
    DECLARE v_personas TINYINT;
    DECLARE v_zona TINYINT;
    DECLARE v_mesa SMALLINT UNSIGNED DEFAULT NULL;

    SELECT fecha, hora, personas, zona_id
      INTO v_fecha, v_hora, v_personas, v_zona
    FROM reservas WHERE id = p_reserva_id;

    SELECT m.id INTO v_mesa
    FROM mesas m
    WHERE m.activa = TRUE
      AND m.capacidad >= v_personas
      AND m.id NOT IN (
          SELECT r.mesa_id FROM reservas r
          WHERE r.mesa_id IS NOT NULL
            AND r.id <> p_reserva_id
            AND r.fecha = v_fecha
            AND r.estado IN ('pendiente', 'confirmada')
            AND ABS(TIME_TO_SEC(TIMEDIFF(r.hora, v_hora))) < 90 * 60
      )
    ORDER BY (m.zona_id = v_zona) DESC, m.capacidad ASC
    LIMIT 1;

    IF v_mesa IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No hay mesas libres para esa reserva';
    END IF;

    UPDATE reservas SET mesa_id = v_mesa, estado = 'confirmada' WHERE id = p_reserva_id;
END //

-- No se permiten reservas en el pasado ni en días cerrados
CREATE TRIGGER trg_reservas_validar
BEFORE INSERT ON reservas
FOR EACH ROW
BEGIN
    IF NEW.fecha < CURRENT_DATE THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede reservar en una fecha pasada';
    END IF;
    IF EXISTS (SELECT 1 FROM dias_cerrados WHERE fecha = NEW.fecha) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El restaurante está cerrado ese día';
    END IF;
END //

DELIMITER ;

-- =========================================================
-- 8. DATOS DE EJEMPLO
-- =========================================================

INSERT INTO categorias (id, nombre, descripcion, orden) VALUES
(1, 'Entrantes',   'Para abrir boca y compartir', 1),
(2, 'Arroces',     'Mínimo dos personas',          2),
(3, 'Principales', 'Carnes y pescados',            3),
(4, 'Postres',     'Caseros, hechos cada día',     4),
(5, 'Bodega',      'Vinos de Granada y bebidas',   5);

INSERT INTO alergenos (id, nombre) VALUES
(1, 'Gluten'), (2, 'Crustáceos'), (3, 'Huevo'), (4, 'Pescado'), (5, 'Cacahuetes'),
(6, 'Soja'), (7, 'Lácteos'), (8, 'Frutos de cáscara'), (9, 'Apio'), (10, 'Mostaza'),
(11, 'Sésamo'), (12, 'Sulfitos'), (13, 'Altramuces'), (14, 'Moluscos');

INSERT INTO platos (id, categoria_id, nombre, descripcion, precio, unidad, imagen, vegetariano, vegano, sin_gluten, destacado) VALUES
(1,  1, 'Salmorejo con jamón y huevo',       'Tomate de la Vega, pan de pueblo y aceite de Montefrío.',          7.50, NULL,  'https://images.unsplash.com/photo-1547592180-85f173990554', 0, 0, 0, 0),
(2,  1, 'Remojón granadino',                 'Naranja, bacalao desalado, aceituna negra y cebolleta.',            8.50, NULL,  'https://images.unsplash.com/photo-1512621776951-a57141f2eefd', 0, 0, 1, 1),
(3,  1, 'Berenjenas fritas con miel de caña','Finas, crujientes y con miel de caña de Frigiliana.',               8.00, NULL,  'https://images.unsplash.com/photo-1512621776951-a57141f2eefd', 1, 0, 0, 0),
(4,  1, 'Croquetas caseras de puchero',      'Seis unidades, con la carne del cocido de la casa.',                9.00, NULL,  'https://images.unsplash.com/photo-1476224203421-9ac39bcb3327', 0, 0, 0, 0),
(5,  2, 'Arroz negro con alioli',            'Sepia de Motril y su tinta. Mínimo dos personas.',                 16.50, 'p.p.','https://images.unsplash.com/photo-1476224203421-9ac39bcb3327', 0, 0, 1, 1),
(6,  2, 'Arroz de verduras de la huerta',    'Alcachofa, judía verde, pimiento y ñora. Mínimo dos personas.',    14.00, 'p.p.','https://images.unsplash.com/photo-1547592180-85f173990554', 1, 1, 1, 0),
(7,  2, 'Arroz meloso de pato y setas',      'Pato confitado y setas de temporada de Sierra Nevada.',            17.00, 'p.p.','https://images.unsplash.com/photo-1476224203421-9ac39bcb3327', 0, 0, 1, 0),
(8,  3, 'Lubina a la sal',                   'Pieza entera para compartir, con verduras asadas.',                19.50, NULL,  'https://images.unsplash.com/photo-1544025162-d76694265947', 0, 0, 1, 0),
(9,  3, 'Carrillada ibérica al vino tinto',  'Guisada a fuego lento durante seis horas, con puré de patata.',    16.00, NULL,  'https://images.unsplash.com/photo-1544025162-d76694265947', 0, 0, 1, 1),
(10, 3, 'Cordero segureño al horno',         'Paletilla con patatas a lo pobre.',                                21.00, NULL,  'https://images.unsplash.com/photo-1544025162-d76694265947', 0, 0, 1, 0),
(11, 3, 'Pasta fresca al pesto de albahaca', 'Hecha cada mañana, con piñones y queso curado.',                   12.50, NULL,  'https://images.unsplash.com/photo-1551183053-bf91a1d81141', 1, 0, 0, 0),
(12, 4, 'Piononos de Santa Fe',              'Bizcocho borracho y crema tostada, el dulce de la provincia.',      5.50, NULL,  'https://images.unsplash.com/photo-1488477181946-6428a0291777', 1, 0, 0, 1),
(13, 4, 'Tarta de queso al horno',           'Cremosa por dentro, con mermelada de higos.',                       6.00, NULL,  'https://images.unsplash.com/photo-1488477181946-6428a0291777', 1, 0, 0, 0),
(14, 4, 'Fruta de temporada',                'Lo que traiga ese día el mercado.',                                 4.50, NULL,  'https://images.unsplash.com/photo-1488477181946-6428a0291777', 1, 1, 1, 0),
(15, 5, 'Vino tinto de la Contraviesa',      'Copa. Tempranillo y garnacha de altura.',                           3.50, 'copa','https://images.unsplash.com/photo-1414235077428-338989a2e8c0', 1, 1, 1, 0),
(16, 5, 'Vino blanco de Granada',            'Copa. Vijiriega, fresco y aromático.',                              3.50, 'copa','https://images.unsplash.com/photo-1414235077428-338989a2e8c0', 1, 1, 1, 0),
(17, 5, 'Cerveza de barril',                 'Caña o tercio, con su tapa.',                                       2.50, NULL,  'https://images.unsplash.com/photo-1414235077428-338989a2e8c0', 1, 1, 0, 0);

INSERT INTO plato_alergeno (plato_id, alergeno_id) VALUES
(1, 1), (1, 3),
(2, 4),
(3, 1),
(4, 1), (4, 3), (4, 7),
(5, 3), (5, 14),
(7, 9), (7, 12),
(8, 4),
(9, 7), (9, 12),
(11, 1), (11, 3), (11, 7), (11, 8),
(12, 1), (12, 3), (12, 7),
(13, 1), (13, 3), (13, 7),
(15, 12), (16, 12),
(17, 1);

INSERT INTO zonas (id, nombre, descripcion) VALUES
(1, 'Salón',     'Comedor principal, 36 plazas'),
(2, 'Terraza',   'Exterior con toldo, 16 plazas'),
(3, 'Reservado', 'Sala privada para grupos, hasta 20 plazas');

INSERT INTO mesas (numero, zona_id, capacidad) VALUES
(1, 1, 2), (2, 1, 2), (3, 1, 2), (4, 1, 4), (5, 1, 4), (6, 1, 4), (7, 1, 6), (8, 1, 6), (9, 1, 6),
(10, 2, 2), (11, 2, 2), (12, 2, 4), (13, 2, 4), (14, 2, 4),
(20, 3, 8), (21, 3, 12);

INSERT INTO horarios (dia_semana, turno, apertura, cierre) VALUES
(1, 'comida', NULL, NULL),        (1, 'cena', NULL, NULL),
(2, 'comida', '13:00', '16:00'),  (2, 'cena', '20:00', '23:30'),
(3, 'comida', '13:00', '16:00'),  (3, 'cena', '20:00', '23:30'),
(4, 'comida', '13:00', '16:00'),  (4, 'cena', '20:00', '23:30'),
(5, 'comida', '13:00', '16:30'),  (5, 'cena', '20:00', '23:59'),
(6, 'comida', '13:00', '16:30'),  (6, 'cena', '20:00', '23:59'),
(7, 'comida', '13:00', '16:30'),  (7, 'cena', NULL, NULL);

INSERT INTO dias_cerrados (fecha, motivo) VALUES
('2026-12-25', 'Navidad'),
('2027-01-01', 'Año Nuevo'),
('2027-01-02', 'Día de la Toma de Granada');

INSERT INTO servicios (nombre, descripcion, precio_desde, orden) VALUES
('Menú del día',      'Primero, segundo, postre, pan y bebida. De martes a viernes a mediodía.',             15.90, 1),
('Celebraciones',     'Cumpleaños, comuniones o comidas de empresa en nuestro reservado para 20 personas.',  35.00, 2),
('Cata con maridaje', 'Un jueves al mes: cinco vinos de Granada con cinco platos de la casa.',              42.00, 3),
('Para llevar',       'Arroces y guisos listos para recoger. Encárgalos con una hora de antelación.',        12.00, 4);

INSERT INTO eventos (titulo, descripcion, fecha, plazas, precio) VALUES
('Cata de vinos de la Contraviesa', 'Cinco vinos de bodegas de la Alpujarra con cinco platos de temporada.', '2026-11-12 20:30:00', 24, 42.00),
('Jornadas de la matanza',          'Menú especial de invierno con productos del cerdo ibérico.',            '2026-12-03 13:30:00', 40, 38.00);

INSERT INTO clientes (id, nombre, telefono, email, acepta_publicidad) VALUES
(1, 'Lucía Martín Ruiz',     '600111222', 'lucia.martin@example.com', 1),
(2, 'Javier Romero López',   '600333444', 'javier.romero@example.com', 0),
(3, 'Carmen Pérez Sánchez',  '600555666', 'carmen.perez@example.com', 1),
(4, 'Antonio Gómez Vílchez', '600777888', 'antonio.gomez@example.com', 0);

-- Reservas en los próximos días (fechas relativas a hoy para que siempre sean válidas)
INSERT INTO reservas (codigo, cliente_id, mesa_id, zona_id, fecha, hora, personas, estado, comentarios) VALUES
('K7Q2XB', 1, 4,  1, CURRENT_DATE + INTERVAL 1 DAY, '14:00', 4,  'confirmada', 'Una persona celíaca'),
('M3D8RA', 2, 21, 3, CURRENT_DATE + INTERVAL 3 DAY, '21:00', 12, 'confirmada', 'Cumpleaños, traen tarta'),
('P9W4LC', 3, NULL, 2, CURRENT_DATE + INTERVAL 2 DAY, '20:30', 2, 'pendiente', NULL),
('T5H6NV', 4, NULL, 1, CURRENT_DATE + INTERVAL 1 DAY, '14:30', 3, 'pendiente', 'Con carrito de bebé');

INSERT INTO opiniones (cliente_id, nombre, puntuacion, comentario, fecha, aprobada) VALUES
(1, 'Lucía M.',  5, 'El arroz negro es de los mejores que he probado en Granada. Y nos atendieron sin ninguna prisa.', '2026-09-14', 1),
(2, 'Javier R.', 5, 'Fuimos doce para un cumpleaños y lo organizaron todo. El pionono del final, imprescindible.',     '2026-08-30', 1),
(3, 'Carmen P.', 4, 'Carta corta pero muy cuidada. Me avisaron de los alérgenos de cada plato sin tener que preguntar.', '2026-10-02', 1),
(NULL, 'Anónimo', 2, 'Tardaron en traer la cuenta.', '2026-10-10', 0);

-- =========================================================
-- 9. CONSULTAS DE PRUEBA (para la memoria del proyecto)
-- =========================================================
-- SELECT * FROM vista_carta;
-- SELECT * FROM vista_reservas_hoy;
-- SELECT fn_mesas_libres(CURRENT_DATE + INTERVAL 1 DAY, '14:00', 4) AS mesas_libres;
-- CALL sp_asignar_mesa(3);
-- SELECT nombre, precio FROM platos WHERE sin_gluten = 1 AND vegetariano = 1;
-- SELECT c.nombre, COUNT(p.id) AS platos, ROUND(AVG(p.precio), 2) AS precio_medio
--   FROM categorias c LEFT JOIN platos p ON p.categoria_id = c.id GROUP BY c.id;
