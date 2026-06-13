<?php

// Duración de sesión: se renueva en cada request mientras haya actividad
const SESION_DURACION = 4 * 60 * 60; // 4 horas

if (session_status() === PHP_SESSION_NONE) {
    session_name("turnos");

    ini_set('session.gc_maxlifetime', SESION_DURACION);
    ini_set('session.cookie_lifetime', SESION_DURACION);

    session_set_cookie_params([
        'lifetime' => SESION_DURACION,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Renovar la cookie en cada request para que el cierre dependa de la
// inactividad (sin uso por SESION_DURACION) y no del momento del login
if (!empty($_SESSION['login']) && !headers_sent()) {
    setcookie(session_name(), session_id(), [
        'expires'  => time() + SESION_DURACION,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}
