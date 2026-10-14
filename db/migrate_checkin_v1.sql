-- ============================================================
-- Sharclean RRHH – Módulo Check-in de empleados (v1)
-- Ejecutar UNA sola vez contra sharclea_dbapp
-- Requiere que migrate_rrhh_v2.sql y migrate_asistencia_v1.sql ya
-- hayan sido ejecutados.
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

USE `sharclea_dbapp`;

-- ── Portal del empleado: foto de referencia + credenciales ──
ALTER TABLE `empleados`
  ADD COLUMN IF NOT EXISTS `foto_referencia`      VARCHAR(255) NULL
    COMMENT 'Path de la foto de referencia para verificación facial' AFTER `email_corporativo`,
  ADD COLUMN IF NOT EXISTS `portal_username`      VARCHAR(50)  NULL UNIQUE
    COMMENT 'Usuario para el portal de check-in del empleado' AFTER `foto_referencia`,
  ADD COLUMN IF NOT EXISTS `portal_password_hash` VARCHAR(255) NULL AFTER `portal_username`,
  ADD COLUMN IF NOT EXISTS `portal_pin_hash`       VARCHAR(255) NULL
    COMMENT 'PIN alterno (hash) para empleados con impedimento para usar password' AFTER `portal_password_hash`,
  ADD COLUMN IF NOT EXISTS `portal_activo`         TINYINT(1) NOT NULL DEFAULT 1
    COMMENT 'Permite al admin desactivar el acceso al portal sin borrar credenciales' AFTER `portal_pin_hash`;

-- ── Check-in propio: geolocalización, selfie y validación facial ──
ALTER TABLE `asistencia`
  ADD COLUMN IF NOT EXISTS `checkin_lat`          DECIMAL(10,7) NULL AFTER `capturado_por`,
  ADD COLUMN IF NOT EXISTS `checkin_lng`          DECIMAL(10,7) NULL AFTER `checkin_lat`,
  ADD COLUMN IF NOT EXISTS `checkout_lat`         DECIMAL(10,7) NULL AFTER `checkin_lng`,
  ADD COLUMN IF NOT EXISTS `checkout_lng`         DECIMAL(10,7) NULL AFTER `checkout_lat`,
  ADD COLUMN IF NOT EXISTS `checkin_foto`         VARCHAR(255)  NULL AFTER `checkout_lng`,
  ADD COLUMN IF NOT EXISTS `checkout_foto`        VARCHAR(255)  NULL AFTER `checkin_foto`,
  ADD COLUMN IF NOT EXISTS `checkin_face_score`   DECIMAL(5,2)  NULL
    COMMENT 'Similitud facial 0-100 contra foto de referencia (check-in)' AFTER `checkout_foto`,
  ADD COLUMN IF NOT EXISTS `checkout_face_score`  DECIMAL(5,2)  NULL
    COMMENT 'Similitud facial 0-100 contra foto de referencia (check-out)' AFTER `checkin_face_score`,
  ADD COLUMN IF NOT EXISTS `revision_manual`      TINYINT(1)    NOT NULL DEFAULT 0
    COMMENT 'Marcado cuando la verificación facial no pudo confirmarse automáticamente' AFTER `checkout_face_score`;
