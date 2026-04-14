<?php
session_name("turnos");
session_start();
require_once '../inc/db.php';

header('Content-Type: application/json');

$id = $_POST['id'] ?? 0;
$nombre = $_POST['nombre'] ?? '';
$accesos = $_POST['accesos'] ?? [];

try {

    // actualizar nombre
    $stmt = $conexion->prepare("UPDATE roles SET nombre=? WHERE Id=?");
    $stmt->execute([$nombre, $id]);

    // eliminar accesos actuales
    $stmt = $conexion->prepare("DELETE FROM roles_accesos WHERE rol_id=?");
    $stmt->execute([$id]);

    // insertar nuevos accesos
    $stmt = $conexion->prepare("INSERT INTO roles_accesos (rol_id, acceso_id) VALUES (?, ?)");

    foreach ($accesos as $acc_id) {
        $stmt->execute([$id, $acc_id]);
    }

    echo json_encode(['ok'=>true,'mensaje'=>'Rol actualizado']);

} catch (Exception $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}