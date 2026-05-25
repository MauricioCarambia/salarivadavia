<?php
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$stmt = $pdo->query("SELECT * FROM articulos ORDER BY id DESC");

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));