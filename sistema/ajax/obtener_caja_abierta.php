<?php
session_name("turnos");
session_start();
header('Content-Type: application/json');
require_once "../inc/db.php";

$usuarioId = $_SESSION['user_id'] ?? null;

if (!$usuarioId) {
    echo json_encode(['success'=>false,'message'=>'Usuario no autenticado']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT cs.id, cs.turno, c.nombre
    FROM caja_sesion cs
    JOIN cajas c ON c.id = cs.caja_id
    WHERE cs.estado = 'abierta' AND cs.usuario_id = ?
    LIMIT 1
");
$stmt->execute([$usuarioId]);
$caja = $stmt->fetch(PDO::FETCH_ASSOC);

if ($caja) {
    echo json_encode(['success'=>true,'caja'=>[
        'id'=>$caja['id'],
        'nombre'=>$caja['nombre'],
        'turno'=>$caja['turno']
    ]]);
} else {
    echo json_encode(['success'=>true,'caja'=>null]);
}