<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
require_once __DIR__ . '/../inc/db.php';
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