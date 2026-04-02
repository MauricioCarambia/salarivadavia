<?php
session_name("turnos");
session_start();
header('Content-Type: application/json');

require_once "../inc/db.php";

$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {

    $stmt = $pdo->prepare("DELETE FROM practicas WHERE id = :id");
    $stmt->execute([':id' => $id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar'
    ]);
}