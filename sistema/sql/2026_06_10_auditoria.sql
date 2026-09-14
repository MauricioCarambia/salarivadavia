-- Tabla de auditoria: registra acciones criticas (altas/bajas/cambios)
-- realizadas por empleados/admins, visible solo para administradores.
CREATE TABLE IF NOT EXISTS auditoria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NULL,
    usuario_nombre VARCHAR(150) NULL,
    accion VARCHAR(100) NOT NULL,
    detalle TEXT NULL,
    ip VARCHAR(45) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_auditoria_creado_en (creado_en),
    INDEX idx_auditoria_usuario_id (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
