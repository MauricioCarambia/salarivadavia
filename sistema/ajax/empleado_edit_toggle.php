<?php
require_once '../inc/db.php';

header('Content-Type: application/json');

try {

    // =============================
    // VALIDACIONES
    // =============================
    if (!isset($_POST['id'], $_POST['activo'])) {
        throw new Exception('Datos incompletos');
    }

    $id = (int) $_POST['id'];
    $activo = (int) $_POST['activo'];
    $user_id = $_SESSION['user_id'] ?? 0;

    if ($id <= 0) {
        throw new Exception('ID inválido');
    }

    if ($activo !== 0 && $activo !== 1) {
        throw new Exception('Estado inválido');
    }

    // =============================
    // SEGURIDAD: NO AUTO-DESACTIVARSE
    // =============================
    if ($id === $user_id && $activo === 0) {
        throw new Exception('No podés desactivarte a vos mismo');
    }

    // =============================
    // UPDATE
    // =============================
    $stmt = $conexion->prepare("
        UPDATE empleado 
        SET activo = :activo 
        WHERE id = :id
    ");

    $stmt->execute([
        ':activo' => $activo,
        ':id' => $id
    ]);

    // =============================
    // RESPUESTA
    // =============================
    echo json_encode([
        'ok' => true,
        'mensaje' => 'Estado actualizado correctamente'
    ]);

} catch (Exception $e) {

    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => 'Error en base de datos'
    ]);
}