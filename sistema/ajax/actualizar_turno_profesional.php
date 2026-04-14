<?php
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once '../inc/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$turno_id = $data['turno_id'] ?? 0;
$atendido = $data['atendido'] ?? false;

if ($turno_id) {

    $stmt = $conexion->prepare("UPDATE turnos SET atendido = :atendido WHERE Id = :id");
    $stmt->execute([
        'atendido' => $atendido ? 1 : 0,
        'id' => $turno_id
    ]);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
exit;