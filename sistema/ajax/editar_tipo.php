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

$data = $_POST;
$id = isset($data['id']) ? (int)$data['id'] : 0;
$tipo = trim($data['tipo'] ?? '');

if(!$id || $tipo === ''){
    echo json_encode(['success'=>false,'message'=>'Datos inválidos']);
    exit;
}

try{
    $stmt = $pdo->prepare("UPDATE tipos_reparto SET nombre = ? WHERE id = ?");
    $stmt->execute([$tipo, $id]);
    echo json_encode(['success'=>true]);
} catch(Exception $e){
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}