<?php
session_start();
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');



// 📥 Validar ID
$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode([
        'success' => false,
        'message' => 'ID requerido'
    ]);
    exit;
}

try {

    $stmt = $pdo->prepare("
        DELETE FROM practicas_precios 
        WHERE id = ?
    ");

    $stmt->execute([$id]);

    echo json_encode([
        'success' => true
    ]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}