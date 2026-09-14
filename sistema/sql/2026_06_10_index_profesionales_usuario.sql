-- login.php busca profesionales por usuario; agrega indice como en empleados.usuario
ALTER TABLE profesionales ADD INDEX idx_prof_usuario (usuario);
