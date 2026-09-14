-- Autorizaciones temporales de acceso fuera de la red de la clinica.
-- Permite que un administrador habilite a un empleado o profesional puntual
-- a ingresar desde cualquier IP durante un tiempo limitado.
CREATE TABLE IF NOT EXISTS autorizaciones_ip (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_usuario ENUM('empleado', 'profesional') NOT NULL,
    usuario_id INT NOT NULL,
    autorizado_por INT NULL,
    expira_en DATETIME NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_autorizaciones_ip_usuario (tipo_usuario, usuario_id),
    INDEX idx_autorizaciones_ip_expira (expira_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
