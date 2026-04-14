<?php
require_once '../inc/db.php';

$id = $_POST['id'] ?? 0;
$monto = $_POST['monto'] ?? 0;
$fecha = $_POST['fecha'] ?? '';

if (!$id || !$monto || !$fecha) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    exit;
}

$fecha .= '-01';

$stmt = $conexion->prepare("
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