<?php
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$id = $_POST['id'] ?? 0;

try {

    $stmt = $pdo->prepare("DELETE FROM articulos WHERE id=?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}