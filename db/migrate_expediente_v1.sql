-- ============================================================
-- Sharclean RRHH – Expediente digital del empleado (v1)
-- Ejecutar UNA sola vez contra sharclea_dbapp
-- Requiere migrate_rrhh_v2.sql
--   · documento_tipos      — catálogo configurable (Empleados → Catálogo de documentos)
--   · empleado_documentos  — archivos del expediente (uploads/expedientes/{empleado_id}/)
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

USE `sharclea_dbapp`;

CREATE TABLE IF NOT EXISTS `documento_tipos` (
  `id`          SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(120) NOT NULL,
  `categoria`   VARCHAR(60)  NOT NULL DEFAULT 'Otros',
  `descripcion` VARCHAR(255) DEFAULT NULL,
  `obligatorio` TINYINT(1)   NOT NULL DEFAULT 0,
  `activo`      TINYINT(1)   NOT NULL DEFAULT 1,
  `orden`       SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_cat_orden` (`categoria`, `orden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de documentos del expediente del empleado';

CREATE TABLE IF NOT EXISTS `empleado_documentos` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empleado_id`     INT UNSIGNED NOT NULL,
  `tipo_id`         SMALLINT UNSIGNED DEFAULT NULL COMMENT 'NULL = documento adicional (usa `nombre`)',
  `nombre`          VARCHAR(150) DEFAULT NULL,
  `archivo`         VARCHAR(80)  NOT NULL COMMENT 'Nombre aleatorio en uploads/expedientes/{empleado_id}/',
  `nombre_original` VARCHAR(200) NOT NULL DEFAULT '',
  `mime`            VARCHAR(60)  NOT NULL,
  `bytes`           INT UNSIGNED NOT NULL DEFAULT 0,
  `subido_por`      INT UNSIGNED DEFAULT NULL COMMENT 'admin_usuarios.id',
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_emp_tipo` (`empleado_id`, `tipo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Archivos del expediente digital de cada empleado';

-- ── Catálogo base (editable desde el panel) ──────────────────
INSERT INTO `documento_tipos` (`nombre`, `categoria`, `descripcion`, `obligatorio`, `orden`)
SELECT * FROM (
  SELECT 'Identificación oficial (INE)'          AS n, 'Identidad y fiscales'  AS c, 'Frente y reverso'                        AS d, 1 AS o, 1 AS r UNION ALL
  SELECT 'CURP',                                       'Identidad y fiscales',  NULL,                                              1, 2 UNION ALL
  SELECT 'Constancia de Situación Fiscal (RFC)',       'Identidad y fiscales',  'Emitida por el SAT, vigencia no mayor a 3 meses', 1, 3 UNION ALL
  SELECT 'Acta de nacimiento',                         'Identidad y fiscales',  NULL,                                              1, 4 UNION ALL
  SELECT 'Número de Seguridad Social (IMSS)',          'Identidad y fiscales',  'Hoja o constancia con el NSS',                    1, 5 UNION ALL
  SELECT 'Comprobante de domicilio',                   'Domicilio y bancarios', 'No mayor a 3 meses',                              1, 1 UNION ALL
  SELECT 'Estado de cuenta bancario (CLABE)',          'Domicilio y bancarios', 'Carátula donde se vea la CLABE',                  1, 2 UNION ALL
  SELECT 'Solicitud de empleo / CV',                   'Laborales',             NULL,                                              1, 1 UNION ALL
  SELECT 'Contrato firmado',                           'Laborales',             NULL,                                              1, 2 UNION ALL
  SELECT 'Cartas de recomendación',                    'Laborales',             NULL,                                              0, 3 UNION ALL
  SELECT 'Comprobante de estudios',                    'Laborales',             NULL,                                              0, 4 UNION ALL
  SELECT 'Certificado médico',                         'Salud y antecedentes',  NULL,                                              0, 1 UNION ALL
  SELECT 'Carta de antecedentes no penales',           'Salud y antecedentes',  NULL,                                              0, 2
) AS base
WHERE NOT EXISTS (SELECT 1 FROM `documento_tipos`);
