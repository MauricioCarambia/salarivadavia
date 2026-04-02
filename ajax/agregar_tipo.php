<?php
require_once '../inc/db.php';
header('Content-Type: application/json');

$tipo = strtolower(trim($_POST['tipo'] ?? ''));

if (!$tipo) {
    echo json_encode(['success' => false, 'message' => 'El nombre del tipo es obligatorio']);
    exit;
}

try {
    // Verificar si ya existe (también en minúsculas)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tipos_reparto WHERE LOWER(nombre) = ?");
    $stmt->execute([$tipo]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'El tipo ya existe']);
        exit;
    }

    // Insertar nuevo tipo en minúsculas
    $stmt = $pdo->prepare("INSERT INTO tipos_reparto (nombre) VALUES (?)");
    $stmt->execute([$tipo]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: '.$e->getMessage()]);
}