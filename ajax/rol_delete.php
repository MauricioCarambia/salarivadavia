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
    throw new Exception('No tenés permisos para eliminar roles');
}

    // 🔥 VER SI EL ROL ESTÁ EN USO
    $stmt = $conexion->prepare("SELECT COUNT(*) FROM empleado WHERE rol_id = ?");
    $stmt->execute([$id]);
    $enUso = $stmt->fetchColumn();

    if ($enUso > 0) {
        throw new Exception('No podés eliminar un rol en uso');
    }

    // 🔥 BORRAR ACCESOS PRIMERO
    $stmt = $conexion->prepare("DELETE FROM roles_accesos WHERE rol_id = ?");
    $stmt->execute([$id]);

    // 🔥 BORRAR ROL
    $stmt = $conexion->prepare("DELETE FROM roles WHERE Id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'ok' => true,
        'mensaje' => 'Rol eliminado correctamente'
    ]);

} catch (Exception $e) {

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}