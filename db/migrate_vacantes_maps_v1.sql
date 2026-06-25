-- Migración: agrega columna maps_url a vacantes
ALTER TABLE vacantes ADD COLUMN maps_url VARCHAR(500) NULL DEFAULT NULL AFTER ubicacion;
