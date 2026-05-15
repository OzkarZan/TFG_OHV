-- Migration: Mechanic scheduling support
-- Run once on the production database after deploying this version.

-- 1. Add 'mecanico' role to USUARIOS
ALTER TABLE `USUARIOS`
    MODIFY COLUMN `rol` ENUM('cliente','empleado','admin','mecanico') NOT NULL DEFAULT 'cliente';

-- 2. Weekly schedule table (one row per mechanic per day)
CREATE TABLE IF NOT EXISTS `MECANICO_HORARIOS` (
    `id_horario`  int          PRIMARY KEY AUTO_INCREMENT,
    `id_mecanico` int          NOT NULL,
    `dia_semana`  TINYINT      NOT NULL COMMENT '1=Lunes … 7=Domingo',
    `hora_inicio` TIME         NOT NULL,
    `hora_fin`    TIME         NOT NULL,
    UNIQUE KEY `uniq_mecanico_dia` (`id_mecanico`, `dia_semana`),
    FOREIGN KEY (`id_mecanico`) REFERENCES `MECANICOS` (`id_mecanico`) ON DELETE CASCADE
);

-- 3. Link appointments to mechanics
ALTER TABLE `CITAS`
    ADD COLUMN IF NOT EXISTS `id_mecanico` int NULL AFTER `id_taller`;

-- 4. Foreign key (only add if it doesn't exist — safe to re-run)
SET @fk_exists = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME        = 'CITAS'
      AND CONSTRAINT_NAME   = 'fk_cita_mecanico'
      AND CONSTRAINT_TYPE   = 'FOREIGN KEY'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE CITAS ADD CONSTRAINT fk_cita_mecanico FOREIGN KEY (id_mecanico) REFERENCES MECANICOS (id_mecanico) ON DELETE SET NULL',
    'SELECT "fk already exists" AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
