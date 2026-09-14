<?php
/**
 * Helpers de protección CSRF basados en token de sesión.
 * Requiere que la sesión ya esté iniciada (inc/session.php).
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_validate(): bool
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (empty($_SESSION['csrf_token']) || !is_string($token)) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Corta la ejecución con un JSON de error si la request es POST y el token
 * CSRF es inválido. Las requests GET no se validan (son de solo lectura).
 * Debe llamarse después de inc/session.php.
 */
function requerirCsrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    if (!csrf_validate()) {
        header('Content-Type: application/json');
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido o expirado']);
        exit;
    }
}
