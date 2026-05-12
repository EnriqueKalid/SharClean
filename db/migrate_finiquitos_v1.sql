-- ============================================================
-- Sharclean RRHH – Finiquitos y Liquidaciones LFT (v1)
-- Ejecutar UNA sola vez contra sharclea_dbapp
-- Requiere migrate_rrhh_v2.sql ejecutado
-- ============================================================

USE `sharclea_dbapp`;

CREATE TABLE IF NOT EXISTS `finiquitos` (
  `id`                          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `empleado_id`                 INT UNSIGNED  NOT NULL,
  `tipo`                        ENUM('finiquito','liquidacion') NOT NULL,
  `motivo`                      ENUM('renuncia','despido_injustificado','despido_justificado',
                                     'fin_contrato','mutuo_acuerdo','defuncion') NOT NULL,
  `fecha_termino`               DATE          NOT NULL  COMMENT 'Último día laborado',

  -- Snapshot laboral al momento del cálculo
  `salario_diario`              DECIMAL(10,2) NOT NULL,
  `salario_diario_integrado`    DECIMAL(10,2) NOT NULL,
  `factor_integracion`          DECIMAL(8,6)  NOT NULL,
  `anos_servicio`               DECIMAL(8,4)  NOT NULL  COMMENT 'Años con decimales',
  `dias_servicio_total`         INT           NOT NULL,

  -- Finiquito (partes proporcionales)
  `dias_pendientes`             INT           NOT NULL DEFAULT 0,
  `monto_dias_pendientes`       DECIMAL(10,2) NOT NULL DEFAULT 0,
  `dias_aguinaldo_prop`         DECIMAL(8,4)  NOT NULL DEFAULT 0,
  `monto_aguinaldo_prop`        DECIMAL(10,2) NOT NULL DEFAULT 0,
  `dias_vacaciones_prop`        DECIMAL(8,4)  NOT NULL DEFAULT 0,
  `monto_vacaciones_prop`       DECIMAL(10,2) NOT NULL DEFAULT 0,
  `monto_prima_vacacional_prop` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `monto_prima_antiguedad`      DECIMAL(10,2) NOT NULL DEFAULT 0,

  -- Liquidación (sólo si despido injustificado)
  `monto_tres_meses`            DECIMAL(10,2) NOT NULL DEFAULT 0,
  `monto_veinte_dias`           DECIMAL(10,2) NOT NULL DEFAULT 0,

  -- Ajustes manuales
  `otras_percepciones`          DECIMAL(10,2) NOT NULL DEFAULT 0,
  `otras_deducciones`           DECIMAL(10,2) NOT NULL DEFAULT 0,
  `notas`                       TEXT          DEFAULT NULL,

  -- Totales
  `total_percepciones`          DECIMAL(10,2) NOT NULL DEFAULT 0,
  `total_deducciones`           DECIMAL(10,2) NOT NULL DEFAULT 0,
  `total_neto`                  DECIMAL(10,2) NOT NULL DEFAULT 0,

  `estado`                      ENUM('borrador','firmado','pagado') NOT NULL DEFAULT 'borrador',
  `fecha_pago`                  DATE          DEFAULT NULL,
  `created_at`                  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_empleado`   (`empleado_id`),
  INDEX `idx_estado`     (`estado`),
  INDEX `idx_fecha_term` (`fecha_termino`),
  CONSTRAINT `fk_fin_emp` FOREIGN KEY (`empleado_id`) REFERENCES `empleados`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Finiquitos y liquidaciones LFT';
