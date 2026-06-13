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
require_once __DIR__.'/../inc/db.php';

header('Content-Type: application/json');

$id = $_POST['id'] ?? 0;

if(!$id){
    echo json_encode([
        "ok"=>false,
        "error"=>"ID inválido"
    ]);
    exit;
}

$stmt = $pdo->prepare("
DELETE FROM turnos
WHERE Id = :id
");

$stmt->execute([
    ':id'=>$id
]);

echo json_encode([
    "ok"=>true
]);