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

CREATE TABLE `USUARIOS` (
  `id_usuario` int PRIMARY KEY AUTO_INCREMENT,
  `email` varchar(100) UNIQUE NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` ENUM('cliente', 'empleado', 'admin', 'mecanico') NOT NULL DEFAULT 'cliente',
  `nombre_completo` varchar(100) NOT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `CLIENTES` (
  `id_cliente` int PRIMARY KEY AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `telefono` varchar(20),
  `direccion` varchar(255),
  FOREIGN KEY (`id_usuario`) REFERENCES `USUARIOS` (`id_usuario`) ON DELETE CASCADE
);

CREATE TABLE `VEHICULOS` (
  `id_vehiculo` int PRIMARY KEY AUTO_INCREMENT,
  `matricula` varchar(15) UNIQUE NOT NULL,
  `modelo` varchar(50),
  `marca` varchar(50),
  `anio` int,
  `id_cliente` int,
  FOREIGN KEY (`id_cliente`) REFERENCES `CLIENTES` (`id_cliente`) ON DELETE CASCADE
);

CREATE TABLE `CITAS` (
  `id_cita` int PRIMARY KEY AUTO_INCREMENT,
  `fecha_hora` datetime NOT NULL,
  `motivo` text,
  `estado` ENUM ('Pendiente', 'Confirmada', 'Cancelada') DEFAULT 'Pendiente',
  `prioridad` ENUM ('Alta', 'Media', 'Baja') DEFAULT 'Media',
  `es_emergencia` boolean DEFAULT false,
  `id_cliente` int,
  `id_vehiculo` int,
  `id_taller` int,
  `id_mecanico` int NULL,
  FOREIGN KEY (`id_cliente`) REFERENCES `CLIENTES` (`id_cliente`),
  FOREIGN KEY (`id_vehiculo`) REFERENCES `VEHICULOS` (`id_vehiculo`),
  FOREIGN KEY (`id_taller`) REFERENCES `TALLER` (`id_taller`)
);

CREATE TABLE `REPARACIONES` (
  `id_reparacion` int PRIMARY KEY AUTO_INCREMENT,
  `id_cita` int,
  `modelo_auto` varchar(100) NOT NULL,
  `matricula` varchar(20) NOT NULL,
  `descripcion_motivo` text NOT NULL,
  `diagnostico` text,
  `fecha_entrada` datetime,
  `fecha_salida` datetime,
  `km_entrada` int,
  `estado_presupuesto` ENUM ('Pendiente', 'Aprobado', 'No Aprobado') DEFAULT 'Pendiente',
  `estado` ENUM ('En Proceso', 'Esperando Piezas', 'Finalizada') DEFAULT 'En Proceso',
  `precio_final` decimal(10,2),
  FOREIGN KEY (`id_cita`) REFERENCES `CITAS` (`id_cita`)
);

CREATE TABLE `PRESUPUESTOS` (
  `id_presupuesto` int PRIMARY KEY AUTO_INCREMENT,
  `id_reparacion` int NULL,
  `id_mecanico` int NULL,
  `id_cliente` int NULL,
  `id_vehiculo` int NULL,
  `km` int NULL,
  `color` varchar(30) NULL,
  `total_piezas` decimal(10,2) DEFAULT 0,
  `total_mano_obra` decimal(10,2) DEFAULT 0,
  `servicios_terceros` decimal(10,2) DEFAULT 0,
  `gran_total` decimal(10,2) DEFAULT 0,
  `fecha_emision` date,
  `estado` ENUM ('Borrador', 'Enviado', 'Aprobado', 'Rechazado') DEFAULT 'Borrador',
  `notas` text NULL,
  FOREIGN KEY (`id_reparacion`) REFERENCES `REPARACIONES` (`id_reparacion`)
);

CREATE TABLE `MECANICOS` (
  `id_mecanico` int PRIMARY KEY AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `especialidad` varchar(100),
  `id_taller` int,
  FOREIGN KEY (`id_usuario`) REFERENCES `USUARIOS` (`id_usuario`) ON DELETE CASCADE,
  FOREIGN KEY (`id_taller`) REFERENCES `TALLER` (`id_taller`)
);

CREATE TABLE `MECANICO_HORARIOS` (
  `id_horario`  int     PRIMARY KEY AUTO_INCREMENT,
  `id_mecanico` int     NOT NULL,
  `dia_semana`  TINYINT NOT NULL COMMENT '1=Lunes … 7=Domingo',
  `hora_inicio` TIME    NOT NULL,
  `hora_fin`    TIME    NOT NULL,
  UNIQUE KEY `uniq_mecanico_dia` (`id_mecanico`, `dia_semana`),
  FOREIGN KEY (`id_mecanico`) REFERENCES `MECANICOS` (`id_mecanico`) ON DELETE CASCADE
);

-- Deferred FK: CITAS.id_mecanico → MECANICOS (defined after MECANICOS)
ALTER TABLE `CITAS`
  ADD CONSTRAINT `fk_cita_mecanico`
  FOREIGN KEY (`id_mecanico`) REFERENCES `MECANICOS` (`id_mecanico`) ON DELETE SET NULL;

CREATE TABLE `REPUESTOS` (
  `id_repuesto` int PRIMARY KEY AUTO_INCREMENT,
  `nombre_pieza` varchar(100) NOT NULL,
  `marca` varchar(50),
  `stock_actual` int DEFAULT 0,
  `stock_minimo` int DEFAULT 5,
  `precio_unitario` decimal(10,2)
);

CREATE TABLE `PRESUPUESTO_DETALLES` (
  `id_detalle` int PRIMARY KEY AUTO_INCREMENT,
  `id_presupuesto` int NOT NULL,
  `tipo_item` ENUM ('Repuesto', 'Mano de Obra') NOT NULL,
  `id_repuesto` int,
  `descripcion` varchar(255) NOT NULL,
  `cantidad` decimal(8,2) NOT NULL DEFAULT 1,
  `precio_unitario` decimal(10,2) NOT NULL,
  FOREIGN KEY (`id_presupuesto`) REFERENCES `PRESUPUESTOS` (`id_presupuesto`) ON DELETE CASCADE,
  FOREIGN KEY (`id_repuesto`) REFERENCES `REPUESTOS` (`id_repuesto`)
);

CREATE TABLE `REPARACION_MECANICO` (
  `id_reparacion` int,
  `id_mecanico` int,
  PRIMARY KEY (`id_reparacion`, `id_mecanico`),
  FOREIGN KEY (`id_reparacion`) REFERENCES `REPARACIONES` (`id_reparacion`),
  FOREIGN KEY (`id_mecanico`) REFERENCES `MECANICOS` (`id_mecanico`)
);

CREATE TABLE `REPARACION_REPUESTOS` (
  `id_reparacion` int,
  `id_repuesto` int,
  `cantidad_usada` int NOT NULL,
  PRIMARY KEY (`id_reparacion`, `id_repuesto`),
  FOREIGN KEY (`id_reparacion`) REFERENCES `REPARACIONES` (`id_reparacion`),
  FOREIGN KEY (`id_repuesto`) REFERENCES `REPUESTOS` (`id_repuesto`)
);

CREATE TABLE `RESET_TOKENS` (
  `id` int PRIMARY KEY AUTO_INCREMENT,
  `id_usuario` int NOT NULL,
  `token` varchar(64) UNIQUE NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado` boolean DEFAULT false,
  FOREIGN KEY (`id_usuario`) REFERENCES `USUARIOS` (`id_usuario`) ON DELETE CASCADE
);
