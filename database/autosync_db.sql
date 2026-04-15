CREATE DATABASE IF NOT EXISTS autosync_db;
USE autosync_db;
CREATE TABLE `TALLER` (
  `id_taller` int PRIMARY KEY AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `cif` varchar(20) UNIQUE NOT NULL COMMENT 'Evita duplicados de empresas',
  `direccion` varchar(255),
  `telefono` varchar(20),
  `horario` varchar(100)
);

CREATE TABLE `CLIENTES` (
  `id_cliente` int PRIMARY KEY AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(20),
  `email` varchar(100) UNIQUE NOT NULL,
  `google_id` varchar(255),
  `token_acceso` varchar(255)
);

CREATE TABLE `VEHICULOS` (
  `id_vehiculo` int PRIMARY KEY AUTO_INCREMENT,
  `matricula` varchar(15) UNIQUE NOT NULL,
  `modelo` varchar(50),
  `marca` varchar(50),
  `anio` int,
  `id_cliente` int
);

CREATE TABLE `CITAS` (
  `id_cita` int PRIMARY KEY AUTO_INCREMENT,
  `fecha_hora` datetime NOT NULL,
  `motivo` text,
  `estado` ENUM ('Pendiente', 'Confirmada', 'Cancelada') DEFAULT 'Pendiente',
  `es_emergencia` boolean DEFAULT false,
  `id_cliente` int,
  `id_vehiculo` int,
  `id_taller` int
);

CREATE TABLE `PRESUPUESTOS` (
  `id_presupuesto` int PRIMARY KEY AUTO_INCREMENT,
  `id_cita` int UNIQUE,
  `monto_total` decimal(10,2),
  `fecha_emision` date,
  `estado` ENUM ('Pendiente', 'Aceptado', 'Rechazado') DEFAULT 'Pendiente'
);

CREATE TABLE `REPARACIONES` (
  `id_reparacion` int PRIMARY KEY AUTO_INCREMENT,
  `id_presupuesto` int UNIQUE,
  `diagnostico` text,
  `fecha_entrada` datetime,
  `fecha_salida` datetime,
  `km_entrada` int,
  `estado` ENUM ('En Proceso', 'Esperando Piezas', 'Finalizada') DEFAULT 'En Proceso',
  `precio_final` decimal(10,2)
);

CREATE TABLE `MECANICOS` (
  `id_mecanico` int PRIMARY KEY AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `especialidad` varchar(100),
  `id_taller` int
);

CREATE TABLE `REPUESTOS` (
  `id_repuesto` int PRIMARY KEY AUTO_INCREMENT,
  `nombre_pieza` varchar(100) NOT NULL,
  `marca` varchar(50),
  `stock_actual` int DEFAULT 0,
  `stock_minimo` int DEFAULT 5,
  `precio_unitario` decimal(10,2)
);

CREATE TABLE `REPARACION_MECANICO` (
  `id_reparacion` int,
  `id_mecanico` int,
  PRIMARY KEY (`id_reparacion`, `id_mecanico`)
);

CREATE TABLE `REPARACION_REPUESTOS` (
  `id_reparacion` int,
  `id_repuesto` int,
  `cantidad_usada` int NOT NULL,
  PRIMARY KEY (`id_reparacion`, `id_repuesto`)
);

ALTER TABLE `VEHICULOS` ADD FOREIGN KEY (`id_cliente`) REFERENCES `CLIENTES` (`id_cliente`) ON DELETE CASCADE;

ALTER TABLE `CITAS` ADD FOREIGN KEY (`id_cliente`) REFERENCES `CLIENTES` (`id_cliente`);

ALTER TABLE `CITAS` ADD FOREIGN KEY (`id_vehiculo`) REFERENCES `VEHICULOS` (`id_vehiculo`);

ALTER TABLE `CITAS` ADD FOREIGN KEY (`id_taller`) REFERENCES `TALLER` (`id_taller`);

ALTER TABLE `CITAS` ADD FOREIGN KEY (`id_cita`) REFERENCES `PRESUPUESTOS` (`id_cita`);

ALTER TABLE `PRESUPUESTOS` ADD FOREIGN KEY (`id_presupuesto`) REFERENCES `REPARACIONES` (`id_presupuesto`);

ALTER TABLE `MECANICOS` ADD FOREIGN KEY (`id_taller`) REFERENCES `TALLER` (`id_taller`);

ALTER TABLE `REPARACION_MECANICO` ADD FOREIGN KEY (`id_reparacion`) REFERENCES `REPARACIONES` (`id_reparacion`);

ALTER TABLE `REPARACION_MECANICO` ADD FOREIGN KEY (`id_mecanico`) REFERENCES `MECANICOS` (`id_mecanico`);

ALTER TABLE `REPARACION_REPUESTOS` ADD FOREIGN KEY (`id_reparacion`) REFERENCES `REPARACIONES` (`id_reparacion`);

ALTER TABLE `REPARACION_REPUESTOS` ADD FOREIGN KEY (`id_repuesto`) REFERENCES `REPUESTOS` (`id_repuesto`);
