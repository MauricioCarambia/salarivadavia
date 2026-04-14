<?php
require_once "../inc/db.php";

header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;
$practica_id = $_GET['practica_id'] ?? 0;

try {

    if (!$id) {
        throw new Exception("ID inválido");
    }

   $stmt = $pdo->prepare("
    SELECT d.id,
           d.tipo_id,
           t.nombre AS tipo,
           d.valor,
           d.orden,
           d.destino_id,
           dr.nombre AS destino
    FROM practicas_reparto pr
    INNER JOIN practicas_reparto_detalle d ON d.reparto_id = pr.id
    INNER JOIN destinos_reparto dr ON dr.id = d.destino_id
    INNER JOIN tipos_reparto t ON t.id = d.tipo_id
    WHERE pr.practica_id = ?
    ORDER BY d.orden
");
$stmt->execute([$practica_id]);

    echo json_encode([
        'success' => true,
        'reglas' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Exception $e) {

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}