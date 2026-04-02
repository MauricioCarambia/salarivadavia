<?php
require_once "../inc/db.php";

header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

/* CABECERA */
$stmt = $pdo->prepare("
    SELECT *
    FROM practicas_reparto
    WHERE id = ?
");
$stmt->execute([$id]);
$reparto = $stmt->fetch(PDO::FETCH_ASSOC);

/* REGLAS */
$stmt = $pdo->prepare("
    SELECT *
    FROM practicas_reparto_detalle
    WHERE reparto_id = ?
    ORDER BY orden
");
$stmt->execute([$id]);
$reglas = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'reparto' => $reparto,
    'reglas' => $reglas
]);