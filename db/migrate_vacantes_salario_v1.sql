-- Migración: agrega columnas rango_salarial y beneficios a vacantes
ALTER TABLE vacantes ADD COLUMN rango_salarial VARCHAR(100) NULL DEFAULT NULL AFTER maps_url;
ALTER TABLE vacantes ADD COLUMN beneficios TEXT NULL DEFAULT NULL AFTER rango_salarial;
