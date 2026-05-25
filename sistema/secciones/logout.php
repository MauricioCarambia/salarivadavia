<?php
require_once __DIR__ . '/../inc/session.php';

// Vaciar sesión
$_SESSION = [];

// Borrar cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir sesión
session_destroy();

// Redirigir
header("Location: ../login.php");
exit;