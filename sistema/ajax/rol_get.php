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

$id = $_POST['id'] ?? 0;

$stmt = $pdo->prepare("SELECT Id, nombre FROM roles WHERE Id = ?");
$stmt->execute([$id]);
$rol = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rol) {
    echo json_encode(['ok'=>false,'error'=>'Rol no encontrado']);
    exit;
}

$stmt = $pdo->prepare("SELECT acceso_id FROM roles_accesos WHERE rol_id = ?");
$stmt->execute([$id]);
$accesos = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode([
    'ok'=>true,
    'rol'=>$rol,
    'accesos'=>$accesos
]);