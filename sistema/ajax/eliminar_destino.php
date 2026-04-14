<?php
require_once '../inc/db.php';
header('Content-Type: application/json');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if(!$id){
    echo json_encode(['success'=>false,'message'=>'ID inválido']);
    exit;
}

try{
    $stmt = $pdo->prepare("DELETE FROM destinos_reparto WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success'=>true]);
} catch(Exception $e){
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}