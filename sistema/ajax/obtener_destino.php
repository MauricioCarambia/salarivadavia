<?php
require_once '../inc/db.php';
header('Content-Type: application/json');

// Forma más compatible de capturar el ID
$id = 0;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
}

if ($id <= 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'ID de destino inválido o no proporcionado',
        'debug' => ['id_recibido' => $_GET['id'] ?? 'null']
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, nombre, tipo, categoria 
        FROM destinos_reparto 
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $destino = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($destino) {
        echo json_encode([
            'success' => true,
            'destino' => $destino
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'El destino no existe'
        ]);
    }

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}