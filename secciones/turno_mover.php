<?php
require_once "../inc/db.php";

date_default_timezone_set('America/Argentina/Buenos_Aires');

header('Content-Type: application/json');

$id     = $_POST['id'] ?? 0;
$fecha  = $_POST['fecha'] ?? null;

if(!$id || !$fecha){
    echo json_encode([
        'ok'=>false,
        'error'=>'Datos inválidos'
    ]);
    exit;
}

/* UPDATE */
$stmt=$conexion->prepare("
UPDATE turnos
SET fecha = :fecha
WHERE Id = :id
");

$stmt->execute([
':fecha'=>$fecha,
':id'=>$id
]);

echo json_encode(['ok'=>true]);