-- Tabla para rate limiting de intentos de login fallidos
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    intentos INT NOT NULL DEFAULT 1,
    bloqueado_hasta DATETIME NULL,
    ultimo_intento DATETIME NOT NULL,
    UNIQUE KEY uq_usuario_ip (usuario, ip)
);
