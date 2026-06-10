<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../inc/csrf.php';
requerirCsrf();

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/permisos.php';

header('Content-Type: application/json');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    if (!isset($_POST['id'], $_POST['pass'])) {
        throw new Exception('Datos incompletos');
    }

    $id   = (int) $_POST['id'];
    $pass = trim($_POST['pass']);

    if ($id <= 0 || strlen($pass) < 4) {
        throw new Exception('Datos inválidos');
    }

    // =============================
    // PERMISOS
    // =============================
    if (!tieneAcceso('gestionar_roles')) {
        throw new Exception('No tenés permisos');
    }

    // =============================
    // VALIDAR EMPLEADO
    // =============================
    $stmt = $pdo->prepare("SELECT Id FROM empleados WHERE Id = ?");
    $stmt->execute([$id]);

    if (!$stmt->fetch()) {
        throw new Exception('Empleado no existe');
    }

    // =============================
    // HASH PASSWORD
    // =============================
    $hash = password_hash($pass, PASSWORD_DEFAULT);

    // =============================
    // UPDATE
    // =============================
    $stmt = $pdo->prepare("
        UPDATE empleados 
        SET contrasenia = :pass
        WHERE Id = :id
    ");

    $stmt->execute([
        ':pass' => $hash,
        ':id'   => $id
    ]);

    echo json_encode([
        'ok' => true,
        'mensaje' => 'Contraseña actualizada correctamente'
    ]);

} catch (PDOException $e) {

    error_log($e->getMessage());
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => 'Error en base de datos'
    ]);

} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}