<?php
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$hcId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM historias_clinicas WHERE Id = :id");
$stmt->execute([':id' => $hcId]);

echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));