<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
require_once __DIR__ . '/../inc/db.php';

$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM cardiologia_sur WHERE Id = :id");
$stmt->execute([':id' => $id]);

echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));