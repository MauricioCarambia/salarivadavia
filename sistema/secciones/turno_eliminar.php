<?php
require_once __DIR__.'/../inc/db.php';

header('Content-Type: application/json');

$id = $_POST['id'] ?? 0;

if(!$id){
    echo json_encode([
        "ok"=>false,
        "error"=>"ID inválido"
    ]);
    exit;
}

$stmt = $pdo->prepare("
DELETE FROM turnos
WHERE Id = :id
");

$stmt->execute([
    ':id'=>$id
]);

echo json_encode([
    "ok"=>true
]);