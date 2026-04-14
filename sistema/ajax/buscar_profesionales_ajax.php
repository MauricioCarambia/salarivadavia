<?php
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$q = $_GET['q'] ?? '';

try {

    $sql = "
        SELECT 
            Id as id,
            CONCAT(apellido, ' ', nombre) AS text
        FROM profesionales
        WHERE (apellido LIKE ? OR nombre LIKE ?)
        ORDER BY apellido ASC
        LIMIT 20
    ";

    $stmt = $pdo->prepare($sql);
    $like = "%$q%";
    $stmt->execute([$like, $like]);

    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($resultados);

} catch (Exception $e) {
    echo json_encode([]);
}