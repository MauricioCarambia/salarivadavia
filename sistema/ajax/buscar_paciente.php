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

$dni = $_GET['dni'] ?? '';

if (!$dni) {
    echo json_encode(['found' => false]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT apellido, nombre, celular, nacimiento, domicilio
    FROM pacientes
    WHERE documento = :dni
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([':dni' => $dni]);

$p = $stmt->fetch(PDO::FETCH_ASSOC);

if ($p) {
    echo json_encode([
        'found' => true,
        'data' => $p
    ]);
} else {
    echo json_encode(['found' => false]);
}