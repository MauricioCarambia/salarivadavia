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

$id = $_POST['id'] ?? 0;

try {

    $pdo->beginTransaction();

    /* 🔥 1. BORRAR DETALLE */
    $stmt = $pdo->prepare("
        DELETE FROM practicas_reparto_detalle 
        WHERE reparto_id = ?
    ");
    $stmt->execute([$id]);

    /* 🔥 2. BORRAR REPARTO */
    $stmt = $pdo->prepare("
        DELETE FROM practicas_reparto 
        WHERE id = ?
    ");
    $stmt->execute([$id]);

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}