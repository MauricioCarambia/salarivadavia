-- Agrega columna "motivo" a la tabla de auditoria, para registrar el motivo
-- ingresado por el empleado al anular cobros o eliminar pagos de afiliados.
ALTER TABLE auditoria
    ADD COLUMN motivo TEXT NULL AFTER detalle;
