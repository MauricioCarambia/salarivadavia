<?php
require_once "../inc/db.php";
header('Content-Type: application/json');

$paciente_id = (int)($_GET['paciente_id'] ?? 0);
$turno_id = (int)($_GET['turno_id'] ?? 0);

if (!$paciente_id || !$turno_id) {
    echo json_encode(['success' => false]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.fecha,
        c.total,
        GROUP_CONCAT(p.nombre SEPARATOR ', ') as detalle
    FROM cobros c
    LEFT JOIN cobros_detalle cd ON cd.cobro_id = c.id
    LEFT JOIN practicas p ON p.id = cd.practica_id
    WHERE c.paciente_id = ?
    AND c.turno_id = ?
    AND c.estado = 'activo'
    GROUP BY c.id
    ORDER BY c.fecha DESC
");

$stmt->execute([$paciente_id, $turno_id]);

$data = [];

foreach ($stmt as $r) {
    $data[] = [
        'id' => (int)$r['id'], // 🔥 CLAVE
        'fecha' => date('d/m/Y H:i', strtotime($r['fecha'])),
        'detalle' => $r['detalle'],
        'total' => (float)$r['total']
    ];
}

echo json_encode([
    'success' => true,
    'data' => $data
]);