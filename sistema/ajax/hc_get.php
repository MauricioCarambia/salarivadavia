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

$hcId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM historias_clinicas WHERE Id = :id");
$stmt->execute([':id' => $hcId]);

echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));