-- ============================================================
-- Sharclean – MySQL Database Schema
-- Engine: InnoDB | Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- ============================================================

-- Create database (run as privileged user)
CREATE DATABASE IF NOT EXISTS `sharclean_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `sharclean_db`;

-- Create dedicated user (run as root/admin)
-- CREATE USER 'sharclean_user'@'localhost' IDENTIFIED BY 'CHANGE_THIS_PASSWORD';
-- GRANT SELECT, INSERT, UPDATE ON `sharclean_db`.* TO 'sharclean_user'@'localhost';
-- FLUSH PRIVILEGES;


-- ── contactos ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `contactos` (
  `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `nombre`          VARCHAR(120)     NOT NULL,
  `email`           VARCHAR(200)     NOT NULL,
  `mensaje`         TEXT             NOT NULL,
  `ip_address`      VARCHAR(45)      DEFAULT NULL COMMENT 'IPv4 or IPv6 of sender',
  `leido`           TINYINT(1)       NOT NULL DEFAULT 0,
  `fecha_creacion`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email`          (`email`),
  INDEX `idx_fecha_creacion` (`fecha_creacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Mensajes del formulario de contacto';


-- ── cotizaciones ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `cotizaciones` (
  `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `nombre`          VARCHAR(120)     NOT NULL,
  `empresa`         VARCHAR(200)     DEFAULT NULL,
  `telefono`        VARCHAR(30)      NOT NULL,
  `email`           VARCHAR(200)     NOT NULL,
  `servicio`        VARCHAR(100)     NOT NULL,
  `descripcion`     VARCHAR(300)     DEFAULT NULL COMMENT 'Tipo de inmueble',
  `area_m2`         DECIMAL(10,2)    DEFAULT NULL COMMENT 'Superficie aproximada en m²',
  `mensaje`         TEXT             DEFAULT NULL,
  `estado`          ENUM(
                      'pending',
                      'contacted',
                      'quoted',
                      'closed'
                    ) NOT NULL DEFAULT 'pending'
                    COMMENT 'pending=recibida, contacted=contactado, quoted=cotización enviada, closed=cerrado',
  `ip_address`      VARCHAR(45)      DEFAULT NULL,
  `fecha_creacion`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualiza` DATETIME         DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_estado`         (`estado`),
  INDEX `idx_email`          (`email`),
  INDEX `idx_fecha_creacion` (`fecha_creacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Solicitudes de cotización recibidas desde el sitio web';


-- ── vacantes ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `vacantes` (
  `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `titulo`          VARCHAR(150)     NOT NULL,
  `area`            VARCHAR(100)     NOT NULL DEFAULT 'Operaciones',
  `tipo`            VARCHAR(60)      NOT NULL DEFAULT 'Tiempo completo',
  `descripcion`     TEXT             DEFAULT NULL,
  `requisitos`      TEXT             DEFAULT NULL,
  `ubicacion`       VARCHAR(120)     DEFAULT 'CDMX / EDOMEX',
  `activa`          TINYINT(1)       NOT NULL DEFAULT 1,
  `fecha_creacion`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_activa`         (`activa`),
  INDEX `idx_fecha_creacion` (`fecha_creacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Vacantes de empleo publicadas';


-- ── aplicaciones_empleo ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `aplicaciones_empleo` (
  `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `nombre`          VARCHAR(120)     NOT NULL,
  `edad`            TINYINT UNSIGNED DEFAULT NULL,
  `telefono`        VARCHAR(30)      NOT NULL,
  `email`           VARCHAR(200)     NOT NULL,
  `puesto_interes`  VARCHAR(150)     NOT NULL,
  `experiencia`     TEXT             DEFAULT NULL,
  `cv_filename`     VARCHAR(260)     DEFAULT NULL COMMENT 'Stored filename of uploaded CV',
  `canal`           VARCHAR(30)      NOT NULL DEFAULT 'web' COMMENT 'web | whatsapp | referido | otro',
  `estado`          ENUM('recibida','revisando','entrevista','contratado','descartado')
                    NOT NULL DEFAULT 'recibida',
  `ip_address`      VARCHAR(45)      DEFAULT NULL,
  `fecha_creacion`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_puesto`         (`puesto_interes`),
  INDEX `idx_estado`         (`estado`),
  INDEX `idx_fecha_creacion` (`fecha_creacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Aplicaciones de empleo recibidas desde el sitio web';


-- ── MIGRACIÓN: agregar columna canal (correr una sola vez) ───
-- ALTER TABLE `aplicaciones_empleo`
--   ADD COLUMN `canal` VARCHAR(30) NOT NULL DEFAULT 'web'
--   COMMENT 'web | whatsapp | referido | otro'
--   AFTER `cv_filename`;

-- ── chatbot_faqs ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `chatbot_faqs` (
  `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `palabras_clave`  TEXT             NOT NULL COMMENT 'Comma-separated keywords',
  `respuesta`       TEXT             NOT NULL,
  `sugerencias`     TEXT             DEFAULT NULL COMMENT 'JSON array of suggestion strings',
  `activa`          TINYINT(1)       NOT NULL DEFAULT 1,
  `orden`           SMALLINT         NOT NULL DEFAULT 0 COMMENT 'Priority order (lower = higher priority)',
  `fecha_creacion`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_activa` (`activa`),
  INDEX `idx_orden`  (`orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='FAQ entries for the chatbot';


-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Vacantes de ejemplo
INSERT INTO `vacantes` (`titulo`, `area`, `tipo`, `descripcion`, `requisitos`, `ubicacion`, `activa`) VALUES
(
  'Supervisor de Limpieza',
  'Operaciones',
  'Tiempo completo',
  'Responsable de coordinar y supervisar al personal de limpieza, verificar la calidad del servicio y gestionar los insumos necesarios.',
  'Mínimo 2 años de experiencia en supervisión. Secundaria o bachillerato. Disponibilidad de horario. Licencia de conducir deseable.',
  'CDMX / EDOMEX',
  1
),
(
  'Técnico en Mantenimiento',
  'Mantenimiento',
  'Tiempo completo',
  'Realizar mantenimiento preventivo y correctivo en instalaciones de clientes: plomería básica, electricidad, carpintería y pintura.',
  'Mínimo 1 año de experiencia en mantenimiento general. Conocimientos en plomería y electricidad básica. Herramienta propia deseable.',
  'CDMX / EDOMEX',
  1
),
(
  'Operador de Máquinas de Limpieza',
  'Operaciones',
  'Tiempo completo',
  'Operar y mantener en buen estado pulidoras, aspiradoras industriales y extractoras de agua caliente para el servicio de pisos y alfombras.',
  'Experiencia en operación de maquinaria industrial de limpieza. Secundaria. Disponibilidad fines de semana. Capacitación incluida.',
  'CDMX',
  1
),
(
  'Auxiliar de Limpieza',
  'Operaciones',
  'Tiempo completo',
  'Apoyar en las labores de limpieza en las instalaciones de nuestros clientes corporativos: oficinas, bodegas y plantas industriales.',
  'No se requiere experiencia previa. Secundaria mínima. Actitud de servicio y responsabilidad. Prestaciones de ley desde el primer día.',
  'CDMX / EDOMEX',
  1
),
(
  'Auxiliar Administrativo',
  'Administración',
  'Tiempo completo',
  'Brindar apoyo en actividades administrativas, atención a clientes, elaboración de reportes y gestión de agenda.',
  'Carrera en Administración o afín (trunca o concluida). Office intermedio. Mínimo 6 meses de experiencia. Excelente comunicación.',
  'CDMX (Oficina)',
  1
);

-- Chatbot FAQs de ejemplo (también cubiertas en chatbot.php, pero aquí para BD opcional)
INSERT INTO `chatbot_faqs` (`palabras_clave`, `respuesta`, `sugerencias`, `activa`, `orden`) VALUES
(
  'servicios,ofrecen,hacen,realizan,trabajan',
  'En Sharclean ofrecemos: limpieza de oficinas, lavado de alfombras, limpieza de vidrios exteriores, fumigación, lavado correctivo de pisos, plomería, instalaciones eléctricas y mantenimiento integral a inmuebles.',
  '["¿Cómo cotizo?","¿Zona de cobertura?","¿Tienen contratos?"]',
  1,
  10
),
(
  'precio,costo,cuesta,cotizar,cotización,cuanto,gratis',
  'Nuestras cotizaciones son completamente gratuitas. Los precios dependen del servicio, metros cuadrados y frecuencia. Solicite su cotización sin compromiso en nuestra página web o llámenos al +52 55 0000 0000.',
  '["Solicitar cotización","¿Tienen contratos mensuales?","¿Zona de cobertura?"]',
  1,
  20
),
(
  'horario,atienden,disponible,días',
  'Horario de atención al cliente: Lun–Vie 8am–6pm | Sáb 9am–2pm. Los servicios pueden realizarse en el horario que mejor convenga, incluyendo nocturno y fines de semana.',
  '["¿Atienden emergencias?","¿Cómo cotizo?","¿Zona de cobertura?"]',
  1,
  30
),
(
  'zona,cobertura,ciudad,cdmx,edomex,donde',
  'Atendemos CDMX (todas las alcaldías), Estado de México, Guadalajara y Monterrey. Para otras ciudades, consúltenos.',
  '["¿Cómo cotizo?","¿Tienen contratos?","Contactar asesor"]',
  1,
  40
),
(
  'trabajo,empleo,vacante,contratar,sueldo',
  '¡Nos alegra tu interés! Visita nuestra sección de Vacantes en el sitio web o escríbenos por WhatsApp al +52 55 0000 0000 con tu nombre y el puesto de interés.',
  '["Ver vacantes","Aplicar por WhatsApp","Contactar RRHH"]',
  1,
  50
),
(
  'emergencia,urgente,hoy,ahora,24',
  '¡Sí atendemos emergencias! Llame al +52 55 0000 0000 o escríbanos por WhatsApp. Los servicios de plomería y electricidad tienen atención urgente disponible.',
  '["Llamar ahora","WhatsApp emergencias","¿Cómo cotizo?"]',
  1,
  60
);

CREATE TABLE chatbot_candidatos (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre           VARCHAR(150) NOT NULL,
  telefono         VARCHAR(30)  NOT NULL,
  estado           VARCHAR(60),
  edad             TINYINT UNSIGNED,
  experiencia      VARCHAR(80),
  traslado         VARCHAR(60),
  condicion_salud  TINYINT(1)   DEFAULT 0,
  dependientes     TINYINT(1)   DEFAULT 0,
  disponibilidad   VARCHAR(60),
  ip_address       VARCHAR(45),
  created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
