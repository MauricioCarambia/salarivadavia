<?php
require_once __DIR__ . '/../inc/db.php';

$busqueda = $_GET['q'] ?? '';
$data = [];

if (!empty($busqueda)) {
    // Buscamos en nombre o apellido (ajustado a tus columnas Id, nombre, apellido)
    $stmt = $pdo->prepare("
        SELECT Id AS id, CONCAT(apellido, ' ', nombre) AS text 
        FROM pacientes 
        WHERE nombre LIKE ? OR apellido LIKE ? 
        LIMIT 20
    ");
    $term = "%$busqueda%";
    $stmt->execute([$term, $term]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json');
echo json_encode($data);