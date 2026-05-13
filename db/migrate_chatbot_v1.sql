-- ============================================================
-- Sharclean – Chatbot de Reclutamiento (v1)
-- Ejecutar UNA sola vez contra sharclea_dbapp
-- ============================================================

USE `sharclea_dbapp`;

CREATE TABLE IF NOT EXISTS `chatbot_candidatos` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `nombre`          VARCHAR(150)  NOT NULL,
  `telefono`        VARCHAR(30)   NOT NULL,
  `estado`          VARCHAR(60)   DEFAULT NULL,
  `edad`            TINYINT UNSIGNED DEFAULT NULL,
  `experiencia`     VARCHAR(120)  DEFAULT NULL,
  `traslado`        VARCHAR(80)   DEFAULT NULL,
  `condicion_salud` TINYINT(1)    NOT NULL DEFAULT 0,
  `dependientes`    TINYINT(1)    NOT NULL DEFAULT 0,
  `disponibilidad`  VARCHAR(80)   DEFAULT NULL,
  `ip_address`      VARCHAR(45)   DEFAULT NULL,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_created` (`created_at`),
  INDEX `idx_telefono` (`telefono`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Candidatos capturados por el chatbot de reclutamiento';
