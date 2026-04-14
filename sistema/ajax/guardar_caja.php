<?php
require_once "../inc/db.php";
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'] ?? null;
$nombre = trim($data['nombre'] ?? '');
$activo = isset($data['activo']) ? (int)$data['activo'] : 0; // por defecto activa

if (!$nombre) {
    echo json_encode([
        'success' => false,
        'message' => 'Nombre requerido'
    ]);
    exit;
}

try {

    if ($id) {
        // ✅ UPDATE
        $stmt = $pdo->prepare("
            UPDATE cajas 
            SET nombre = ?, activo = ?
            WHERE id = ?
        ");
        $stmt->execute([$nombre, $activo, $id]);

    } else {
        // ✅ INSERT
        $stmt = $pdo->prepare("
            INSERT INTO cajas (nombre, activo) 
            VALUES (?, ?)
        ");
        $stmt->execute([$nombre, $activo]);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}