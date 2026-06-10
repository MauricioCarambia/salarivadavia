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

    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // 🔥 VALIDAR PERMISOS
    if (!tieneAcceso('gestionar_roles')) {
        throw new Exception('No tenés permisos para eliminar roles');
    }

    // 🔥 VER SI EL ROL ESTÁ EN USO
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM empleados WHERE rol_id = ?");
    $stmt->execute([$id]);
    $enUso = $stmt->fetchColumn();

    if ($enUso > 0) {
        throw new Exception('No podés eliminar un rol en uso');
    }

    // 🔥 BORRAR ACCESOS PRIMERO
    $stmt = $pdo->prepare("DELETE FROM roles_accesos WHERE rol_id = ?");
    $stmt->execute([$id]);

    // 🔥 BORRAR ROL
    $stmt = $pdo->prepare("DELETE FROM roles WHERE Id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'ok' => true,
        'mensaje' => 'Rol eliminado correctamente'
    ]);

} catch (PDOException $e) {

    error_log($e->getMessage());
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => 'Error en base de datos'
    ]);

} catch (Exception $e) {

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}