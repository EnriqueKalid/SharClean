-- ============================================================
-- Sharclean RRHH – Módulo Nómina (v1)
-- Ejecutar UNA sola vez contra sharclea_dbapp
-- Requiere migrate_asistencia_v1.sql ya ejecutado
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

USE `sharclea_dbapp`;

-- ── Períodos de nómina ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nomina_periodos` (
  `id`                 INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `tipo`               ENUM('semanal','quincenal') NOT NULL,
  `fecha_inicio`       DATE          NOT NULL,
  `fecha_fin`          DATE          NOT NULL,
  `fecha_pago`         DATE          DEFAULT NULL,
  `estado`             ENUM('borrador','cerrado','pagado') NOT NULL DEFAULT 'borrador',
  `total_percepciones` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total_deducciones`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `total_neto`         DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `empleados_count`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `notas`              VARCHAR(500)  DEFAULT NULL,
  `creado_por`         VARCHAR(80)   DEFAULT NULL,
  `fecha_creacion`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_estado`   (`estado`),
  INDEX `idx_fechas`   (`fecha_inicio`, `fecha_fin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Períodos de nómina (semanal o quincenal)';

-- ── Líneas de nómina por empleado ─────────────────────────────
CREATE TABLE IF NOT EXISTS `nomina_lineas` (
  `id`                       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `periodo_id`               INT UNSIGNED  NOT NULL,
  `empleado_id`              INT UNSIGNED  NOT NULL,

  -- Resumen de asistencia del período
  `dias_periodo`             TINYINT UNSIGNED NOT NULL DEFAULT 0   COMMENT 'Días calendario del período',
  `dias_trabajados`          DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `dias_falta_injustificada` DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `dias_falta_justificada`   DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `dias_vacaciones`          DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `dias_incapacidad`         DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  `horas_extra`              DECIMAL(6,2)  NOT NULL DEFAULT 0.00,
  `minutos_retardo`          INT UNSIGNED  NOT NULL DEFAULT 0,

  -- Base salarial
  `salario_diario`           DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
  `dias_pagados`             DECIMAL(5,2)  NOT NULL DEFAULT 0.00,

  -- Percepciones
  `sueldo_base`              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `horas_extra_monto`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `prima_dominical`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `bono_asistencia`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `otras_percepciones`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_percepciones`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,

  -- Deducciones
  `imss_obrero`              DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `isr_retenido`             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `subsidio_empleo`          DECIMAL(10,2) NOT NULL DEFAULT 0.00  COMMENT 'Informativo; ya descontado de isr_retenido',
  `descuento_faltas`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `otras_deducciones`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_deducciones`        DECIMAL(10,2) NOT NULL DEFAULT 0.00,

  `neto_pagar`               DECIMAL(10,2) NOT NULL DEFAULT 0.00,

  `estado`                   ENUM('calculado','revisado','pagado') NOT NULL DEFAULT 'calculado',
  `observaciones`            VARCHAR(500)  DEFAULT NULL,
  `fecha_actualiza`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY  `uk_periodo_empleado` (`periodo_id`, `empleado_id`),
  INDEX `idx_empleado` (`empleado_id`),
  INDEX `idx_periodo`  (`periodo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Líneas de nómina por empleado y período';
