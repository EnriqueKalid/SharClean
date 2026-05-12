-- ============================================================
-- Sharclean RRHH – Módulo Vacaciones y Permisos (v1)
-- Ejecutar UNA sola vez contra sharclea_dbapp
-- Requiere migrate_asistencia_v1.sql ya ejecutado
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

USE `sharclea_dbapp`;

-- Agrega notas del admin y timestamp de respuesta a incidencias
ALTER TABLE `incidencias`
  ADD COLUMN IF NOT EXISTS `notas_admin`      VARCHAR(400) DEFAULT NULL
    COMMENT 'Motivo de aprobación o rechazo' AFTER `aprobado_por`,
  ADD COLUMN IF NOT EXISTS `fecha_respuesta`  DATETIME     DEFAULT NULL
    COMMENT 'Cuándo se aprobó o rechazó'     AFTER `notas_admin`;
