<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
require_once __DIR__ . '/../inc/db.php';

if(!empty($_POST['ids'])){

$ids = $_POST['ids'];

$in = str_repeat('?,',count($ids)-1).'?';

$stmt = $pdo->prepare("DELETE FROM cardiologia_sur WHERE Id IN ($in)");

$stmt->execute($ids);

}