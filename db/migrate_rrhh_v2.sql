-- ============================================================
-- Sharclean RRHH v2 – Migration
-- Ejecutar UNA sola vez contra sharclean_db
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

USE `sharclean_db`;

-- ── aplicaciones_empleo: nuevas columnas ─────────────────────
-- (omitir si ya existen)
ALTER TABLE `aplicaciones_empleo`
  ADD COLUMN `vacante_id`       INT UNSIGNED DEFAULT NULL
    COMMENT 'FK a vacantes' AFTER `puesto_interes`,
  ADD COLUMN `notas_reclutador` TEXT DEFAULT NULL
    AFTER `estado`;

ALTER TABLE `aplicaciones_empleo`
  ADD INDEX `idx_vacante_id` (`vacante_id`);


-- ── aplicacion_notas: historial de seguimiento ───────────────
CREATE TABLE IF NOT EXISTS `aplicacion_notas` (
  `id`              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `aplicacion_id`   INT UNSIGNED  NOT NULL,
  `autor`           VARCHAR(80)   NOT NULL DEFAULT 'Admin',
  `nota`            TEXT          NOT NULL,
  `fecha_creacion`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_aplicacion` (`aplicacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Historial de notas por candidato';


-- ── departamentos ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `departamentos` (
  `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `activo` TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `departamentos` (`id`, `nombre`) VALUES
  (1, 'Operaciones'),
  (2, 'Limpieza'),
  (3, 'Mantenimiento'),
  (4, 'Administración'),
  (5, 'Ventas');


-- ── puestos ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `puestos` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`          VARCHAR(150) NOT NULL,
  `departamento_id` INT UNSIGNED NOT NULL,
  `activo`          TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  INDEX `idx_dept` (`departamento_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `puestos` (`id`, `nombre`, `departamento_id`) VALUES
  (1, 'Afanador / Limpieza General', 1),
  (2, 'Auxiliar de Limpieza',        2),
  (3, 'Supervisor de Limpieza',      2),
  (4, 'Técnico en Mantenimiento',    3),
  (5, 'Operador de Máquinas',        1),
  (6, 'Auxiliar Administrativo',     4),
  (7, 'Ejecutivo de Ventas',         5),
  (8, 'Gerente Operativo',           1);


-- ── empleados ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `empleados` (
  `id`                INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `numero_empleado`   VARCHAR(20)   NOT NULL  COMMENT 'Ej. EMP-0001',

  -- Datos personales
  `nombre`            VARCHAR(120)  NOT NULL,
  `apellido_paterno`  VARCHAR(80)   NOT NULL,
  `apellido_materno`  VARCHAR(80)   DEFAULT NULL,
  `fecha_nacimiento`  DATE          DEFAULT NULL,
  `sexo`              ENUM('M','F','NB') DEFAULT NULL,
  `estado_civil`      ENUM('soltero','casado','union_libre','divorciado','viudo') DEFAULT NULL,

  -- Documentos oficiales
  `curp`              VARCHAR(18)   DEFAULT NULL,
  `rfc`               VARCHAR(13)   DEFAULT NULL,
  `nss`               VARCHAR(11)   DEFAULT NULL  COMMENT 'NSS IMSS (11 dígitos)',

  -- Contacto
  `telefono`          VARCHAR(30)   DEFAULT NULL,
  `email`             VARCHAR(200)  DEFAULT NULL,
  `email_corporativo` VARCHAR(200)  DEFAULT NULL,

  -- Domicilio
  `direccion`         VARCHAR(300)  DEFAULT NULL,
  `colonia`           VARCHAR(120)  DEFAULT NULL,
  `ciudad`            VARCHAR(100)  DEFAULT 'CDMX',
  `estado_residencia` VARCHAR(80)   DEFAULT NULL,
  `cp`                VARCHAR(10)   DEFAULT NULL,

  -- Posición
  `departamento_id`   INT UNSIGNED  DEFAULT NULL,
  `puesto_id`         INT UNSIGNED  DEFAULT NULL,
  `puesto_nombre`     VARCHAR(150)  DEFAULT NULL  COMMENT 'Texto libre si no usa catálogo',

  -- Contrato
  `tipo_contrato`     ENUM('indeterminado','determinado','por_obra','confianza','honorarios')
                      NOT NULL DEFAULT 'indeterminado',
  `fecha_ingreso`     DATE          NOT NULL,
  `fecha_fin_contrato` DATE         DEFAULT NULL,

  -- Salario
  `salario_base`      DECIMAL(12,2) DEFAULT NULL  COMMENT 'Salario bruto mensual en MXN',
  `periodo_pago`      ENUM('semanal','quincenal','mensual') NOT NULL DEFAULT 'quincenal',

  -- Banco
  `banco`             VARCHAR(80)   DEFAULT NULL,
  `clabe_bancaria`    VARCHAR(18)   DEFAULT NULL,

  -- Estatus laboral
  `estatus`           ENUM('activo','vacaciones','incapacidad','baja_temporal','baja_definitiva')
                      NOT NULL DEFAULT 'activo',
  `fecha_baja`        DATE          DEFAULT NULL,
  `motivo_baja`       VARCHAR(300)  DEFAULT NULL,

  -- Vínculo reclutamiento
  `aplicacion_id`     INT UNSIGNED  DEFAULT NULL  COMMENT 'FK a aplicaciones_empleo',

  -- Extra
  `notas`             TEXT          DEFAULT NULL,
  `fecha_creacion`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualiza`   DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY  `uk_numero`         (`numero_empleado`),
  INDEX       `idx_estatus`       (`estatus`),
  INDEX       `idx_departamento`  (`departamento_id`),
  INDEX       `idx_fecha_ingreso` (`fecha_ingreso`),
  INDEX       `idx_aplicacion`    (`aplicacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Expediente de empleados';
