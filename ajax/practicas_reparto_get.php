<?php
require_once "../inc/db.php";

header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT *
    FROM practicas_reparto_detalle
    WHERE reparto_id = ?
    ORDER BY orden
");
$stmt->execute([$id]);

echo json_encode([
    'success' => true,
    'reglas' => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);