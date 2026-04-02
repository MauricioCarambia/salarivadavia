<?php
require_once '../inc/db.php';

header('Content-Type: application/json');

$id = (int) ($_POST['id'] ?? 0);

if(!$id){
    echo json_encode(['success'=>false,'message'=>'ID inválido']);
    exit;
}

$stmt = $conexion->prepare("
    DELETE FROM pagos_profesionales
    WHERE Id = :id
");

echo json_encode([
    'success' => $stmt->execute([':id'=>$id])
]);