<?php
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$id = $_POST['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {

    // Validar si tiene sesiones
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM caja_sesion WHERE caja_id = ?");
    $stmt->execute([$id]);

    if ($stmt->fetchColumn() > 0) {
        throw new Exception("No se puede eliminar: contiene cobros realizados antiguamente.");
    }

    $stmt = $pdo->prepare("DELETE FROM cajas WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}