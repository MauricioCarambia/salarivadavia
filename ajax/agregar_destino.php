<?php
require_once '../inc/db.php';
header('Content-Type: application/json');

$destino = strtolower(trim($_POST['destino'] ?? ''));

if (!$destino) {
    echo json_encode(['success' => false, 'message' => 'El nombre del destino es obligatorio']);
    exit;
}

try {
    // Verificar si ya existe (también en minúsculas)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM destinos_reparto WHERE LOWER(nombre) = ?");
    $stmt->execute([$destino]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'El destino ya existe']);
        exit;
    }

    // Insertar nuevo destino en minúsculas
    $stmt = $pdo->prepare("INSERT INTO destinos_reparto (nombre) VALUES (?)");
    $stmt->execute([$destino]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: '.$e->getMessage()]);
}