<?php

if (session_status() === PHP_SESSION_NONE) {
    session_name("turnos");

    // Mantener sesión activa 8 horas
    ini_set('session.gc_maxlifetime', 28800);
    ini_set('session.cookie_lifetime', 28800);

    session_set_cookie_params([
        'lifetime' => 28800,   // ← era 0, ahora 8 horas
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}