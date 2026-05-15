-- Tabla para solicitudes/pedidos de piezas que no hay en stock
CREATE TABLE IF NOT EXISTS SOLICITUDES_PIEZAS (
    id_solicitud    INT PRIMARY KEY AUTO_INCREMENT,
    id_repuesto     INT NULL,
    nombre_pieza    VARCHAR(100) NOT NULL,
    cantidad        DECIMAL(8,2) NOT NULL DEFAULT 1,
    estado          ENUM('Pendiente','Pedido','Recibido') NOT NULL DEFAULT 'Pendiente',
    fecha_solicitud DATE NOT NULL,
    notas           TEXT NULL,
    FOREIGN KEY (id_repuesto) REFERENCES REPUESTOS(id_repuesto) ON DELETE SET NULL
);
