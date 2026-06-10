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
$nombre = $_POST['nombre'] ?? '';
$accesos = $_POST['accesos'] ?? [];

try {

    // actualizar nombre
    $stmt = $pdo->prepare("UPDATE roles SET nombre=? WHERE Id=?");
    $stmt->execute([$nombre, $id]);

    // eliminar accesos actuales
    $stmt = $pdo->prepare("DELETE FROM roles_accesos WHERE rol_id=?");
    $stmt->execute([$id]);

    // insertar nuevos accesos
    $stmt = $pdo->prepare("INSERT INTO roles_accesos (rol_id, acceso_id) VALUES (?, ?)");

    foreach ($accesos as $acc_id) {
        $stmt->execute([$id, $acc_id]);
    }

    echo json_encode(['ok'=>true,'mensaje'=>'Rol actualizado']);

} catch (Exception $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}