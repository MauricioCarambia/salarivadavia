<?php
require_once '../inc/db.php';

header('Content-Type: application/json');

$tipo = strtolower(trim($_POST['tipo'] ?? ''));

if (!$tipo) {
    echo json_encode([
        'success' => false,
        'message' => 'El nombre del tipo es obligatorio'
    ]);
    exit;
}

try {

    /* =========================
       VALIDAR EXISTENCIA
    ========================= */
    $stmt = $pdo->prepare("
        SELECT id 
        FROM tipos_reparto 
        WHERE LOWER(TRIM(nombre)) = ?
        LIMIT 1
    ");
    $stmt->execute([$tipo]);

    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'El tipo ya existe'
        ]);
        exit;
    }

    /* =========================
       INSERTAR
    ========================= */
    $stmt = $pdo->prepare("
        INSERT INTO tipos_reparto (nombre) 
        VALUES (?)
    ");
    $stmt->execute([$tipo]);

    $id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'id' => $id,
        'nombre' => $tipo
    ]);

} catch (PDOException $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Error de base de datos'
    ]);
}