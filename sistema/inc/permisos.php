<?php

/**
 * Devuelve true si el usuario logueado tiene el permiso indicado
 * (admin y accesos = ['*'] tienen acceso a todo).
 */
function tieneAcceso(string $permiso): bool
{
    if (!empty($_SESSION['es_admin'])) {
        return true;
    }

    $accesos = $_SESSION['accesos'] ?? [];

    if (in_array('*', $accesos, true)) {
        return true;
    }

    return in_array($permiso, $accesos, true);
}

/**
 * Corta la ejecución con un JSON de error si el usuario no tiene el permiso indicado.
 * Debe llamarse después de incluir inc/session.php (y de validar el login).
 */
function requerirAcceso(string $permiso): void
{
    if (!tieneAcceso($permiso)) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No tenés permisos para esta acción']);
        exit;
    }
}
