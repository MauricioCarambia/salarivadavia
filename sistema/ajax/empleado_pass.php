<?php
session_name("turnos");
session_start();
require_once '../inc/db.php';

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
    if (empty($_SESSION['es_admin']) && !in_array('gestionar_roles', $_SESSION['accesos'] ?? [])) {
        throw new Exception('No tenés permisos');
    }

    // =============================
    // VALIDAR EMPLEADO
    // =============================
    $stmt = $conexion->prepare("SELECT Id FROM empleados WHERE Id = ?");
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
    $stmt = $conexion->prepare("
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

} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}