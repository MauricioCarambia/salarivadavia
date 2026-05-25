<?php
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$id = $_POST['id'] ?? null;

if (!$id) {
    echo json_encode([
        'success' => false,
        'message' => 'ID inválido'
    ]);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM destinos_reparto WHERE id = ?");
$ok = $stmt->execute([$id]);

echo json_encode([
    'success' => $ok
]);