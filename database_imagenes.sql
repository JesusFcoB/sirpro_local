-- Agrega soporte de imagenes comprimidas a productos existentes
-- Ejecutar solo si ya tenias la base de datos creada antes de esta funcionalidad.
-- Las instalaciones nuevas ya incluyen esta columna en database.sql.

USE sirpro;

ALTER TABLE productos
    ADD COLUMN imagen VARCHAR(120) NULL AFTER descripcion;
