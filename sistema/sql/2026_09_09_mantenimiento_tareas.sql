-- Tabla de control para tareas de mantenimiento que se ejecutan
-- de forma oportunista (al cargar la app), throttladas a 1 vez por día.
CREATE TABLE IF NOT EXISTS mantenimiento_tareas (
    tarea VARCHAR(100) NOT NULL PRIMARY KEY,
    ultima_ejecucion DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
