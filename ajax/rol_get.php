<?php
session_name("turnos");
session_start();
require_once '../inc/db.php';

header('Content-Type: application/json');

$id = $_POST['id'] ?? 0;

$stmt = $conexion->prepare("SELECT Id, nombre FROM roles WHERE Id = ?");
$stmt->execute([$id]);
$rol = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rol) {
    echo json_encode(['ok'=>false,'error'=>'Rol no encontrado']);
    exit;
}

$stmt = $conexion->prepare("SELECT acceso_id FROM roles_accesos WHERE rol_id = ?");
$stmt->execute([$id]);
$accesos = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode([
    'ok'=>true,
    'rol'=>$rol,
    'accesos'=>$accesos
]);