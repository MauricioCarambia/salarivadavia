-- Nuevos permisos asignables a roles para las secciones agregadas:
-- Auditoría, Estadísticas y Autorizaciones de acceso remoto.
INSERT INTO accesos (nombre) VALUES
    ('auditoria'),
    ('estadisticas'),
    ('autorizaciones_ip');
