<?php
require_once __DIR__ . '/../inc/session.php';

if (empty($_SESSION['login']) || $_SESSION['login'] !== 'si') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}
require_once __DIR__ . '/../inc/db.php';

$id = $_POST['id'] ?? 0;
$monto = $_POST['monto'] ?? 0;
$fecha = $_POST['fecha'] ?? '';

if (!$id || !$monto || !$fecha) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$fecha .= '-01';

$stmt = $pdo->prepare("
    UPDATE pagos_afiliados
    SET monto = :monto,
        fecha_correspondiente = :fecha
    WHERE Id = :id
");

$ok = $stmt->execute([
    ':monto' => $monto,
    ':fecha' => $fecha,
    ':id' => $id
]);

echo json_encode(['success' => $ok]);