<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
require_once __DIR__ . '/../inc/db.php';

$id = (int) $_GET['paciente_id'];

$stmt = $pdo->prepare("
    SELECT 
        Id,
        apellido,
        nombre,
        nro_afiliado
    FROM pacientes
    WHERE SUBSTRING_INDEX(SUBSTRING_INDEX(nro_afiliado, '/', 1), ' ', 1) = (
        SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(nro_afiliado, '/', 1), ' ', 1)
        FROM pacientes
        WHERE Id = :id
    )
");

$stmt->execute([':id' => $id]);
$pacientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($pacientes);