<?php
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de destino inválido o no proporcionado'
    ]);
    exit;
}

try {

    $stmt = $pdo->prepare("
        SELECT id, nombre, tipo, categoria 
        FROM destinos_reparto 
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);

    $destino = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$destino) {
        echo json_encode([
            'success' => false,
            'message' => 'El destino no existe'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'destino' => $destino
    ]);

} catch (PDOException $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos'
    ]);
}