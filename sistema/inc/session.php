<?php

if (session_status() === PHP_SESSION_NONE) {

    session_name("turnos");

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}