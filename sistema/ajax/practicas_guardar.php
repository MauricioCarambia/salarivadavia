<?php
session_start();
header('Content-Type: application/json');
require_once "../inc/db.php";

$id = $_POST['id'] ?? null;
$nombre = trim($_POST['nombre'] ?? '');
$tipo = $_POST['tipo'] ?? 'consulta';
$activo = $_POST['activo'] ?? 1;

if ($nombre === '') {
    echo json_encode(['success' => false, 'message' => 'Nombre requerido']);
    exit;
}

try {

    if ($id) {

        $stmt = $pdo->prepare("
            UPDATE practicas 
            SET nombre = :nombre, tipo = :tipo, activo = :activo
            WHERE id = :id
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':tipo' => $tipo,
            ':activo' => $activo,
            ':id' => $id
        ]);

    } else {

        $stmt = $pdo->prepare("
            INSERT INTO practicas (nombre, tipo, activo)
            VALUES (:nombre, :tipo, 1)
        ");
        $stmt->execute([
            ':nombre' => $nombre,
            ':tipo' => $tipo
        ]);

        $id = $pdo->lastInsertId();
    }

    echo json_encode([
        'success' => true,
        'id' => $id,
        'nombre' => $nombre,
        'tipo' => $tipo,
        'activo' => $activo
    ]);

} catch (Exception $e) {

    echo json_encode(['success' => false, 'message' => 'Error DB']);
}