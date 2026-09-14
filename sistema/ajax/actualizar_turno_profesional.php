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
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$turno_id = $data['turno_id'] ?? 0;
$atendido = $data['atendido'] ?? false;

if ($turno_id) {

    $stmt = $pdo->prepare("UPDATE turnos SET atendido = :atendido WHERE Id = :id");
    $stmt->execute([
        'atendido' => $atendido ? 1 : 0,
        'id' => $turno_id
    ]);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
exit;