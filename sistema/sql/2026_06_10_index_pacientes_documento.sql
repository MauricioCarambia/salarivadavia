-- pacientes (71k+ filas) no tenia ningun indice fuera de Id.
-- Las busquedas de pacientes (turnos, historia clinica, socios) filtran
-- por documento; se agrega indice para acelerar busquedas exactas y por
-- prefijo (LIKE 'term%').
ALTER TABLE pacientes ADD INDEX idx_pacientes_documento (documento);
