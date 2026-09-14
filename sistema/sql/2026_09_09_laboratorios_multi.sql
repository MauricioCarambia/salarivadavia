-- Soporte para múltiples laboratorios con listas de precios propias.
-- Crea la tabla `laboratorios` y vincula cada estudio de `estudio_lab`
-- a un laboratorio mediante `laboratorio_id`. Los estudios existentes
-- quedan asignados a "Laboratorio Messina" (el que ya se usaba).
CREATE TABLE IF NOT EXISTS laboratorios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    direccion VARCHAR(150) NULL,
    telefono VARCHAR(50) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_laboratorios_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO laboratorios (id, nombre, direccion, telefono)
    VALUES (1, 'Laboratorio Messina', 'Garibaldi 176, Temperley', '11-2871-6602')
    ON DUPLICATE KEY UPDATE nombre = nombre;

ALTER TABLE estudio_lab
    ADD COLUMN laboratorio_id INT NOT NULL DEFAULT 1 AFTER ID;

UPDATE estudio_lab SET laboratorio_id = 1 WHERE laboratorio_id = 0;

ALTER TABLE estudio_lab
    ADD CONSTRAINT fk_estudio_lab_laboratorio
        FOREIGN KEY (laboratorio_id) REFERENCES laboratorios(id),
    ADD UNIQUE KEY uq_estudio_lab_lab_estudio (laboratorio_id, estudio);
