-- Migration: add standalone presupuesto support
-- Makes id_reparacion nullable and adds columns for standalone presupuestos

USE autosync_db;

-- 1. Make id_reparacion nullable
ALTER TABLE PRESUPUESTOS MODIFY COLUMN id_reparacion int NULL;

-- 2. Add id_mecanico column (idempotent)
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='PRESUPUESTOS' AND COLUMN_NAME='id_mecanico')=0,
    'ALTER TABLE PRESUPUESTOS ADD COLUMN id_mecanico int NULL AFTER id_reparacion', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. Add id_cliente column
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='PRESUPUESTOS' AND COLUMN_NAME='id_cliente')=0,
    'ALTER TABLE PRESUPUESTOS ADD COLUMN id_cliente int NULL AFTER id_mecanico', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4. Add id_vehiculo column
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='PRESUPUESTOS' AND COLUMN_NAME='id_vehiculo')=0,
    'ALTER TABLE PRESUPUESTOS ADD COLUMN id_vehiculo int NULL AFTER id_cliente', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 5. Add km column
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='PRESUPUESTOS' AND COLUMN_NAME='km')=0,
    'ALTER TABLE PRESUPUESTOS ADD COLUMN km int NULL AFTER id_vehiculo', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 6. Add color column
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='PRESUPUESTOS' AND COLUMN_NAME='color')=0,
    'ALTER TABLE PRESUPUESTOS ADD COLUMN color varchar(30) NULL AFTER km', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 7. Add servicios_terceros column
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='PRESUPUESTOS' AND COLUMN_NAME='servicios_terceros')=0,
    'ALTER TABLE PRESUPUESTOS ADD COLUMN servicios_terceros decimal(10,2) DEFAULT 0 AFTER total_mano_obra', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 8. Add notas column
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='PRESUPUESTOS' AND COLUMN_NAME='notas')=0,
    'ALTER TABLE PRESUPUESTOS ADD COLUMN notas text NULL AFTER estado', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
