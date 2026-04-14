<?php
require_once '../inc/db.php';
header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if(!$id){
    echo json_encode(['success'=>false,'message'=>'ID inválido']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM tipos_reparto WHERE id = ?");
$stmt->execute([$id]);
$tipo = $stmt->fetch(PDO::FETCH_ASSOC);

if($tipo){
    echo json_encode(['success'=>true,'tipo'=>$tipo]);
} else {
    echo json_encode(['success'=>false,'message'=>'Tipo no encontrado']);
}