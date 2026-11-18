-- Migracion: alertas de stock minimo (seguro ejecutarla mas de una vez)
USE sirpro;

SET @existe = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'productos'
      AND COLUMN_NAME = 'stock_minimo'
);

SET @sql = IF(
    @existe = 0,
    'ALTER TABLE productos ADD COLUMN stock_minimo INT NOT NULL DEFAULT 5 AFTER stock',
    'SELECT ''stock_minimo ya existe'' AS aviso'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
