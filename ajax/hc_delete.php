<?php
require_once __DIR__ . '/../inc/db.php';
header('Content-Type: application/json');

session_start();
$tipoUsuario = $_SESSION['tipo'] ?? '';
$profesionalId = $_SESSION['user_id'] ?? 0;

$id = (int)($_POST['id'] ?? 0);



if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID inválido.']);
    exit;
}

try {
    // Verificamos que la HC exista y pertenezca al profesional
    $stmt = $pdo->prepare("SELECT profesional_id FROM historias_clinicas WHERE Id = ?");
    $stmt->execute([$id]);
    $hc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$hc) {
        echo json_encode(['success' => false, 'message' => 'Historia clínica no encontrada.']);
        exit;
    }


    // Eliminamos la HC
    $stmtDel = $pdo->prepare("DELETE FROM historias_clinicas WHERE Id = ?");
    $stmtDel->execute([$id]);

    echo json_encode(['success' => true, 'message' => 'Historia clínica eliminada correctamente.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
}