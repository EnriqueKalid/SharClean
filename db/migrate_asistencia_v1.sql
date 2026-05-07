-- ============================================================
-- Sharclean RRHH – Módulo Asistencia (v1)
-- Ejecutar UNA sola vez contra sharclean_db
-- Requiere que migrate_rrhh_v2.sql ya haya sido ejecutado
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

USE `sharclea_dbapp`;

-- ── Columnas de jornada en empleados ─────────────────────────
-- (ignorar error si ya existen)
ALTER TABLE `empleados`
  ADD COLUMN IF NOT EXISTS `jornada_horas`         TINYINT UNSIGNED NOT NULL DEFAULT 8
    COMMENT 'Horas de jornada laboral diaria' AFTER `clabe_bancaria`,
  ADD COLUMN IF NOT EXISTS `hora_entrada_esperada`  TIME NOT NULL DEFAULT '08:00:00'
    COMMENT 'Hora de entrada según turno' AFTER `jornada_horas`,
  ADD COLUMN IF NOT EXISTS `tolerancia_min`         TINYINT UNSIGNED NOT NULL DEFAULT 10
    COMMENT 'Minutos de tolerancia antes de marcar retardo' AFTER `hora_entrada_esperada`;


-- ── Tabla: asistencia ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `asistencia` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `empleado_id`      INT UNSIGNED  NOT NULL,
  `fecha`            DATE          NOT NULL,
  `tipo`             ENUM(
                       'normal','falta_justificada','falta_injustificada',
                       'vacaciones','incapacidad','permiso_goce',
                       'permiso_sin_goce','descanso','festivo'
                     ) NOT NULL DEFAULT 'normal',
  `hora_entrada`     TIME          DEFAULT NULL,
  `hora_salida`      TIME          DEFAULT NULL,
  `horas_trabajadas` DECIMAL(5,2)  DEFAULT NULL,
  `horas_extra`      DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `retardo`          TINYINT(1)    NOT NULL DEFAULT 0,
  `minutos_retardo`  SMALLINT      NOT NULL DEFAULT 0,
  `observaciones`    VARCHAR(500)  DEFAULT NULL,
  `capturado_por`    VARCHAR(100)  DEFAULT NULL,
  `fecha_creacion`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY  `uk_emp_fecha`   (`empleado_id`, `fecha`),
  INDEX       `idx_fecha`      (`fecha`),
  INDEX       `idx_empleado`   (`empleado_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registro diario de asistencia';


-- ── Tabla: incidencias ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `incidencias` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `empleado_id`    INT UNSIGNED  NOT NULL,
  `tipo`           ENUM(
                     'falta_justificada','incapacidad_imss','incapacidad_empresa',
                     'vacaciones','permiso_goce','permiso_sin_goce','suspension','otro'
                   ) NOT NULL,
  `fecha_inicio`   DATE          NOT NULL,
  `fecha_fin`      DATE          NOT NULL,
  `dias`           SMALLINT      NOT NULL DEFAULT 1,
  `descripcion`    VARCHAR(500)  DEFAULT NULL,
  `estado`         ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
  `aprobado_por`   VARCHAR(80)   DEFAULT NULL,
  `fecha_creacion` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_empleado` (`empleado_id`),
  INDEX `idx_estado`   (`estado`),
  INDEX `idx_fechas`   (`fecha_inicio`, `fecha_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Incidencias: faltas, permisos, incapacidades, vacaciones';
