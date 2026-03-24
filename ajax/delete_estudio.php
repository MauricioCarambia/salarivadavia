<?php
require_once __DIR__ . '/../inc/db.php';

$id = $_POST['id'] ?? 0;

if (!$id) {
    echo json_encode(['status' => 'error', 'msg' => 'ID inválido']);
    exit;
}

try {

    // verificar existencia
    $stmt = $pdo->prepare("
        SELECT estudio
        FROM estudio_lab
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['status' => 'error', 'msg' => 'No existe']);
        exit;
    }

    // eliminar
    $delete = $pdo->prepare("
        DELETE FROM estudio_lab
        WHERE id = :id
    ");

    $delete->execute([':id' => $id]);

    echo json_encode([
        'status' => 'ok',
        'estudio' => $row['estudio']
    ]);

} catch (PDOException $e) {

    echo json_encode([
        'status' => 'error',
        'msg' => 'Error al eliminar'
    ]);
}