<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
require_once __DIR__ . '/../inc/db.php';

$dni = $_POST['dni'] ?? '';

$stmt = $pdo->prepare("
    SELECT nombre, apellido, celular
    FROM pacientes
    WHERE documento = :dni
    LIMIT 1
");

$stmt->execute([':dni'=>$dni]);

$p = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'encontrado' => $p ? true : false,
    'nombre' => $p['nombre'] ?? '',
    'apellido' => $p['apellido'] ?? '',
    'celular' => $p['celular'] ?? ''
]);