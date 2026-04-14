<?php
require_once "../inc/db.php";

header('Content-Type: application/json');

$id = $_POST['id'] ?? 0;

try {

    $pdo->beginTransaction();

    /* 🔥 1. BORRAR DETALLE */
    $stmt = $pdo->prepare("
        DELETE FROM practicas_reparto_detalle 
        WHERE reparto_id = ?
    ");
    $stmt->execute([$id]);

    /* 🔥 2. BORRAR REPARTO */
    $stmt = $pdo->prepare("
        DELETE FROM practicas_reparto 
        WHERE id = ?
    ");
    $stmt->execute([$id]);

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}