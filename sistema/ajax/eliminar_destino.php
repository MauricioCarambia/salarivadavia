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
    echo json_encode([
        'success' => false,
        'message' => 'ID inválido'
    ]);
    exit;
}

$stmt = $pdo->prepare("DELETE FROM destinos_reparto WHERE id = ?");
$ok = $stmt->execute([$id]);

echo json_encode([
    'success' => $ok
]);