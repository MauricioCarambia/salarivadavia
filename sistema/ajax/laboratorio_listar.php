<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

require_once __DIR__ . '/../inc/db.php';

header('Content-Type: application/json');

$rows = $pdo->query("
    SELECT l.id, l.nombre, l.direccion, l.telefono,
           (SELECT COUNT(*) FROM estudio_lab e WHERE e.laboratorio_id = l.id) AS total_estudios
    FROM laboratorios l
    ORDER BY l.nombre ASC
")->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($rows);
