-- =========================================================
--  MIGRACIÓN 008 · Emails de reserva y lista de espera   (6 de noviembre de 2026)
--
--  1. reservas.idioma: el email de confirmación y el recordatorio
--     llegan en el idioma en que se hizo la reserva (es / en).
--  2. reservas.recordatorio_en: cuándo se envió el recordatorio del
--     día antes (así nunca se manda dos veces).
--  3. lista_espera: si una hora está completa, el cliente se apunta.
--     Cuando alguien cancela y queda sitio para su grupo, le llega un
--     email con un enlace para reservar.
--
--  4. correos_enviados: copia de cada email para verlo en el panel
--     (también en "modo pruebas", antes de activar el envío por Gmail).
--
--  No borra nada. Se puede ejecutar varias veces sin problema.
--
--  XAMPP:   phpMyAdmin → base sabor_mediterraneo → Importar → este archivo
--  Ubuntu:  sudo mysql sabor_mediterraneo < database/migraciones/008_correo_lista_espera.sql
-- =========================================================

USE sabor_mediterraneo;

ALTER TABLE reservas
    ADD COLUMN IF NOT EXISTS idioma CHAR(2) NOT NULL DEFAULT 'es' COMMENT 'Idioma de los emails: es / en',
    ADD COLUMN IF NOT EXISTS recordatorio_en DATETIME NULL COMMENT 'Cuándo se envió el recordatorio del día antes';

CREATE TABLE IF NOT EXISTS lista_espera (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha       DATE NOT NULL,
    turno       ENUM('comida', 'cena') NOT NULL,
    personas    TINYINT UNSIGNED NOT NULL,
    nombre      VARCHAR(100) NOT NULL,
    telefono    VARCHAR(20)  NOT NULL,
    email       VARCHAR(120) NOT NULL,
    idioma      CHAR(2) NOT NULL DEFAULT 'es',
    -- esperando: aún no hay sitio · avisado: se le ha mandado el email de hueco libre
    -- atendido: el restaurante ya lo ha gestionado (le ha llamado, ha reservado...)
    estado      ENUM('esperando', 'avisado', 'atendido') NOT NULL DEFAULT 'esperando',
    creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    avisado_en  DATETIME NULL,
    CONSTRAINT chk_espera_personas CHECK (personas BETWEEN 1 AND 20),
    UNIQUE KEY uq_espera (fecha, turno, email),
    INDEX idx_espera_fecha (fecha, turno, estado)
) ENGINE = InnoDB;

-- Registro de emails: cada correo que manda la web queda guardado para verlo en
-- el panel (Correos enviados). Si el envío por Gmail aún no está activado, el
-- correo no sale pero se guarda igual ("modo pruebas"). Se borran a los 60 días.
CREATE TABLE IF NOT EXISTS correos_enviados (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    para       VARCHAR(120) NOT NULL,
    asunto     VARCHAR(200) NOT NULL,
    html       MEDIUMTEXT NOT NULL,
    estado     ENUM('enviado', 'pruebas', 'error') NOT NULL,
    creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_correos_fecha (creado_en)
) ENGINE = InnoDB;
