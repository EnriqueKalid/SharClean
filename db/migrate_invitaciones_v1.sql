-- ============================================================
-- Sharclean RRHH – Invitaciones de autorregistro (v1)
-- Ejecutar UNA sola vez contra sharclea_dbapp
-- Requiere migrate_registro_v1.sql
--
--   · RRHH invita a los empleados ya dados de alta (sin acceso al portal)
--     por correo y/o WhatsApp para que registren su selfie, contraseña y/o PIN.
--   · Estas columnas solo guardan cuándo y por qué canal se les invitó por
--     última vez (para no invitar dos veces a la misma persona sin querer).
--     Sin ellas el envío funciona igual, solo que no se lleva el registro.
-- Compatible: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

USE `sharclea_dbapp`;

ALTER TABLE `empleados`
  ADD COLUMN IF NOT EXISTS `invitacion_fecha` DATETIME NULL
    COMMENT 'Última invitación de autorregistro enviada' AFTER `registro_revisado_por`,
  ADD COLUMN IF NOT EXISTS `invitacion_canal` VARCHAR(20) NULL
    COMMENT 'correo | whatsapp | correo+whatsapp' AFTER `invitacion_fecha`;
