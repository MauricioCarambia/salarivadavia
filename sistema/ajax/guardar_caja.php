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