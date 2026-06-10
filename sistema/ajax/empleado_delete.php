<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/permisos.php';
header('Content-Type: application/json');

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    $id = (int)($_POST['id'] ?? 0);
    $user_id = $_SESSION['user_id'] ?? 0;

    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    // 🔥 VALIDAR PERMISOS
    if (!tieneAcceso('gestionar_roles')) {
        throw new Exception('No tenés permisos para eliminar empleados');
    }

    // 🔥 NO BORRARSE A SÍ MISMO
    if ($id === $user_id) {
        throw new Exception('No podés eliminarte a vos mismo');
    }

    // 🔥 DELETE
    $stmt = $pdo->prepare("DELETE FROM empleados WHERE Id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('No se encontró el empleado');
    }

    echo json_encode([
        'ok' => true,
        'mensaje' => 'Empleado eliminado correctamente'
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