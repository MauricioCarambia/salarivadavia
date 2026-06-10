-- pagos_afiliados (120k+ filas) se filtra/joinea constantemente por paciente_id
-- (estado de socio, historial de pagos) y no tenia indice en esa columna.
ALTER TABLE pagos_afiliados ADD INDEX idx_pa_paciente_id (paciente_id);
