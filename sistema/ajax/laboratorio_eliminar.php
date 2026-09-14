<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../inc/csrf.php';
requerirCsrf();
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM estudio_lab WHERE laboratorio_id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->fetchColumn() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No se puede eliminar: el laboratorio tiene estudios cargados. Eliminalos primero.'
        ]);
        exit;
    }

    $del = $pdo->prepare("DELETE FROM laboratorios WHERE id = :id");
    $del->execute([':id' => $id]);

    echo json_encode(['success' => true]);

} catch (Throwable $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
}
