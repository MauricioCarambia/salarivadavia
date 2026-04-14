<?php
session_name("turnos");
session_start();
require_once '../inc/db.php';
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

    // 🔥 PERMISOS
    function tieneAcceso($permiso) {
    if (!empty($_SESSION['es_admin'])) return true;
    return in_array($permiso, $_SESSION['accesos'] ?? []);
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
    $stmt = $conexion->prepare("DELETE FROM empleados WHERE Id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('No se encontró el empleado');
    }

    echo json_encode([
        'ok' => true,
        'mensaje' => 'Empleado eliminado correctamente'
    ]);

} catch (Exception $e) {

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}