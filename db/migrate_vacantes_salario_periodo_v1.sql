-- Migración: periodo del salario en vacantes
-- Permite indicar si el rango salarial es mensual, quincenal, semanal, diario o por hora.

ALTER TABLE `vacantes`
  ADD COLUMN `salario_periodo` ENUM('Mensual','Quincenal','Semanal','Diario','Por hora')
    NOT NULL DEFAULT 'Mensual' AFTER `rango_salarial`;
